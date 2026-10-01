import { useState } from 'react';
import { toast } from 'sonner';

import { apiUpload } from '@/lib/api';
import type { EditSettings } from '@/lib/image-editor/settings';
import type { MediaView } from '@/types/compose';

type Endpoints = {
    /** POST {owner}/image-edit — attach a newly beautified image. */
    store: (ownerId: string) => string;
    /** POST {owner}/image-edit/{media} with _method=put — replace an existing one. */
    update: (ownerId: string, mediaId: string) => string;
};

type Options = {
    /** Owning record id — a reply/post id; when null the caller must pass onEnsurePost. */
    ownerId: string | null;
    endpoints: Endpoints;
    /** Resolve the owner lazily (composer drafts the post on first save). */
    onEnsurePost?: () => Promise<string>;
    onAddMedia: (media: MediaView) => void;
    onReplaceMedia: (media: MediaView) => void;
};

function blobToFile(blob: Blob, baseName: string): File {
    const type = blob.type || 'image/png';
    const ext =
        type === 'image/webp' ? 'webp' : type === 'image/jpeg' ? 'jpg' : 'png';

    return new File([blob], `${baseName}.${ext}`, { type });
}

/**
 * Save/replace calls for the shared ImageEditor dialog. Mirrors the legacy
 * useHttp version; endpoints are caller-supplied (reply box vs future composer).
 */
export function useImageEditor({
    ownerId,
    endpoints,
    onEnsurePost,
    onAddMedia,
    onReplaceMedia,
}: Options) {
    const [isSaving, setIsSaving] = useState(false);

    async function resolveOwner(): Promise<string> {
        if (onEnsurePost) {
            return onEnsurePost();
        }
        if (ownerId === null) {
            throw new Error('No owner id to save the image against.');
        }

        return ownerId;
    }

    /** Returns true only if the image was saved and attached. */
    async function applyNew(
        composed: Blob,
        source: Blob,
        settings: EditSettings,
        altText = '',
    ): Promise<boolean> {
        setIsSaving(true);
        try {
            const id = await resolveOwner();
            const { media } = await apiUpload<{ media: MediaView }>(
                endpoints.store(id),
                {
                    composed: blobToFile(composed, 'image'),
                    source: blobToFile(source, 'source'),
                    settings: JSON.stringify(settings),
                    alt_text: altText,
                },
            );
            onAddMedia(media);

            return true;
        } catch {
            toast.error('Could not save the image.');

            return false;
        } finally {
            setIsSaving(false);
        }
    }

    /** Returns true only if the edit was persisted. */
    async function applyEdit(
        mediaId: string,
        composed: Blob,
        settings: EditSettings,
        altText = '',
    ): Promise<boolean> {
        setIsSaving(true);
        try {
            const id = await resolveOwner();
            const { media } = await apiUpload<{ media: MediaView }>(
                endpoints.update(id, mediaId),
                {
                    composed: blobToFile(composed, 'image'),
                    settings: JSON.stringify(settings),
                    alt_text: altText,
                    _method: 'put',
                },
            );
            onReplaceMedia(media);

            return true;
        } catch {
            toast.error('Could not update the image.');

            return false;
        } finally {
            setIsSaving(false);
        }
    }

    return { applyNew, applyEdit, isSaving };
}
