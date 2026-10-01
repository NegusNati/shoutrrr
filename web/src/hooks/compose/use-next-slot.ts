import { useEffect, useState } from 'react';

import { apiFetch } from '@/lib/api';
import { dayjs } from '@/lib/datetime/dayjs';

/** Raw payload returned by GET posts/next-slot. */
export type NextSlotPayload = {
    has_schedule: boolean;
    slot: string | null;
    slots?: string[];
    timezone: string;
};

export type QueueSlotStatus =
    | 'idle'
    | 'loading'
    | 'found'
    | 'no-schedule'
    | 'full'
    | 'error';

export type QueueSlotState = {
    status: QueueSlotStatus;
    /** ISO-8601 UTC instant when status === 'found', else null. */
    slot: string | null;
    /** Open ISO-8601 UTC instants the user may choose from. */
    slots: string[];
    /** The scheduling timezone the slot should be displayed in. */
    tz: string;
};

/** Map a resolved payload to one of the three terminal UI states. */
export function deriveQueueStatus(payload: NextSlotPayload): QueueSlotStatus {
    if (!payload.has_schedule) {
        return 'no-schedule';
    }
    if (payload.slot === null) {
        return 'full';
    }

    return 'found';
}

/** Format an ISO instant as a slot label in `tz`, e.g. "Tue, Jun 16 · 9:00 AM". */
export function formatSlotLabel(iso: string, tz: string): string {
    return dayjs(iso).tz(tz).format('ddd, MMM D · h:mm A');
}

/**
 * Fetch the next open posting slot while `active` is true (the Queue tab is
 * selected). Re-fetches whenever it transitions to active so the preview reflects
 * slots taken since the composer loaded. Returns 'idle' while inactive.
 */
export function useNextSlot(
    active: boolean,
    fallbackTz: string,
): QueueSlotState {
    const [state, setState] = useState<QueueSlotState>({
        status: 'idle',
        slot: null,
        slots: [],
        tz: fallbackTz,
    });

    useEffect(() => {
        if (!active) {
            setState({ status: 'idle', slot: null, slots: [], tz: fallbackTz });

            return;
        }

        let cancelled = false;
        setState({ status: 'loading', slot: null, slots: [], tz: fallbackTz });

        const failTerminal = () => {
            if (cancelled) {
                return;
            }
            setState({
                status: 'error',
                slot: null,
                slots: [],
                tz: fallbackTz,
            });
        };

        // Any failure (HTTP error or network) must leave 'loading' for a
        // terminal state.
        apiFetch<NextSlotPayload>('posts/next-slot')
            .then((data) => {
                if (cancelled) {
                    return;
                }
                setState({
                    status: deriveQueueStatus(data),
                    slot: data.slot,
                    slots: data.slots ?? (data.slot ? [data.slot] : []),
                    tz: data.timezone || fallbackTz,
                });
            })
            .catch(() => failTerminal());

        return () => {
            cancelled = true;
        };
    }, [active, fallbackTz]);

    return state;
}
