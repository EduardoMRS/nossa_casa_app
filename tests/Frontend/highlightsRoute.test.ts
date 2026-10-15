import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const highlights = readFileSync(
    new URL('../../resources/js/pages/Admin/Highlights.vue', import.meta.url),
    'utf8',
);
const highlightRoute = readFileSync(
    new URL(
        '../../resources/js/actions/App/Http/Controllers/HighlightController.ts',
        import.meta.url,
    ),
    'utf8',
);

test('saving highlights uses the generated Laravel route', () => {
    assert.equal(
        highlightRoute.includes("url: '/api/church/{church}/highlights'"),
        true,
    );
    assert.match(
        highlights,
        /import \{ updateChurchHighlights \} from '@\/actions\/App\/Http\/Controllers\/HighlightController';/,
    );
    assert.match(
        highlights,
        /axios\.put\(updateChurchHighlights\.url\(props\.churchId\),/,
    );
});