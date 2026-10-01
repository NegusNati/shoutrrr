import { useState, type FormEvent } from 'react';

import BlueskyOAuthController from '@/actions/App/Http/Controllers/ConnectedAccounts/BlueskyOAuthController';
import MetaConnectionController from '@/actions/App/Http/Controllers/ConnectedAccounts/MetaConnectionController';
import OAuthConnectionController from '@/actions/App/Http/Controllers/ConnectedAccounts/OAuthConnectionController';
import InputError from '@/components/common/input-error';
import { PlatformGlyph } from '@/components/common/platform-glyph';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AtSign, ChevronDown, Loader2, Plus } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import {
    connectBluesky,
    connectDiscord,
    type Capability,
} from '@/features/accounts/accounts';
import { useBlueskyHandleResolver } from '@/hooks/use-bluesky-handle-resolver';
import { ApiError, errorMessage } from '@/lib/api';
import type { PlatformName } from '@/types/compose';

export const COLLAPSIBLE_TRIGGER_ICON_CLASS =
    '[&[data-panel-open]_svg]:rotate-180';

const SUPPORTED_PLATFORM_ICONS = [
    'x',
    'bluesky',
    'linkedin',
    'facebook',
    'instagram',
    'threads',
    'discord',
];

export function isSupportedPlatformIcon(
    platform: string,
): platform is PlatformName {
    return SUPPORTED_PLATFORM_ICONS.includes(platform);
}

function platformIcon(platform: string) {
    if (!isSupportedPlatformIcon(platform)) {
        return <AtSign className="size-4" />;
    }

    return <PlatformGlyph platform={platform} size={16} className="size-4" />;
}

function BlueskyConnectDialog({
    open,
    onOpenChange,
    onConnected,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConnected: () => void;
}) {
    const [appPasswordOpen, setAppPasswordOpen] = useState(false);
    const [oauthServiceOpen, setOauthServiceOpen] = useState(false);
    const [appPasswordServiceOpen, setAppPasswordServiceOpen] = useState(false);
    const [oauthLoading, setOauthLoading] = useState(false);
    const [handle, setHandle] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const resolver = useBlueskyHandleResolver();

    const submitAppPassword = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

        setProcessing(true);
        setErrors({});
        setFormError(null);

        connectBluesky({
            identifier: data.get('identifier') as string,
            app_password: data.get('app_password') as string,
            ...(data.get('pds_url')
                ? { pds_url: data.get('pds_url') as string }
                : {}),
        })
            .then(() => {
                onConnected();
                onOpenChange(false);
            })
            .catch((error: unknown) => {
                if (error instanceof ApiError) {
                    setErrors(error.errors);
                    if (Object.keys(error.errors).length === 0) {
                        setFormError(
                            errorMessage(
                                error,
                                'Could not connect the account.',
                            ),
                        );
                    }
                } else {
                    setFormError('Could not connect the account.');
                }
            })
            .finally(() => setProcessing(false));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Connect a Bluesky account</DialogTitle>
                    <DialogDescription>
                        Enter your handle, or leave it blank to choose on
                        Bluesky.
                    </DialogDescription>
                </DialogHeader>
                <form
                    action={BlueskyOAuthController.redirect.url()}
                    method="get"
                    className="space-y-4 py-2"
                    onSubmit={() => setOauthLoading(true)}
                >
                    <div className="relative grid gap-2">
                        <Label htmlFor="oauth_identifier">Handle</Label>
                        <InputGroup>
                            <InputGroupAddon>
                                {resolver.avatar ? (
                                    <img
                                        src={resolver.avatar}
                                        alt=""
                                        className="size-4 rounded-full object-cover"
                                    />
                                ) : (
                                    '@'
                                )}
                            </InputGroupAddon>
                            <InputGroupInput
                                id="oauth_identifier"
                                name="identifier"
                                placeholder="you.bsky.social"
                                value={handle}
                                autoComplete="off"
                                role="combobox"
                                aria-expanded={
                                    resolver.suggestionsOpen &&
                                    resolver.suggestions.length > 0
                                }
                                aria-controls="bluesky-handle-listbox"
                                aria-activedescendant={
                                    resolver.selectedIdx >= 0
                                        ? `bluesky-handle-option-${resolver.selectedIdx}`
                                        : undefined
                                }
                                onChange={(e) => {
                                    setHandle(e.target.value);
                                    resolver.onInput(e.target.value);
                                }}
                                onKeyDown={(e) => {
                                    if (
                                        e.key === 'Enter' &&
                                        resolver.selectedIdx >= 0
                                    ) {
                                        e.preventDefault();
                                        const s =
                                            resolver.suggestions[
                                                resolver.selectedIdx
                                            ];
                                        const h = resolver.selectSuggestion(
                                            s.handle,
                                            s.avatar,
                                        );
                                        setHandle(h);
                                    } else {
                                        resolver.onKeydown(e);
                                    }
                                }}
                                onBlur={() =>
                                    setTimeout(
                                        () =>
                                            resolver.setSuggestionsOpen(false),
                                        150,
                                    )
                                }
                                onFocus={() => {
                                    if (resolver.suggestions.length) {
                                        resolver.setSuggestionsOpen(true);
                                    }
                                }}
                            />
                        </InputGroup>
                        {resolver.suggestionsOpen &&
                            resolver.suggestions.length > 0 && (
                                <div
                                    id="bluesky-handle-listbox"
                                    role="listbox"
                                    className="absolute top-full right-0 left-0 z-50 mt-1 rounded-xl border bg-popover p-1 text-popover-foreground shadow-md"
                                >
                                    {resolver.suggestions.map((s, i) => (
                                        <button
                                            key={s.did}
                                            type="button"
                                            role="option"
                                            id={`bluesky-handle-option-${i}`}
                                            aria-selected={
                                                i === resolver.selectedIdx
                                            }
                                            className={`flex w-full items-center gap-2 rounded-xl px-2 py-1.5 text-sm outline-hidden select-none hover:bg-muted ${i === resolver.selectedIdx ? 'bg-muted' : ''}`}
                                            onMouseDown={(e) => {
                                                e.preventDefault();
                                                const h =
                                                    resolver.selectSuggestion(
                                                        s.handle,
                                                        s.avatar,
                                                    );
                                                setHandle(h);
                                            }}
                                        >
                                            {s.avatar ? (
                                                <img
                                                    src={s.avatar}
                                                    alt=""
                                                    className="size-6 shrink-0 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="size-6 shrink-0 rounded-full bg-muted-foreground/20" />
                                            )}
                                            <span className="truncate font-medium">
                                                {s.displayName || s.handle}
                                            </span>
                                            {s.displayName && (
                                                <span className="truncate text-muted-foreground">
                                                    @{s.handle}
                                                </span>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            )}
                    </div>
                    <Collapsible
                        open={oauthServiceOpen}
                        onOpenChange={setOauthServiceOpen}
                    >
                        <CollapsibleTrigger
                            render={
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className={`px-0 text-muted-foreground ${COLLAPSIBLE_TRIGGER_ICON_CLASS}`}
                                />
                            }
                        >
                            Choose Bluesky instance
                            <ChevronDown
                                aria-hidden="true"
                                data-icon="inline-end"
                                className="size-4 text-muted-foreground transition-transform"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="grid gap-2 pt-2">
                            <Label htmlFor="oauth_pds_url">Service URL</Label>
                            <Input
                                id="oauth_pds_url"
                                name="pds_url"
                                type="url"
                                placeholder="https://bsky.social"
                                autoComplete="url"
                            />
                            <p className="text-xs text-muted-foreground">
                                Use this only if your account is hosted by a
                                custom ATProto/Bluesky service.
                            </p>
                        </CollapsibleContent>
                    </Collapsible>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={oauthLoading}
                    >
                        {oauthLoading && (
                            <Loader2 className="size-4 animate-spin" />
                        )}
                        Continue with Bluesky
                    </Button>
                </form>
                <Collapsible
                    open={appPasswordOpen}
                    onOpenChange={setAppPasswordOpen}
                >
                    <div className="flex items-center gap-3 py-1 text-[11px] tracking-wide text-muted-foreground uppercase">
                        <span className="h-px flex-1 bg-border" />
                        <CollapsibleTrigger
                            render={
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className={COLLAPSIBLE_TRIGGER_ICON_CLASS}
                                />
                            }
                        >
                            Use app password instead
                            <ChevronDown
                                aria-hidden="true"
                                data-icon="inline-end"
                                className="size-4 text-muted-foreground transition-transform"
                            />
                        </CollapsibleTrigger>
                        <span className="h-px flex-1 bg-border" />
                    </div>
                    <CollapsibleContent>
                        <p className="pb-2 text-sm text-muted-foreground">
                            <strong>Not recommended.</strong> App passwords
                            bypass 2FA, and disconnecting here does not revoke
                            them on Bluesky. You can manage them on{' '}
                            <a
                                href="https://bsky.app/settings/app-passwords"
                                target="_blank"
                                rel="noreferrer"
                                className="underline"
                            >
                                Bluesky
                            </a>
                            .
                        </p>
                        <form onSubmit={submitAppPassword}>
                            <div className="space-y-4">
                                {formError && (
                                    <p className="text-sm text-destructive">
                                        {formError}
                                    </p>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="identifier">
                                        Handle or email
                                    </Label>
                                    <InputGroup>
                                        <InputGroupAddon>@</InputGroupAddon>
                                        <InputGroupInput
                                            id="identifier"
                                            name="identifier"
                                            placeholder="you.bsky.social"
                                            aria-invalid={
                                                errors.identifier
                                                    ? true
                                                    : undefined
                                            }
                                            required
                                        />
                                    </InputGroup>
                                    <InputError
                                        message={errors.identifier?.[0]}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="app_password">
                                        App password
                                    </Label>
                                    <Input
                                        id="app_password"
                                        name="app_password"
                                        type="password"
                                        placeholder="xxxx-xxxx-xxxx-xxxx"
                                        required
                                    />
                                    <InputError
                                        message={errors.app_password?.[0]}
                                    />
                                </div>
                                <Collapsible
                                    open={appPasswordServiceOpen}
                                    onOpenChange={setAppPasswordServiceOpen}
                                >
                                    <CollapsibleTrigger
                                        render={
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className={`px-0 text-muted-foreground ${COLLAPSIBLE_TRIGGER_ICON_CLASS}`}
                                            />
                                        }
                                    >
                                        Choose Bluesky instance
                                        <ChevronDown
                                            aria-hidden="true"
                                            data-icon="inline-end"
                                            className="size-4 text-muted-foreground transition-transform"
                                        />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="grid gap-2 pt-2">
                                        <Label htmlFor="app_password_pds_url">
                                            Service URL
                                        </Label>
                                        <Input
                                            id="app_password_pds_url"
                                            name="pds_url"
                                            type="url"
                                            placeholder="https://bsky.social"
                                            autoComplete="url"
                                            aria-invalid={
                                                errors.pds_url
                                                    ? true
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            message={errors.pds_url?.[0]}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Use this only if your account is
                                            hosted by a custom ATProto/Bluesky
                                            service.
                                        </p>
                                    </CollapsibleContent>
                                </Collapsible>
                            </div>
                            <DialogFooter className="pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Connecting...' : 'Connect'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </CollapsibleContent>
                </Collapsible>
            </DialogContent>
        </Dialog>
    );
}

function DiscordConnectDialog({
    open,
    onOpenChange,
    onConnected,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConnected: () => void;
}) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [formError, setFormError] = useState<string | null>(null);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

        setProcessing(true);
        setErrors({});
        setFormError(null);

        connectDiscord(data.get('webhook_url') as string)
            .then(() => {
                onConnected();
                onOpenChange(false);
            })
            .catch((error: unknown) => {
                if (error instanceof ApiError) {
                    setErrors(error.errors);
                    if (Object.keys(error.errors).length === 0) {
                        setFormError(
                            errorMessage(
                                error,
                                'Could not connect the channel.',
                            ),
                        );
                    }
                } else {
                    setFormError('Could not connect the channel.');
                }
            })
            .finally(() => setProcessing(false));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Connect a Discord channel</DialogTitle>
                    <DialogDescription>
                        In Discord: Channel Settings → Integrations → Webhooks →
                        New Webhook → Copy Webhook URL, then paste it here.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-2 py-2">
                        {formError && (
                            <p className="text-sm text-destructive">
                                {formError}
                            </p>
                        )}
                        <Label htmlFor="webhook_url">Webhook URL</Label>
                        <Input
                            id="webhook_url"
                            name="webhook_url"
                            type="url"
                            placeholder="https://discord.com/api/webhooks/..."
                            autoComplete="off"
                            aria-invalid={errors.webhook_url ? true : undefined}
                            required
                        />
                        <InputError message={errors.webhook_url?.[0]} />
                    </div>
                    <DialogFooter className="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Connecting...' : 'Connect'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Facebook and Instagram share a single Facebook Login flow
 * (`MetaConnectionController`), so they get one combined entry point instead
 * of two separate buttons. The label mentions Instagram only once it's
 * launched.
 */
export function metaConnectLabel(capabilities: Capability[]): string {
    const instagram = capabilities.find((c) => c.platform === 'instagram');

    return instagram?.launched ? 'Facebook / Instagram' : 'Facebook';
}

/**
 * Where a platform's connect flow lives: Facebook (and its folded-in Instagram)
 * go through the Meta Page-selection flow; everyone else shares the generic
 * OAuth redirect. Bluesky is handled separately — it opens a dialog, not a link.
 */
function connectHref(capability: Capability): string {
    return capability.platform === 'facebook'
        ? MetaConnectionController.redirect.url()
        : OAuthConnectionController.redirect.url({
              platform: capability.platform,
          });
}

/**
 * A single "Connect account" button that opens a menu of every platform, instead
 * of a row of per-platform buttons that wraps once there are more than a few.
 * Platforms with no OAuth credentials on this instance stay visible but dimmed
 * with a reason, so an admin knows why they can't be connected yet.
 */
export function ConnectButtons({
    capabilities,
    onConnected,
}: {
    capabilities: Capability[];
    onConnected: () => void;
}) {
    const [blueskyOpen, setBlueskyOpen] = useState(false);
    const [discordOpen, setDiscordOpen] = useState(false);

    // Instagram connects through the Facebook (Meta) entry, so it isn't its own row.
    const platforms = capabilities.filter((c) => c.platform !== 'instagram');

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button className="w-full justify-center sm:w-auto [&[data-popup-open]>svg:last-of-type]:rotate-180" />
                    }
                >
                    <Plus className="size-4" />
                    Connect account
                    <ChevronDown
                        className="size-4 opacity-70 transition-transform"
                        aria-hidden
                    />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-64">
                    <DropdownMenuLabel className="text-muted-foreground">
                        Connect a social account
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {platforms.map((capability) => {
                        if (!capability.enabled) {
                            return null;
                        }

                        const label =
                            capability.platform === 'facebook'
                                ? metaConnectLabel(capabilities)
                                : capability.label;

                        // Discord authenticates via a pasted webhook URL dialog.
                        if (capability.supportsWebhook) {
                            return (
                                <DropdownMenuItem
                                    key={capability.platform}
                                    className="gap-2.5"
                                    onClick={() => setDiscordOpen(true)}
                                >
                                    {platformIcon(capability.platform)}
                                    {label}
                                </DropdownMenuItem>
                            );
                        }

                        // Bluesky authenticates via a handle / app-password dialog.
                        if (capability.supportsAppPassword) {
                            return (
                                <DropdownMenuItem
                                    key={capability.platform}
                                    className="gap-2.5"
                                    onClick={() => setBlueskyOpen(true)}
                                >
                                    {platformIcon(capability.platform)}
                                    {label}
                                </DropdownMenuItem>
                            );
                        }

                        if (!capability.launched || !capability.configured) {
                            return (
                                <DropdownMenuItem
                                    key={capability.platform}
                                    disabled
                                    className="gap-2.5"
                                >
                                    {platformIcon(capability.platform)}
                                    <span className="flex-1 truncate">
                                        {label}
                                    </span>
                                    <span className="text-xs whitespace-nowrap text-muted-foreground">
                                        Not set up
                                    </span>
                                </DropdownMenuItem>
                            );
                        }

                        return (
                            <DropdownMenuItem
                                key={capability.platform}
                                className="gap-2.5"
                                render={<a href={connectHref(capability)} />}
                            >
                                {platformIcon(capability.platform)}
                                {label}
                            </DropdownMenuItem>
                        );
                    })}
                </DropdownMenuContent>
            </DropdownMenu>
            <BlueskyConnectDialog
                open={blueskyOpen}
                onOpenChange={setBlueskyOpen}
                onConnected={onConnected}
            />
            <DiscordConnectDialog
                open={discordOpen}
                onOpenChange={setDiscordOpen}
                onConnected={onConnected}
            />
        </>
    );
}
