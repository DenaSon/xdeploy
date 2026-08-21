import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const app = readProjectFile('resources/js/app.js');
const theme = readProjectFile('resources/css/coreflare-theme-contract.css');

test('Coreflare theme contract is loaded by the main Vite entry', () => {
    assert.match(
        app,
        /import '\.\.\/css\/coreflare-theme-contract\.css';/,
    );
});

test('Coreflare light theme keeps the canonical evergreen primary color', () => {
    assert.match(theme, /\[data-theme="light"\]/);
    assert.match(theme, /--color-primary:\s*#0d7a57;/i);
    assert.match(theme, /--color-primary-content:\s*#f6fff9;/i);
});

test('Coreflare dark theme keeps the canonical dark primary color', () => {
    assert.match(theme, /\[data-theme="dark"\]/);
    assert.match(theme, /--color-primary:\s*#57d79e;/i);
    assert.match(theme, /--color-primary-content:\s*#08281b;/i);
});

function readProjectFile(path) {
    return readFileSync(
        new URL(`../../${path}`, import.meta.url),
        'utf8',
    );
}
