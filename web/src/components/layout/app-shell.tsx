import type { ReactNode } from 'react';

import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

/** Same default the old web shell applied server-side from `sidebar_state`. */
function initialSidebarOpen(): boolean {
    const raw = document.cookie.match(/sidebar_state=([^;]+)/)?.[1];

    return raw === undefined || raw === 'true';
}

export function AppShell({ children, variant = 'sidebar' }: Props) {
    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <SidebarProvider defaultOpen={initialSidebarOpen()}>
            {children}
        </SidebarProvider>
    );
}
