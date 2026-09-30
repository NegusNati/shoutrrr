import { useQuery } from '@tanstack/react-query';

import Heading from '@/components/common/heading';
import ManageConnectedAccounts from '@/components/settings/manage-connected-accounts';
import { connectionsQuery } from '@/features/settings/settings';

export default function Connections() {
    const { data } = useQuery(connectionsQuery);

    if (!data) {
        return null;
    }

    return (
        <>
            <h1 className="sr-only">Connected accounts</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Connected accounts"
                    description="Manage the providers you can sign in with"
                />

                <ManageConnectedAccounts connections={data.connections} />
            </div>
        </>
    );
}
