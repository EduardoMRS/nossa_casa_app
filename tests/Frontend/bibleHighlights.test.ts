import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const bible = readFileSync(
    new URL('../../resources/js/pages/Library/Bible.vue', import.meta.url),
    'utf8',
);

test('Bible highlighting is triggered only by a native text selection', () => {
    assert.match(bible, /window\.getSelection\(\)/);
    assert.match(bible, /selection\.getRangeAt\(0\)/);
    assert.match(bible, /data-test="bible-selectable-text"/);
    assert.match(bible, /data-bible-verse-text/);
    assert.match(bible, /@mouseup="queueTextSelectionCapture"/);
    assert.match(bible, /@touchend="queueTextSelectionCapture"/);
    assert.match(bible, /selectionchange/);
    assert.doesNotMatch(bible, /@click="selectVerse/);
    assert.doesNotMatch(bible, /role="button"\s+tabindex="0"/);
});

test('selected text ranges are persisted and rendered offline', () => {
    assert.match(bible, /type BibleTextHighlight/);
    assert.match(bible, /bible-text-highlights:v2/);
    assert.match(bible, /start: fragment\.start/);
    assert.match(bible, /end: fragment\.end/);
    assert.match(bible, /verseTextSegments\(item\)/);
    assert.match(bible, /v-if="segment\.color"/);
    assert.match(bible, /setTextHighlight/);
    assert.match(bible, /removeTextHighlight/);
    assert.match(bible, /localStorage\.setItem\(/);
});

test('one selection may create fragments across multiple verses', () => {
    assert.match(bible, /querySelectorAll<HTMLElement>\('\[data-bible-verse-text\]'\)/);
    assert.match(bible, /range\.intersectsNode\(element\)/);
    assert.match(bible, /fragments\.push/);
    assert.match(bible, /selection\.fragments\.forEach/);
});
