import { useHttp } from '@/lib/api/use-http';
import { useState } from 'react';
import { toast } from 'sonner';

import { endpoints } from '@/lib/api/endpoints';
import type { EditSettings } from '@/lib/image-editor/settings';
import type { MediaView } from '@/types/compose';

type Options = {
    onEnsurePost: () => Promise<string>;
    onAddMedia: (media: MediaView) => void;
    onReplaceMedia: (media: MediaView) => void;
};

function blobToFile(blob: Blob, baseName: string): File {
    const type = blob.type || 'image/png';
    const ext =
        type === 'image/webp' ? 'webp' : type === 'image/jpeg' ? 'jpg' : 'png';

    return new File([blob], `${baseName}.${ext}`, { type });
}

export function useImageEditor({
    onEnsurePost,
    onAddMedia,
    onReplaceMedia,
}: Options) {
    const http = useHttp<{ composed?: File | null }, { media: MediaView }>({});
    const [isSaving, setIsSaving] = useState(false);

    /** Returns true only if the image was saved and attached. */
    async function applyNew(
        composed: Blob,
        source: Blob,
        settings: EditSettings,
        altText = '',
    ): Promise<boolean> {
        setIsSaving(true);
        try {
            const id = await onEnsurePost();
            if (!id) {
                toast.error('Could not save the image.');

                return false;
            }
            http.transform(() => ({
                composed: blobToFile(composed, 'image'),
                source: blobToFile(source, 'source'),
                settings: JSON.stringify(settings),
                alt_text: altText,
            }));
            const result = await http.post(endpoints.imageEditStore(id), {
                onNetworkError: () => undefined,
            });
            if (!result) {
                return false;
            }
            onAddMedia(result.media);

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
            const id = await onEnsurePost();
            if (!id) {
                toast.error('Could not update the image.');

                return false;
            }
            http.transform(() => ({
                composed: blobToFile(composed, 'image'),
                settings: JSON.stringify(settings),
                alt_text: altText,
            }));
            const result = await http.put(
                endpoints.imageEditUpdate(id, mediaId),
                { onNetworkError: () => undefined },
            );
            if (!result) {
                return false;
            }
            onReplaceMedia(result.media);

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
