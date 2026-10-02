import { useNavigate } from '@tanstack/react-router';

import { CommandGroup, CommandItem } from '@/components/ui/command';
import { ListChecks, Pencil, Share2 } from '@/components/ui/icons';
import type { Account, AccountSet } from '@/types/compose';

interface ComposeDestinationPageProps {
    accounts: Account[];
    sets: AccountSet[];
    composeUrl: string;
    run: (fn: () => void) => () => void;
}

export function ComposeDestinationPage({
    accounts,
    sets,
    composeUrl,
    run,
}: ComposeDestinationPageProps) {
    const navigate = useNavigate();

    return (
        <CommandGroup heading="Compose for…">
            <CommandItem
                value="compose all"
                onSelect={run(() => void navigate({ to: composeUrl }))}
            >
                <Pencil className="size-4" aria-hidden />
                All accounts
            </CommandItem>
            {accounts.map((account) => (
                <CommandItem
                    key={account.id}
                    value={`compose account ${account.handle}`}
                    onSelect={run(
                        () =>
                            void navigate({
                                to: '/dashboard',
                                search: {
                                    destination: `account:${account.id}`,
                                },
                            }),
                    )}
                >
                    <Share2 className="size-4" aria-hidden />
                    <span className="truncate">{account.handle}</span>
                </CommandItem>
            ))}
            {sets.map((set) => (
                <CommandItem
                    key={set.id}
                    value={`compose set ${set.name}`}
                    onSelect={run(
                        () =>
                            void navigate({
                                to: '/dashboard',
                                search: { destination: `set:${set.id}` },
                            }),
                    )}
                >
                    <ListChecks className="size-4" aria-hidden />
                    <span className="truncate">{set.name}</span>
                </CommandItem>
            ))}
        </CommandGroup>
    );
}
