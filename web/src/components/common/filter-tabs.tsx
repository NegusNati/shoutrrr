import { cn } from '@/lib/utils';

export type FilterTab<T extends string = string> = {
    value: T;
    label: string;
    count?: number;
};

/**
 * Pill-style segmented tabs with optional counts. Generic over the tab value
 * so callers' onChange handlers get their own union type, not `string`.
 */
export function FilterTabs<T extends string>({
    tabs,
    value,
    onChange,
    className,
}: {
    tabs: FilterTab<T>[];
    value: T;
    onChange: (value: T) => void;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'no-scrollbar flex min-w-0 items-center gap-1 overflow-x-auto',
                className,
            )}
        >
            {tabs.map((tab) => {
                const isActive = tab.value === value;

                return (
                    <button
                        key={tab.value}
                        type="button"
                        onClick={() => onChange(tab.value)}
                        className={cn(
                            'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-[13px] whitespace-nowrap transition-colors',
                            isActive
                                ? 'bg-muted font-medium text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.label}
                        {tab.count !== undefined && (
                            <span
                                className={cn(
                                    'text-[11px] tabular-nums',
                                    isActive
                                        ? 'text-foreground/60'
                                        : 'text-muted-foreground/50',
                                )}
                            >
                                {tab.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
