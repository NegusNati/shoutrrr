import { useQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { useEffect } from 'react';

import Composer from '@/components/compose/composer';
import { PostPageActions } from '@/components/posts/post-page-actions';
import { PublishedPostView } from '@/components/posts/published-post-view';
import { Button } from '@/components/ui/button';
import { ArrowLeft, PenLine } from '@/components/ui/icons';
import { Skeleton } from '@/components/ui/skeleton';
import { useMeData } from '@/features/me/me';
import {
    platformLimitsQuery,
    workspaceMentionsQuery,
} from '@/features/posts/compose.queries';
import { usePostStatusPoll } from '@/hooks/compose/use-post-status-poll';
import { firstLineTitle } from '@/lib/compose/composer-state';
import type { Destination, PostView } from '@/types/compose';

export function ComposePage({
    post,
    initialScheduleAt = null,
    initialDestination = null,
}: {
    post: PostView | null;
    initialScheduleAt?: string | null;
    initialDestination?: Destination | null;
}) {
    const me = useMeData();
    const { data: mentionsData } = useQuery(workspaceMentionsQuery);
    const { data: limitsData } = useQuery(platformLimitsQuery);
    const title = firstLineTitle(post?.segments ?? ['']);
    const isPublished = Boolean(
        post && post.targets.some((t) => t.status === 'published'),
    );
    usePostStatusPoll(post);

    useEffect(() => {
        document.title = title ? `${title} — Compose` : 'Compose';

        return () => {
            document.title = 'Shoutrrr';
        };
    }, [title]);

    const accounts = me?.shell.accounts ?? [];
    const sets = me?.shell.sets ?? [];
    const limits = limitsData?.limits ?? [];
    const savedMentions = mentionsData?.data ?? [];
    const showMetrics = Boolean(me?.features.analytics);

    return (
        <div className="mx-auto w-full max-w-7xl px-4 pt-6 pb-16 sm:px-6">
            <div className="sticky top-0 z-10 mb-5 flex items-center gap-2 border-b border-border bg-background/85 px-2 py-2 backdrop-blur-md">
                <Button
                    nativeButton={false}
                    variant="ghost"
                    size="sm"
                    className="h-8 gap-1.5 px-2 text-muted-foreground hover:text-foreground"
                    render={
                        <Link
                            to="/posts"
                            search={{
                                status: 'all',
                                set: '',
                                platform: '',
                                q: '',
                            }}
                        />
                    }
                >
                    <ArrowLeft className="size-4" />
                    Posts
                </Button>
                <div className="h-4 w-px bg-border" aria-hidden />
                <div className="flex min-w-0 flex-1 items-center gap-1.5">
                    <PenLine
                        className="size-3.5 shrink-0 text-muted-foreground"
                        aria-hidden
                    />
                    <span className="truncate text-[13px] font-medium tracking-tight">
                        {title || 'Untitled draft'}
                    </span>
                </div>
                {post && <PostPageActions post={post} />}
            </div>

            {post && isPublished ? (
                <PublishedPostView post={post} showMetrics={showMetrics} />
            ) : (
                <Composer
                    post={post}
                    accounts={accounts}
                    sets={sets}
                    limits={limits}
                    initialScheduleAt={initialScheduleAt}
                    initialDestination={initialDestination}
                    initialSavedMentions={savedMentions}
                />
            )}
        </div>
    );
}

/** Full-page placeholder while the post detail query resolves. */
export function ComposePageSkeleton() {
    return (
        <div className="mx-auto w-full max-w-7xl px-4 pt-6 pb-16 sm:px-6">
            <Skeleton className="mb-5 h-9 w-full" />
            <Skeleton className="h-96 w-full rounded-2xl" />
        </div>
    );
}
