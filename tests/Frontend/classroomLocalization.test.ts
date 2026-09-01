import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const localeKeys = (
    value: unknown,
    prefix = '',
    keys = new Set<string>(),
): Set<string> => {
    if (Array.isArray(value)) {
        keys.add(prefix);
        return keys;
    }

    if (value && typeof value === 'object') {
        for (const [key, child] of Object.entries(value)) {
            localeKeys(child, prefix ? `${prefix}.${key}` : key, keys);
        }
        return keys;
    }

    keys.add(prefix);
    return keys;
};

test('frontend locales expose the same translation keys and collection shapes', () => {
    const english = JSON.parse(
        readSource('resources/js/locales/en.json'),
    ) as Record<string, unknown>;
    const portuguese = JSON.parse(
        readSource('resources/js/locales/pt.json'),
    ) as Record<string, unknown>;

    assert.deepEqual(
        [...localeKeys(english)].sort(),
        [...localeKeys(portuguese)].sort(),
    );
});

test('classroom interfaces use the shared translation layer', () => {
    const sources = [
        'resources/js/pages/Classrooms/Index.vue',
        'resources/js/pages/Classrooms/Portal.vue',
        'resources/js/pages/Admin/ClassroomContent.vue',
        'resources/js/components/forms/DynamicFormRenderer.vue',
    ].map(readSource);

    for (const source of sources) {
        assert.match(source, /useI18n/);
    }

    assert.doesNotMatch(
        sources.join('\n'),
        />\s*(?:Minhas salas|Gerenciar|Nenhuma atividade|Nenhum material|Iniciar discussão|Salvar portal|Nova atividade|Adicionar material|Selecione)\s*</,
    );
});

test('classroom API responses use locale files instead of sentence keys', () => {
    const contentController = readSource(
        'app/Http/Controllers/Admin/ClassroomContentController.php',
    );
    const submissionController = readSource(
        'app/Http/Controllers/ClassroomActivitySubmissionController.php',
    );
    const english = readSource('lang/en/classroom.php');
    const portuguese = readSource('lang/pt/classroom.php');

    assert.match(contentController, /classroom\.notifications\.portal_updated/);
    assert.match(submissionController, /classroom\.validation\.activity_unavailable/);
    assert.doesNotMatch(
        `${contentController}\n${submissionController}`,
        /__\('[A-Z][^']+\.'/,
    );
    assert.match(english, /'attempt_limit'/);
    assert.match(portuguese, /'attempt_limit'/);
});
