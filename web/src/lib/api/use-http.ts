import { useCallback, useRef, useState } from 'react';

import { ApiError, apiFetch } from '@/lib/api';

/**
 * Drop-in replacement for Inertia v3's `useHttp` — same call-site surface
 * (`data` / `setData` / `transform` / `post(url, { onSuccess, onHttpException,
 * onNetworkError })`), backed by the first-party `apiFetch` so composer code
 * ported off Inertia keeps working against `/api/v1` session auth.
 */

export type HttpExceptionResponse = { status: number; data: unknown };

export type HttpCallbacks<TRes> = {
    onSuccess?: (data: TRes) => unknown;
    /** Fires for validation failures (422) with Laravel's `errors` bag. */
    onError?: (errors: Record<string, string[]>) => unknown;
    /** Fires for non-2xx responses that aren't validation errors. */
    onHttpException?: (response: HttpExceptionResponse) => unknown;
    onNetworkError?: (error: unknown) => unknown;
    onFinish?: () => unknown;
    onCancel?: () => unknown;
};

type HttpMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

/** Mirror Inertia: any File/Blob value flips the whole body to multipart. */
function hasBinaryValue(value: unknown): boolean {
    if (value instanceof File || value instanceof Blob) {
        return true;
    }
    return (
        typeof value === 'object' &&
        value !== null &&
        Object.values(value).some((v) => v instanceof File || v instanceof Blob)
    );
}

function toFormData(value: Record<string, unknown>): FormData {
    const form = new FormData();
    for (const [key, entry] of Object.entries(value)) {
        if (entry === undefined || entry === null) {
            continue;
        }
        if (entry instanceof File || entry instanceof Blob) {
            form.append(key, entry);
        } else if (Array.isArray(entry)) {
            for (const item of entry) {
                form.append(
                    `${key}[]`,
                    item instanceof File || item instanceof Blob
                        ? item
                        : typeof item === 'string'
                          ? item
                          : JSON.stringify(item),
                );
            }
        } else if (typeof entry === 'object') {
            form.append(key, JSON.stringify(entry));
        } else {
            // Scalars (objects and binaries are handled above).
            form.append(
                key,
                typeof entry === 'string' ? entry : JSON.stringify(entry),
            );
        }
    }
    return form;
}

type SetDataArg<TData> =
    | Partial<TData>
    | ((previous: TData) => TData | Partial<TData>);

export type Http<TData extends object, TRes> = {
    data: TData;
    processing: boolean;
    response: TRes | undefined;
    setData: (value: SetDataArg<TData>) => void;
    transform: (callback: (data: TData) => unknown) => void;
    cancel: () => void;
} & Record<
    HttpMethod,
    (path: string, options?: HttpCallbacks<TRes>) => Promise<TRes | undefined>
>;

export function useHttp<TData extends object, TRes = unknown>(
    initialData: TData,
): Http<TData, TRes> {
    const [data, setDataState] = useState<TData>(initialData);
    const [processing, setProcessing] = useState(false);
    const [response, setResponse] = useState<TRes | undefined>(undefined);
    const transformRef = useRef<(data: TData) => unknown>((d) => d);
    const dataRef = useRef(data);
    dataRef.current = data;
    const abortRef = useRef<AbortController | null>(null);

    const setData = useCallback((value: SetDataArg<TData>) => {
        setDataState((previous) => {
            const next =
                typeof value === 'function' ? value(previous) : value;
            return { ...previous, ...next };
        });
    }, []);

    const transform = useCallback(
        (callback: (data: TData) => unknown) => {
            transformRef.current = callback;
        },
        [],
    );

    const cancel = useCallback(() => {
        abortRef.current?.abort();
    }, []);

    const request = useCallback(
        async (
            method: HttpMethod,
            path: string,
            options: HttpCallbacks<TRes> = {},
        ): Promise<TRes | undefined> => {
            const controller = new AbortController();
            abortRef.current = controller;
            setProcessing(true);
            try {
                const payload = transformRef.current(dataRef.current);
                const body =
                    payload === undefined
                        ? undefined
                        : hasBinaryValue(payload)
                          ? toFormData(payload as Record<string, unknown>)
                          : payload;
                const result = await apiFetch<TRes>(path, {
                    method: method.toUpperCase() as
                        | 'GET'
                        | 'POST'
                        | 'PUT'
                        | 'PATCH'
                        | 'DELETE',
                    body,
                    signal: controller.signal,
                });
                setResponse(result);
                await options.onSuccess?.(result);
                return result;
            } catch (error) {
                if (controller.signal.aborted) {
                    options.onCancel?.();
                } else if (error instanceof ApiError) {
                    // onHttpException sees every non-2xx (call sites branch on
                    // status); onError additionally surfaces the 422 bag.
                    if (Object.keys(error.errors).length > 0) {
                        options.onError?.(error.errors);
                    }
                    options.onHttpException?.({
                        status: error.status,
                        data: error.body,
                    });
                } else {
                    options.onNetworkError?.(error);
                }
                return undefined;
            } finally {
                abortRef.current = null;
                setProcessing(false);
                options.onFinish?.();
            }
        },
        [],
    );

    return {
        data,
        processing,
        response,
        setData,
        transform,
        cancel,
        get: (path, options) => request('get', path, options),
        post: (path, options) => request('post', path, options),
        put: (path, options) => request('put', path, options),
        patch: (path, options) => request('patch', path, options),
        delete: (path, options) => request('delete', path, options),
    };
}
