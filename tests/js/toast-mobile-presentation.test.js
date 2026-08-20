import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readProjectFile('resources/css/app.css');

test('it keeps the mobile toast inside direction-safe viewport gutters', () => {
    assert.match(
        css,
        /\.toast\.toast-center\s*\{[\s\S]*?inset-inline:\s*0\.75rem;[\s\S]*?width:\s*auto;[\s\S]*?translate:\s*0 0;/,
    );

    assert.match(
        css,
        /top:\s*calc\(env\(safe-area-inset-top, 0px\) \+ 0\.75rem\);/,
    );

    assert.match(
        css,
        /overflow-wrap:\s*anywhere;/,
    );
});

test('it centers larger toasts independently from document direction', () => {
    assert.match(
        css,
        /@media \(min-width: 640px\)[\s\S]*?left:\s*50%;[\s\S]*?right:\s*auto;[\s\S]*?translate:\s*-50% 0;/,
    );
});

test('all application layouts use the shared toast configuration', () => {
    const layouts = [
        'resources/views/layouts/admin.blade.php',
        'resources/views/layouts/app.blade.php',
        'resources/views/layouts/guest.blade.php',
        'resources/views/layouts/panel.blade.php',
        'resources/views/layouts/public.blade.php',
    ];

    for (const layout of layouts) {
        const contents = readProjectFile(layout);

        assert.match(contents, /<x-global-toast\s*\/>/);
        assert.doesNotMatch(contents, /<x-toast\b/);
    }

    assert.match(
        readProjectFile(
            'resources/views/components/global-toast.blade.php',
        ),
        /<x-toast position="toast-top toast-center"\s*\/>/,
    );
});

test('the purchase page owns initialization recovery without duplicate offline feedback', () => {
    const purchasePage = readProjectFile(
        'resources/views/livewire/servers/buy.blade.php',
    );

    assert.match(
        purchasePage,
        /data-livewire-request-feedback="inline"/,
    );
    assert.match(
        purchasePage,
        /x-on:xdeploy-livewire-request-failed\.window=/,
    );
    assert.doesNotMatch(
        purchasePage,
        /wire:offline/,
    );

    assert.match(
        readProjectFile(
            'resources/views/layouts/panel.blade.php',
        ),
        /<x-components\.panel\.offline-indicator\s*\/>/,
    );
});

function readProjectFile(path) {
    return readFileSync(
        new URL(`../../${path}`, import.meta.url),
        'utf8',
    );
}
