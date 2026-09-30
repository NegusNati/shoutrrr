import { webPost } from '@/lib/api';
import { appUrl, toUrl } from '@/lib/href';
import { queryClient } from '@/lib/query-client';
import { logout as logoutRoute } from '@/routes';

/** Ends the Fortify session, drops the cached bootstrap, and returns home. */
export async function logout() {
    try {
        await webPost(toUrl(logoutRoute()));
    } finally {
        queryClient.clear();
        window.location.href = appUrl('/login');
    }
}
