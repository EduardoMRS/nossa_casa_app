import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const gallery = readFileSync(
    new URL('../../resources/js/pages/Gallery/Index.vue', import.meta.url),
    'utf8',
);
const home = readFileSync(
    new URL('../../resources/js/pages/Home.vue', import.meta.url),
    'utf8',
);

test('transmissions tab passes its filter through the Wayfinder route helper', () => {
    assert.match(
        gallery,
        /galleryIndex\(undefined,\s*\{\s*query:\s*\{\s*view:\s*'transmissions'/,
    );
    assert.doesNotMatch(gallery, /`\$\{galleryIndex\(\)\}\?view=transmissions`/);
    assert.match(
        home,
        /galleryIndex\(undefined,\s*\{\s*query:\s*\{\s*view:\s*'transmissions'/,
    );
});