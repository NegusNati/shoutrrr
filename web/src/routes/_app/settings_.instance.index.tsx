import { createFileRoute } from '@tanstack/react-router';

import InstanceSettings from '@/pages/settings/instance/index';

export const Route = createFileRoute('/_app/settings_/instance/')({
    component: InstanceSettings,
});
