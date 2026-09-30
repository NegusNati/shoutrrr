import type { HTMLAttributeAnchorTarget } from 'react';

import type { IconComponent } from '@/components/ui/icons';
import type { Href } from '@/lib/href';

export type BreadcrumbItem = {
    title: string;
    href: Href;
};

export type NavItem = {
    title: string;
    href: Href;
    icon?: IconComponent | null;
    isActive?: boolean;
    target?: HTMLAttributeAnchorTarget;
};
