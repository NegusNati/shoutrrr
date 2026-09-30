import { createFileRoute } from '@tanstack/react-router';

import ConnectedAccounts, { type AccountsSearch } from '@/pages/accounts/index';

export const Route = createFileRoute('/_app/accounts/')({
    validateSearch: (search: Record<string, unknown>): AccountsSearch => ({
        success:
            typeof search.success === 'string' ? search.success : undefined,
        error: typeof search.error === 'string' ? search.error : undefined,
    }),
    component: AccountsIndexRoute,
});

function AccountsIndexRoute() {
    const search = Route.useSearch();

    return <ConnectedAccounts search={search} />;
}
