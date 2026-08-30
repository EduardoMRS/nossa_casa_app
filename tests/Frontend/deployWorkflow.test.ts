import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const workflow = readSource('.github/workflows/deploy-web.yml');

test('web deployment builds the frontend before updating Arcane', () => {
    const sourceStep = workflow.indexOf('Download repository source');
    const buildStep = workflow.indexOf('Build production frontend image stage');
    const updateStep = workflow.indexOf('Update NossaCasaApp in Arcane');
    const waitStep = workflow.indexOf('Wait for Arcane project update');
    const deployStep = workflow.indexOf('Deploy NossaCasaApp in Arcane');

    assert.ok(sourceStep >= 0);
    assert.ok(sourceStep < buildStep);
    assert.ok(buildStep < updateStep);
    assert.ok(updateStep < waitStep);
    assert.ok(waitStep < deployStep);
    assert.match(workflow, /sleep 45/);
    assert.match(workflow, /--target frontend-builder/);
    assert.match(workflow, /tarball\/\$\{GITHUB_SHA\}/);
    assert.match(workflow, /GITHUB_TOKEN: \$\{\{ github\.token \}\}/);
    assert.doesNotMatch(workflow, /uses:\s+actions\/checkout/);
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
