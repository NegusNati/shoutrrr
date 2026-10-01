import { createFileRoute } from '@tanstack/react-router';

import ComposePage from '@/pages/compose/index';

export const Route = createFileRoute('/_app/posts/$postId')({
    component: ComposeRoute,
});

function ComposeRoute() {
    const { postId } = Route.useParams();

    return <ComposePage postId={postId} />;
}
