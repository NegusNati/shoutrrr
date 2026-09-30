import { useQuery } from '@tanstack/react-query';
import { createFileRoute, notFound } from '@tanstack/react-router';

import {
    postDetailQuery,
    postKeys,
} from '@/features/posts/compose.queries';
import { ApiError } from '@/lib/api';
import { ComposePage, ComposePageSkeleton } from '@/pages/compose';

export const Route = createFileRoute('/_app/posts/$postId')({
    loader: ({ context, params }) =>
        context.queryClient.ensureQueryData(postDetailQuery(params.postId)),
    component: PostDetailRoute,
    pendingComponent: ComposePageSkeleton,
    notFoundComponent: () => (
        <div className="mx-auto max-w-3xl px-4 py-16 text-center text-muted-foreground">
            Post not found.
        </div>
    ),
});

function PostDetailRoute() {
    const { postId } = Route.useParams();
    const { data: post, error } = useQuery({
        ...postDetailQuery(postId),
        queryKey: postKeys.detail(postId),
    });

    if (error instanceof ApiError && error.status === 404) {
        throw notFound();
    }
    if (!post) {
        return <ComposePageSkeleton />;
    }

    return <ComposePage post={post} />;
}
