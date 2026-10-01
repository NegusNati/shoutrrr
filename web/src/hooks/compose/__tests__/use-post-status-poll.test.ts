/** @vitest-environment jsdom */

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, createElement } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { composeQuery } from '@/features/compose/compose';
import { usePostStatusPoll } from '@/hooks/compose/use-post-status-poll';
import type { PostView, TargetStatus, TargetView } from '@/types/compose';

let root: Root | null = null;
let container: HTMLDivElement | null = null;
let queryClient: QueryClient;
let invalidate: ReturnType<typeof vi.fn>;

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
    invalidate = vi.fn();
    queryClient.invalidateQueries = invalidate as never;
    container = document.createElement('div');
    root = createRoot(container);
});

afterEach(() => {
    act(() => root?.unmount());
    root = null;
    container = null;
    vi.clearAllMocks();
    vi.useRealTimers();
});

describe('usePostStatusPoll', () => {
    it('keeps polling after the first target publishes while another is queued', async () => {
        render(
            post(
                [target('published', 'published'), target('queued', 'pending')],
                'publishing',
            ),
        );

        await act(async () => {
            await vi.advanceTimersByTimeAsync(6500);
        });

        expect(invalidate).toHaveBeenCalledTimes(2);
        expect(invalidate).toHaveBeenCalledWith({
            queryKey: composeQuery('post-1').queryKey,
        });
    });

    it('stops polling once every target is terminal', async () => {
        render(post([target('first', 'publishing')], 'publishing'));

        await act(async () => {
            await vi.advanceTimersByTimeAsync(3500);
        });
        expect(invalidate).toHaveBeenCalledOnce();

        invalidate.mockClear();
        render(post([target('first', 'published')], 'published'));

        await act(async () => {
            await vi.advanceTimersByTimeAsync(6500);
        });
        expect(invalidate).not.toHaveBeenCalled();
    });
});
