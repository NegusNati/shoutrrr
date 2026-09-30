import { createFileRoute } from '@tanstack/react-router';

import Appearance from '@/pages/settings/appearance';

export const Route = createFileRoute('/_app/settings/appearance')({
    component: Appearance,
});
