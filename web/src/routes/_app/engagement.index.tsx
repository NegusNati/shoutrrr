import { createFileRoute } from '@tanstack/react-router';

import EngagementIndexPage, {
    type EngagementSearch,
} from '@/pages/engagement/index';

const str = (v: unknown): string => (typeof v === 'string' ? v : '');

export const Route = createFileRoute('/_app/engagement/')({
    validateSearch: (search: Record<string, unknown>): EngagementSearch => ({
        account: str(search.account),
        platform: str(search.platform),
        target: str(search.target),
        post: str(search.post),
        reply: str(search.reply),
        unread: search.unread === true || search.unread === 'true',
        archived: search.archived === true || search.archived === 'true',
    }),
    component: EngagementIndexRoute,
});

function EngagementIndexRoute() {
    const search = Route.useSearch();

    return <EngagementIndexPage search={search} />;
}
