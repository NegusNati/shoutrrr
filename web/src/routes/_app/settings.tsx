import { createFileRoute } from '@tanstack/react-router';

import SettingsLayout from '@/layouts/settings-layout';

export const Route = createFileRoute('/_app/settings')({
    component: SettingsLayout,
});
