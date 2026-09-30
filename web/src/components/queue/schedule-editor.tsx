import { useState } from 'react';

import { dayjs } from '@/lib/datetime/dayjs';
import {
    addSlot,
    DISPLAY_DAYS,
    removeSlot,
    type Slot,
    slotsEqual,
} from '@/lib/queue/queue-schedule';

import { CadenceOverview } from './cadence-overview';
import { DayColumn } from './day-column';
import { QuickAddToolbar } from './quick-add-toolbar';
import { SaveBar } from './save-bar';

type Props = {
    initialSlots: Slot[];
    timezone: string;
    canManage: boolean;
    /** Persist the normalized slot list (PUT posting-schedule). */
    onSave: (
        slots: { weekday: number; hour: number; minute: number }[],
    ) => void;
    saving: boolean;
};

/** Stateful schedule editor: owns slot state and composes child panels; the host owns persistence. */
export function ScheduleEditor({
    initialSlots,
    timezone,
    canManage,
    onSave,
    saving,
}: Props) {
    const [slots, setSlots] = useState<Slot[]>(initialSlots);

    const dirty = !slotsEqual(slots, initialSlots);

    const now = dayjs().tz(timezone);
    const todayWeekday = now.day();

    return (
        <div className="space-y-5">
            <CadenceOverview
                slots={slots}
                now={now}
                todayWeekday={todayWeekday}
            />

            {canManage && (
                <QuickAddToolbar slots={slots} onSlotsChange={setSlots} />
            )}

            {canManage && dirty && (
                <SaveBar
                    saving={saving}
                    onSave={() => onSave(slots)}
                    onDiscard={() => setSlots(initialSlots)}
                />
            )}

            {/* Week board */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                {DISPLAY_DAYS.map(({ weekday, label }) => (
                    <DayColumn
                        key={weekday}
                        weekday={weekday}
                        label={label}
                        slots={slots}
                        isToday={weekday === todayWeekday}
                        canManage={canManage}
                        onAdd={(hour, minute) =>
                            setSlots((s) => addSlot(s, weekday, hour, minute))
                        }
                        onRemove={(hour, minute) =>
                            setSlots((s) =>
                                removeSlot(s, weekday, hour, minute),
                            )
                        }
                    />
                ))}
            </div>

            {canManage && dirty && (
                <SaveBar
                    saving={saving}
                    onSave={() => onSave(slots)}
                    onDiscard={() => setSlots(initialSlots)}
                />
            )}
        </div>
    );
}
