import { createFileRoute } from '@tanstack/react-router';

import CalendarIndexPage, { type CalendarSearch } from '@/pages/calendar/index';

export const Route = createFileRoute('/_app/calendar/')({
    validateSearch: (search: Record<string, unknown>): CalendarSearch => ({
        month:
            typeof search.month === 'string' &&
            /^\d{4}-(0[1-9]|1[0-2])$/.test(search.month)
                ? search.month
                : '',
        view: search.view === 'week' ? 'week' : 'month',
        start: typeof search.start === 'string' ? search.start : '',
    }),
    component: CalendarIndexRoute,
});

function CalendarIndexRoute() {
    const search = Route.useSearch();

    return <CalendarIndexPage search={search} />;
}
