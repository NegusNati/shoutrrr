import { createFileRoute } from '@tanstack/react-router';

import Subscription from '@/pages/settings/workspace/subscription';

export const Route = createFileRoute('/_app/settings_/workspace/subscription')({
    component: Subscription,
});
