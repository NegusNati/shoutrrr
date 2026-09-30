import { createFileRoute } from '@tanstack/react-router';

import ApiKeys from '@/pages/settings/workspace/api-keys';

export const Route = createFileRoute('/_app/settings_/workspace/api-keys')({
    component: ApiKeys,
});
