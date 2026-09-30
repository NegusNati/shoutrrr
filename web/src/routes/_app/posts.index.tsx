import { createFileRoute } from '@tanstack/react-router';

import { isStatusTab } from '@/lib/posts/status-tabs';
import PostsIndexPage, { type PostsSearch } from '@/pages/posts/index';

export const Route = createFileRoute('/_app/posts/')({
    validateSearch: (search: Record<string, unknown>): PostsSearch => ({
        status: isStatusTab(search.status) ? search.status : 'all',
        set: typeof search.set === 'string' ? search.set : '',
        platform: typeof search.platform === 'string' ? search.platform : '',
        q: typeof search.q === 'string' ? search.q : '',
    }),
    component: PostsIndexRoute,
});

function PostsIndexRoute() {
    const search = Route.useSearch();

    return <PostsIndexPage search={search} />;
}
