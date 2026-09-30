import type { PlatformName } from '@/types/compose';

const PLATFORM_LABELS = {
    x: 'X',
    bluesky: 'Bluesky',
    linkedin: 'LinkedIn',
    facebook: 'Facebook',
    instagram: 'Instagram',
    threads: 'Threads',
    discord: 'Discord',
} satisfies Record<PlatformName, string>;

export function isPlatformName(value: string): value is PlatformName {
    return Object.hasOwn(PLATFORM_LABELS, value);
}

export function platformLabel(platform: string): string {
    return isPlatformName(platform) ? PLATFORM_LABELS[platform] : platform;
}

export function platformKeys(
    enabled: Record<PlatformName, boolean>,
): PlatformName[] {
    return Object.keys(enabled).filter(isPlatformName);
}

export function disabledPlatformLabels(
    enabled: Record<PlatformName, boolean>,
): string[] {
    return platformKeys(enabled)
        .filter((platform) => !enabled[platform])
        .map((platform) => platformLabel(platform));
}
