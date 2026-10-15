import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const styles = readSource('resources/css/app.css');
const publicHeader = readSource('resources/js/components/PublicHeader.vue');
const appLogo = readSource('resources/js/components/AppLogo.vue');

test('scrollbars are thin and church branding truncates in navigation', () => {
    assert.match(styles, /scrollbar-width:\s*thin/);
    assert.match(styles, /\*::-webkit-scrollbar\s*\{\s*width:\s*8px/);
    assert.match(publicHeader, /max-w-\[min\(42vw,18rem\)\]/);
    assert.match(publicHeader, /truncate text-base leading-tight/);
    assert.match(appLogo, /truncate leading-tight font-semibold/);
});