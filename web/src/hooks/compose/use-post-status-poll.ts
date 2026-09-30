import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';

import { shouldPollPostStatus } from '@/lib/compose/publish-status';
import { postKeys } from '@/features/posts/compose.queries';
import type { PostView } from '@/types/compose';

const POLL_INTERVAL_MS = 3000;

/**
 * Keep async target statuses fresh across composer/read-only view changes:
 * while any target is still publishing/queued, invalidate the post + metrics
 * queries every 3s so TanStack Query refetches them.
 */
export function usePostStatusPoll(post: PostView | null) {
    const queryClient = useQueryClient();
    const active = post ? shouldPollPostStatus(post) : false;
    const postId = post?.id;

    useEffect(() => {
        if (!active || !postId) {
            return;
        }

        const interval = window.setInterval(() => {
            void queryClient.invalidateQueries({
                queryKey: postKeys.detail(postId),
            });
            void queryClient.invalidateQueries({
                queryKey: postKeys.metrics(postId),
            });
        }, POLL_INTERVAL_MS);

        return () => window.clearInterval(interval);
    }, [active, postId, queryClient]);
}
