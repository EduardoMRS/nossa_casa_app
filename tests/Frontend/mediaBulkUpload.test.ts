import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const mediaModeration = readFileSync(
    new URL(
        '../../resources/js/pages/Admin/MediaModeration.vue',
        import.meta.url,
    ),
    'utf8',
);

test('media moderation supports bulk file selection only for file sources', () => {
    assert.match(mediaModeration, /type="file"[\s\S]*?multiple/);
    assert.match(
        mediaModeration,
        /sourceType\.value === 'file' &&[\s\S]*selectedFiles\.value\.length > 1/,
    );
    assert.match(mediaModeration, /payload\.append\('file', file\)/);
    assert.match(mediaModeration, /payload\.append\('gallery'/);
    assert.match(mediaModeration, /payload\.append\('category_ids\[\]'/);
});

test('bulk upload hides per-item title, description, and status fields', () => {
    assert.match(
        mediaModeration,
        /v-if="!isBulkUpload"\s+class="block text-xs font-bold"[\s\S]*?admin\.media\.record_title/,
    );
    assert.match(
        mediaModeration,
        /v-if="!isBulkUpload"\s+class="block text-xs font-bold"[\s\S]*?admin\.media\.record_description/,
    );
    assert.match(
        mediaModeration,
        /v-if="!isBulkUpload"[\s\S]*?admin\.common\.status/,
    );
    assert.match(mediaModeration, /v-model="gallery" type="checkbox"/);
});
