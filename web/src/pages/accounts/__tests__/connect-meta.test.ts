import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

import { buildMetaSelection } from '@/pages/accounts/connect-meta';

const source = readFileSync(
    resolve(process.cwd(), 'web/src/pages/accounts/connect-meta.tsx'),
    'utf8',
);

describe('buildMetaSelection', () => {
    it('flattens checked (assetKey, platform) pairs into the store payload shape', () => {
        expect(
            buildMetaSelection({
                page_1: { facebook: true, instagram: false },
                page_2: { facebook: false, instagram: true },
            }),
        ).toEqual([
            { assetKey: 'page_1', platform: 'facebook' },
            { assetKey: 'page_2', platform: 'instagram' },
        ]);
    });

    it('omits assets with no checked platforms', () => {
        expect(
            buildMetaSelection({
                page_1: { facebook: false, instagram: false },
            }),
        ).toEqual([]);
    });

    it('returns an empty array when nothing was ever toggled', () => {
        expect(buildMetaSelection({})).toEqual([]);
    });
});

describe('connect-meta page', () => {
    it('renders each asset page name and, when offered, an Instagram sub-row', () => {
        expect(source).toContain('asset.pageName');
        expect(source).toContain('asset.igUsername');
        expect(source).toContain("asset.platforms.includes('instagram')");
    });

    it('shows a friendly empty state when there are no assets', () => {
        expect(source).toContain('No Pages found');
    });

    it('renders a checkbox per (asset, platform) pair', () => {
        expect(source).toContain('Checkbox');
        expect(source).toContain("toggle(asset.key, 'facebook')");
        expect(source).toContain("toggle(asset.key, 'instagram')");
    });

    it('posts the built selection through the Meta connect endpoint', () => {
        expect(source).toContain('storeMetaSelection(selection)');
        expect(source).toContain('metaPickerQuery');
    });

    it('disables submit when nothing is selected or a request is in flight', () => {
        expect(source).toContain(
            'disabled={selection.length === 0 || processing}',
        );
    });
});
