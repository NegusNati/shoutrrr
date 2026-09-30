import { UserInfo } from '@/components/layout/user-info';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { LogOut, Settings } from '@/components/ui/icons';
import { useSidebar } from '@/components/ui/sidebar';
import { logout } from '@/features/auth/logout';
import { toUrl } from '@/lib/href';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const { setOpenMobile } = useSidebar();
    const cleanup = () => setOpenMobile(false);

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem
                    render={
                        <a
                            className="block w-full cursor-pointer"
                            href={toUrl(edit())}
                            onClick={cleanup}
                        />
                    }
                >
                    <Settings className="mr-2" />
                    Settings
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                render={
                    <button
                        className="block w-full cursor-pointer"
                        onClick={() => {
                            cleanup();
                            void logout();
                        }}
                        data-test="logout-button"
                    />
                }
            >
                <LogOut className="mr-2" />
                Log out
            </DropdownMenuItem>
        </>
    );
}
