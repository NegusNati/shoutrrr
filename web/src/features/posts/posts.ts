import { infiniteQueryOptions, queryOptions } from '@tanstack/react-query';

import type { PostRowData } from '@/components/posts/post-row';
import { apiCall, apiClient } from '@/lib/api';
import type { paths } from '@/lib/api/schema.gen';
import { queryClient } from '@/lib/query-client';
import type { OnboardingData } from '@/types';
import type { WorkspaceMention } from '@/types/compose';

type PostsIndexQuery = NonNullable<
    paths['/posts']['get']['parameters']['query']
>;

export type PostsFilters = {
    status?: PostsIndexQuery['status'];
    set?: string;
    platform?: string;
    q?: string;
    cursor?: string;
};

export type PostsIndexData = {
    data: PostRowData[];
    pagination: {
        per_page: number;
        next_cursor: string | null;
        prev_cursor: string | null;
        has_more: boolean;
    };
    meta: {
        counts: {
            all: number;
            scheduled: number;
            draft: number;
            published: number;
            missed: number;
        };
        sets: { id: string; name: string }[];
        filters: { status: string; set: string; platform: string; q: string };
    };
};

export type DashboardData = {
    onboarding: OnboardingData | null;
    savedMentions: WorkspaceMention[];
    posts: PostRowData[];
};

const postsQueryParams = (
    filters: PostsFilters,
    cursor?: string,
): PostsIndexQuery => ({
    status: filters.status,
    set: filters.set,
    platform: filters.platform,
    q: filters.q,
    cursor: cursor !== '' ? cursor : undefined,
    sort: 'timeline',
});

export const postsQuery = (filters: PostsFilters) =>
    queryOptions({
        queryKey: ['posts', filters],
        queryFn: () =>
            apiCall<PostsIndexData>(
                apiClient.GET('/posts', {
                    params: { query: postsQueryParams(filters) },
                }),
            ),
    });

/**
 * Cursor-paginated posts list — the TanStack equivalent of the old Inertia
 * InfiniteScroll prop. `cursor` is the API's `next_cursor` page param.
 */
export const postsInfiniteQuery = (filters: PostsFilters) =>
    infiniteQueryOptions({
        queryKey: ['posts', filters],
        queryFn: ({ pageParam }) =>
            apiCall<PostsIndexData>(
                apiClient.GET('/posts', {
                    params: { query: postsQueryParams(filters, pageParam) },
                }),
            ),
        initialPageParam: '',
        getNextPageParam: (lastPage) =>
            lastPage.pagination.has_more
                ? (lastPage.pagination.next_cursor ?? undefined)
                : undefined,
    });

export const dashboardQuery = queryOptions({
    queryKey: ['dashboard'],
    queryFn: () => apiCall<DashboardData>(apiClient.GET('/dashboard')),
});

type PostsInfiniteData = {
    pages: PostsIndexData[];
    pageParams: unknown[];
};

/**
 * Applies `update` to every cached post list — the posts index pages
 * (['posts', filters], infinite-query shape) and the dashboard's recent-posts
 * feed — the TanStack equivalent of the old Inertia `optimistic` prop patch.
 */
export function updatePostInCaches(
    postId: string,
    update: (post: PostRowData) => PostRowData,
) {
    queryClient.setQueriesData<PostsInfiniteData>(
        { queryKey: ['posts'] },
        (infinite) =>
            infinite === undefined
                ? infinite
                : {
                      ...infinite,
                      pages: infinite.pages.map((page) => ({
                          ...page,
                          data: page.data.map((p) =>
                              p.id === postId ? update(p) : p,
                          ),
                      })),
                  },
    );

    queryClient.setQueryData<DashboardData>(['dashboard'], (data) =>
        data === undefined
            ? data
            : {
                  ...data,
                  posts: data.posts.map((p) =>
                      p.id === postId ? update(p) : p,
                  ),
              },
    );
}

export function removePostFromCaches(postId: string) {
    queryClient.setQueriesData<PostsInfiniteData>(
        { queryKey: ['posts'] },
        (infinite) =>
            infinite === undefined
                ? infinite
                : {
                      ...infinite,
                      pages: infinite.pages.map((page) => ({
                          ...page,
                          data: page.data.filter((p) => p.id !== postId),
                      })),
                  },
    );

    queryClient.setQueryData<DashboardData>(['dashboard'], (data) =>
        data === undefined
            ? data
            : { ...data, posts: data.posts.filter((p) => p.id !== postId) },
    );
}

/** Refetches every post-bearing cache after a mutation settles. */
export function invalidatePostQueries() {
    void queryClient.invalidateQueries({ queryKey: ['posts'] });
    void queryClient.invalidateQueries({ queryKey: ['dashboard'] });
}
