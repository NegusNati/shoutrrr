import { describe, expect, it } from 'vitest';

import { buildFeedbackPayload } from '../build-feedback-payload';

// The payload MUST be a plain object, never a `FormData` instance: the
// submission helper (features/feedback) iterates the object's entries into a
// FormData body — handing it a FormData directly would bypass the field
// assembly. These assertions read fields directly off the returned object to
// guard against that regression.
describe('buildFeedbackPayload', () => {
    it('returns a plain object with core fields and omits optional files when null', () => {
        const payload = buildFeedbackPayload({
            type: 'bug',
            message: 'It broke',
            url: 'https://app.test/x',
            browser: 'UA',
            screenshot: null,
            diagnostics: null,
        });

        expect(payload).not.toBeInstanceOf(FormData);
        expect(payload.type).toBe('bug');
        expect(payload.message).toBe('It broke');
        expect(payload.url).toBe('https://app.test/x');
        expect(payload.browser).toBe('UA');
        expect('screenshot' in payload).toBe(false);
        expect('diagnostics' in payload).toBe(false);
    });

    it('attaches the screenshot as a File when present', () => {
        const blob = new Blob(['x'], { type: 'image/png' });
        const payload = buildFeedbackPayload({
            type: 'feedback',
            message: 'nice',
            url: 'u',
            browser: 'UA',
            screenshot: blob,
            diagnostics: null,
        });

        expect(payload).not.toBeInstanceOf(FormData);
        expect(payload.screenshot).toBeInstanceOf(File);
        expect(payload.screenshot?.name).toBe('screenshot.png');
    });

    it('attaches diagnostics as a JSON File when present', () => {
        const payload = buildFeedbackPayload({
            type: 'bug',
            message: 'broke',
            url: 'u',
            browser: 'UA',
            screenshot: null,
            diagnostics: '{"logs":[]}',
        });

        expect(payload.diagnostics).toBeInstanceOf(File);
        expect(payload.diagnostics?.name).toBe('diagnostics.json');
        expect(payload.diagnostics?.type).toBe('application/json');
    });
});
