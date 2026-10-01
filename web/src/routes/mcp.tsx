import { createFileRoute } from '@tanstack/react-router';

import McpLanding from '@/pages/mcp';

// Public landing for browsers that hit the MCP endpoint — GET /mcp redirects here.
export const Route = createFileRoute('/mcp')({
    component: McpLanding,
});
