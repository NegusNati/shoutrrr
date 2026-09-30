import { useEffect, useRef, useState, type RefObject } from 'react';

import { EmojiPopover } from '@/components/compose/emoji-popover';
import { GifPopover } from '@/components/compose/gif-popover';
import { Button } from '@/components/ui/button';
import { AtSign, ImagePlay, Paperclip, Smile } from '@/components/ui/icons';
import { Kbd } from '@/components/ui/kbd';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useMeData } from '@/features/me/me';
import { useAttachments } from '@/hooks/compose/use-attachments';
import { useEmojiPreferences } from '@/hooks/compose/use-emoji-preferences';
import {
    replaceMentionTokens,
    savedMentionToPlaceholder,
    syncMentionsFromText,
    type MentionPlaceholder,
} from '@/lib/compose/mentions';
import { cn } from '@/lib/utils';
import type {
    MediaView,
    PlatformName,
    WorkspaceMention,
} from '@/types/compose';

const EMPTY_SAVED_MENTIONS: WorkspaceMention[] = [];

const LIMITS: Record<string, number> = { x: 280, bluesky: 300, linkedin: 3000 };
export const QUICK_REPLY_SEND_SHORTCUT = '⌘/Ctrl↵';

/**
 * Mirrors `Platform::supportsReplyMedia()` — LinkedIn, Meta and Threads reject
 * attachments on a comment. Same approach as `lib/compose/platform-newlines.ts`.
 */
const REPLY_MEDIA_PLATFORMS: PlatformName[] = ['x', 'bluesky'];

type Props = {
    replyId: string;
    platform: PlatformName;
    replyingTo?: string;
    maxLength?: number;
    disabled?: boolean;
    disabledReason?: string;
    editorRef?: RefObject<HTMLTextAreaElement | null>;
    /** Workspace saved-mention library, shared with the composer's picker. */
    savedMentions?: WorkspaceMention[];
    onSend: (text: string, mediaIds: string[]) => Promise<void>;
};

/**
 * The reply composer: a plain textarea (no tiptap) that still resolves the
 * workspace's saved-mention library — typing `@Name` or picking one from the
 * mention popover records a placeholder, and `replaceMentionTokens` swaps the
 * label for this platform's handle in the outgoing payload.
 */
export function QuickReplyBox({
    replyId,
    platform,
    replyingTo,
    maxLength,
    disabled,
    disabledReason,
    editorRef: editorRefProp,
    savedMentions: initialSavedMentions = EMPTY_SAVED_MENTIONS,
    onSend,
}: Props) {
    const shell = useMeData()?.shell;
    const [text, setText] = useState('');
    const [sending, setSending] = useState(false);
    const [media, setMedia] = useState<MediaView[]>([]);
    const [mentions, setMentions] = useState<MentionPlaceholder[]>([]);
    const [savedMentions, setSavedMentions] = useState(initialSavedMentions);
    useEffect(() => {
        setSavedMentions(initialSavedMentions);
    }, [initialSavedMentions]);

    // Paste and drag/drop are unwired too, not just the buttons.
    const canAttachMedia = REPLY_MEDIA_PLATFORMS.includes(platform);

    const rm = useAttachments({
        ownerId: replyId,
        platform,
        media,
        onChange: setMedia,
        endpoints: {
            imageStore: (id) => `engagement/${id}/media`,
            videoSign: (id) => `engagement/${id}/media/video-url`,
            videoStore: (id) => `engagement/${id}/media/video`,
            gifStore: (id) => `engagement/${id}/gifs`,
        },
    });

    // The parent drives focus (the "r" triage shortcut) through this handle; when
    // it doesn't pass one we fall back to a local ref so emoji insertion still
    // works.
    const fallbackEditorRef = useRef<HTMLTextAreaElement>(null);
    const editorRef = editorRefProp ?? fallbackEditorRef;
    const emojiPrefs = useEmojiPreferences();

    function insertAtCaret(insert: string) {
        const field = editorRef.current;
        if (!field) {
            handleText(text + insert);

            return;
        }

        const start = field.selectionStart;
        const end = field.selectionEnd;
        handleText(text.slice(0, start) + insert + text.slice(end));

        // Restore the caret after React commits, or it snaps to the end.
        requestAnimationFrame(() => {
            field.focus();
            field.setSelectionRange(
                start + insert.length,
                start + insert.length,
            );
        });
    }

    function insertEmoji(emoji: string) {
        insertAtCaret(emoji);
        emojiPrefs.addRecent(emoji);
    }

    // Keep the mention list reconciled with the text as the user types, mirroring
    // the composer's syncMentions — minus the segment/override machinery, since a
    // reply is a single plain string.
    function handleText(next: string) {
        setText(next);
        setMentions((current) =>
            syncMentionsFromText(next, current, savedMentions),
        );
    }

    // Insert a saved mention's label token at the caret and register the
    // placeholder so it resolves to this platform's handle on send.
    function applySavedMention(saved: WorkspaceMention) {
        setMentions((current) => [
            ...current,
            savedMentionToPlaceholder(saved),
        ]);
        insertAtCaret(saved.name);
    }

    // What actually gets sent: mention labels resolved to this platform's handle.
    // Char counting also uses the resolved text so the count matches the payload.
    const outgoing = replaceMentionTokens(text, mentions, platform);
    const limit = maxLength ?? LIMITS[platform] ?? 280;
    const remaining = limit - outgoing.length;
    const tooLong = remaining < 0;
    const empty = outgoing.trim() === '' && media.length === 0;
    const canSend = !empty && !tooLong && !sending && !rm.isUploading;

    async function send() {
        if (!canSend) {
            return;
        }
        setSending(true);
        try {
            await onSend(
                outgoing,
                media.map((m) => m.id),
            );
            setText('');
            setMedia([]);
            setMentions([]);
        } finally {
            setSending(false);
        }
    }

    return (
        <div
            className="shrink-0 border-t bg-background p-3"
            {...(canAttachMedia ? rm.dropHandlers : {})}
        >
            {canAttachMedia ? rm.fileInput : null}

            <Textarea
                ref={editorRef}
                value={text}
                onChange={(e) => handleText(e.target.value)}
                onPaste={(e) => {
                    if (canAttachMedia && e.clipboardData.files.length > 0) {
                        e.preventDefault();
                        void rm.handleAddedFiles(e.clipboardData.files);
                    }
                }}
                onKeyDown={(e) => {
                    // Escape releases the editor so the ↑/↓/a/r triage shortcuts
                    // work again without a stray keystroke landing in the reply.
                    if (e.key === 'Escape') {
                        e.currentTarget.blur();

                        return;
                    }
                    if (
                        e.key === 'Enter' &&
                        (e.metaKey || e.ctrlKey) &&
                        !e.shiftKey
                    ) {
                        e.preventDefault();
                        void send();
                    }
                }}
                disabled={disabled || sending}
                placeholder={
                    replyingTo ? `Reply to ${replyingTo}…` : 'Write a reply…'
                }
                className="min-h-16"
            />

            {canAttachMedia && rm.chips ? (
                <div className="mt-2">{rm.chips}</div>
            ) : null}

            <div className="mt-2 flex items-center gap-2">
                <EmojiPopover
                    recents={emojiPrefs.recents}
                    skinTone={emojiPrefs.skinTone}
                    onSkinToneChange={emojiPrefs.setSkinTone}
                    onSelect={insertEmoji}
                    side="top"
                    align="start"
                    tooltip="Emoji"
                    trigger={(open) => (
                        <button
                            type="button"
                            aria-label="Insert emoji"
                            disabled={disabled || sending}
                            data-active={open}
                            className={cn(
                                'inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors',
                                'hover:bg-accent hover:text-foreground',
                                'data-[active=true]:bg-accent data-[active=true]:text-foreground',
                                'disabled:pointer-events-none disabled:opacity-50',
                            )}
                        />
                    )}
                >
                    <Smile className="size-4" aria-hidden="true" />
                </EmojiPopover>

                {savedMentions.length > 0 && (
                    <Popover>
                        <PopoverTrigger
                            render={
                                <button
                                    type="button"
                                    aria-label="Insert a saved mention"
                                    disabled={disabled || sending}
                                    className={cn(
                                        'inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors',
                                        'hover:bg-accent hover:text-foreground',
                                        'disabled:pointer-events-none disabled:opacity-50',
                                    )}
                                >
                                    <AtSign
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            }
                        />
                        <PopoverContent
                            side="top"
                            align="start"
                            className="w-56 p-1"
                        >
                            {savedMentions.map((mention) => (
                                <button
                                    key={mention.id}
                                    type="button"
                                    onClick={() => applySavedMention(mention)}
                                    className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-foreground"
                                >
                                    <span className="truncate font-medium">
                                        {mention.name}
                                    </span>
                                    <span className="ml-auto shrink-0 text-[11px] text-muted-foreground">
                                        {mention.handles[platform] ?? ''}
                                    </span>
                                </button>
                            ))}
                        </PopoverContent>
                    </Popover>
                )}

                {canAttachMedia && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Attach photo or video"
                        title="Attach photo or video"
                        disabled={disabled || sending}
                        onClick={rm.openFilePicker}
                        className="size-8 shrink-0 text-muted-foreground hover:text-foreground"
                    >
                        <Paperclip className="size-4" aria-hidden="true" />
                    </Button>
                )}

                {canAttachMedia && shell?.gifs_enabled && (
                    <GifPopover
                        onSelect={(item) => void rm.attachGif(item)}
                        side="top"
                        align="start"
                        tooltip="GIFs, stickers & clips"
                        trigger={(open) => (
                            <button
                                type="button"
                                aria-label="Insert a GIF, sticker or clip"
                                disabled={disabled || sending}
                                data-active={open}
                                className={cn(
                                    'inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors',
                                    'hover:bg-accent hover:text-foreground',
                                    'data-[active=true]:bg-accent data-[active=true]:text-foreground',
                                    'disabled:pointer-events-none disabled:opacity-50',
                                )}
                            />
                        )}
                    >
                        <ImagePlay className="size-4" aria-hidden="true" />
                    </GifPopover>
                )}

                {rm.isUploading ? (
                    <span className="text-[11px] text-muted-foreground">
                        Uploading…
                    </span>
                ) : null}

                <span
                    className={cn(
                        'ml-auto text-xs tabular-nums',
                        tooLong
                            ? 'font-medium text-destructive'
                            : remaining <= 20
                              ? 'text-amber-600 dark:text-amber-500'
                              : 'text-muted-foreground',
                    )}
                >
                    {remaining}
                </span>

                {(() => {
                    const replyButton = (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => void send()}
                            disabled={disabled || !canSend}
                        >
                            {sending ? (
                                'Sending…'
                            ) : (
                                <>
                                    <span>Reply</span>
                                    <Kbd
                                        aria-hidden="true"
                                        className="ml-0.5 hidden h-4 min-w-0 border border-primary-foreground/25 bg-primary-foreground/15 px-1 font-mono text-[10px] leading-none font-normal text-primary-foreground/90 sm:inline-flex"
                                    >
                                        {QUICK_REPLY_SEND_SHORTCUT}
                                    </Kbd>
                                </>
                            )}
                        </Button>
                    );

                    // A disabled <button> swallows pointer events, so the tooltip
                    // trigger wraps a focusable span rather than the button itself.
                    return disabled && disabledReason ? (
                        <Tooltip>
                            <TooltipTrigger render={<span tabIndex={0} />}>
                                {replyButton}
                            </TooltipTrigger>
                            <TooltipContent side="top" align="end">
                                {disabledReason}
                            </TooltipContent>
                        </Tooltip>
                    ) : (
                        replyButton
                    );
                })()}
            </div>
        </div>
    );
}
