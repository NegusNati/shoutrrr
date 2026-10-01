import { Outlet } from '@tanstack/react-router';
import type { PropsWithChildren } from 'react';

import Heading from '@/components/common/heading';
import { useMeData } from '@/features/me/me';

export default function WorkspaceSettingsLayout({
    children,
}: PropsWithChildren) {
    const me = useMeData();
    const current = me?.workspaces.current;

    return (
        <div className="mx-auto w-full max-w-2xl px-4 pt-6 pb-16 sm:px-6">
            <Heading
                title="Workspace settings"
                description={
                    current
                        ? `Manage ${current.name} and its members`
                        : 'Manage your workspace and its members'
                }
            />

            <section className="space-y-12">{children ?? <Outlet />}</section>
        </div>
    );
}
