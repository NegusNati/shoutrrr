import { useNavigate } from '@tanstack/react-router';
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { useConfirm } from '@/components/common/confirm-dialog';
import {
    defaultPickedAt,
    PickTimePopover,
} from '@/components/compose/pick-time-popover';
import { Button } from '@/components/ui/button';
import {
    CalendarClock,
    CalendarX,
    Copy,
    MessageCircle,
    RotateCw,
    Share2,
    Trash2,
} from '@/components/ui/icons';
import { useMeData } from '@/features/me/me';
import { postKeys } from '@/features/posts/compose.queries';
import { useHttp } from '@/lib/api/use-http';
import { api } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { useSchedulingTimezone } from '@/hooks/posts/use-scheduling-timezone';
import { dayjs } from '@/lib/datetime/dayjs';
import { postCapabilities } from '@/lib/posts/capabilities';
import { postLiveStatus } from '@/lib/posts/live-status';
import type { PostView } from '@/types/compose';

import { ShareDialog } from './share-dialog';

type Props = {
    post: PostView;
};

/**
 * Contextual actions for a single post's detail page, gated by the same
 * capabilities as the list row. The schedule/publish endpoints return JSON, so
 * mutations go through `useHttp` and then reload the page (mirroring SubmitBar);
 * delete returns a redirect, so it goes through the Inertia router and lands on
 * the posts index. A live "going live in …" line ticks once a minute.
 */
export function PostPageActions({ post }: Props) {
    const features = useMeData()?.features;
    const caps = postCapabilities(post);
    const tz = useSchedulingTimezone();
    const confirm = useConfirm();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const http = useHttp<Record<string, never>, { post: PostView }>({});

    // A post can draw replies only once it's live somewhere, so the jump to its
    // engagement is offered exactly when at least one target has published.
    const hasPublishedTarget = post.targets.some(
        (t) => t.status === 'published',
    );
    const showReplies = Boolean(features?.engagement) && hasPublishedTarget;

    const [rescheduling, setRescheduling] = useState(false);
    const [pickedAt, setPickedAt] = useState<string>(() => defaultPickedAt(tz));
    const [shareOpen, setShareOpen] = useState(false);
    const [duplicating, setDuplicating] = useState(false);

    // Re-render each minute so the relative "going live" label stays current.
    const [, setTick] = useState(0);
    useEffect(() => {
        const id = setInterval(() => setTick((n) => n + 1), 60_000);

        return () => clearInterval(id);
    }, []);

    const liveStatus = postLiveStatus(post, tz);

    function refresh() {
        void queryClient.invalidateQueries({
            queryKey: postKeys.detail(post.id),
        });
        void queryClient.invalidateQueries({
            queryKey: postKeys.metrics(post.id),
        });
    }

    async function handleDuplicate() {
        if (duplicating) {
            return;
        }
        setDuplicating(true);
        try {
            const result = await api.post<{ post: PostView }>(
                endpoints.postDuplicate(post.id),
            );
            void navigate({
                to: '/posts/$postId',
                params: { postId: result.post.id },
            });
        } finally {
            setDuplicating(false);
        }
    }

    function openReschedule() {
        // A missed post's stored time is in the past, which the picker rejects;
        // only prefill the existing time when it is still in the future.
        const existing = post.scheduled_at;
        setPickedAt(
            existing && dayjs(existing).isAfter(dayjs())
                ? existing
                : defaultPickedAt(tz),
        );
        setRescheduling(true);
    }

    async function saveReschedule() {
        http.transform(() => ({ scheduled_at: pickedAt }));
        await http.post(endpoints.postSchedule(post.id), {
            onSuccess: refresh,
        });
        setRescheduling(false);
    }

    async function handleUnschedule() {
        const ok = await confirm({
            title: 'Move back to drafts?',
            description:
                'The post will be unscheduled and returned to your drafts.',
            actionLabel: 'Unschedule',
        });
        if (!ok) {
            return;
        }
        http.transform(() => ({ scheduled_at: null }));
        await http.post(endpoints.postSchedule(post.id), {
            onSuccess: refresh,
        });
    }

    async function handleRetry() {
        const failed = post.targets.find((t) => t.status === 'failed');
        if (!failed) {
            return;
        }
        http.transform(() => ({}));
        await http.post(endpoints.postTargetRetry(post.id, failed.id), {
            onSuccess: refresh,
        });
    }

    async function handleDelete() {
        const ok = await confirm({
            title: 'Delete post?',
            description:
                post.status === 'draft' || post.status === 'scheduled'
                    ? 'This removes the post. The content is not kept.'
                    : 'Published copies will be removed from connected accounts where possible.',
            actionLabel: 'Delete',
            destructive: true,
        });
        if (!ok) {
            return;
        }
        await api.delete(endpoints.post(post.id));
        void navigate({
            to: '/posts',
            search: { status: 'all', set: '', platform: '', q: '' },
        });
    }

    return (
        <div className="flex shrink-0 items-center gap-1.5">
            {liveStatus && (
                <span className="mr-1 hidden text-[12px] text-muted-foreground tabular-nums sm:inline">
                    {liveStatus}
                </span>
            )}

            {showReplies && (
                <Button
                    size="sm"
                    variant="outline"
                    aria-label="View replies"
                    nativeButton={false}
                    render={
                        // Engagement still lives in the legacy app.
                        <a href={`/engagement?post=${post.id}`} />
                    }
                >
                    <MessageCircle className="size-3.5" aria-hidden />
                    <span className="hidden sm:inline">Replies</span>
                </Button>
            )}

            {rescheduling ? (
                <>
                    <PickTimePopover
                        value={pickedAt}
                        onChange={setPickedAt}
                        tz={tz}
                    />
                    <Button
                        size="sm"
                        disabled={http.processing}
                        onClick={() => void saveReschedule()}
                    >
                        Save
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setRescheduling(false)}
                    >
                        Cancel
                    </Button>
                </>
            ) : (
                <>
                    {caps.canReschedule && (
                        <Button
                            size="sm"
                            variant="outline"
                            aria-label="Reschedule"
                            onClick={openReschedule}
                        >
                            <CalendarClock className="size-3.5" aria-hidden />
                            <span className="hidden sm:inline">Reschedule</span>
                        </Button>
                    )}
                    {caps.canUnschedule && (
                        <Button
                            size="sm"
                            variant="outline"
                            aria-label="Unschedule"
                            onClick={() => void handleUnschedule()}
                        >
                            <CalendarX className="size-3.5" aria-hidden />
                            <span className="hidden sm:inline">Unschedule</span>
                        </Button>
                    )}
                    {caps.canRetry && (
                        <Button
                            size="sm"
                            variant="outline"
                            aria-label="Retry failed"
                            onClick={() => void handleRetry()}
                        >
                            <RotateCw className="size-3.5" aria-hidden />
                            <span className="hidden sm:inline">
                                Retry failed
                            </span>
                        </Button>
                    )}
                    {post.status !== 'draft' && (
                        <Button
                            size="sm"
                            variant="outline"
                            aria-label="Share"
                            onClick={() => setShareOpen(true)}
                        >
                            <Share2 className="size-3.5" aria-hidden />
                            <span className="hidden sm:inline">Share</span>
                        </Button>
                    )}
                    {caps.canDuplicate && (
                        <Button
                            size="sm"
                            variant="outline"
                            aria-label="Copy as draft"
                            disabled={duplicating}
                            onClick={handleDuplicate}
                        >
                            <Copy className="size-3.5" aria-hidden />
                            <span className="hidden sm:inline">
                                Copy as draft
                            </span>
                        </Button>
                    )}
                    {caps.canDelete && (
                        <Button
                            size="sm"
                            variant="destructive"
                            aria-label="Delete"
                            onClick={() => void handleDelete()}
                        >
                            <Trash2 className="size-3.5" aria-hidden />
                            <span className="hidden sm:inline">Delete</span>
                        </Button>
                    )}
                </>
            )}

            <ShareDialog
                postId={post.id}
                open={shareOpen}
                onOpenChange={setShareOpen}
            />
        </div>
    );
}
