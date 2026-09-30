/**
 * Wayfinder route objects are `{ url, method }` pairs; navigation targets in
 * the SPA accept either a raw string or such an object.
 */
export type Href = string | { url: string };

export function toUrl(href: Href): string {
    return typeof href === 'string' ? href : href.url;
}

/** Wayfinder "form" objects embed the action URL under `action`. */
export type FormAction = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

export const APP_BASE = '/app';

/**
 * Prefix a legacy app URL with the SPA base path so in-app navigation stays
 * inside the React router (e.g. `/dashboard` → `/app/dashboard`). Only call
 * this for pages that live inside the SPA — links to pages still served by
 * the server-rendered app use the legacy URL as-is.
 */
export function appUrl(href: Href): string {
    const url = toUrl(href);

    return `${APP_BASE}${url.startsWith('/') ? url : `/${url}`}`;
}
