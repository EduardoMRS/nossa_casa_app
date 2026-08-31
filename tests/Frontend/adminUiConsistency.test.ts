import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('event registration column picker is not clipped by its table card', () => {
    const source = readSource(
        'resources/js/pages/Admin/EventRegistrations.vue',
    );

    assert.match(source, /class="relative z-30"/);
    assert.match(source, /absolute left-0 z-50/);
    assert.match(source, /bg-popover/);
    assert.doesNotMatch(
        source,
        /<section class="overflow-hidden rounded-2xl border bg-white/,
    );
});

test('shared category manager uses semantic theme colors', () => {
    const source = readSource(
        'resources/js/components/CategoryManagerModal.vue',
    );

    assert.match(source, /border-border bg-card/);
    assert.match(source, /bg-muted\/70/);
    assert.match(source, /text-card-foreground/);
    assert.match(source, /text-destructive/);
    assert.doesNotMatch(source, /bg-slate-50/);
});

test('post editor relies on inline content embeds instead of a form selector', () => {
    const source = readSource('resources/js/pages/Posts/Form.vue');

    assert.match(source, /<MarkdownWysiwyg v-model="form\.content"/);
    assert.doesNotMatch(source, /form\.form_id/);
    assert.doesNotMatch(source, /props\.forms/);
});

test('custom embed control has a centered icon', () => {
    const source = readSource('resources/js/components/MarkdownWysiwyg.vue');

    assert.match(source, /embedButton\.innerHTML/);
    assert.match(source, /display: inline-flex !important/);
    assert.match(source, /align-items: center/);
    assert.match(source, /justify-content: center/);
});

test('form builder preview renders interactive field controls', () => {
    const source = readSource('resources/js/pages/Admin/Form.vue');
    const preview = source.slice(source.indexOf('<aside'));

    assert.match(source, /const previewValues = ref/);
    assert.match(preview, /v-model="previewValues\[field\.id\]"/);
    assert.match(preview, /field\.type === 'radio'/);
    assert.match(preview, /field\.type === 'checkbox'/);
    assert.match(preview, /@update:model-value=/);
    assert.doesNotMatch(preview, /\sdisabled(?:\s|>)/);
});

test('dark theme uses layered slate-indigo surfaces instead of near black', () => {
    const source = readSource('resources/css/app.css');
    const dark = source.slice(source.indexOf('.dark {'));

    assert.match(dark, /--background: hsl\(229 24% 10%\)/);
    assert.match(dark, /--card: hsl\(229 21% 13%\)/);
    assert.match(dark, /--primary: hsl\(246 75% 68%\)/);
    assert.match(dark, /\.dark select option,/);
    assert.match(dark, /background-color: var\(--popover\) !important/);
    assert.match(dark, /color: var\(--popover-foreground\) !important/);
    assert.doesNotMatch(dark, /--background: hsl\(0 0% 3\.9%\)/);
});

test('logs and metrics refresh even when realtime is unavailable', () => {
    const source = readSource('resources/js/pages/Admin/LogsMetrics.vue');

    assert.match(source, /watch\(\s*\(\) => props\.logs/);
    assert.match(source, /window\.setInterval/);
    assert.match(source, /only: \['stats', 'queue', 'logs', 'liveStreams'\]/);
    assert.match(source, /window\.clearInterval/);
});
