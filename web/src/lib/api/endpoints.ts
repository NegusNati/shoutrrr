/**
 * `/api/v1` path builders for the composer surface — the fetch layer prefixes
 * `/api/v1/` itself, so these stay relative (`posts/${id}/media`). Centralized
 * so a route move is one edit instead of a grep-and-replace across call sites.
 */

/** Full `/api/v1/...` URL for callers that fetch directly rather than via apiFetch. */
export const apiUrl = (path: string) => `/api/v1/${path}`;

export const endpoints = {
    posts: 'posts',
    post: (id: string) => `posts/${id}`,
    postSchedule: (id: string) => `posts/${id}/schedule`,
    postQueue: (id: string) => `posts/${id}/queue`,
    postPublish: (id: string) => `posts/${id}/publish`,
    postDuplicate: (id: string) => `posts/${id}/duplicate`,
    postTargetRetry: (id: string, targetId: string) =>
        `posts/${id}/targets/${targetId}/retry`,
    postMetricsRefresh: (id: string) => `posts/${id}/metrics/refresh`,
    postShares: (id: string) => `posts/${id}/shares`,
    postShare: (id: string, shareId: string) => `posts/${id}/shares/${shareId}`,

    nextSlot: 'posts/next-slot',

    calendar: (month: string) => `calendar?month=${month}`,

    postMediaStore: (postId: string) => `posts/${postId}/media`,
    postMediaAlt: (postId: string, mediaId: string) =>
        `posts/${postId}/media/${mediaId}/alt`,
    postMediaDelete: (postId: string, mediaId: string) =>
        `posts/${postId}/media/${mediaId}`,
    videoSignedUrl: (postId: string) => `posts/${postId}/media/video-url`,
    videoStore: (postId: string) => `posts/${postId}/media/video`,

    imageEditStore: (postId: string) => `posts/${postId}/image-edit`,
    imageEditUpdate: (postId: string, mediaId: string) =>
        `posts/${postId}/image-edit/${mediaId}`,

    gifBrowse: (catalog: string) => `gifs/${catalog}`,
    gifRecent: (catalog: string) => `gifs/${catalog}/recent`,
    postGifs: (postId: string) => `posts/${postId}/gifs`,

    workspaceMentions: 'workspace-mentions',
    workspaceMention: (id: string) => `workspace-mentions/${id}`,
    platformLimits: 'platform-limits',

    settingsProfile: 'settings/profile',
    settingsSecurity: 'settings/security',
    settingsPassword: 'settings/password',
    settingsConnections: 'settings/connections',
    settingsConnection: (id: string | number) => `settings/connections/${id}`,
    settingsNotifications: 'settings/notifications',
    settingsWorkspace: 'settings/workspace',
    settingsWorkspaceTimezone: 'settings/workspace/timezone',
    settingsWorkspaceMembers: 'settings/workspace/members',
    settingsWorkspaceMember: (id: string) => `settings/workspace/members/${id}`,
    settingsWorkspaceInvitation: (id: string) =>
        `settings/workspace/invitations/${id}`,
    settingsWorkspaceInvite: 'settings/workspace/invite',
    settingsWorkspaceLeave: 'settings/workspace/leave',
    settingsWorkspaceTransfer: 'settings/workspace/transfer',
    settingsWorkspaceApiKeys: 'settings/workspace/api-keys',
    settingsWorkspaceApiKey: (id: string) => `settings/workspace/api-keys/${id}`,
    settingsWorkspaceSubscription: 'settings/workspace/subscription',
} as const;
