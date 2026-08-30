import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('public mobile shell keeps the hero and locale selector compact', () => {
    const home = readSource('resources/js/pages/Home.vue');
    const localeSwitcher = readSource(
        'resources/js/components/LocaleSwitcher.vue',
    );

    assert.match(home, /mt-4 min-h-44/);
    assert.match(home, /text-\[1\.75rem\]/);
    assert.match(localeSwitcher, /sr-only sm:not-sr-only/);
    assert.match(localeSwitcher, /sm:rounded-full/);
});

test('public mobile navigation owns the account action', () => {
    const header = readSource('resources/js/components/PublicHeader.vue');

    assert.match(header, /data-test="mobile-account-section"/);
    assert.match(header, /md:flex/);
    assert.match(header, /redirect: page\.url/);
});

test('portal nearby results stay inside the iOS viewport', () => {
    const portal = readSource('resources/js/pages/Portal/Index.vue');
    const styles = readSource('resources/css/app.css');

    assert.match(portal, /data-test="nearby-communities-container"/);
    assert.match(portal, /mt-6 grid min-w-0 gap-4/);
    assert.match(portal, /min-w-0 flex-1 truncate/);
    assert.doesNotMatch(
        portal,
        /(?:<\/div>\s*){3}<div\s+v-if="nearbyCommunities\.length"/,
    );
    assert.match(styles, /overscroll-behavior-x: none/);
    assert.match(styles, /overflow-x: clip/);
    assert.match(styles, /#app \{/);
});

test('shared form controls cannot expand the mobile viewport', () => {
    const styles = readSource('resources/css/app.css');
    const input = readSource('resources/js/components/ui/input/Input.vue');
    const select = readSource(
        'resources/js/components/ui/select/SelectTrigger.vue',
    );
    const phone = readSource('resources/js/components/PhoneInput.vue');
    const money = readSource('resources/js/components/MoneyInput.vue');
    const password = readSource('resources/js/components/PasswordInput.vue');
    const scrollDialog = readSource(
        'resources/js/components/ui/dialog/DialogScrollContent.vue',
    );

    assert.match(styles, /:where\(input, select, textarea\)/);
    assert.match(styles, /max-width: 100%/);
    assert.match(input, /w-full min-w-0 max-w-full/);
    assert.match(select, /w-full min-w-0 max-w-full/);
    assert.match(phone, /w-full min-w-0 max-w-full/);
    assert.match(money, /w-full min-w-0 max-w-full/);
    assert.match(password, /w-full min-w-0 max-w-full/);
    assert.match(scrollDialog, /max-w-\[calc\(100%-2rem\)\]/);
});
