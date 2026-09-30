import { useInfiniteQuery } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useEffect, useRef, useState } from 'react';

import { FilterTabs } from '@/components/common/filter-tabs';
import { PostRow } from '@/components/posts/post-row';
import { PostListSkeleton } from '@/components/skeletons/post-list-skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Filter, Inbox, Search, SearchX, X } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { postsInfiniteQuery } from '@/features/posts/posts';
import { dashboard } from '@/routes';

export type PostsSearch = {
    status: string;
    set: string;
    platform: string;
    q: string;
};

type StatusTab = 'all' | 'scheduled' | 'draft' | 'published' | 'missed';

const STATUS_TABS: { value: StatusTab; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'draft', label: 'Drafts' },
    { value: 'published', label: 'Published' },
    { value: 'missed', label: 'Missed' },
];

const PLATFORM_OPTIONS: { value: string; label: string }[] = [
    { value: 'x', label: 'X' },
    { value: 'bluesky', label: 'Bluesky' },
    { value: 'linkedin', label: 'LinkedIn' },
];

function FilterChip({
    label,
    onClear,
}: {
    label: string;
    onClear: () => void;
}) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-muted py-0.5 pr-1 pl-2 text-[12px] font-medium text-foreground">
            {label}
            <button
                type="button"
                aria-label={`Remove ${label} filter`}
                onClick={onClear}
                className="grid size-4 place-items-center rounded-full text-muted-foreground hover:bg-foreground/10 hover:text-foreground"
            >
                <X className="size-3" />
            </button>
        </span>
    );
}

export default function PostsIndexPage({ search }: { search: PostsSearch }) {
    const navigate = useNavigate();

    const [localQ, setLocalQ] = useState(search.q);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Keep localQ in sync when search params change (e.g. back/forward nav)
    useEffect(() => {
        setLocalQ(search.q);
    }, [search.q]);

    function applyFilters(next: Partial<PostsSearch>) {
        void navigate({
            to: '/posts',
            search: {
                status: next.status ?? search.status,
                set: next.set ?? search.set,
                platform: next.platform ?? search.platform,
                q: next.q ?? search.q,
            },
            replace: true,
        });
    }

    function handleQChange(value: string) {
        setLocalQ(value);
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
        debounceRef.current = setTimeout(() => {
            applyFilters({ q: value });
        }, 250);
    }

    function clearQ() {
        setLocalQ('');
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
        applyFilters({ q: '' });
    }

    function handleStatusChange(status: StatusTab) {
        applyFilters({ status });
    }

    // Platform is a single-select checkbox toggle (click again to deselect)
    function handlePlatformToggle(platform: string) {
        applyFilters({
            platform: search.platform === platform ? '' : platform,
        });
    }

    function handleSetChange(setId: string) {
        applyFilters({ set: setId === 'all' ? '' : setId });
    }

    const activeFilterCount =
        (search.platform !== '' ? 1 : 0) + (search.set !== '' ? 1 : 0);

    const hasActiveFilter =
        activeFilterCount > 0 || search.q !== '' || search.status !== 'all';

    const platformLabel = PLATFORM_OPTIONS.find(
        (o) => o.value === search.platform,
    )?.label;

    const { data, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } =
        useInfiniteQuery(postsInfiniteQuery(search));

    const sets = data?.pages[0]?.meta.sets ?? [];
    const counts = data?.pages[0]?.meta.counts;
    const setLabel = sets.find((s) => s.id === search.set)?.name;

    const items = data?.pages.flatMap((page) => page.data) ?? [];

    // IntersectionObserver sentinel — the InfiniteScroll equivalent.
    const sentinelRef = useRef<HTMLDivElement | null>(null);
    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (!sentinel || !hasNextPage) {
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            if (entries[0]?.isIntersecting && !isFetchingNextPage) {
                void fetchNextPage();
            }
        });
        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-0">
            {/* Command bar */}
            <div className="sticky top-0 z-10 flex flex-wrap items-center gap-2 border-b border-border bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                <h2 className="mr-2 text-[15px] font-semibold tracking-tight">
                    Posts
                </h2>

                {/* Search */}
                <div className="relative order-last w-full sm:order-none sm:max-w-xs sm:flex-1">
                    <Search className="pointer-events-none absolute inset-y-0 left-2.5 my-auto size-3.5 text-muted-foreground" />
                    <Input
                        placeholder="Search posts…"
                        value={localQ}
                        onChange={(e) => handleQChange(e.target.value)}
                        className="h-8 pr-7 pl-8 text-sm"
                    />
                    {localQ && (
                        <button
                            type="button"
                            aria-label="Clear search"
                            onClick={clearQ}
                            className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                </div>

                {/* Filter dropdown */}
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 gap-1.5"
                            />
                        }
                    >
                        <Filter className="size-3.5" />
                        Filter
                        {activeFilterCount > 0 && (
                            <Badge
                                variant="secondary"
                                className="ml-0.5 h-4 rounded-full px-1.5 text-[10px]"
                            >
                                {activeFilterCount}
                            </Badge>
                        )}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuLabel className="text-xs font-medium text-muted-foreground">
                            Platform
                        </DropdownMenuLabel>
                        {PLATFORM_OPTIONS.map((opt) => (
                            <DropdownMenuCheckboxItem
                                key={opt.value}
                                checked={search.platform === opt.value}
                                closeOnClick={false}
                                onCheckedChange={() =>
                                    handlePlatformToggle(opt.value)
                                }
                            >
                                {opt.label}
                            </DropdownMenuCheckboxItem>
                        ))}
                        {sets.length > 0 && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel className="text-xs font-medium text-muted-foreground">
                                    Set
                                </DropdownMenuLabel>
                                <DropdownMenuRadioGroup
                                    value={search.set || 'all'}
                                    onValueChange={handleSetChange}
                                >
                                    <DropdownMenuRadioItem value="all">
                                        All sets
                                    </DropdownMenuRadioItem>
                                    {sets.map((s) => (
                                        <DropdownMenuRadioItem
                                            key={s.id}
                                            value={s.id}
                                        >
                                            {s.name}
                                        </DropdownMenuRadioItem>
                                    ))}
                                </DropdownMenuRadioGroup>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>

                <div className="ml-auto">
                    <Button
                        nativeButton={false}
                        size="sm"
                        className="h-8"
                        render={<a href={dashboard().url} />}
                    >
                        New post
                    </Button>
                </div>
            </div>

            {/* Status tabs */}
            <div className="border-b border-border px-4 py-2">
                <FilterTabs
                    tabs={STATUS_TABS.map((tab) => ({
                        ...tab,
                        count: counts?.[tab.value] ?? 0,
                    }))}
                    value={search.status}
                    onChange={(v) => handleStatusChange(v as StatusTab)}
                />
            </div>

            {/* Active filter chips */}
            {(search.platform || search.set) && (
                <div className="flex flex-wrap items-center gap-1.5 border-b border-border px-4 py-2">
                    <span className="text-[12px] text-muted-foreground">
                        Filters
                    </span>
                    {search.platform && (
                        <FilterChip
                            label={platformLabel ?? search.platform}
                            onClear={() =>
                                handlePlatformToggle(search.platform)
                            }
                        />
                    )}
                    {search.set && (
                        <FilterChip
                            label={setLabel ?? 'Set'}
                            onClear={() => handleSetChange('all')}
                        />
                    )}
                    <button
                        type="button"
                        onClick={() => applyFilters({ platform: '', set: '' })}
                        className="ml-1 text-[12px] text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                    >
                        Clear all
                    </button>
                </div>
            )}

            {/* List body */}
            <div className="p-4">
                {isLoading ? (
                    <PostListSkeleton />
                ) : items.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                {hasActiveFilter ? <SearchX /> : <Inbox />}
                            </EmptyMedia>
                            <EmptyTitle>
                                {hasActiveFilter
                                    ? 'No matching posts'
                                    : 'No posts yet'}
                            </EmptyTitle>
                            {hasActiveFilter && (
                                <EmptyDescription>
                                    No posts match your search or filters.
                                </EmptyDescription>
                            )}
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <div className="rounded-xl border border-border">
                            {items.map((post) => (
                                <PostRow key={post.id} post={post} />
                            ))}
                        </div>

                        {isFetchingNextPage && (
                            <Skeleton className="mt-2 h-12 w-full" />
                        )}

                        {hasNextPage ? (
                            <div ref={sentinelRef} className="h-px" />
                        ) : (
                            <p className="mt-4 text-center text-xs text-muted-foreground">
                                All posts loaded.
                            </p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
