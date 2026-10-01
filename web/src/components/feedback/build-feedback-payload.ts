export type FeedbackType = 'bug' | 'feedback' | 'question';

type FeedbackInput = {
    type: FeedbackType;
    message: string;
    url: string;
    browser: string;
    screenshot: Blob | null;
    diagnostics: string | null;
};

export type FeedbackPayload = {
    type: FeedbackType;
    message: string;
    url: string;
    browser: string;
    screenshot?: File;
    diagnostics?: File;
};

/**
 * Build the submission payload as a plain object, not a `FormData` instance.
 *
 * `apiUpload` builds the multipart body by iterating the object's entries — a
 * plain object with `File`-valued properties is what it expects (see
 * `features/user-settings`'s `updateProfile`, which assembles the same shape).
 * The `screenshot`/`diagnostics` keys are omitted entirely when there's no
 * attachment, rather than set to `null`/`undefined`.
 */
export function buildFeedbackPayload(input: FeedbackInput): FeedbackPayload {
    const payload: FeedbackPayload = {
        type: input.type,
        message: input.message,
        url: input.url,
        browser: input.browser,
    };

    if (input.screenshot) {
        payload.screenshot = new File([input.screenshot], 'screenshot.png', {
            type: 'image/png',
        });
    }

    if (input.diagnostics) {
        payload.diagnostics = new File(
            [input.diagnostics],
            'diagnostics.json',
            {
                type: 'application/json',
            },
        );
    }

    return payload;
}
