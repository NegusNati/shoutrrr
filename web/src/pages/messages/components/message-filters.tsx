import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

import type { MessagesFilters } from '../types';

type Props = {
    filters: MessagesFilters;
    /** Apply a filter patch to the route's search params (drives the query). */
    onUpdate: (patch: Partial<MessagesFilters>) => void;
};

export function MessageFilters({ filters, onUpdate }: Props) {
    function update(patch: Partial<MessagesFilters>) {
        onUpdate(patch);
    }

    return (
        <div className="flex flex-wrap items-center gap-2 border-b px-3 py-2.5">
            <ToggleGroup
                value={[filters.archived ? 'archived' : 'all']}
                onValueChange={(value) => {
                    const v = value[0];
                    if (v) {
                        update({ archived: v === 'archived' });
                    }
                }}
                variant="outline"
                size="sm"
            >
                <ToggleGroupItem value="all" className="px-3 text-xs">
                    All
                </ToggleGroupItem>
                <ToggleGroupItem value="archived" className="px-3 text-xs">
                    Archived
                </ToggleGroupItem>
            </ToggleGroup>
        </div>
    );
}
