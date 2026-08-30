import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const workflow = readFileSync(
    new URL('../../.github/workflows/deploy-web.yml', import.meta.url),
    'utf8',
);

test('web deployment waits for the Arcane project update before deploying', () => {
    const updateStep = workflow.indexOf('Update NossaCasaApp in Arcane');
    const waitStep = workflow.indexOf('Wait for Arcane project update');
    const deployStep = workflow.indexOf('Deploy NossaCasaApp in Arcane');

    assert.ok(updateStep >= 0);
    assert.ok(updateStep < waitStep);
    assert.ok(waitStep < deployStep);
    assert.match(workflow, /sleep 45/);
    assert.equal(
        workflow.match(/jq -e '.success == true and .data.status == "accepted"'/g)
            ?.length,
        2,
    );
});
