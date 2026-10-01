import { createFileRoute } from '@tanstack/react-router';

import type { UsageFilters } from '@/features/settings/instance-settings';
import InstanceUsage from '@/pages/settings/instance-usage';

export const Route = createFileRoute('/_app/settings/instance/usage')({
    validateSearch: (search: Record<string, unknown>): UsageFilters => ({
        search: typeof search.search === 'string' ? search.search : null,
        sort: search.sort === 'name' ? 'name' : 'spend',
        workspace:
            typeof search.workspace === 'string' ? search.workspace : null,
        page:
            typeof search.page === 'number' && search.page > 0
                ? Math.floor(search.page)
                : 1,
    }),
    component: InstanceUsageRoute,
});

function InstanceUsageRoute() {
    const filters = Route.useSearch();

    return <InstanceUsage filters={filters} />;
}
