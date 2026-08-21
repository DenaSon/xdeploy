import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readProjectFile('resources/css/buy.css');
const app = readProjectFile('resources/js/app.js');
const page = readProjectFile(
    'resources/views/livewire/servers/buy-page.blade.php',
);

test('the Buy responsive stylesheet is loaded by the main Vite entry', () => {
    assert.match(
        app,
        /import '\.\.\/css\/buy\.css';/,
    );
});

test('Buy presentation is centralized outside the Blade wrapper', () => {
    assert.doesNotMatch(page, /<style>/);

    assert.match(
        css,
        /\.cloud-purchase-page--fixed-disk/,
    );
});

test('mobile and desktop purchase summaries are mutually exclusive', () => {
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
        /@media \(max-width: 1279px\)[\s\S]*?> div\.fixed\.inset-x-0\.bottom-0 \{[\s\S]*?display:\s*block;/,
    );

    assert.match(
        css,
        /@media \(min-width: 1280px\)[\s\S]*?> div\.grid > aside,[\s\S]*?display:\s*block;/,
    );

    assert.match(
        css,
        /@media \(min-width: 1280px\)[\s\S]*?> div\.fixed\.inset-x-0\.bottom-0 \{[\s\S]*?display:\s*none;/,
    );
});

test('desktop Buy grids keep the summary column aligned', () => {
    assert.match(
        css,
        /@media \(min-width: 1280px\)[\s\S]*?grid-template-columns:\s*minmax\(0, 1fr\) 320px;/,
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
