import assert from 'node:assert/strict';
import {
    existsSync,
    readFileSync,
} from 'node:fs';
import test from 'node:test';

const css = readProjectFile('resources/css/buy.css');
const buy = readProjectFile('resources/views/livewire/servers/buy.blade.php');
const buyPage = readProjectFile('resources/views/livewire/servers/buy-page.blade.php');
const app = readProjectFile('resources/js/app.js');
const guardPath = new URL(
    '../../resources/js/buy-responsive-guard.js',
    import.meta.url,
);

test('the bottom purchase bar is mobile-only at the rendered markup boundary', () => {
    assert.match(buy, /data-buy-mobile-action/);
    assert.match(buy, /data-buy-desktop-summary/);
    assert.match(
        buy,
        /@media \(min-width: 768px\)[\s\S]*?\[data-buy-mobile-action\][\s\S]*?display:\s*none\s*!important;/,
    );
    assert.match(
        buy,
        /@media \(min-width: 768px\)[\s\S]*?\[data-buy-desktop-summary\][\s\S]*?display:\s*block\s*!important;/,
    );
});

test('Buy markup uses one tablet-and-desktop breakpoint contract', () => {
    assert.match(buy, /class="pb-24 md:pb-0"/);
    assert.match(buy, /class="hidden md:block"/);
    assert.match(buy, /md:hidden/);
    assert.match(buy, /md:grid-cols-\[minmax\(0,1fr\)_320px\]/);
    assert.doesNotMatch(buy, /xl:(?:hidden|block|pb-0|grid-cols-)/);

    assert.match(buyPage, /data-buy-provider-layout/);
    assert.match(buyPage, /data-buy-desktop-placeholder/);
    assert.match(buyPage, /md:grid-cols-\[minmax\(0,1fr\)_320px\]/);
    assert.doesNotMatch(buyPage, /xl:(?:hidden|block|grid-cols-)/);
});

test('the stylesheet fallback keeps the same 768px contract', () => {
    assert.match(
        css,
        /@media \(max-width: 767px\)[\s\S]*?> div\.fixed\.inset-x-0\.bottom-0 \{[\s\S]*?display:\s*block;/,
    );
    assert.match(
        css,
        /@media \(min-width: 768px\)[\s\S]*?> div\.fixed\.inset-x-0\.bottom-0 \{[\s\S]*?display:\s*none;/,
    );
    assert.match(
        css,
        /@media \(min-width: 768px\)[\s\S]*?grid-template-columns:\s*minmax\(0, 1fr\) 320px;/,
    );
});

test('the mobile action viewport visibility is CSS-owned', () => {
    assert.doesNotMatch(app, /buy-responsive-guard/);
    assert.equal(existsSync(guardPath), false);

    assert.match(buy, /data-buy-mobile-action/);
    assert.doesNotMatch(buy, /mobileMediaQuery/);
    assert.doesNotMatch(buy, /isMobileViewport/);
    assert.doesNotMatch(buy, /x-show\.important=/);
    assert.doesNotMatch(buy, /wire:ignore\.self/);
});

test('the mobile purchase bar respects the bottom safe area', () => {
    assert.match(buy, /env\(safe-area-inset-bottom, 0px\)/);
    assert.match(css, /env\(safe-area-inset-bottom, 0px\)/);
});

function readProjectFile(path) {
    return readFileSync(
        new URL(`../../${path}`, import.meta.url),
        'utf8',
    );
}
