import { createFileRoute, redirect } from '@tanstack/react-router';

// /app — enter the authenticated area; its guard bounces to /login if needed.
export const Route = createFileRoute('/')({
    beforeLoad: () => {
        throw redirect({ to: '/dashboard' });
    },
});
