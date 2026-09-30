export type StatusTab = 'all' | 'scheduled' | 'draft' | 'published' | 'missed';

export const STATUS_TABS: { value: StatusTab; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'draft', label: 'Drafts' },
    { value: 'published', label: 'Published' },
    { value: 'missed', label: 'Missed' },
];

const STATUS_TAB_VALUES = new Set<StatusTab>(
    STATUS_TABS.map((tab) => tab.value),
);

export function isStatusTab(value: unknown): value is StatusTab {
    return STATUS_TAB_VALUES.has(value as StatusTab);
}
