import { useQuery } from '@tanstack/react-query';

import Heading from '@/components/common/heading';
import TextLink from '@/components/common/text-link';
import ManageConnectedAccounts from '@/components/settings/manage-connected-accounts';
import { connectionsQuery } from '@/features/user-settings/user-settings';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { request as passwordReset } from '@/routes/password';

export default function ConnectionsPage() {
    useDocumentTitle('Connected accounts');

    const { data } = useQuery(connectionsQuery);
    const connections = data?.connections ?? [];

    return (
        <>
            <h1 className="sr-only">Connected accounts</h1>

            <ManageConnectedAccounts connections={connections} />

            {data?.hasPassword === false && (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Set a password"
                        description="Add a password to your account so you can also sign in without a connected provider"
                    />

                    <TextLink href={passwordReset()}>Set a password</TextLink>
                </div>
            )}
        </>
    );
}
