/** @vitest-environment jsdom */

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, createElement } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { postKeys } from '@/features/posts/compose.queries';
import { usePostStatusPoll } from '@/hooks/compose/use-post-status-poll';
import type { PostView, TargetStatus, TargetView } from '@/types/compose';

let root: Root | null = null;
let container: HTMLDivElement | null = null;
let queryClient: QueryClient;
let invalidateSpy: ReturnType<typeof vi.spyOn>;

function target(id: string, status: TargetStatus): TargetView {
    return {
        id,
        connected_account_id: `account-${id}`,
        platform: 'x',
        handle: '@handle',
        display_name: null,
        avatar_url: null,
        sections: ['Hello'],
        content_override: null,
        auto_split: true,
        format: 'feed',
        issues: [],
        status,
        error_kind: null,
        error_message: null,
        attempts: 0,
        remote_id: null,
    };
}

function post(targets: TargetView[], status: PostView['status']): PostView {
    return {
        id: 'post-1',
        base_text: 'Hello',
        segments: ['Hello'],
        status,
        published_at: null,
        updated_at: '2026-07-16T10:00:00+00:00',
        scheduled_at: null,
        auto_repost: null,
        destination: { kind: 'all', id: null },
        targets,
        media: [],
    };
}

function Harness({ value }: { value: PostView }) {
    usePostStatusPoll(value);

    return null;
}

function render(value: PostView) {
    act(() => {
        root?.render(
            createElement(
                QueryClientProvider,
                { client: queryClient },
                createElement(Harness, { value }),
            ),
        );
    });
}

beforeEach(() => {
    vi.useFakeTimers();
    queryClient = new QueryClient();
    invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries');
    container = document.createElement('div');
    root = createRoot(container);
});

afterEach(() => {
    act(() => root?.unmount());
    root = null;
    container = null;
    queryClient.clear();
    vi.clearAllMocks();
    vi.useRealTimers();
});

describe('usePostStatusPoll', () => {
    it('keeps invalidating after the first target publishes while another is queued', async () => {
        render(
            post(
                [
                    target('published', 'published'),
                    target('queued', 'pending'),
                ],
                'publishing',
            ),
        );

        await act(async () => {
            await vi.advanceTimersByTimeAsync(3000);
        });

        expect(invalidateSpy).toHaveBeenCalledWith({
            queryKey: postKeys.detail('post-1'),
        });
        expect(invalidateSpy).toHaveBeenCalledWith({
            queryKey: postKeys.metrics('post-1'),
        });
    });

    it('stops polling once every target is terminal', async () => {
        render(post([target('first', 'publishing')], 'publishing'));

        await act(async () => {
            await vi.advanceTimersByTimeAsync(3000);
        });
        const callsWhileActive = invalidateSpy.mock.calls.length;
        expect(callsWhileActive).toBeGreaterThan(0);

        render(post([target('first', 'published')], 'published'));

        await act(async () => {
            await vi.advanceTimersByTimeAsync(6000);
        });
        expect(invalidateSpy.mock.calls.length).toBe(callsWhileActive);
    });
});
