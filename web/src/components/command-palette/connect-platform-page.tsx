import { useNavigate } from '@tanstack/react-router';

import { CommandGroup, CommandItem } from '@/components/ui/command';
import { Plug } from '@/components/ui/icons';
import { connect as accountConnect } from '@/routes/accounts';

interface ConnectPlatformPageProps {
    run: (fn: () => void) => () => void;
}

const PLATFORMS = [
    ['x', 'X', true],
    ['linkedin', 'LinkedIn', true],
    ['bluesky', 'Bluesky', false],
] as const;

export function ConnectPlatformPage({ run }: ConnectPlatformPageProps) {
    const navigate = useNavigate();

    return (
        <CommandGroup heading="Connect account">
            {PLATFORMS.map(([platform, label, isOAuth]) => (
                <CommandItem
                    key={platform}
                    value={`connect ${platform}`}
                    onSelect={run(() => {
                        if (isOAuth) {
                            window.location.href = accountConnect({
                                platform,
                            }).url;
                        } else {
                            // Bluesky connects through a dialog on the accounts
                            // page rather than an OAuth redirect.
                            void navigate({ to: '/accounts' });
                        }
                    })}
                >
                    <Plug className="size-4" aria-hidden />
                    {label}
                </CommandItem>
            ))}
        </CommandGroup>
    );
}
