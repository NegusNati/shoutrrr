import { Link } from '@tanstack/react-router';

import { toUrl } from '@/lib/href';
import type { Href } from '@/lib/href';
import { cn } from '@/lib/utils';

type Props = {
    href: Href;
    className?: string;
    tabIndex?: number;
    children?: React.ReactNode;
};

export default function TextLink({
    className = '',
    children,
    href,
    ...props
}: Props) {
    return (
        <Link
            to={toUrl(href)}
            className={cn(
                'text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
