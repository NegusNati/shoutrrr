import { describe, expect, it } from 'vitest';

import { apiActionPath } from '../notification-bell';

describe('apiActionPath', () => {
    it('accepts /api/v1-prefixed invitation hrefs emitted by NotificationPresenter', () => {
        expect(
            apiActionPath('/api/v1/workspace-invitations/abc-123/accept'),
        ).toBe('workspace-invitations/abc-123/accept');
        expect(apiActionPath('/api/v1/workspace-invitations/abc-123')).toBe(
            'workspace-invitations/abc-123',
        );
    });

    it('still accepts legacy un-prefixed web hrefs', () => {
        expect(apiActionPath('/workspace-invitations/abc-123/accept')).toBe(
            'workspace-invitations/abc-123/accept',
        );
    });

    it('returns null for actions without an API equivalent', () => {
        expect(apiActionPath('/settings/workspace')).toBeNull();
        expect(apiActionPath('/api/v1/posts/abc')).toBeNull();
        expect(apiActionPath('https://example.com/x')).toBeNull();
    });
});
