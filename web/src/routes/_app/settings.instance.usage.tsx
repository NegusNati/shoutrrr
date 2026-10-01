import { createFileRoute } from '@tanstack/react-router';

import InstanceUsagePage, {
    type UsageSearch,
} from '@/pages/settings/instance-usage';

export const Route = createFileRoute('/_app/settings/instance/usage')({
    validateSearch: (search: Record<string, unknown>): UsageSearch => ({
        search: typeof search.search === 'string' ? search.search : undefined,
        sort:
            search.sort === 'name' || search.sort === 'spend'
                ? search.sort
                : undefined,
        workspace:
            typeof search.workspace === 'string' ? search.workspace : undefined,
        page:
            typeof search.page === 'number' && search.page > 0
                ? search.page
                : undefined,
    }),
    component: InstanceUsageRoute,
});

function InstanceUsageRoute() {
    const search = Route.useSearch();

    return <InstanceUsagePage search={search} />;
}
