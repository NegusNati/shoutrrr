import type { QueryClient } from '@tanstack/react-query';
import { createRootRouteWithContext, Outlet } from '@tanstack/react-router';

import { ConfirmProvider } from '@/components/common/confirm-dialog';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/hooks/use-appearance';

type RouterContext = {
    queryClient: QueryClient;
};

export const Route = createRootRouteWithContext<RouterContext>()({
    component: RootComponent,
});

function RootComponent() {
    // Applies the stored light/dark/system preference onto <html>.
    useAppearance();

    return (
        <ConfirmProvider>
            <Outlet />
            <Toaster />
        </ConfirmProvider>
    );
}
