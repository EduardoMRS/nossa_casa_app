import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import test from 'node:test';

type Catalog = Record<string, string | Catalog>;

const projectRoot = process.cwd();

const flattenCatalog = (
    catalog: Catalog,
    prefix = '',
    entries: Record<string, string> = {},
): Record<string, string> => {
    for (const [key, value] of Object.entries(catalog)) {
        const path = prefix === '' ? key : `${prefix}.${key}`;

        if (typeof value === 'string') {
            entries[path] = value;
        } else {
            flattenCatalog(value, path, entries);
        }
    }

    return entries;
};

const placeholders = (value: string): string[] =>
    [...value.matchAll(/\{([^}]+)\}/g)]
        .map((match) => match[1].trim())
        .sort();

const vueFiles = (directory: string): string[] =>
    readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            return vueFiles(path);
        }

        return entry.isFile() && entry.name.endsWith('.vue') ? [path] : [];
    });

test('frontend translation catalogs have matching keys and placeholders', () => {
    const en = flattenCatalog(
        JSON.parse(
            readFileSync(
                join(projectRoot, 'resources/js/locales/en.json'),
                'utf8',
            ),
        ) as Catalog,
    );
    const pt = flattenCatalog(
        JSON.parse(
            readFileSync(
                join(projectRoot, 'resources/js/locales/pt.json'),
                'utf8',
            ),
        ) as Catalog,
    );

    assert.deepEqual(Object.keys(pt).sort(), Object.keys(en).sort());

    for (const key of Object.keys(en)) {
        assert.deepEqual(placeholders(pt[key]), placeholders(en[key]), key);
    }
});

test('known user-facing literals are not embedded in Vue components', () => {
    const source = vueFiles(join(projectRoot, 'resources/js'))
        .map((path) => readFileSync(path, 'utf8'))
        .join('\n');

    for (const literal of [
        'Permitir comentários',
        'Permitir reações',
        "label ?? 'Platform'",
        'tentativa(s)',
    ]) {
        assert.equal(source.includes(literal), false, literal);
    }

    assert.doesNotMatch(source, />\s*Close\s*</);
    assert.doesNotMatch(source, /}}\s+km\b/);
});

test('portal onboarding exposes the complete light-theme registration flow', () => {
    const source = readFileSync(
        join(projectRoot, 'resources/js/pages/Portal/Index.vue'),
        'utf8',
    );

    for (const binding of [
        'churchForm.community_id',
        'churchForm.parent_church_id',
        'churchForm.address',
        'churchForm.locale',
        'communityForm.address',
        'communityForm.default_locale',
    ]) {
        assert.equal(source.includes(`v-model="${binding}"`), true, binding);
    }

    assert.match(source, /content-class="[^"]*bg-white[^"]*text-slate-950/);
    assert.match(source, /content-class="[^"]*max-h-\\[90dvh\\][^"]*overflow-hidden/);
    assert.equal((source.match(/overflow-y-auto/g) ?? []).length >= 2, true);
    assert.match(source, /<PhoneInput[\\s\\S]*?class="[^"]*!bg-white/);
    assert.match(source, /v-if="onboardingMode === 'new_community'"/);
    assert.match(source, /<form\\s+v-else/);
});
