import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('public template variants have distinct layout systems', () => {
    const styles = readSource('resources/css/app.css');

    assert.match(styles, /data-public-template='classic'/);
    assert.match(styles, /--public-section-shadow/);
    assert.match(styles, /data-public-template='editorial'/);
    assert.match(styles, /--public-content-width: 88rem/);
    assert.match(styles, /font-family: Georgia/);
    assert.match(styles, /data-public-template='minimal'/);
    assert.match(styles, /--public-content-width: 64rem/);
    assert.match(styles, /box-shadow: none !important/);
});

test('branding settings explain and preview every template variant', () => {
    const branding = readSource('resources/js/pages/Admin/Branding.vue');
    const preview = readSource(
        'resources/js/components/TemplateVariantPreview.vue',
    );
    const english = JSON.parse(readSource('resources/js/locales/en.json'));
    const portuguese = JSON.parse(readSource('resources/js/locales/pt.json'));

    for (const variant of ['classic', 'editorial', 'minimal']) {
        assert.ok(
            english.admin.branding.template_variant_descriptions[variant],
        );
        assert.ok(
            portuguese.admin.branding.template_variant_descriptions[variant],
        );
        assert.match(preview, new RegExp(`template-preview--${variant}`));
    }

    assert.match(branding, /template_variant_descriptions/);
    assert.match(branding, /sm:grid-cols-3/);
});
