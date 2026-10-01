import { describe, expect, it } from 'vitest';

import { isStatusTab, STATUS_TABS } from '../status-tabs';

describe('isStatusTab', () => {
    it.each(STATUS_TABS.map((tab) => tab.value))('accepts "%s"', (value) => {
        expect(isStatusTab(value)).toBe(true);
    });

    it.each([['deleted'], [''], ['ALL'], [null], [5]])(
        'rejects %s',
        (value) => {
            expect(isStatusTab(value)).toBe(false);
        },
    );
});
