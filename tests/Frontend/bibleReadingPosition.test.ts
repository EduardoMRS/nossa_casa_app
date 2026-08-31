import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const bible = readFileSync(
    new URL('../../resources/js/pages/Library/Bible.vue', import.meta.url),
    'utf8',
);

test('Bible reader restores and persists the last valid reading position', () => {
    assert.match(bible, /bibleReadingPositionKey/);
    assert.match(bible, /localStorage\.getItem\(bibleReadingPositionKey\)/);
    assert.match(bible, /localStorage\.setItem\(/);
    assert.match(bible, /version: version\.value/);
    assert.match(bible, /book: book\.value/);
    assert.match(bible, /chapter: chapter\.value/);
    assert.match(bible, /restoreReadingPosition\(\);[\s\S]*isOnline\.value/);
    assert.match(bible, /verses\.value = payload\.verses;[\s\S]*persistReadingPosition\(\)/);
});

test('changing Bible version preserves the current book and chapter when available', () => {
    assert.match(bible, /preferredBook: string = book\.value/);
    assert.match(bible, /preferredChapter: number \| null = chapter\.value/);
    assert.match(bible, /item\.slug === preferredBook/);
    assert.match(bible, /payload\.chapters\.includes\(preferredChapter\)/);
    assert.match(bible, /loadBooks\(book\.value, chapter\.value\)/);
    assert.match(bible, /@change="changeVersion"/);
    assert.doesNotMatch(bible, /@change="loadBooks"/);
});
