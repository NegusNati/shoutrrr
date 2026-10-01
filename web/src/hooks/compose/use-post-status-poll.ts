import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';

import { composeQuery } from '@/features/compose/compose';
import { shouldPollPostStatus } from '@/lib/compose/publish-status';
import type { PostView } from '@/types/compose';

const POLL_INTERVAL_MS = 3000;

/**
 * Keep async target statuses fresh across composer/read-only view changes —
 * refetches the compose payload (which carries `post`) on an interval while
 * any target is mid-transition.
 */
export function usePostStatusPoll(post: PostView | null) {
    const queryClient = useQueryClient();
    const active = post ? shouldPollPostStatus(post) : false;
    const postId = post?.id;

    useEffect(() => {
        if (!active || !postId) {
            return;
        }

        const timer = setInterval(() => {
            void queryClient.invalidateQueries({
                queryKey: composeQuery(postId).queryKey,
            });
        }, POLL_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [active, postId, queryClient]);
}
