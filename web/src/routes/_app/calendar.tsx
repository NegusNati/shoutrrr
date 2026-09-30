import { createFileRoute } from '@tanstack/react-router';

import { parseYm } from '@/lib/datetime/dayjs';
import { CalendarPage, type CalendarSearch } from '@/pages/calendar';

export const Route = createFileRoute('/_app/calendar')({
    validateSearch: (search: Record<string, unknown>): CalendarSearch => ({
        month:
            typeof search.month === 'string' && parseYm(search.month)
                ? search.month
                : null,
        view: search.view === 'week' ? 'week' : 'month',
        start: typeof search.start === 'string' ? search.start : null,
    }),
    component: CalendarRoute,
});

function CalendarRoute() {
    const search = Route.useSearch();

    return <CalendarPage search={search} />;
}
