import { createFileRoute } from '@tanstack/react-router';

import InstancePolling from '@/pages/settings/instance-polling';

export const Route = createFileRoute('/_app/settings/instance/polling')({
    component: InstancePolling,
});
