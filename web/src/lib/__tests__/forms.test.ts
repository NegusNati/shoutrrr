import { describe, expect, it } from 'vitest';

import { fieldString } from '../forms';

describe('fieldString', () => {
    it('returns the field value', () => {
        const form = new FormData();
        form.set('email', 'a@b.c');

        expect(fieldString(form, 'email')).toBe('a@b.c');
    });

    it('returns empty string for a missing field', () => {
        expect(fieldString(new FormData(), 'email')).toBe('');
    });

    it('returns empty string for a File value', () => {
        const form = new FormData();
        form.set('avatar', new File(['x'], 'x.png'));

        expect(fieldString(form, 'avatar')).toBe('');
    });
});
