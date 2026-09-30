import { createFileRoute } from '@tanstack/react-router';

import Security from '@/pages/settings/security';

export const Route = createFileRoute('/_app/settings/security')({
    component: Security,
});
