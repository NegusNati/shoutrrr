import { beforeEach, describe, expect, it, vi } from 'vitest';

import { switchWorkspace } from '../switch-workspace';

const apiMock = vi.hoisted(() => ({
    post: vi.fn().mockResolvedValue(undefined),
}));

const queryClientMock = vi.hoisted(() => ({
    invalidateQueries: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
    api: apiMock,
}));

vi.mock('@/lib/query-client', () => ({
    queryClient: queryClientMock,
}));

describe('switchWorkspace', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('posts the workspace switch to the API and invalidates cached data', async () => {
        await switchWorkspace('workspace-2');

        expect(apiMock.post).toHaveBeenCalledWith('workspaces/switch', {
            workspace_id: 'workspace-2',
        });
        expect(queryClientMock.invalidateQueries).toHaveBeenCalledOnce();
    });

    it('passes through the finish callback', async () => {
        const onFinish = vi.fn();

        await switchWorkspace('workspace-2', { onFinish });

        expect(onFinish).toHaveBeenCalledOnce();
    });
});
