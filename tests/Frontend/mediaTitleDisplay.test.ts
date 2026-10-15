import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const adminMedia = readSource('resources/js/pages/Admin/MediaModeration.vue');
const gallery = readSource('resources/js/pages/Gallery/Index.vue');
const home = readSource('resources/js/pages/Home.vue');

test('administration falls back to the original media filename', () => {
    assert.match(adminMedia, /item\.title \?\? item\.original_filename \?\? item\.file_path/);
});

test('public gallery and recording views hide empty titles', () => {
    assert.match(gallery, /v-if="item\.title"[\s\S]*?item\.title/);
    assert.match(gallery, /v-if="selectedMedia\.title"[\s\S]*?selectedMedia\.title/);
    assert.equal([...home.matchAll(/v-if="recording\.title"/g)].length, 2);
    assert.match(home, /v-if="recording\.title"[\s\S]*?recording\.title/);
    assert.match(home, /v-if="selectedRecording\.title"[\s\S]*?selectedRecording\.title/);
});