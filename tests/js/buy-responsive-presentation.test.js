import assert from 'node:assert/strict';
import {
    existsSync,
    readFileSync,
} from 'node:fs';
import test from 'node:test';

const app = readProjectFile('resources/js/app.js');
const buy = readProjectFile('resources/views/livewire/servers/buy.blade.php');
const buyPage = readProjectFile('resources/views/livewire/servers/buy-page.blade.php');
const mobileCta = readProjectFile(
    'resources/views/livewire/servers/partials/buy-mobile-cta.blade.php',
);

const buyCssPath = new URL(
    '../../resources/css/buy.css',
    import.meta.url,
);
const buyCriticalCssPath = new URL(
    '../../resources/css/buy-critical.css',
    import.meta.url,
);

test('mobile purchase CTA has a stable root outside Buy catalog conditionals', () => {
    assert.match(
        buyPage,
        /@include\([\s\S]*?livewire\.servers\.partials\.buy-mobile-cta[\s\S]*?renderStableMobileCta[\s\S]*?true[\s\S]*?\)/,
    );
    assert.match(
        buyPage,
        /buy-mobile-cta[\s\S]*?@include\('livewire\.servers\.buy'\)/,
    );

    assert.match(mobileCta, /\$renderStableMobileCta \?\? false/);
    assert.match(mobileCta, /wire:key="buy-mobile-cta-root"/);
    assert.match(mobileCta, /data-buy-mobile-action/);
    assert.match(mobileCta, /fixed! inset-x-0 bottom-0/);
    assert.match(mobileCta, /md:hidden!/);
    assert.match(mobileCta, /'hidden!' => ! \$ctaReady/);
    assert.match(mobileCta, /\$catalogLoaded/);
    assert.match(mobileCta, /label="پرداخت"/);
    assert.doesNotMatch(mobileCta, /dock dock-xl/);
    assert.doesNotMatch(mobileCta, /label="پرداخت و ساخت"/);

    assert.doesNotMatch(buyPage, /data-buy-mobile-action/);
    assert.doesNotMatch(buy, /data-buy-mobile-action/);
    assert.doesNotMatch(buy, /data-buy-mobile-notice/);
});

test('legacy nested include cannot emit a second mobile CTA root', () => {
    assert.match(
        buy,
        /@include\('livewire\.servers\.partials\.buy-mobile-cta'\)/,
    );
    assert.match(
        mobileCta,
        /^@if\(\$renderStableMobileCta \?\? false\)/,
    );

    const explicitStableMounts =
        buyPage.match(/renderStableMobileCta/g) ?? [];

    assert.equal(explicitStableMounts.length, 1);
});

test('desktop and mobile purchase actions remain intentionally separate', () => {
    const desktopPurchaseActions = buy.match(/wire:click="purchase"/g) ?? [];
    const mobilePurchaseActions = mobileCta.match(/wire:click="purchase"/g) ?? [];

    assert.equal(desktopPurchaseActions.length, 1);
    assert.equal(mobilePurchaseActions.length, 1);

    assert.match(
        buy,
        /data-buy-desktop-summary[\s\S]*?class="[\s\S]*?hidden md:block[\s\S]*?"[\s\S]*?wire:click="purchase"/,
    );
    assert.match(buy, /pb-28/);
    assert.match(buy, /md:pb-0/);
});

test('mobile CTA follows the same quote safety conditions as desktop checkout', () => {
    assert.match(mobileCta, /\$quote === \[\]/);
    assert.match(mobileCta, /\$catalogError !== null/);
    assert.match(mobileCta, /\$quoteError !== null/);
    assert.match(mobileCta, /wire:loading\.attr="disabled"/);
    assert.match(mobileCta, /wire:target="purchase"/);
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
