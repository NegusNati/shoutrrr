import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

import { remainingXBudgetLabel } from '../subscription';

const source = () =>
    readFileSync(
        resolve(
            process.cwd(),
            'web/src/pages/settings/workspace/subscription.tsx',
        ),
        'utf8',
    );

describe('subscription checkout', () => {
    it('lives in the workspace settings navigation and SPA routes', () => {
        const workspaceSettingsNav = readFileSync(
            resolve(
                process.cwd(),
                'web/src/lib/navigation/workspace-settings-nav.ts',
            ),
            'utf8',
        );
        const spaRoute = readFileSync(
            resolve(
                process.cwd(),
                'web/src/routes/_app/settings.workspace.subscription.tsx',
            ),
            'utf8',
        );

        expect(workspaceSettingsNav).toContain("title: 'Subscription'");
        expect(workspaceSettingsNav).toContain('BillingController.index()');
        expect(spaRoute).toContain('/_app/settings/workspace/subscription');
    });

    it('redirects to Stripe through the billing API instead of Inertia forms', () => {
        const subscriptionPage = source();

        expect(subscriptionPage).not.toContain('@inertiajs/react');
        expect(subscriptionPage).toContain('billingCheckout');
        expect(subscriptionPage).toContain('billingPortal');
        expect(subscriptionPage).toContain('window.location.href = url');
    });

    it('renders current month X budget usage and unlimited non-X publishing copy', () => {
        const subscriptionPage = source();

        expect(subscriptionPage).toContain('X budget this month');
        expect(subscriptionPage).toContain('monthlyXBudgetUsedMicrousd');
        expect(subscriptionPage).toContain('monthlyXBudgetRemainingMicrousd');
        expect(subscriptionPage).toContain('unlimited publishes to');
        expect(subscriptionPage).toContain('every other platform');
        expect(subscriptionPage).toContain('X/Twitter');
        expect(subscriptionPage).toContain('usage budget');
    });

    it('labels remaining X budget as dollars', () => {
        expect(remainingXBudgetLabel(null)).toBe('Unlimited remaining');
        expect(remainingXBudgetLabel(4_820_000)).toBe('$4.82 remaining');
        expect(remainingXBudgetLabel(15_000)).toBe('$0.015 remaining');
    });
});
