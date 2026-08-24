import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    confirmationCanSubmit,
    modalSizeClass,
} from '../../resources/js/lib/modal.ts';

const frontendRoot = fileURLToPath(new URL('../../resources/js', import.meta.url));

const sourceFiles = (directory: string): string[] =>
    readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = `${directory}/${entry.name}`;

        if (entry.isDirectory()) {
            return sourceFiles(path);
        }

        return /\.(ts|vue)$/.test(entry.name) ? [path] : [];
    });

test('modal sizes map to bounded responsive widths', () => {
    assert.equal(modalSizeClass('sm'), 'sm:max-w-sm');
    assert.equal(modalSizeClass('2xl'), 'sm:max-w-6xl');
    assert.equal(modalSizeClass('full'), 'sm:max-w-[calc(100%-2rem)]');
});

test('required confirmation input must contain non-whitespace text', () => {
    assert.equal(confirmationCanSubmit(false, true, '   '), false);
    assert.equal(confirmationCanSubmit(false, true, 'reason'), true);
    assert.equal(confirmationCanSubmit(false, false, ''), true);
    assert.equal(confirmationCanSubmit(true, false, ''), false);
});

test('application screens do not use native browser confirmation dialogs', () => {
    const offenders = sourceFiles(frontendRoot).filter((file) =>
        /window\.(confirm|prompt)\s*\(/.test(readFileSync(file, 'utf8')),
    );

    assert.deepEqual(offenders, []);
});

test('application screens do not implement their own modal overlays', () => {
    const offenders = sourceFiles(frontendRoot).filter((file) => {
        if (file.includes('/components/ui/')) {
            return false;
        }

        const source = readFileSync(file, 'utf8');

        return (
            source.includes('<Teleport') ||
            source.includes('role="dialog"') ||
            source.includes('aria-modal="true"') ||
            source.includes('fixed inset-0')
        );
    });

    assert.deepEqual(offenders, []);
});
