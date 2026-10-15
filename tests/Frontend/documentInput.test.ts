import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const documentInput = readSource('resources/js/components/DocumentInput.vue');
const formBuilder = readSource('resources/js/pages/Admin/Form.vue');
const eventRegistration = readSource('resources/js/pages/Events/Register.vue');
const profileWorkspace = readSource('resources/js/pages/settings/Workspace.vue');
const classroomRenderer = readSource(
    'resources/js/components/forms/DynamicFormRenderer.vue',
);
const moneyInput = readSource('resources/js/components/MoneyInput.vue');

test('document input supports each document type with its mask and flag', () => {
    for (const type of ['cpf', 'ssn', 'nif', 'nino', 'cuit', 'dni', 'passport']) {
        assert.match(documentInput, new RegExp(`type: '${type}'`));
    }

    assert.match(documentInput, /v-mask-input="selectedDocument\.mask"/);
    assert.match(documentInput, /type: selectedType\.value,[\s\S]*number: number\.value/);
});

test('document fields are available in the builder and response forms', () => {
    assert.match(formBuilder, /\['document', t\('admin\.forms\.types\.document'\)\]/);
    assert.match(formBuilder, /field\.type === 'document'/);
    assert.match(eventRegistration, /field\.type === 'document'/);
    assert.match(classroomRenderer, /field\.type === 'document'/);
    assert.match(profileWorkspace, /v-model="profile\.document"/);
    assert.match(profileWorkspace, /router\.post\(\s*updateProfile\.url\(\)/);
    assert.match(profileWorkspace, /_method: 'patch'/);
});

test('currency options display their flag and symbol instead of the ISO code', () => {
    assert.match(moneyInput, /flag: '🇧🇷'/);
    assert.match(moneyInput, /flag: '🇺🇸'/);
    assert.match(moneyInput, /\{\{ item\.flag \}\} \{\{ item\.symbol \}\}/);
    assert.doesNotMatch(moneyInput, /\{\{ item\.symbol \}\} \{\{ item\.code \}\}/);
});