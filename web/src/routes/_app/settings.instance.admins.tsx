import { createFileRoute } from '@tanstack/react-router';

import InstanceAdminsPage from '@/pages/settings/instance-admins';

export const Route = createFileRoute('/_app/settings/instance/admins')({
    component: InstanceAdminsPage,
});
