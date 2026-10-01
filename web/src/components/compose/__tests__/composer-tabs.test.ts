import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

describe('composer platform tabs', () => {
    it('uses the section count chip for every platform', () => {
        const source = readFileSync(
            resolve(
                process.cwd(),
                'web/src/components/compose/composer.tsx',
            ),
            'utf8',
        );

        expect(source).toContain(
            'return String(target?.sections.length ?? 1);',
        );
        expect(source).not.toContain("account.platform === 'linkedin'");
    });

    it('shows an account-attention icon that opens accounts', () => {
        const source = readFileSync(
            resolve(
                process.cwd(),
                'web/src/components/compose/platform-tabs.tsx',
            ),
            'utf8',
        );

        expect(source).toContain(
            "const needsAttention = account.status === 'needs_attention';",
        );
        expect(source).toContain(
            '{needsAttention && <NeedsAttentionIcon account={account} />}',
        );
        expect(source).toContain("navigate({ to: '/accounts' })");
        expect(source).toContain('Reconnect {account.handle} before posting.');
    });
});
