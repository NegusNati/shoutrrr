import createClient from 'openapi-fetch';

import { xsrfHeader } from '@/lib/csrf';

import type { paths } from './api/schema.gen';

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** First validation message for a field, matching Fortify's `errors` bag. */
    fieldError(field: string): string | undefined {
        return this.errors[field]?.[0];
    }
}

type ApiOptions = {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    body?: unknown;
    headers?: Record<string, string>;
};

/** Parse a JSON response; throw ApiError (with Laravel's `errors` bag) on non-2xx. */
async function parseJsonResponse<T>(response: Response): Promise<T> {
    if (response.status === 204) {
        return undefined as T;
    }

    const payload = (await response.json().catch(() => ({}))) as Record<
        string,
        unknown
    >;

    if (!response.ok) {
        // Laravel's error contract: { message: string, errors: {field: string[]} }
        const errors =
            typeof payload.errors === 'object' && payload.errors !== null
                ? (payload.errors as Record<string, string[]>)
                : {};

        throw new ApiError(
            response.status,
            typeof payload.message === 'string'
                ? payload.message
                : `Request failed (${response.status})`,
            errors,
        );
    }

    return payload as T;
}

/**
 * Thin fetch wrapper for the first-party API. Session-authenticated (cookie +
 * XSRF header); returns parsed JSON and throws ApiError on non-2xx so callers
 * can pattern-match on `status` (401 → login redirect, 422 → field errors).
 */
export async function apiFetch<T = unknown>(
    path: string,
    options: ApiOptions = {},
): Promise<T> {
    const response = await fetch(`/api/v1/${path}`, {
        method: options.method ?? 'GET',
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            ...(options.body !== undefined && {
                'Content-Type': 'application/json',
            }),
            ...(options.method !== undefined && options.method !== 'GET'
                ? xsrfHeader()
                : {}),
            ...options.headers,
        },
        ...(options.body !== undefined && {
            body: JSON.stringify(options.body),
        }),
    });

    return parseJsonResponse<T>(response);
}

export const api = {
    get: <T = unknown>(path: string) => apiFetch<T>(path),
    post: <T = unknown>(path: string, body?: unknown) =>
        apiFetch<T>(path, { method: 'POST', body }),
    put: <T = unknown>(path: string, body?: unknown) =>
        apiFetch<T>(path, { method: 'PUT', body }),
    patch: <T = unknown>(path: string, body?: unknown) =>
        apiFetch<T>(path, { method: 'PATCH', body }),
    delete: <T = unknown>(path: string) =>
        apiFetch<T>(path, { method: 'DELETE' }),
};

/**
 * Generated-schema typed client — paths, params, and response shapes come from
 * `web/src/lib/api/schema.gen.ts` (built by `bun run api:gen` from the Scramble
 * OpenAPI export). Same session + XSRF contract as apiFetch.
 */
export const apiClient = createClient<paths>({
    baseUrl: '/api/v1',
    credentials: 'include',
});

apiClient.use({
    onRequest({ request }) {
        request.headers.set('Accept', 'application/json');
        if (request.method !== 'GET') {
            for (const [key, value] of Object.entries(xsrfHeader())) {
                request.headers.set(key, value);
            }
        }
        return request;
    },
});

/**
 * Unwraps an apiClient call: returns the typed payload on success, throws
 * ApiError (with Laravel's `errors` field bag) on failure — so call sites keep
 * the same 401-redirect / 422-field-errors contract as apiFetch.
 */
export async function apiCall<T>(
    request: Promise<{
        data?: unknown;
        error?: unknown;
        response: Response;
    }>,
): Promise<T> {
    const { data, error, response } = await request;

    if (error === undefined || error === null) {
        return data as T;
    }

    const body =
        typeof error === 'object' && error !== null
            ? (error as { message?: unknown; errors?: unknown })
            : {};

    throw new ApiError(
        response.status,
        typeof body.message === 'string'
            ? body.message
            : `Request failed (${response.status})`,
        typeof body.errors === 'object' && body.errors !== null
            ? (body.errors as Record<string, string[]>)
            : {},
    );
}

/**
 * POSTs to non-API (web) endpoints such as Fortify's auth routes and the
 * logout route. Same XSRF contract as apiFetch, but the path is absolute.
 */
export async function webPost<T = unknown>(
    url: string,
    body: Record<string, unknown> = {},
): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...xsrfHeader(),
        },
        body: JSON.stringify(body),
    });

    return parseJsonResponse<T>(response);
}
