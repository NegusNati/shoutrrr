import { createFileRoute } from '@tanstack/react-router';

import InstanceSettingsLayout from '@/layouts/instance-settings-layout';

export const Route = createFileRoute('/_app/settings_/instance')({
    component: InstanceSettingsLayout,
});
