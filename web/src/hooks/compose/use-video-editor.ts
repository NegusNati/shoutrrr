import { useState } from 'react';
import { toast } from 'sonner';

import { apiFetch } from '@/lib/api';
import {
    minVideoBytes,
    putWithProgress,
    readVideoMetadata,
    validateVideo,
} from '@/lib/compose/video';
import type { VideoEditSettings } from '@/lib/video-editor/settings';
import type { MediaView, PlatformLimits } from '@/types/compose';

type ApplyInput = {
    source: Blob;
    oldMediaId: string | null;
    settings: VideoEditSettings;
    altText?: string;
    limits: PlatformLimits[];
};

type Args = {
    onEnsurePost: () => Promise<string>;
    onComplete: (oldMediaId: string | null, media: MediaView) => void;
};

export function useVideoEditor({ onEnsurePost, onComplete }: Args) {
    const [phase, setPhase] = useState<
        'idle' | 'rendering' | 'compressing' | 'uploading'
    >('idle');
    const [progress, setProgress] = useState(0);

    async function apply({
        source,
        oldMediaId,
        settings,
        altText = '',
        limits,
    }: ApplyInput): Promise<boolean> {
        try {
            setPhase('rendering');
            setProgress(0);
            const { renderVideo } = await import('@/lib/video-editor/render');
            const blob = await renderVideo(source, settings, setProgress);

            let file = new File([blob], 'edited-video.mp4', {
                type: 'video/mp4',
            });
            let meta = await readVideoMetadata(file);

            // Keep the edited output within the selected platforms' caps too.
            const maxBytes = minVideoBytes(limits);
            if (Number.isFinite(maxBytes) && file.size > maxBytes) {
                setPhase('compressing');
                setProgress(0);
                const { compressVideoToFit } =
                    await import('@/lib/video-editor/compress');
                const compressed = await compressVideoToFit(
                    file,
                    maxBytes,
                    setProgress,
                );
                if (compressed) {
                    file = new File([compressed], 'edited-video.mp4', {
                        type: 'video/mp4',
                    });
                    meta = await readVideoMetadata(file);
                }
            }

            const verdict = validateVideo(meta, limits);
            if (!verdict.ok) {
                toast.error(verdict.reason);

                return false;
            }

            setPhase('uploading');
            setProgress(0);
            const id = await onEnsurePost();

            // 1. Sign → 2. PUT direct to storage → 3. confirm.
            const signed = await apiFetch<{
                key: string;
                url: string;
                headers: Record<string, string>;
            }>(`posts/${id}/media/video-url`, {
                method: 'POST',
                body: { content_type: 'video/mp4' },
            });

            await putWithProgress(signed.url, signed.headers, file, (pct) =>
                setProgress(pct / 100),
            );

            const { media } = await apiFetch<{ media: MediaView }>(
                `posts/${id}/media/video`,
                {
                    method: 'POST',
                    body: {
                        key: signed.key,
                        duration_seconds: meta.durationSeconds,
                        width: meta.width,
                        height: meta.height,
                        alt_text: altText || null,
                    },
                },
            );

            onComplete(oldMediaId, media);

            return true;
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Could not save the edited video.',
            );

            return false;
        } finally {
            setPhase('idle');
        }
    }

    return { apply, phase, progress };
}
