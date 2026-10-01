import { createFileRoute } from '@tanstack/react-router';

import AccountsIndexPage from '@/pages/accounts/index';

export const Route = createFileRoute('/_app/accounts/')({
    component: AccountsIndexPage,
});
