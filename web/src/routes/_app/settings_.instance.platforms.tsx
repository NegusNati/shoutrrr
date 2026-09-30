import { createFileRoute } from '@tanstack/react-router';

import InstancePlatforms from '@/pages/settings/instance/platforms';

export const Route = createFileRoute('/_app/settings_/instance/platforms')({
    component: InstancePlatforms,
});
