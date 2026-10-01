import { createFileRoute } from '@tanstack/react-router';

import InstanceSettingsPage from '@/pages/settings/instance';

export const Route = createFileRoute('/_app/settings/instance/')({
    component: InstanceSettingsPage,
});
