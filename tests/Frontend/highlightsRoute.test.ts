import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const highlights = readFileSync(
    new URL('../../resources/js/pages/Admin/Highlights.vue', import.meta.url),
    'utf8',
);
const apiRoutes = readFileSync(
    new URL('../../routes/api.php', import.meta.url),
    'utf8',
);

test('saving highlights uses the registered Laravel route', () => {
    assert.match(apiRoutes, /Route::put\('church\/\{church\}\/highlights'/);
    assert.match(
        highlights,
        /import \{ updateChurchHighlights \} from '@\/actions\/App\/Http\/Controllers\/HighlightController';/,
    );
    assert.match(
        highlights,
        /axios\.put\(updateChurchHighlights\.url\(props\.churchId\),/,
    );
});