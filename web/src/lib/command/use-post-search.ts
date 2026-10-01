import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import {
    commandSearchQuery,
    type CommandPost,
} from '@/features/command-search/command-search';

const DEBOUNCE_MS = 200;

/**
 * Debounced post search for the command palette. Queries shorter than two
 * characters are inert; results follow the workspace-scoped
 * /api/v1/command-search endpoint.
 */
export function usePostSearch(query: string): {
    posts: CommandPost[];
    loading: boolean;
    error: boolean;
} {
    const trimmed = query.trim();
    const [debounced, setDebounced] = useState('');

    useEffect(() => {
        const handle = window.setTimeout(
            () => setDebounced(trimmed),
            DEBOUNCE_MS,
        );

        return () => window.clearTimeout(handle);
    }, [trimmed]);

    const active = debounced.length >= 2;
    const result = useQuery({
        ...commandSearchQuery(debounced),
        enabled: active,
    });

    return {
        posts: active ? (result.data?.posts ?? []) : [],
        loading: active && result.isFetching,
        error: result.isError,
    };
}
