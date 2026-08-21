import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readProjectFile('resources/css/buy-critical.css');
const app = readProjectFile('resources/js/app.js');

test('critical Buy responsive CSS is loaded by the main Vite entry', () => {
    assert.match(app, /import '\.\.\/css\/buy-critical\.css';/);
});

test('critical Buy visibility does not depend on the workspace wrapper', () => {
    assert.match(css, /\[data-buy-mobile-action\]\s*\{[\s\S]*?display:\s*none\s*!important;/);
    assert.match(css, /\[data-buy-mobile-action\]\s*\{[\s\S]*?visibility:\s*hidden\s*!important;/);
    assert.match(css, /\[data-buy-mobile-action\]\s*\{[\s\S]*?pointer-events:\s*none\s*!important;/);
    assert.match(css, /@media \(max-width: 767px\)[\s\S]*?\[data-buy-mobile-action\]\s*\{[\s\S]*?display:\s*block\s*!important;/);
    assert.match(css, /@media \(max-width: 767px\)[\s\S]*?visibility:\s*visible\s*!important;/);
    assert.match(css, /@media \(max-width: 767px\)[\s\S]*?pointer-events:\s*auto\s*!important;/);
    assert.doesNotMatch(css, /\.cloud-purchase-page\s+\[data-buy-mobile-action\]/);
});

function readProjectFile(path) {
    return readFileSync(
        new URL(`../../${path}`, import.meta.url),
        'utf8',
    );
}
