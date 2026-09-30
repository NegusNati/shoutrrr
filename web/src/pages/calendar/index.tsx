import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    pointerWithin,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragMoveEvent,
} from '@dnd-kit/core';
import { restrictToWindowEdges } from '@dnd-kit/modifiers';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useEffect, useState } from 'react';

import { AgendaList } from '@/components/posts/calendar/agenda-list';
import { CalendarHeader } from '@/components/posts/calendar/calendar-header';
import {
    MonthGrid,
    computeMonthDrop,
} from '@/components/posts/calendar/month-grid';
import type { WeekDropHint } from '@/components/posts/calendar/week-grid';
import {
    WeekGrid,
    computeWeekDrop,
} from '@/components/posts/calendar/week-grid';
import { CalendarSkeleton } from '@/components/skeletons/calendar-skeleton';
import { calendarKeys, calendarQuery } from '@/features/posts/calendar';
import { useSchedulingTimezone } from '@/hooks/posts/use-scheduling-timezone';
import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { dayjs, parseYm, ymKey } from '@/lib/datetime/dayjs';
import type { Dayjs } from '@/lib/datetime/dayjs';

export type CalendarSearch = {
    /** YYYY-MM anchor month — matches the API window the posts query fetches. */
    month: string | null;
    view: 'month' | 'week';
    /** YYYY-MM-DD anchor day for week view (defaults to today). */
    start: string | null;
};

export function CalendarPage({ search }: { search: CalendarSearch }) {
    const tz = useSchedulingTimezone();
    const anchor =
        (search.month ? parseYm(search.month) : null) ??
        dayjs().tz(tz).startOf('month');
    const yyyymm = ymKey(anchor);
    const view = search.view;

    // Live drop preview for week-view dragging (target column + snapped time).
    const [dropHint, setDropHint] = useState<WeekDropHint | null>(null);

    // Week view anchors on a specific day (the `start` search param), defaulting
    // to today — not the month's 1st. This makes "Week" open the *current* week
    // with a live now-line and solid (non-dimmed) cells, so the hour grid lines
    // read crisply instead of washing out behind all-past, translucent cells.
    const weekAnchor =
        view === 'week'
            ? search.start
                ? dayjs.tz(search.start, 'YYYY-MM-DD', tz)
                : dayjs().tz(tz)
            : anchor;
    const displayAnchor = view === 'week' ? weekAnchor : anchor;

    const label =
        view === 'month'
            ? anchor.format('MMMM YYYY')
            : weekAnchor.format('MMM D, YYYY');

    useEffect(() => {
        document.title = 'Calendar';

        return () => {
            document.title = 'Shoutrrr';
        };
    }, []);

    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data, isPending } = useQuery(calendarQuery(yyyymm));
    const posts = data?.posts;

    // A small activation distance lets a plain click through to the chip's
    // open-post handler; only a >4px drag starts a reschedule.
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor),
    );

    // Week nav keeps `month` synced to the week's month so the month's
    // post query always covers the visible week.
    function goToWeek(weekStart: Dayjs) {
        void navigate({
            to: '/calendar',
            search: {
                month: ymKey(weekStart),
                view: 'week',
                start: weekStart.format('YYYY-MM-DD'),
            },
        });
    }

    function goToMonth(monthAnchor: Dayjs) {
        void navigate({
            to: '/calendar',
            search: { month: ymKey(monthAnchor), view: 'month', start: null },
        });
    }

    function onPrev() {
        if (view === 'month') {
            goToMonth(anchor.subtract(1, 'month'));
        } else {
            goToWeek(weekAnchor.subtract(7, 'day'));
        }
    }

    function onNext() {
        if (view === 'month') {
            goToMonth(anchor.add(1, 'month'));
        } else {
            goToWeek(weekAnchor.add(7, 'day'));
        }
    }

    function onToday() {
        const today = dayjs().tz(tz);
        if (view === 'month') {
            goToMonth(today);
        } else {
            goToWeek(today);
        }
    }

    function onSetView(v: 'month' | 'week') {
        if (v === 'week') {
            goToWeek(dayjs().tz(tz));
        } else {
            goToMonth(anchor);
        }
    }

    function onSelectDate(d: Dayjs) {
        if (view === 'week') {
            goToWeek(d);
        } else {
            goToMonth(d);
        }
    }

    // Clicking an empty slot opens the composer pre-set to that schedule time.
    // Month cells default to 09:00; week cells use the clicked hour. Both resolve
    // the wall-clock time in the user's tz (same math as a drag-reschedule).
    function openComposerAt(day: Dayjs, hour: number) {
        const scheduleAt = computeWeekDrop(day.format('YYYY-MM-DD'), hour, tz);
        void navigate({
            to: '/compose',
            search: { schedule_at: scheduleAt, destination: null },
        });
    }

    function onEmptyDay(day: Dayjs) {
        openComposerAt(day, 9);
    }

    function onEmptyHour(day: Dayjs, hour: number) {
        openComposerAt(day, hour);
    }

    // Week view: derive a 15-minute offset within the dropped-over hour cell from
    // the cursor's vertical position, so drops aren't locked to whole hours.
    // Uses the pointer (activator + delta) rather than the chip rect, so the time
    // tracks where the user is actually pointing.
    function weekDropOffset(e: DragEndEvent | DragMoveEvent): {
        hourDelta: number;
        minute: number;
    } {
        const overRect = e.over?.rect;
        const activator = e.activatorEvent as { clientY?: number } | null;
        if (
            !overRect ||
            overRect.height <= 0 ||
            typeof activator?.clientY !== 'number'
        ) {
            return { hourDelta: 0, minute: 0 };
        }
        const pointerY = activator.clientY + e.delta.y;
        const frac = Math.min(
            Math.max((pointerY - overRect.top) / overRect.height, 0),
            1,
        );
        const snapped = Math.round((frac * 60) / 15) * 15; // 0 | 15 | 30 | 45 | 60
        return snapped >= 60
            ? { hourDelta: 1, minute: 0 }
            : { hourDelta: 0, minute: snapped };
    }

    // Track the live drop target while dragging in week view so the grid can
    // render a preview line + time at exactly where the post will land.
    function onDragMove(e: DragMoveEvent) {
        if (view !== 'week') {
            return;
        }
        const over = e.over?.data.current as
            | { day?: string; hour?: number }
            | undefined;
        if (!over?.day) {
            setDropHint(null);
            return;
        }
        const { hourDelta, minute } = weekDropOffset(e);
        setDropHint({
            day: over.day,
            hour: (over.hour ?? 0) + hourDelta,
            minute,
        });
    }

    function onDragCancel() {
        setDropHint(null);
    }

    function onDragEnd(e: DragEndEvent) {
        setDropHint(null);
        const active = e.active.data.current as
            | { scheduledAt?: string | null }
            | undefined;
        const over = e.over?.data.current as
            | { day?: string; hour?: number }
            | undefined;
        if (!active?.scheduledAt || !over?.day) {
            return;
        }
        let nextIso: string;
        if (view === 'month') {
            nextIso = computeMonthDrop(active.scheduledAt, over.day, tz);
        } else {
            const { hourDelta, minute } = weekDropOffset(e);
            nextIso = computeWeekDrop(
                over.day,
                (over.hour ?? 0) + hourDelta,
                tz,
                minute,
            );
        }
        if (nextIso === active.scheduledAt) {
            return;
        }
        const postId = String(e.active.id).replace(/^post-/, '');
        void apiFetch(endpoints.postSchedule(postId), {
            method: 'POST',
            body: { scheduled_at: nextIso },
        })
            .catch(() => undefined)
            .then(() =>
                queryClient.invalidateQueries({
                    queryKey: calendarKeys.month(yyyymm),
                }),
            );
    }

    return (
        <div className="mx-auto w-full max-w-6xl px-4 pt-6 pb-16 sm:px-6">
            <CalendarHeader
                label={label}
                view={view}
                anchor={displayAnchor}
                onPrev={onPrev}
                onNext={onNext}
                onToday={onToday}
                onSetView={onSetView}
                onSelectDate={onSelectDate}
            />

            <DndContext
                sensors={sensors}
                collisionDetection={pointerWithin}
                modifiers={[restrictToWindowEdges]}
                onDragMove={onDragMove}
                onDragEnd={onDragEnd}
                onDragCancel={onDragCancel}
            >
                {isPending || !posts ? (
                    <CalendarSkeleton />
                ) : (
                    <>
                        {/* Desktop: the dense month/week grids with drag-to-reschedule. */}
                        <div className="hidden sm:block">
                            {view === 'month' ? (
                                <MonthGrid
                                    anchor={anchor}
                                    posts={posts}
                                    onEmptyDayClick={onEmptyDay}
                                />
                            ) : (
                                <WeekGrid
                                    anchor={weekAnchor}
                                    posts={posts}
                                    onEmptyHourClick={onEmptyHour}
                                    dropHint={dropHint}
                                />
                            )}
                        </div>

                        {/* Mobile: a tappable agenda over the same date window. */}
                        <div className="sm:hidden">
                            <AgendaList
                                anchor={view === 'month' ? anchor : weekAnchor}
                                view={view}
                                posts={posts}
                                onEmptyDayClick={onEmptyDay}
                            />
                        </div>
                    </>
                )}
            </DndContext>
        </div>
    );
}
