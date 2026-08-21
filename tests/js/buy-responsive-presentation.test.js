import assert from 'node:assert/strict';
import {
    existsSync,
    readFileSync,
} from 'node:fs';
import test from 'node:test';

const app = readProjectFile('resources/js/app.js');
const buy = readProjectFile('resources/views/livewire/servers/buy.blade.php');
const buyPage = readProjectFile('resources/views/livewire/servers/buy-page.blade.php');

const buyCssPath = new URL(
    '../../resources/css/buy.css',
    import.meta.url,
);
const buyCriticalCssPath = new URL(
    '../../resources/css/buy-critical.css',
    import.meta.url,
);

test('Buy no longer renders a mobile purchase CTA', () => {
    assert.doesNotMatch(buy, /data-buy-mobile-action/);
    assert.doesNotMatch(buy, /fixed\s+inset-x-0\s+bottom-0/);
    assert.doesNotMatch(buy, /label="پرداخت"/);

    const purchaseActions = buy.match(/wire:click="purchase"/g) ?? [];
    assert.equal(purchaseActions.length, 1);
});

test('the only purchase action belongs to the desktop summary', () => {
    assert.match(
        buy,
        /data-buy-desktop-summary[\s\S]*?class="[\s\S]*?hidden md:block[\s\S]*?"[\s\S]*?wire:click="purchase"/,
    );

    assert.match(buy, /data-buy-mobile-notice/);
    assert.match(
        buy,
        /در این مرحله، ثبت و پرداخت سفارش از نمایش دسکتاپ انجام می‌شود/,
    );
});

test('Buy responsive layout is expressed in Tailwind markup only', () => {
    assert.match(buy, /data-buy-main-layout/);
    assert.match(buy, /md:grid-cols-\[minmax\(0,1fr\)_320px\]/);
    assert.doesNotMatch(buy, /<style>/);
    assert.doesNotMatch(buy, /data-buy-layout/);
    assert.doesNotMatch(buy, /data-buy-desktop-placeholder/);

    assert.doesNotMatch(buyPage, /data-buy-provider-layout/);
    assert.doesNotMatch(buyPage, /data-buy-desktop-placeholder/);
});

test('legacy Buy-specific stylesheets are removed from the frontend entry', () => {
    assert.doesNotMatch(app, /css\/buy\.css/);
    assert.doesNotMatch(app, /css\/buy-critical\.css/);
    assert.equal(existsSync(buyCssPath), false);
    assert.equal(existsSync(buyCriticalCssPath), false);
});

test('fixed-disk presentation is decided in Blade instead of CSS selectors', () => {
    assert.match(buy, /@if\(\$customDiskEnabled\)/);
    assert.match(buy, /wire:click="decreaseDisk"/);
    assert.match(buy, /wire:click="increaseDisk"/);
    assert.doesNotMatch(buyPage, /cloud-purchase-page--fixed-disk/);
});

function readProjectFile(path) {
    return readFileSync(
        new URL(`../../${path}`, import.meta.url),
        'utf8',
    );
}
