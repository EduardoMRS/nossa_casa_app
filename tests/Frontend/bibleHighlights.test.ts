import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const bible = readFileSync(
    new URL('../../resources/js/pages/Library/Bible.vue', import.meta.url),
    'utf8',
);

test('Bible verses can be highlighted with an offline-safe local palette', () => {
    assert.match(bible, /type BibleHighlightColor/);
    assert.match(bible, /bibleHighlightsKey/);
    assert.match(bible, /localStorage\.getItem\(bibleHighlightsKey\)/);
    assert.match(bible, /localStorage\.setItem\(/);
    assert.match(bible, /const highlightPalette/);
    assert.match(bible, /setVerseHighlight/);
    assert.match(bible, /removeVerseHighlight/);
    assert.match(bible, /verseHighlightStyle\(item\.verse\)/);
});

test('highlight toolbar is accessible from each rendered verse', () => {
    assert.match(bible, /data-test="bible-highlight-toolbar"/);
    assert.match(bible, /role="button"/);
    assert.match(bible, /tabindex="0"/);
    assert.match(bible, /@keydown\.enter\.prevent/);
    assert.match(bible, /@keydown\.space\.prevent/);
    assert.match(bible, /v-for="paletteItem in highlightPalette"/);
    assert.match(bible, /v-if="selectedVerseHighlight"/);
});
