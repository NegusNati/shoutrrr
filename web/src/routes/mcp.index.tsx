import { createFileRoute } from '@tanstack/react-router';

import McpLanding from '@/pages/mcp/landing';

export const Route = createFileRoute('/mcp/')({
    component: McpLanding,
});
