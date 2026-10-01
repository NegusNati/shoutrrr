import { useQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';

import Composer from '@/components/compose/composer';
import { PostPageActions } from '@/components/posts/post-page-actions';
import { PublishedPostView } from '@/components/posts/published-post-view';
import { Button } from '@/components/ui/button';
import { ArrowLeft, PenLine } from '@/components/ui/icons';
import { composeQuery } from '@/features/compose/compose';
import { usePostStatusPoll } from '@/hooks/compose/use-post-status-poll';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { firstLineTitle } from '@/lib/compose/composer-state';

export default function ComposePage({ postId }: { postId: string }) {
    const { data } = useQuery(composeQuery(postId));

    const post = data?.post ?? null;
    const title = firstLineTitle(post?.segments ?? ['']);
    const isPublished = Boolean(
        post && post.targets.some((t) => t.status === 'published'),
    );
    useDocumentTitle(title || 'Compose');
    usePostStatusPoll(post);

    if (!data) {
        return null;
    }

    return (
        <div className="mx-auto w-full max-w-7xl px-4 pt-6 pb-16 sm:px-6">
            <div className="sticky top-0 z-10 mb-5 flex items-center gap-2 border-b border-border bg-background/85 px-2 py-2 backdrop-blur-md">
                <Button
                    nativeButton={false}
                    variant="ghost"
                    size="sm"
                    className="h-8 gap-1.5 px-2 text-muted-foreground hover:text-foreground"
                    render={
                        <Link
                            to="/posts"
                            search={{
                                status: 'all',
                                set: '',
                                platform: '',
                                q: '',
                            }}
                        />
                    }
                >
                    <ArrowLeft className="size-4" />
                    Posts
                </Button>
                <div className="h-4 w-px bg-border" aria-hidden />
                <div className="flex min-w-0 flex-1 items-center gap-1.5">
                    <PenLine
                        className="size-3.5 shrink-0 text-muted-foreground"
                        aria-hidden
                    />
                    <span className="truncate text-[13px] font-medium tracking-tight">
                        {title || 'Untitled draft'}
                    </span>
                </div>
                {post && <PostPageActions post={post} />}
            </div>

            {post && isPublished ? (
                <PublishedPostView
                    post={post}
                    showMetrics={data.metricsEnabled}
                />
            ) : (
                <Composer
                    post={post}
                    accounts={data.accounts}
                    sets={data.sets}
                    limits={data.limits}
                    initialSavedMentions={data.savedMentions}
                />
            )}
        </div>
    );
}
