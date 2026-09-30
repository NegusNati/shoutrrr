import dayjs from 'dayjs';
import { useState } from 'react';

import ComposerController from '@/actions/App/Http/Controllers/Posts/ComposerController';
import { useConfirm } from '@/components/common/confirm-dialog';
import {
    defaultPickedAt,
    PickTimePopover,
} from '@/components/compose/pick-time-popover';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal } from '@/components/ui/icons';
import {
    invalidatePostQueries,
    removePostFromCaches,
    updatePostInCaches,
} from '@/features/posts/posts';
import { useSchedulingTimezone } from '@/hooks/posts/use-scheduling-timezone';
import { api } from '@/lib/api';
import { postCapabilities } from '@/lib/posts/capabilities';

import type { PostRowData } from './post-row';
import { ShareDialog } from './share-dialog';

type Props = {
    post: PostRowData;
};

type Mode = 'menu' | 'scheduling' | 'rescheduling';

function stopBubble(e: React.MouseEvent | React.KeyboardEvent) {
    e.stopPropagation();
}

export function PostRowActions({ post }: Props) {
    const caps = postCapabilities(post);
    const tz = useSchedulingTimezone();
    const confirm = useConfirm();

    const [mode, setMode] = useState<Mode>('menu');
    const [pickedAt, setPickedAt] = useState<string>(() => defaultPickedAt(tz));
    const [shareOpen, setShareOpen] = useState(false);
    const [duplicating, setDuplicating] = useState(false);

    function handleEdit() {
        // The composer still lives in the server-rendered app.
        window.location.assign(ComposerController.show(post.id).url);
    }

    function handleDuplicate() {
        if (duplicating) {
            return;
        }
        setDuplicating(true);
        void api
            .post<{ post: { id: string } }>(`posts/${post.id}/duplicate`)
            .then((result) => {
                invalidatePostQueries();
                // Parity with the web duplicate flow: land on the new draft's
                // composer page (still server-rendered).
                window.location.assign(
                    ComposerController.show(result.post.id).url,
                );
            })
            .finally(() => setDuplicating(false));
    }

    function openSchedule() {
        setPickedAt(defaultPickedAt(tz));
        setMode('scheduling');
    }

    function openReschedule() {
        // A missed post's stored time is in the past, which the picker rejects;
        // only prefill the existing time when it is still in the future.
        const existing = post.scheduled_at;
        const prefill =
            existing && dayjs(existing).isAfter(dayjs())
                ? existing
                : defaultPickedAt(tz);
        setPickedAt(prefill);
        setMode('rescheduling');
    }

    function saveSchedule() {
        updatePostInCaches(post.id, (p) => ({
            ...p,
            status: 'scheduled',
            scheduled_at: pickedAt,
        }));
        void api
            .post(`posts/${post.id}/schedule`, { scheduled_at: pickedAt })
            .finally(invalidatePostQueries);
        setMode('menu');
    }

    function cancelSchedule() {
        setMode('menu');
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
        updatePostInCaches(post.id, (p) => ({
            ...p,
            status: 'draft',
            scheduled_at: null,
        }));
        void api
            .post(`posts/${post.id}/schedule`, { scheduled_at: null })
            .finally(invalidatePostQueries);
    }

    function handleRetry() {
        const failedTarget = post.targets.find((t) => t.status === 'failed');
        if (!failedTarget) {
            return;
        }
        updatePostInCaches(post.id, (p) => ({
            ...p,
            targets: p.targets.map((t) =>
                t.id === failedTarget.id ? { ...t, status: 'pending' } : t,
            ),
        }));
        void api
            .post(`posts/${post.id}/targets/${failedTarget.id}/retry`)
            .finally(invalidatePostQueries);
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
        removePostFromCaches(post.id);
        void api.delete(`posts/${post.id}`).finally(invalidatePostQueries);
    }

    // Scheduling / rescheduling inline picker replaces the dropdown
    if (mode === 'scheduling' || mode === 'rescheduling') {
        return (
            // oxlint-disable-next-line prefer-tag-over-role -- wrapper stops row-click bubbling only
            <div
                role="presentation"
                onClick={stopBubble}
                onKeyDown={stopBubble}
                className="flex items-center gap-1.5"
            >
                <PickTimePopover
                    value={pickedAt}
                    onChange={setPickedAt}
                    tz={tz}
                />
                <Button
                    size="sm"
                    className="h-8 text-[12.5px]"
                    onClick={saveSchedule}
                >
                    Save
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 text-[12.5px]"
                    onClick={cancelSchedule}
                >
                    Cancel
                </Button>
            </div>
        );
    }

    return (
        // oxlint-disable-next-line prefer-tag-over-role -- wrapper stops row-click bubbling only
        <div role="presentation" onClick={stopBubble} onKeyDown={stopBubble}>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Post actions"
                            className="size-8 text-muted-foreground hover:text-foreground"
                        />
                    }
                >
                    <MoreHorizontal className="size-4" aria-hidden />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                    {caps.canEdit && (
                        <DropdownMenuItem onClick={handleEdit}>
                            Edit
                        </DropdownMenuItem>
                    )}
                    {caps.canSchedule && (
                        <DropdownMenuItem onClick={openSchedule}>
                            Schedule&hellip;
                        </DropdownMenuItem>
                    )}
                    {caps.canReschedule && (
                        <DropdownMenuItem onClick={openReschedule}>
                            Reschedule&hellip;
                        </DropdownMenuItem>
                    )}
                    {caps.canRetry && (
                        <DropdownMenuItem onClick={handleRetry}>
                            Retry failed
                        </DropdownMenuItem>
                    )}
                    {caps.canUnschedule && (
                        <DropdownMenuItem
                            onClick={() => void handleUnschedule()}
                        >
                            Unschedule
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem onClick={() => setShareOpen(true)}>
                        Share&hellip;
                    </DropdownMenuItem>
                    {caps.canDuplicate && (
                        <DropdownMenuItem
                            disabled={duplicating}
                            onClick={handleDuplicate}
                        >
                            Copy as draft
                        </DropdownMenuItem>
                    )}
                    {caps.canDelete && (
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={() => void handleDelete()}
                        >
                            Delete
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {/* Share dialog */}
            <ShareDialog
                postId={post.id}
                open={shareOpen}
                onOpenChange={setShareOpen}
            />
        </div>
    );
}
