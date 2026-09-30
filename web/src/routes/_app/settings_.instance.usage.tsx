import { createFileRoute } from '@tanstack/react-router';

import InstanceUsage, {
    type InstanceUsageSearch,
} from '@/pages/settings/instance/usage';

export const Route = createFileRoute('/_app/settings_/instance/usage')({
    validateSearch: (
        search: Record<string, unknown>,
    ): InstanceUsageSearch => ({
        search: typeof search.search === 'string' ? search.search : '',
        sort: search.sort === 'name' ? 'name' : 'spend',
        workspace:
            typeof search.workspace === 'string' ? search.workspace : '',
        page:
            typeof search.page === 'number' && search.page > 0
                ? search.page
                : 1,
    }),
    component: InstanceUsageRoute,
});

function InstanceUsageRoute() {
    const search = Route.useSearch();

    return <InstanceUsage search={search} />;
}
