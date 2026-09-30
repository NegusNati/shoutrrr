import { Link } from '@tanstack/react-router';
import { useEffect } from 'react';

import PostingScheduleController from '@/actions/App/Http/Controllers/Posts/PostingScheduleController';
import SyncPipelinesController from '@/actions/App/Http/Controllers/Settings/SyncPipelinesController';
import AppLogo from '@/components/layout/app-logo';
import { NavUser } from '@/components/layout/nav-user';
import { SidebarFooterCard } from '@/components/layout/sidebar-footer-card';
import type { IconComponent } from '@/components/ui/icons';
import {
    Blocks,
    CalendarDays,
    ChartColumn,
    CreditCard,
    Inbox,
    KeyRound,
    ListChecks,
    MessageCircle,
    MessageSquare,
    Pencil,
    RefreshCw,
    Settings,
    Share2,
    Shield,
    Users,
    Wrench,
} from '@/components/ui/icons';
import { Kbd } from '@/components/ui/kbd';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { WorkspaceSelector } from '@/components/workspace/workspace-selector';
import { useMeData } from '@/features/me/me';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/href';
import type { Href } from '@/lib/href';
import {
    composeButtonClassName,
    composeIconClassName,
} from '@/lib/navigation/compose-nav';
import {
    instanceSettingsNavItems,
    type InstanceSettingsNavKey,
} from '@/lib/navigation/instance-settings-nav';
import {
    workspaceSettingsNavItems,
    type WorkspaceSettingsNavKey,
} from '@/lib/navigation/workspace-settings-nav';
import { appVersion, githubReleaseUrl } from '@/lib/version';
import { index as accountsRoute } from '@/routes/accounts';
import { index as analyticsRoute } from '@/routes/analytics';
import { index as engagementRoute } from '@/routes/engagement';
import { index as messagesRoute } from '@/routes/messages';
import { index as postsRoute } from '@/routes/posts';

type NavItem = {
    title: string;
    href: Href;
    icon: IconComponent;
    /** When true, the target lives inside the SPA and navigates client-side. */
    spa?: boolean;
};

export const workspaceSettingsLabel = 'Workspace';
export const instanceSettingsLabel = 'Instance settings';

const workspaceSettingsIcons: Record<WorkspaceSettingsNavKey, IconComponent> = {
    overview: Settings,
    members: Users,
    apiKeys: KeyRound,
    subscription: CreditCard,
};

const instanceSettingsIcons: Record<InstanceSettingsNavKey, IconComponent> = {
    general: Wrench,
    polling: RefreshCw,
    platforms: Blocks,
    usage: ChartColumn,
    admins: Shield,
};

const versionBadgeClassName =
    'rounded-full border border-sidebar-border px-1.5 py-0.5 text-[10px] leading-none font-medium text-sidebar-foreground/60 transition-colors hover:border-sidebar-accent-foreground/30 hover:text-sidebar-foreground';

const postsNavItems: NavItem[] = [
    { title: 'Posts', href: postsRoute(), icon: Inbox, spa: true },
    { title: 'Calendar', href: '/calendar', icon: CalendarDays, spa: true },
    {
        title: 'Queue',
        href: PostingScheduleController.show(),
        icon: ListChecks,
    },
    { title: 'Accounts', href: accountsRoute(), icon: Share2 },
    { title: 'Engagement', href: engagementRoute(), icon: MessageCircle },
    { title: 'Messages', href: messagesRoute(), icon: MessageSquare },
];

/**
 * Navigation link for sidebar items: TanStack Link (client-side) when the
 * target is a ported SPA route, a plain anchor (full load into the
 * server-rendered app) for pages that haven't been ported yet.
 */
function NavItemLink({
    href,
    spa,
    className,
    children,
}: {
    href: Href;
    spa?: boolean;
    className?: string;
    children?: React.ReactNode;
}) {
    if (spa) {
        return (
            <Link to={toUrl(href)} className={className}>
                {children}
            </Link>
        );
    }

    return (
        <a href={toUrl(href)} className={className}>
            {children}
        </a>
    );
}

export function AppSidebar() {
    const me = useMeData();
    const {
        workspaces,
        features,
        instance,
        shell,
        updateAvailable,
        latestVersion,
        latestReleaseUrl,
    } = me ?? {};
    const unreadReplies = shell?.unreadReplies ?? 0;
    const unreadMessages = shell?.unreadMessages ?? 0;
    const { isCurrentOrParentUrl, isCurrentUrl, currentUrl } = useCurrentUrl();
    const { state, setOpenMobile } = useSidebar();
    const collapsed = state === 'collapsed';

    // Close the mobile off-canvas sidebar whenever a navigation commits.
    useEffect(() => {
        setOpenMobile(false);
    }, [currentUrl, setOpenMobile]);

    const logoHref = '/dashboard';
    const composeHref = '/compose';
    const showWorkspaceSettings = workspaces?.enabled && workspaces.current;
    const canManageWorkspace = (
        workspaces?.current?.permissions ?? []
    ).includes('workspace.settings.manage');
    const showSyncPipelines = !!showWorkspaceSettings && canManageWorkspace;
    const showInstanceSettings = instance?.isOwner ?? false;
    const settingsItems = showWorkspaceSettings
        ? workspaceSettingsNavItems({
              permissions: workspaces?.current?.permissions ?? [],
              billingEnabled: !!features?.billing,
          })
        : [];
    const instanceItems = showInstanceSettings
        ? instanceSettingsNavItems()
        : [];
    const isItemActive = (
        item: (typeof settingsItems)[number] | (typeof instanceItems)[number],
    ) =>
        item.key === 'overview' || item.key === 'general'
            ? isCurrentUrl(item.href)
            : isCurrentOrParentUrl(item.href);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-1.5">
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center gap-1">
                        <SidebarMenuButton
                            className="h-8 min-w-0 flex-1 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0!"
                            render={<NavItemLink href={logoHref} spa />}
                        >
                            <AppLogo />
                        </SidebarMenuButton>
                        <span className="relative flex group-data-[collapsible=icon]:hidden">
                            {(() => {
                                const badge = (
                                    <a
                                        href={
                                            updateAvailable && latestReleaseUrl
                                                ? latestReleaseUrl
                                                : githubReleaseUrl
                                        }
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={versionBadgeClassName}
                                        aria-label={
                                            updateAvailable
                                                ? `Shoutrrr ${appVersion} — update ${latestVersion ?? ''} available on GitHub`
                                                : `View Shoutrrr ${appVersion} release notes on GitHub`
                                        }
                                    >
                                        {appVersion}
                                    </a>
                                );

                                return updateAvailable ? (
                                    <Tooltip>
                                        <TooltipTrigger render={badge} />
                                        <TooltipContent>
                                            Update available: {latestVersion}
                                        </TooltipContent>
                                    </Tooltip>
                                ) : (
                                    badge
                                );
                            })()}
                            {updateAvailable && (
                                <span
                                    className="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-sidebar"
                                    aria-hidden="true"
                                />
                            )}
                        </span>
                    </SidebarMenuItem>
                </SidebarMenu>
                <WorkspaceSelector />
            </SidebarHeader>

            <SidebarContent className="gap-0">
                <SidebarGroup className="border-b border-sidebar-border">
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    tooltip="Compose new post"
                                    isActive={isCurrentUrl(composeHref)}
                                    className={composeButtonClassName(
                                        collapsed,
                                    )}
                                    render={
                                        <NavItemLink href={composeHref} spa />
                                    }
                                >
                                    <span className="pointer-events-none flex items-center gap-2">
                                        <span
                                            className={composeIconClassName()}
                                        >
                                            <Pencil aria-hidden="true" />
                                        </span>
                                        {!collapsed && (
                                            <span>Compose post</span>
                                        )}
                                    </span>
                                    {!collapsed && (
                                        <Kbd className="bg-primary-foreground/15 text-primary-foreground">
                                            ⌘.
                                        </Kbd>
                                    )}
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel>Posts</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {postsNavItems
                                .filter(
                                    (item) =>
                                        (item.title !== 'Engagement' ||
                                            features?.engagement) &&
                                        (item.title !== 'Messages' ||
                                            features?.messages),
                                )
                                .map((item) => (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            tooltip={item.title}
                                            isActive={isCurrentUrl(item.href)}
                                            render={
                                                <NavItemLink
                                                    href={item.href}
                                                    spa={item.spa}
                                                />
                                            }
                                        >
                                            <item.icon aria-hidden="true" />
                                            <span>{item.title}</span>
                                            {item.title === 'Engagement' &&
                                            unreadReplies > 0 ? (
                                                <span className="ml-auto rounded-full bg-primary-gradient px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground">
                                                    {unreadReplies > 99
                                                        ? '99+'
                                                        : unreadReplies}
                                                </span>
                                            ) : null}
                                            {item.title === 'Messages' &&
                                            unreadMessages > 0 ? (
                                                <span className="ml-auto rounded-full bg-primary-gradient px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground">
                                                    {unreadMessages > 99
                                                        ? '99+'
                                                        : unreadMessages}
                                                </span>
                                            ) : null}
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            {features?.analytics && (
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        tooltip="Analytics"
                                        isActive={isCurrentUrl(
                                            analyticsRoute(),
                                        )}
                                        render={
                                            <a href={toUrl(analyticsRoute())} />
                                        }
                                    >
                                        <ChartColumn aria-hidden="true" />
                                        <span>Analytics</span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            )}
                            {showSyncPipelines && (
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        tooltip="Sync pipelines"
                                        isActive={isCurrentOrParentUrl(
                                            SyncPipelinesController.index().url,
                                        )}
                                        render={
                                            <a
                                                href={
                                                    SyncPipelinesController.index()
                                                        .url
                                                }
                                            />
                                        }
                                    >
                                        <RefreshCw aria-hidden="true" />
                                        <span>Sync pipelines</span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            )}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                {showWorkspaceSettings && (
                    <SidebarGroup>
                        <SidebarGroupLabel>
                            {workspaceSettingsLabel}
                        </SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {settingsItems.map((item) => {
                                    const Icon =
                                        workspaceSettingsIcons[item.key];

                                    return (
                                        <SidebarMenuItem key={item.key}>
                                            <SidebarMenuButton
                                                tooltip={item.title}
                                                isActive={isItemActive(item)}
                                                render={
                                                    <a
                                                        href={toUrl(item.href)}
                                                    />
                                                }
                                            >
                                                <Icon aria-hidden="true" />
                                                <span>{item.title}</span>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    );
                                })}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                )}

                {showInstanceSettings && (
                    <SidebarGroup>
                        <SidebarGroupLabel>
                            {instanceSettingsLabel}
                        </SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {instanceItems.map((item) => {
                                    const Icon =
                                        instanceSettingsIcons[item.key];

                                    return (
                                        <SidebarMenuItem key={item.key}>
                                            <SidebarMenuButton
                                                tooltip={item.title}
                                                isActive={isItemActive(item)}
                                                render={
                                                    <a
                                                        href={toUrl(item.href)}
                                                    />
                                                }
                                            >
                                                <Icon aria-hidden="true" />
                                                <span>{item.title}</span>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    );
                                })}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>
                <SidebarFooterCard />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
