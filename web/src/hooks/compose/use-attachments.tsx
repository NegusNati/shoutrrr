import { type ReactNode, useRef } from 'react';
import { toast } from 'sonner';

import { MediaChips } from '@/components/compose/media-chips';
import { useMeData } from '@/features/me/me';
import { useMediaUploads } from '@/hooks/compose/use-media-uploads';
import { postGifAttachment } from '@/lib/compose/gifs/attach';
import {
    wouldMixVideoAndImages,
    wouldViolateBlueskyGif,
} from '@/lib/compose/media-rules';
import type { MediaView, PlatformName } from '@/types/compose';
import type { GifItem } from '@/types/gifs';

type Endpoints = {
    imageStore: (id: string) => string;
    videoSign: (id: string) => string;
    videoStore: (id: string) => string;
    gifStore: (id: string) => string;
};

type Args = {
    /** Owning record id — a reply id or a conversation id; only used to build endpoint URLs. */
    ownerId: string;
    platform: PlatformName;
    media: MediaView[];
    onChange: (media: MediaView[]) => void;
    endpoints: Endpoints;
    /** Wording for the one-video-or-images error toast: 'reply' | 'message'. Default 'reply'. */
    subject?: string;
    /**
     * Hard cap on how many attachments this surface accepts. Omitted, the surface
     * is unlimited (the reply box). DM composers pass `1` so the hook — the only
     * place that sees every file-selection, paste and drop — enforces the limit
     * rather than trusting callers to hide the attach button in time.
     */
    maxMedia?: number;
};

/**
 * The render-ready pieces the host box composes into its own layout: a hidden
 * file input, the attach trigger, the media-chips strip (null when empty), and
 * the drag-drop handlers.
 */
type Attachments = {
    isUploading: boolean;
    hasMedia: boolean;
    openFilePicker: () => void;
    fileInput: ReactNode;
    chips: ReactNode | null;
    dropHandlers: {
        onDragOver: (e: React.DragEvent) => void;
        onDrop: (e: React.DragEvent) => void;
    };
    /**
     * Validate + attach a batch of files. Exposed for surfaces that source files
     * themselves rather than through the picker or drop handlers — the editor's
     * paste-to-upload passes its clipboard FileList straight in.
     */
    handleAddedFiles: (files: FileList | File[]) => Promise<void>;
    /** Attach a chosen GIF/sticker/clip; the server downloads and re-hosts it. */
    attachGif: (item: GifItem) => Promise<void>;
};

/**
 * Owns a compose surface's media lifecycle and hands back render-ready pieces,
 * so the host box lays out the attach button, chips and footer however it likes.
 * Endpoints are caller-supplied, so this serves both the reply box and the DM
 * composer; all upload logic is the composer's `useMediaUploads`. Unlike the
 * Inertia version there is no image editor — picked images upload straight
 * through the same path videos take.
 */
export function useAttachments({
    ownerId,
    platform,
    media,
    onChange,
    endpoints,
    subject = 'reply',
    maxMedia,
}: Args): Attachments {
    const shell = useMeData()?.shell;
    // Validate video against this surface's own platform limits, not every platform.
    const videoLimits = (shell?.limits ?? []).filter(
        (l) => l.platform === platform,
    );

    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const {
        pending,
        isUploading,
        handleFiles,
        dismissPending,
        cancelPending,
        trackPending,
    } = useMediaUploads({
        // This surface (reply box / DM composer) has no thread segments — every
        // upload judges the mixing rule against the surface's whole media list.
        mediaForSegment: () => media,
        videoLimits,
        onEnsurePost: async () => ownerId,
        onAddMedia: (m) => onChange([...media, m]),
        // This surface (reply box / DM composer) has no thread segments.
        activeSegmentRef: () => '__head__',
        endpoints: {
            imageStore: endpoints.imageStore,
            videoSign: endpoints.videoSign,
            videoStore: endpoints.videoStore,
        },
    });

    // The server downloads and re-hosts the chosen GIF, so this is a chip +
    // fetch rather than the local upload flow the other media handlers use.
    // Unlike the composer, ownerId is always available (no ensure-post step
    // that can fail), so there is no early-bail guard here.
    async function attachGif(item: GifItem): Promise<void> {
        await trackPending(
            {
                kind: item.catalog === 'clip' ? 'video' : 'image',
                previewUrl: item.preview.url,
            },
            () =>
                postGifAttachment(
                    endpoints.gifStore(ownerId),
                    item,
                    // post_media has no reply_id/conversation_id column, so the
                    // server has no way to know what this surface already holds
                    // — we tell it. See AttachGifRequest::rules() and the GIF
                    // controller's $existing query.
                    media.map((m) => m.id),
                ),
        );
    }

    // --- File handling (mirrors handleAddedFiles in composer.tsx) ----------

    async function handleAddedFiles(files: FileList | File[]): Promise<void> {
        const all = Array.from(files);

        // The native file dialog and drag-drop can both hand over more files than
        // this surface allows in one go, so the cap lives here rather than in the
        // caller's button-hiding. `undefined` (the reply box) means unlimited.
        const remainingSlots =
            maxMedia === undefined
                ? Infinity
                : Math.max(0, maxMedia - media.length);

        if (remainingSlots === 0) {
            toast.error(
                `A ${subject} can only include ${maxMedia} attachment${maxMedia === 1 ? '' : 's'}.`,
            );

            return;
        }

        // Bluesky publishes a GIF as video and allows only one, unmixed. Block it
        // up front (same as the one-video rule below) so it never reaches posting.
        if (platform === 'bluesky' && wouldViolateBlueskyGif(media, all)) {
            toast.error(
                'Bluesky supports one animated GIF per post, and it cannot be mixed with other media.',
            );

            return;
        }

        const videos = all.filter((f) => f.type.startsWith('video/'));
        // The paste path supplies raw clipboard batches, so filter to real images
        // rather than "anything non-video" — otherwise a pasted PDF is queued
        // into upload as if it were an image.
        const images = all.filter((f) => f.type.startsWith('image/'));

        if (wouldMixVideoAndImages(media, all)) {
            toast.error(
                `A ${subject} can contain one video or images, not both.`,
            );

            return;
        }

        if (videos.length > 0) {
            void handleFiles(videos.slice(0, remainingSlots));

            return;
        }
        if (images.length === 0) {
            return;
        }
        // Respect the surface's cap; `Infinity` (the reply box) keeps every image.
        // Without an image-edit endpoint images take the same straight-to-upload
        // path videos do.
        void handleFiles(images.slice(0, remainingSlots));
    }

    const hasVideo = media.some((m) => m.kind === 'video');
    const hasMedia = media.length > 0 || pending.length > 0;

    function acceptFromInput(files: FileList) {
        void handleAddedFiles(files).finally(() => {
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        });
    }

    const fileInput = (
        <input
            ref={fileInputRef}
            type="file"
            accept={hasVideo ? 'image/*' : 'image/*,video/*'}
            multiple={maxMedia !== 1}
            hidden
            onChange={(e) => {
                if (e.target.files && e.target.files.length > 0) {
                    acceptFromInput(e.target.files);
                }
            }}
        />
    );

    const chips = hasMedia ? (
        <MediaChips
            media={media}
            pending={pending}
            isExcluded={() => false}
            onToggleExclude={() => {}}
            onReorder={(ids) =>
                onChange(
                    ids
                        .map((id) => media.find((m) => m.id === id)!)
                        .filter(Boolean),
                )
            }
            onRemove={(id) => onChange(media.filter((m) => m.id !== id))}
            onDismissPending={dismissPending}
            onCancelPending={cancelPending}
        />
    ) : null;

    return {
        isUploading,
        hasMedia,
        openFilePicker: () => fileInputRef.current?.click(),
        fileInput,
        chips,
        dropHandlers: {
            onDragOver: (e) => e.preventDefault(),
            onDrop: (e) => {
                e.preventDefault();
                if (e.dataTransfer.files.length > 0) {
                    void handleAddedFiles(e.dataTransfer.files);
                }
            },
        },
        handleAddedFiles,
        attachGif,
    };
}
