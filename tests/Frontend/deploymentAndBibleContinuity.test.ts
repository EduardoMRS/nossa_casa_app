import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('classroom portal migration resumes after a partial MySQL execution', () => {
    const migration = readSource(
        'database/migrations/2026_09_01_000001_create_classroom_portal_tables.php',
    );

    for (const table of [
        'classroom_activities',
        'classroom_activity_submissions',
        'classroom_materials',
        'classroom_discussions',
        'classroom_discussion_replies',
    ]) {
        assert.match(
            migration,
            new RegExp(`Schema::hasTable\\('${table}'\\)`),
        );
    }
});

test('production startup retries only database readiness failures', () => {
    const entrypoint = readSource('docker/entrypoint.sh');

    assert.match(entrypoint, /until php -r "\$database_ready_command"/);
    assert.match(entrypoint, /connection\(\)->getPdo\(\)/);
    assert.doesNotMatch(entrypoint, /php artisan db:show/);
    assert.match(entrypoint, /php artisan migrate --force --no-interaction/);
    assert.doesNotMatch(entrypoint, /until php artisan migrate/);
});

test('database stays internal and gates dependent containers by health', () => {
    const compose = readSource('docker-compose.yml');
    const databaseService =
        compose.match(/\n  db:\n([\s\S]*?)\n  minio:/)?.[1] ?? '';

    assert.match(databaseService, /mysqladmin ping/);
    assert.doesNotMatch(databaseService, /\n\s+ports:/);

    for (const service of ['app', 'queue-worker', 'media-worker', 'scheduler']) {
        const serviceDefinition =
            compose.match(
                new RegExp(
                    `\\n  ${service}:\\n([\\s\\S]*?)(?=\\n  [a-z][a-z0-9-]*:\\n|\\nvolumes:)`,
                ),
            )?.[1] ?? '';

        assert.match(
            serviceDefinition,
            /depends_on:[\s\S]*?db:\n\s+condition: service_healthy/,
            `${service} must wait for a healthy database`,
        );
    }
});

test('Bible version changes preserve the canonical book and chapter', () => {
    const reader = readSource('resources/js/pages/Library/Bible.vue');

    assert.match(reader, /const currentBookIndex = computed/);
    assert.match(reader, /preferredBookIndex: number = currentBookIndex\.value/);
    assert.match(reader, /payload\.books\[preferredBookIndex\]/);
    assert.match(
        reader,
        /loadBooks\(book\.value, chapter\.value\)/,
    );
});
