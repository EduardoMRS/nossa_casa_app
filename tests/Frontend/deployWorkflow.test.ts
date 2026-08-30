import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const workflow = readSource('.github/workflows/deploy-web.yml');

test('web deployment waits for the Arcane project update before deploying', () => {
    const updateStep = workflow.indexOf('Update NossaCasaApp in Arcane');
    const waitStep = workflow.indexOf('Wait for Arcane project update');
    const deployStep = workflow.indexOf('Deploy NossaCasaApp in Arcane');

    assert.ok(updateStep >= 0);
    assert.ok(updateStep < waitStep);
    assert.ok(waitStep < deployStep);
    assert.match(workflow, /sleep 45/);
    assert.match(workflow, /--target frontend-builder/);
    assert.ok(
        workflow.indexOf('Build production frontend image stage') < updateStep,
    );
    assert.equal(
        workflow.match(/jq -e '.success == true and .data.status == "accepted"'/g)
            ?.length,
        2,
    );
});

test('production image packages Vite assets outside the source mount', () => {
    const dockerfile = readSource('docker/Dockerfile');
    const entrypoint = readSource('docker/entrypoint.sh');

    assert.match(dockerfile, /FROM base AS frontend-builder/);
    assert.match(dockerfile, /npm run test:frontend/);
    assert.match(dockerfile, /npm run build/);
    assert.match(dockerfile, /test -f public\/build\/manifest\.json/);
    assert.match(
        dockerfile,
        /\/build\/public\/build \/opt\/nossa-casa\/public-build/,
    );
    assert.match(entrypoint, /use_prebuilt_frontend=true/);
    assert.match(entrypoint, /cp -R "\$prebuilt_frontend_dir\/\." public\/build\//);
});
