import { Link, Outlet } from '@tanstack/react-router';
import type { PropsWithChildren } from 'react';

import Heading from '@/components/common/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useMeData } from '@/features/me/me';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

type SettingsNavItem = {
    title: string;
    href: string;
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const me = useMeData();
    const hasSocialProviders = (me?.socialite.providers.length ?? 0) > 0;

    const sidebarNavItems: SettingsNavItem[] = [
        { title: 'Profile', href: '/settings/profile' },
        { title: 'Security', href: '/settings/security' },
        ...(hasSocialProviders
            ? [
                  {
                      title: 'Connected accounts',
                      href: '/settings/connections',
                  },
              ]
            : []),
        { title: 'Appearance', href: '/settings/appearance' },
        { title: 'Notifications', href: '/settings/notifications' },
    ];

    return (
        <div className="mx-auto w-full max-w-6xl px-4 pt-6 pb-16 sm:px-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item) => (
                            <Button
                                key={item.href}
                                size="sm"
                                variant="ghost"
                                nativeButton={false}
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(
                                        item.href,
                                    ),
                                })}
                                render={<Link to={item.href} />}
                            >
                                {item.title}
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children ?? <Outlet />}
                    </section>
                </div>
            </div>
        </div>
    );
}
