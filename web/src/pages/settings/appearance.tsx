import Heading from '@/components/common/heading';
import AppearanceTabs from '@/components/settings/appearance-tabs';

export default function Appearance() {
    return (
        <>
            <h1 className="sr-only">Appearance settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Appearance settings"
                    description="Update your account's appearance settings"
                />

                <AppearanceTabs />
            </div>
        </>
    );
}
