import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readProjectFile('resources/css/buy.css');
const app = readProjectFile('resources/js/app.js');

test('the Buy responsive stylesheet is loaded by the main Vite entry', () => {
    assert.match(
        app,
        /import '\.\.\/css\/buy\.css';/,
    );
});

test('the bottom purchase bar is mobile-only', () => {
    assert.match(
        css,
        /> div\.grid > aside,[\s\S]*?display:\s*none;/,
    );

    assert.match(
        css,
        /> div\.fixed\.inset-x-0\.bottom-0 \{[\s\S]*?display:\s*none;/,
    );

    assert.match(
        css,
        /@media \(max-width: 767px\)[\s\S]*?> div\.fixed\.inset-x-0\.bottom-0 \{[\s\S]*?display:\s*block;/,
    );

    assert.match(
        css,
        /@media \(min-width: 768px\)[\s\S]*?> div\.grid > aside,[\s\S]*?display:\s*block;/,
    );

    assert.match(
        css,
        /@media \(min-width: 768px\)[\s\S]*?> div\.fixed\.inset-x-0\.bottom-0 \{[\s\S]*?display:\s*none;/,
    );
});

test('tablet and narrow desktop widths use the desktop summary layout', () => {
    assert.match(
        css,
        /@media \(min-width: 768px\)[\s\S]*?grid-template-columns:\s*minmax\(0, 1fr\) 320px;/,
    );

    assert.doesNotMatch(
        css,
        /@media \(max-width: 1279px\)[\s\S]*?> div\.fixed\.inset-x-0\.bottom-0 \{[\s\S]*?display:\s*block;/,
    );
});

test('the mobile purchase bar respects the bottom safe area', () => {
    assert.match(
        css,
        /env\(safe-area-inset-bottom, 0px\)/,
    );

    assert.match(
        css,
        /bottom:\s*calc\([\s\S]*?0\.75rem[\s\S]*?env\(safe-area-inset-bottom, 0px\)/,
    );
});

function readProjectFile(path) {
    return readFileSync(
        new URL(`../../${path}`, import.meta.url),
        'utf8',
    );
}
