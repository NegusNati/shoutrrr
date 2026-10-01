import { type ReactNode, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { ImageEditor } from '@/components/compose/image-editor';
import { MediaChips } from '@/components/compose/media-chips';
import { useMeData } from '@/features/me/me';
import { useImageEditor } from '@/hooks/compose/use-image-editor';
import { useMediaUploads } from '@/hooks/compose/use-media-uploads';
import { postGifAttachment } from '@/lib/compose/gifs/attach';
import {
    isAttachOnlyImage,
    wouldMixVideoAndImages,
    wouldViolateBlueskyGif,
} from '@/lib/compose/media-rules';
import {
    defaultSettings,
    normalizeSettings,
    type EditSettings,
} from '@/lib/image-editor/settings';
import type { MediaView, PlatformName } from '@/types/compose';
import type { GifItem } from '@/types/gifs';

type Endpoints = {
    imageStore: (id: string) => string;
    videoSign: (id: string) => string;
    videoStore: (id: string) => string;
    gifStore: (id: string) => string;
    /**
     * When present, picked still images open the beauty editor before
     * attaching (GIFs always upload straight through — it flattens animation).
     */
    imageEdit?: {
        store: (id: string) => string;
        update: (id: string, mediaId: string) => string;
    };
};

/** What the image editor is currently working on (SPA has no video editor). */
type Editing =
    | {
          kind: 'batch';
          items: { file: File; url: string }[];
          index: number;
      }
    | {
          kind: 'reedit';
          url: string;
          settings: EditSettings;
          mediaId: string;
          altText: string | null;
      }
    | { kind: 'raw'; url: string; mediaId: string };

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
 * file input, the attach trigger, the media-chips strip (null when empty),
 * the image-editor dialog (null unless `endpoints.imageEdit` is set and an
 * edit session is open), and the drag-drop handlers.
 */
type Attachments = {
    isUploading: boolean;
    hasMedia: boolean;
    openFilePicker: () => void;
    fileInput: ReactNode;
    chips: ReactNode | null;
    editor: ReactNode;
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
 * composer; all upload logic is the composer's `useMediaUploads`.
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
    const [editing, setEditing] = useState<Editing | null>(null);
    // Revoke outstanding batch object URLs if the host unmounts mid-batch.
    const editingRef = useRef(editing);
    editingRef.current = editing;
    useEffect(
        () => () => {
            const e = editingRef.current;
            if (e?.kind === 'batch') {
                for (const it of e.items) {
                    URL.revokeObjectURL(it.url);
                }
            }
        },
        [],
    );

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

    const imageEditor = useImageEditor({
        ownerId,
        endpoints: endpoints.imageEdit ?? {
            store: () => '',
            update: () => '',
        },
        onAddMedia: (m) => onChange([...media, m]),
        onReplaceMedia: (updated) =>
            onChange(media.map((m) => (m.id === updated.id ? updated : m))),
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

    // --- Image editor session -------------------------------------------------

    // Advance to the next batch image, or close the editor (revoking the
    // batch's object URLs) when the batch is done. Re-edits just close.
    function endEditingStep() {
        if (editing?.kind === 'batch') {
            if (editing.index + 1 < editing.items.length) {
                setEditing({ ...editing, index: editing.index + 1 });

                return;
            }
            for (const it of editing.items) {
                URL.revokeObjectURL(it.url);
            }
        }
        setEditing(null);
    }

    // Apply: persist the composed image, then advance the batch / close. On a
    // failed save the editor stays open (the hook already toasted) so the user
    // can retry — the original attachment is never dropped.
    async function applyEditing(
        composed: Blob,
        settings: EditSettings,
        altText: string,
    ): Promise<void> {
        if (!editing) {
            return;
        }
        if (editing.kind === 'batch') {
            const ok = await imageEditor.applyNew(
                composed,
                editing.items[editing.index].file,
                settings,
                altText,
            );
            if (!ok) {
                return;
            }
        } else if (editing.kind === 'reedit') {
            const ok = await imageEditor.applyEdit(
                editing.mediaId,
                composed,
                settings,
                altText,
            );
            if (!ok) {
                return;
            }
        } else if (editing.kind === 'raw') {
            // A plain image beautified for the first time: keep the raw image
            // as the source, attach the composed result, drop the raw one.
            const rawBlob = await fetch(editing.url).then((r) => r.blob());
            const ok = await imageEditor.applyNew(
                composed,
                rawBlob,
                settings,
                altText,
            );
            if (!ok) {
                return;
            }
            onChange(media.filter((m) => m.id !== editing.mediaId));
        }
        endEditingStep();
    }

    // "Continue without editing": a fresh batch image attaches as-is; re-edits
    // just close with no change.
    function cancelEditing() {
        if (editing?.kind === 'batch') {
            void handleFiles([editing.items[editing.index].file]);
        }
        endEditingStep();
    }

    // Discard: drop a fresh upload without attaching; remove an attached image.
    function discardEditing() {
        if (editing?.kind === 'reedit' || editing?.kind === 'raw') {
            onChange(media.filter((m) => m.id !== editing.mediaId));
        }
        endEditingStep();
    }

    // Re-open an attached image: a beautified one rehydrates from its persisted
    // source + settings; a plain one is beautified from scratch.
    function openImage(mediaId: string) {
        const m = media.find((x) => x.id === mediaId);
        // Animated images (GIF, or a GIF-browser WebP) have no editor — the
        // beautifier would flatten them to a still frame.
        if (!m || m.kind === 'video' || isAttachOnlyImage(m)) {
            return;
        }
        if (m.edit_settings && m.source_url) {
            setEditing({
                kind: 'reedit',
                url: m.source_edit_url ?? m.edit_url,
                settings: normalizeSettings(m.edit_settings),
                mediaId: m.id,
                altText: m.alt_text,
            });
        } else {
            setEditing({ kind: 'raw', url: m.edit_url, mediaId: m.id });
        }
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

        const capped = images.slice(0, remainingSlots);
        // Without an image-edit endpoint images take the same straight-to-upload
        // path videos do.
        if (!endpoints.imageEdit) {
            void handleFiles(capped);

            return;
        }

        // GIFs skip the beautifier (it flattens animation); the rest open the
        // editor as a batch, edited one at a time.
        const gifs = capped.filter((f) => f.type === 'image/gif');
        const editable = capped.filter((f) => f.type !== 'image/gif');

        if (gifs.length > 0) {
            void handleFiles(gifs);
        }
        if (editable.length === 0) {
            return;
        }
        setEditing({
            kind: 'batch',
            items: editable.map((f) => ({
                file: f,
                url: URL.createObjectURL(f),
            })),
            index: 0,
        });
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
            onImageClick={endpoints.imageEdit ? openImage : undefined}
        />
    ) : null;

    const editor =
        endpoints.imageEdit && editing !== null ? (
            <ImageEditor
                open
                sourceUrl={
                    editing.kind === 'batch'
                        ? editing.items[editing.index].url
                        : editing.url
                }
                initialSettings={
                    editing.kind === 'reedit'
                        ? editing.settings
                        : defaultSettings()
                }
                initialAltText={
                    editing.kind === 'reedit' ? editing.altText : null
                }
                onApply={applyEditing}
                onCancel={cancelEditing}
                onDiscard={discardEditing}
                variant={editing.kind === 'batch' ? 'new' : 'existing'}
                isSaving={imageEditor.isSaving}
                queue={
                    editing.kind === 'batch'
                        ? {
                              thumbnails: editing.items.map((it) => it.url),
                              index: editing.index,
                          }
                        : undefined
                }
            />
        ) : null;

    return {
        isUploading: isUploading || imageEditor.isSaving,
        hasMedia,
        openFilePicker: () => fileInputRef.current?.click(),
        fileInput,
        chips,
        editor,
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
