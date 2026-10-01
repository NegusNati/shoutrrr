import { createFileRoute } from '@tanstack/react-router';

import MessagesIndexPage, { type MessagesSearch } from '@/pages/messages/index';

const str = (v: unknown): string => (typeof v === 'string' ? v : '');

export const Route = createFileRoute('/_app/messages/')({
    validateSearch: (search: Record<string, unknown>): MessagesSearch => ({
        conversation: str(search.conversation),
        archived: search.archived === true || search.archived === 'true',
    }),
    component: MessagesIndexRoute,
});

function MessagesIndexRoute() {
    const search = Route.useSearch();

    return <MessagesIndexPage search={search} />;
}
