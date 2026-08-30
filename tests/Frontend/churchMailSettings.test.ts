import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('church SMTP fields are disclosed only for an enabled custom server', () => {
    const branding = readSource('resources/js/pages/Admin/Branding.vue');

    assert.match(
        branding,
        /const useOwnMailServer = ref\(props\.mailSettings\.enabled\)/,
    );
    assert.match(branding, /v-model="useOwnMailServer"/);
    assert.match(branding, /v-if="useOwnMailServer"/);
    assert.match(branding, /data-test="custom-mail-server-options"/);
    assert.ok(
        branding.indexOf('v-if="useOwnMailServer"') <
            branding.indexOf('name="mail[host]"'),
    );
});
