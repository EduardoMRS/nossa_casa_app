import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const bible = readFileSync(
    new URL('../../resources/js/pages/Library/Bible.vue', import.meta.url),
    'utf8',
);

test('offline Bible selector only exposes versions cached on the device', () => {
    assert.match(bible, /const selectableVersions = computed/);
    assert.match(bible, /isOnline\.value[\s\S]*readyOfflineVersions\.value\.includes/);
    assert.match(bible, /v-for="item in selectableVersions"/);
    assert.match(bible, /offline_no_versions/);
    assert.match(bible, /ensureSelectableVersion/);
    assert.match(bible, /addEventListener\('offline', handleOffline\)/);
});

test('offline download action respects each Bible version license', () => {
    assert.match(bible, /const selectedVersionCanBeDownloaded = computed/);
    assert.match(bible, /selectedVersion\.value\?\.offline_available/);
    assert.match(bible, /selectedVersion\.value\.offline_url/);
    assert.match(bible, /v-if="selectedVersionCanBeDownloaded"/);
    assert.match(bible, /downloadSelectedVersionForOffline/);
    assert.match(bible, /await downloadForOffline\(\[selected\]\)/);
});
