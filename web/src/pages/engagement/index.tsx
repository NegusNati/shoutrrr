import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useEffect, useRef, useState, type RefObject } from 'react';
import { toast } from 'sonner';

import ComposerController from '@/actions/App/Http/Controllers/Posts/ComposerController';
import { PlatformGlyph } from '@/components/common/platform-glyph';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Archive,
    ArrowUp,
    ChevronDown,
    Inbox,
    MessagesSquare,
    PauseCircle,
    SearchX,
} from '@/components/ui/icons';
import { Kbd } from '@/components/ui/kbd';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    archiveReply,
    destroyReply,
    engagementQuery,
    engagementThreadQuery,
    likeReply,
    respondToReply,
    unlikeReply,
} from '@/features/engagement/engagement';
import { useInvalidateMe } from '@/features/me/me';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { useInfiniteScroll } from '@/hooks/use-infinite-scroll';
import { useIsMobile } from '@/hooks/use-mobile';
import { errorMessage } from '@/lib/api';
import {
    disabledPlatformLabels,
    platformKeys,
    platformLabel,
} from '@/lib/platforms';
import { postPermalink } from '@/lib/posts/permalink';
import { cn } from '@/lib/utils';
import type { PlatformName, WorkspaceMention } from '@/types/compose';

import { QuickReplyBox } from './components/quick-reply-box';
import { ReplyFilters } from './components/reply-filters';
import { ReplyStream } from './components/reply-stream';
import { ReplyThread } from './components/reply-thread';
import {
    adjacentIndex,
    atHandle,
    engagementShortcut,
    initials,
    nextAfterArchive,
    unseenIds,
} from './helpers';
import type { EngagementFilters, ReplyItem } from './types';

const EMPTY_ENGAGEMENT: Record<PlatformName, boolean> = {
    x: false,
    bluesky: false,
    linkedin: false,
    facebook: false,
    instagram: false,
    threads: false,
    discord: false,
};

export type EngagementSearch = EngagementFilters;

function StreamSkeleton() {
    return (
        <div className="space-y-1 p-3">
            {[0, 1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="flex gap-3 py-2">
                    <Skeleton className="size-9 shrink-0 rounded-full" />
                    <div className="flex-1 space-y-2 py-1">
                        <Skeleton className="h-3 w-1/3" />
                        <Skeleton className="h-3 w-2/3" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function StreamEmpty({ filtered }: { filtered: boolean }) {
    return (
        <Empty className="h-full">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    {filtered ? <SearchX /> : <Inbox />}
                </EmptyMedia>
                <EmptyTitle>
                    {filtered ? 'No replies match' : 'No replies yet'}
                </EmptyTitle>
                <EmptyDescription>
                    {filtered
                        ? 'Try clearing a filter to see more of your inbox.'
                        : 'Replies to your published posts land here after periodic checks. Publish a post and check back later.'}
                </EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}

/**
 * Twitter-style hand-off: replies that arrive while you read wait behind this
 * button instead of shifting the list. It floats over the top of the stream so
 * it is visible wherever the reader has scrolled to.
 */
function NewRepliesButton({
    count,
    onShow,
}: {
    count: number;
    onShow: () => void;
}) {
    return (
        // The live region is always mounted — a region that appears at the same
        // moment as its content is not announced.
        <div
            aria-live="polite"
            className="pointer-events-none absolute inset-x-0 top-2 z-10 flex justify-center"
        >
            {count > 0 ? (
                <button
                    type="button"
                    onClick={onShow}
                    className="pointer-events-auto flex animate-in items-center gap-1.5 rounded-full bg-primary-gradient px-3 py-1.5 text-xs font-medium text-primary-foreground shadow-lg ring-1 inset-shadow-[0_1px_0_0_var(--primary-gradient-highlight)] ring-primary/20 transition-[transform,--primary-gradient-top] duration-150 ease-out fade-in-0 hover:[--primary-gradient-top:var(--primary-gradient-highlight)] active:scale-[0.97] motion-safe:slide-in-from-top-2"
                >
                    <ArrowUp className="size-3.5" />
                    {count === 1
                        ? 'Show 1 new reply'
                        : `Show ${count} new replies`}
                </button>
            ) : null}
        </div>
    );
}

function ShortcutHint({ keys, label }: { keys: string[]; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
            <span className="inline-flex items-center gap-0.5">
                {keys.map((key) => (
                    <Kbd key={key}>{key}</Kbd>
                ))}
            </span>
            <span>{label}</span>
        </span>
    );
}

function ConversationPrompt() {
    return (
        <Empty className="h-full">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <MessagesSquare />
                </EmptyMedia>
                <EmptyTitle>Pick a reply</EmptyTitle>
                <EmptyDescription>
                    Select a reply on the left to see the conversation and
                    respond.
                </EmptyDescription>
            </EmptyHeader>
            <div className="mt-6 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 px-6">
                <ShortcutHint keys={['↑', '↓']} label="move" />
                <ShortcutHint keys={['A']} label="archive" />
                <ShortcutHint keys={['O']} label="open comment" />
                <ShortcutHint keys={['R']} label="reply" />
            </div>
        </Empty>
    );
}

function EngagementDisabledNotice() {
    return (
        <Empty className="h-full">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <PauseCircle />
                </EmptyMedia>
                <EmptyTitle>Engagement temporarily disabled</EmptyTitle>
                <EmptyDescription>
                    Reply polling is paused by your instance admin. Existing
                    replies remain visible, but new replies will not sync until
                    polling is enabled again.
                </EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}

function EngagementDisabledBanner({
    disabledPlatforms,
}: {
    disabledPlatforms: string[];
}) {
    if (disabledPlatforms.length === 0) {
        return null;
    }

    return (
        <div className="border-b border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
            <div className="flex items-start gap-2">
                <PauseCircle className="mt-0.5 size-4 shrink-0" />
                <p>
                    Reply polling is temporarily disabled for{' '}
                    <span className="font-medium">
                        {disabledPlatforms.join(', ')}
                    </span>
                    . Existing replies remain visible, but new replies for{' '}
                    {disabledPlatforms.length === 1
                        ? 'that platform'
                        : 'those platforms'}{' '}
                    will not sync until polling is enabled again.
                </p>
            </div>
        </div>
    );
}

type RightPaneProps = {
    selected: ReplyItem;
    onArchived: (id: string) => void;
    onResponded: (id: string) => void;
    replyEditorRef?: RefObject<HTMLTextAreaElement | null>;
    savedMentions: WorkspaceMention[];
    reserveCloseButtonSpace?: boolean;
};

function RightPane({
    selected,
    onArchived,
    onResponded,
    replyEditorRef,
    savedMentions,
    reserveCloseButtonSpace = false,
}: RightPaneProps) {
    const threadQuery = useQuery(engagementThreadQuery(selected.id));
    const [threadOverrides, setThreadOverrides] = useState<ReplyItem[] | null>(
        null,
    );
    const thread = threadOverrides ?? threadQuery.data?.thread ?? [];
    const postExcerpt = threadQuery.data?.post_excerpt ?? null;
    const loading = threadQuery.isPending;

    // New selection → clear any optimistic rows from the previous thread.
    const selectedId = selected.id;
    const prevSelectedId = useRef(selectedId);
    if (prevSelectedId.current !== selectedId) {
        prevSelectedId.current = selectedId;
        setThreadOverrides(null);
    }

    const platformName = platformLabel(selected.platform);
    const commentOnPlatformUrl = postPermalink(
        selected.platform,
        selected.author_handle,
        selected.remote_reply_id,
    );
    const postOnPlatformUrl = postPermalink(
        selected.platform,
        selected.account_handle,
        selected.post_remote_id,
    );
    const postInShoutrrrUrl = selected.post_id
        ? ComposerController.show(selected.post_id).url
        : null;
    const hasOpenTargets = Boolean(
        commentOnPlatformUrl || postOnPlatformUrl || postInShoutrrrUrl,
    );

    function setThread(updater: (prev: ReplyItem[]) => ReplyItem[]) {
        setThreadOverrides((prev) =>
            updater(prev ?? threadQuery.data?.thread ?? []),
        );
    }

    function setSendStatus(id: string, status: ReplyItem['send_status']) {
        setThread((prev) =>
            prev.map((r) => (r.id === id ? { ...r, send_status: status } : r)),
        );
    }

    async function send(text: string, mediaIds: string[]) {
        const tempId = `temp-${Date.now()}`;
        setThread((prev) => [
            ...prev,
            {
                ...selected,
                id: tempId,
                text,
                is_read: true,
                is_ours: true,
                status: 'responded',
                send_status: 'sending' as const,
            },
        ]);

        try {
            // The server row carries the real id and send_status (media replies
            // hand off to a job and stay "sending").
            const { reply } = await respondToReply(selected.id, {
                text,
                media: mediaIds,
            });
            setThread((prev) => prev.map((r) => (r.id === tempId ? reply : r)));
            onResponded(selected.id);
        } catch (error) {
            setSendStatus(tempId, 'failed');
            toast.error(errorMessage(error, 'Could not send the reply.'));
            throw error;
        }
    }

    function toggleLike(reply: ReplyItem) {
        const wasLiked = reply.is_liked;
        const setLiked = (isLiked: boolean) =>
            setThread((prev) =>
                prev.map((r) =>
                    r.id === reply.id ? { ...r, is_liked: isLiked } : r,
                ),
            );
        const restore = () => setLiked(wasLiked);
        const fallback = wasLiked
            ? 'Could not remove the like.'
            : 'Could not like this reply.';

        setLiked(!wasLiked);

        void (wasLiked ? unlikeReply(reply.id) : likeReply(reply.id))
            .then(({ is_liked }) => setLiked(is_liked))
            .catch((error: unknown) => {
                restore();
                toast.error(errorMessage(error, fallback));
            });
    }

    function remove(reply: ReplyItem) {
        const index = thread.findIndex((r) => r.id === reply.id);
        const restore = () =>
            setThread((prev) => {
                const next = [...prev];
                next.splice(index < 0 ? next.length : index, 0, reply);

                return next;
            });

        setThread((prev) => prev.filter((r) => r.id !== reply.id));

        void destroyReply(reply.id).catch((error: unknown) => {
            restore();
            toast.error(errorMessage(error, 'Could not delete the reply.'));
        });
    }

    function archive() {
        void archiveReply(selected.id)
            .then(() => onArchived(selected.id))
            .catch((error: unknown) => {
                toast.error(
                    errorMessage(error, 'Could not archive the reply.'),
                );
            });
    }

    return (
        <div className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden">
            <header
                className={cn(
                    'flex shrink-0 items-center gap-2.5 border-b px-4 py-3',
                    reserveCloseButtonSpace && 'pr-14',
                )}
            >
                <div className="relative shrink-0">
                    <Avatar className="size-8">
                        {selected.author_avatar_url ? (
                            <AvatarImage
                                src={selected.author_avatar_url}
                                alt=""
                            />
                        ) : null}
                        <AvatarFallback className="text-[11px]">
                            {initials(selected)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="absolute -right-0.5 -bottom-0.5 flex size-3.5 items-center justify-center rounded-full bg-background text-muted-foreground ring-1 ring-border">
                        <PlatformGlyph platform={selected.platform} size={8} />
                    </span>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold">
                        {selected.author_name ??
                            atHandle(selected.author_handle)}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                        {platformLabel(selected.platform)}
                        {selected.account_handle
                            ? ` · to ${selected.account_handle}`
                            : ''}
                    </div>
                </div>
                {hasOpenTargets ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="gap-1 text-muted-foreground hover:text-foreground"
                                />
                            }
                        >
                            Open in
                            <ChevronDown className="size-3.5 opacity-70" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="min-w-64 whitespace-nowrap"
                        >
                            {commentOnPlatformUrl ? (
                                <DropdownMenuItem
                                    render={
                                        <a
                                            href={commentOnPlatformUrl}
                                            target="_blank"
                                            rel="noreferrer noopener"
                                        />
                                    }
                                >
                                    <span className="flex-1">
                                        Open comment on {platformName}
                                    </span>
                                    <Kbd className="ml-2 h-5 min-w-5 px-1 text-[10px]">
                                        O
                                    </Kbd>
                                </DropdownMenuItem>
                            ) : null}
                            {postOnPlatformUrl ? (
                                <DropdownMenuItem
                                    render={
                                        <a
                                            href={postOnPlatformUrl}
                                            target="_blank"
                                            rel="noreferrer noopener"
                                        />
                                    }
                                >
                                    Open post on {platformName}
                                </DropdownMenuItem>
                            ) : null}
                            {postInShoutrrrUrl ? (
                                <DropdownMenuItem
                                    render={<a href={postInShoutrrrUrl} />}
                                >
                                    Open in Shoutrrr
                                </DropdownMenuItem>
                            ) : null}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
                <Tooltip>
                    <TooltipTrigger
                        render={
                            <Button
                                variant="ghost"
                                size="sm"
                                aria-label="Archive reply"
                                className="gap-1.5 text-muted-foreground hover:text-foreground"
                                onClick={archive}
                            />
                        }
                    >
                        <Archive className="size-4" />
                        <Kbd className="h-5 min-w-5 px-1 text-[10px]">A</Kbd>
                    </TooltipTrigger>
                    <TooltipContent
                        side="bottom"
                        className="flex items-center gap-1.5"
                    >
                        Archive
                        <Kbd>A</Kbd>
                    </TooltipContent>
                </Tooltip>
            </header>

            <ReplyThread
                postExcerpt={postExcerpt}
                thread={thread}
                loading={loading}
                onToggleLike={toggleLike}
                onDelete={remove}
            />

            {/* Key by conversation so switching replies gives a fresh draft:
                remounting clears the editor text, mentions, and in-flight media
                (all local state, incl. useAttachments') instead of carrying the
                previous conversation's reply over. */}
            <QuickReplyBox
                key={selected.id}
                replyId={selected.id}
                platform={selected.platform}
                replyingTo={atHandle(selected.author_handle)}
                maxLength={selected.account_max_text_length ?? undefined}
                disabled={selected.account_disabled}
                disabledReason={
                    selected.account_disabled
                        ? 'This account is disabled in the workspace. Enable it in Accounts to reply.'
                        : undefined
                }
                editorRef={replyEditorRef}
                savedMentions={savedMentions}
                onSend={send}
            />
        </div>
    );
}

export default function EngagementIndexPage({
    search,
}: {
    search: EngagementSearch;
}) {
    useDocumentTitle('Engagement');
    const isMobile = useIsMobile();
    const invalidateMe = useInvalidateMe();
    const navigate = useNavigate();
    // The poll refetches continuously; rows only move when the reader asks —
    // nothing reshuffles under a cursor that is mid-triage.
    const { data, isPending, hasNextPage, isFetchingNextPage, fetchNextPage } =
        useInfiniteQuery(engagementQuery(search));
    const sentinelRef = useInfiniteScroll(() => {
        if (!isFetchingNextPage) {
            void fetchNextPage();
        }
    }, hasNextPage);

    function applyFilters(patch: Partial<EngagementFilters>) {
        void navigate({
            to: '/engagement',
            search: { ...search, ...patch },
            replace: true,
        });
    }
    const [selected, setSelected] = useState<ReplyItem | null>(null);
    const replyEditorRef = useRef<HTMLTextAreaElement>(null);
    const streamRef = useRef<HTMLDivElement>(null);
    // The list the reader is looking at. The poll keeps `replies` fresh in the
    // background, but rows only move when the reader asks for them.
    const [onScreen, setOnScreen] = useState<ReplyItem[] | null>(null);
    // Rows revealed by the last "show new replies" press, so only those animate
    // in rather than the whole list flashing.
    const [revealedIds, setRevealedIds] = useState<string[]>([]);
    // Client-side overlay over the query result: archiving or responding must
    // update the left list without waiting for the next poll.
    const [overrides, setOverrides] = useState<
        Record<string, 'archived' | 'responded'>
    >({});

    const filters = search;
    const {
        account: filterAccount,
        platform: filterPlatform,
        target: filterTarget,
        post: filterPost,
        unread: filterUnread,
        archived: filterArchived,
    } = filters;

    const incoming = data?.pages.flatMap((p) => p.replies.data) ?? [];
    const facets = data?.pages[0]?.facets ?? { accounts: [], posts: [] };
    const engagementEnabled =
        data?.pages[0]?.engagementEnabled ?? EMPTY_ENGAGEMENT;
    const savedMentions = data?.pages[0]?.savedMentions ?? [];

    // Filter changes come back as a different query key; stale overrides would
    // wrongly hide rows in, say, the archived view. Reset during render (not an
    // effect) so no wasted commit fires with the old overrides applied.
    const filterKey = `${filterAccount}|${filterPlatform}|${filterTarget}|${filterPost}|${filterUnread}|${filterArchived}`;
    const prevFilterKey = useRef(filterKey);
    if (prevFilterKey.current !== filterKey) {
        prevFilterKey.current = filterKey;
        setOverrides({});
        // A filter change is the reader asking for a different list, so adopt it
        // outright instead of offering it behind the button.
        setOnScreen(incoming);
        setRevealedIds([]);
    }

    const unseen = unseenIds(onScreen ?? incoming, incoming);
    const items = (onScreen ?? incoming)
        .filter((r) => overrides[r.id] !== 'archived')
        .map((r) =>
            overrides[r.id] === 'responded'
                ? { ...r, status: 'responded' as const }
                : r,
        );

    // With an empty list there is no reading position to protect — the first
    // paint, the query landing, or the reader having archived the lot. Take the
    // fresh rows without making them ask.
    if (items.length === 0 && unseen.length > 0) {
        setOnScreen(incoming);
    }

    function showNewReplies() {
        setRevealedIds(unseen);
        setOnScreen(incoming);
        streamRef.current?.scrollTo({
            top: 0,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'auto'
                : 'smooth',
        });
    }
    const disabledPlatforms = disabledPlatformLabels(engagementEnabled);
    const allEngagementDisabled =
        disabledPlatforms.length === platformKeys(engagementEnabled).length &&
        disabledPlatforms.length > 0;
    // LinkedIn reply polling stays off until the operator enables the restricted
    // Community Management scope in instance settings. That's the expected default,
    // not a temporary pause, so keep it out of the banner unless the scope is on.
    const bannerDisabledPlatforms = data?.pages[0]
        ?.linkedinCommunityManagementEnabled
        ? disabledPlatforms
        : disabledPlatforms.filter(
              (label) => label !== platformLabel('linkedin'),
          );
    const filtered =
        filters.unread ||
        filters.archived ||
        filters.platform !== '' ||
        filters.account !== '' ||
        filters.post !== '' ||
        filters.target !== '';

    function clearSelection() {
        setSelected(null);
    }

    function selectById(id: string | null) {
        if (id === null) {
            setSelected(null);
            return;
        }

        const next = items.find((item) => item.id === id) ?? null;
        setSelected(next);
    }

    function moveSelection(delta: 1 | -1) {
        if (items.length === 0) {
            return;
        }

        const currentIndex = selected
            ? items.findIndex((item) => item.id === selected.id)
            : -1;
        const nextIndex = adjacentIndex(items.length, currentIndex, delta);
        const next = items[nextIndex];

        if (!next) {
            return;
        }

        setSelected(next);
        requestAnimationFrame(() => {
            document
                .getElementById(`engagement-reply-${next.id}`)
                ?.scrollIntoView({ block: 'nearest' });
        });
    }

    function archiveSelected() {
        if (!selected) {
            return;
        }

        const archivedId = selected.id;

        void archiveReply(archivedId)
            .then(() => handleArchived(archivedId))
            .catch((error: unknown) => {
                toast.error(
                    errorMessage(error, 'Could not archive the reply.'),
                );
            });
    }

    function focusReply() {
        if (!selected) {
            return;
        }

        replyEditorRef.current?.focus();
    }

    function openSelectedComment() {
        if (!selected) {
            return;
        }

        const url = postPermalink(
            selected.platform,
            selected.author_handle,
            selected.remote_reply_id,
        );

        if (!url) {
            return;
        }

        window.open(url, '_blank', 'noopener,noreferrer');
    }

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            const shortcut = engagementShortcut(event);

            if (!shortcut) {
                return;
            }

            event.preventDefault();

            switch (shortcut.type) {
                case 'next':
                    moveSelection(1);
                    break;
                case 'prev':
                    moveSelection(-1);
                    break;
                case 'archive':
                    archiveSelected();
                    break;
                case 'open':
                    openSelectedComment();
                    break;
                case 'reply':
                    focusReply();
                    break;
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    });

    function handleArchived(id: string) {
        const nextId = nextAfterArchive(
            items.map((item) => item.id),
            id,
        );
        setOverrides((prev) => ({ ...prev, [id]: 'archived' }));
        selectById(nextId);
        // The sidebar's unread badge lives on the /me shell payload.
        void invalidateMe();
    }

    function handleResponded(id: string) {
        setOverrides((prev) => ({ ...prev, [id]: 'responded' }));
    }

    return (
        /*
          Fill the viewport below the sticky app header (h-16) so each
          column owns its own scroll and the reply box stays pinned. On md+
          the sidebar `variant="inset"` gives the <main> an `m-2` (1rem of
          vertical margin), so the desk is `100svh - 4rem - 1rem`; without
          that extra rem the desk overran its inset and spilled a window
          scrollbar under the shortcut bar.
        */
        <div className="grid h-[calc(100svh-4rem)] min-h-0 grid-cols-1 overflow-hidden md:h-[calc(100svh-5rem)] md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            {/* Left: triage column */}
            <div className="flex min-h-0 min-w-0 flex-col overflow-hidden border-r">
                <EngagementDisabledBanner
                    disabledPlatforms={bannerDisabledPlatforms}
                />
                <div className="shrink-0">
                    <ReplyFilters
                        filters={filters}
                        accounts={facets.accounts}
                        posts={facets.posts}
                        onUpdate={applyFilters}
                    />
                </div>
                <div className="relative min-h-0 flex-1">
                    <NewRepliesButton
                        count={unseen.length}
                        onShow={showNewReplies}
                    />
                    <div ref={streamRef} className="h-full overflow-y-auto">
                        {isPending ? (
                            <StreamSkeleton />
                        ) : allEngagementDisabled && items.length === 0 ? (
                            <EngagementDisabledNotice />
                        ) : items.length === 0 ? (
                            <StreamEmpty filtered={filtered} />
                        ) : (
                            <ReplyStream
                                replies={items}
                                revealedIds={revealedIds}
                                selectedId={selected?.id ?? null}
                                onSelect={setSelected}
                            />
                        )}
                        {items.length > 0 && hasNextPage ? (
                            <div ref={sentinelRef} aria-hidden="true" />
                        ) : null}
                        {isFetchingNextPage ? (
                            <div className="flex justify-center py-3">
                                <Skeleton className="h-4 w-24" />
                            </div>
                        ) : null}
                    </div>
                </div>
                {items.length > 0 ? (
                    <div className="hidden shrink-0 flex-wrap items-center gap-x-3 gap-y-1 border-t px-3 py-2 md:flex">
                        <ShortcutHint keys={['↑', '↓']} label="move" />
                        <ShortcutHint keys={['A']} label="archive" />
                        <ShortcutHint keys={['O']} label="open comment" />
                        <ShortcutHint keys={['R']} label="reply" />
                    </div>
                ) : null}
            </div>

            {/* Right: conversation desk (desktop) */}
            {!isMobile ? (
                <div className="hidden min-h-0 min-w-0 flex-col overflow-hidden md:flex">
                    {selected ? (
                        <RightPane
                            selected={selected}
                            onArchived={handleArchived}
                            onResponded={handleResponded}
                            replyEditorRef={replyEditorRef}
                            savedMentions={savedMentions}
                        />
                    ) : allEngagementDisabled ? (
                        <EngagementDisabledNotice />
                    ) : (
                        <ConversationPrompt />
                    )}
                </div>
            ) : null}

            {/* Conversation as a slide-over on mobile */}
            {isMobile ? (
                <Sheet
                    open={selected !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            clearSelection();
                        }
                    }}
                >
                    <SheetContent
                        side="right"
                        className="flex h-full w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-md"
                    >
                        <SheetTitle className="sr-only">
                            Conversation
                        </SheetTitle>
                        {selected ? (
                            <RightPane
                                selected={selected}
                                onArchived={handleArchived}
                                onResponded={handleResponded}
                                replyEditorRef={replyEditorRef}
                                savedMentions={savedMentions}
                                reserveCloseButtonSpace
                            />
                        ) : null}
                    </SheetContent>
                </Sheet>
            ) : null}
        </div>
    );
}
