import { createFileRoute } from '@tanstack/react-router';

import InstanceGeneral from '@/pages/settings/instance';

export const Route = createFileRoute('/_app/settings/instance/')({
    component: InstanceGeneral,
});
