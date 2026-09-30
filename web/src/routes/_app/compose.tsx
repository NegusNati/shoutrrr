import { createFileRoute } from '@tanstack/react-router';

import { ComposePage } from '@/pages/compose';

export const Route = createFileRoute('/_app/compose')({
    component: ComposeRoute,
});

function ComposeRoute() {
    return <ComposePage post={null} />;
}
