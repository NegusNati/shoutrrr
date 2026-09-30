import { createFileRoute } from '@tanstack/react-router';

import InstanceAdmins from '@/pages/settings/instance/admins';

export const Route = createFileRoute('/_app/settings_/instance/admins')({
    component: InstanceAdmins,
});
