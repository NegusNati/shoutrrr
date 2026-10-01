import type { FeedbackPayload } from '@/components/feedback/build-feedback-payload';
import { apiUpload } from '@/lib/api';

/**
 * POST /api/v1/feedback as multipart so the optional screenshot and
 * diagnostics files ride alongside the text fields — same shape the legacy
 * web route accepted.
 */
export function submitFeedback(payload: FeedbackPayload) {
    const form = new FormData();
    form.set('type', payload.type);
    form.set('message', payload.message);
    form.set('url', payload.url);
    form.set('browser', payload.browser);
    if (payload.screenshot) {
        form.set('screenshot', payload.screenshot);
    }
    if (payload.diagnostics) {
        form.set('diagnostics', payload.diagnostics);
    }

    return apiUpload<{ ok: boolean }>('feedback', form);
}
