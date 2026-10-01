import Heading from '@/components/common/heading';
import AppearanceTabs from '@/components/settings/appearance-tabs';
import { useDocumentTitle } from '@/hooks/use-document-title';

export default function AppearancePage() {
    useDocumentTitle('Appearance settings');

    return (
        <>
            <h1 className="sr-only">Appearance settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Appearance settings"
                    description="Update the appearance settings for your account"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}
