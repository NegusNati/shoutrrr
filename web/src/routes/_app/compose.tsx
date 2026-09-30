import { createFileRoute } from '@tanstack/react-router';

import { parseDestinationParam } from '@/lib/compose/composer-state';
import { ComposePage } from '@/pages/compose';

export type ComposeSearch = {
    /** ISO instant — calendar slot clicks open the composer pre-scheduled. */
    schedule_at?: string | null;
    /** `account:<id>` | `set:<id>` — command-palette "compose for channel". */
    destination?: string | null;
};

export const Route = createFileRoute('/_app/compose')({
    validateSearch: (search: Record<string, unknown>): ComposeSearch => {
        const parsed: ComposeSearch = {};
        if (typeof search.schedule_at === 'string') {
            parsed.schedule_at = search.schedule_at;
        }
        if (typeof search.destination === 'string') {
            parsed.destination = search.destination;
        }

        return parsed;
    },
    component: ComposeRoute,
});

function ComposeRoute() {
    const search = Route.useSearch();

    return (
        <ComposePage
            post={null}
            initialScheduleAt={search.schedule_at ?? null}
            initialDestination={parseDestinationParam(
                search.destination ?? null,
            )}
        />
    );
}
