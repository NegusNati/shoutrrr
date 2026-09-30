import { createFileRoute } from '@tanstack/react-router';

import MessagesIndexPage, { type MessagesSearch } from '@/pages/messages/index';

export const Route = createFileRoute('/_app/messages/')({
    validateSearch: (search: Record<string, unknown>): MessagesSearch => ({
        archived: search.archived === true || search.archived === 'true',
    }),
    component: MessagesIndexRoute,
});

function MessagesIndexRoute() {
    const search = Route.useSearch();

    return <MessagesIndexPage search={search} />;
}
