import { QueryClient } from '@tanstack/react-query';

import { ApiError } from '@/lib/api';

export const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 15_000,
            retry: (failureCount, error) => {
                // 4xx is a steady-state answer (401/403/404/422) — never retry it.
                if (
                    error instanceof ApiError &&
                    error.status >= 400 &&
                    error.status < 500
                ) {
                    return false;
                }

                return failureCount < 2;
            },
        },
    },
});
