import assert from 'node:assert/strict';
import test from 'node:test';

import {
    classifyLivewireRequestError,
    createRequestErrorDeduplicator,
    registerLivewireRequestErrorHandler,
} from '../../resources/js/livewire/request-error-handler.js';

test('it classifies only handled HTTP errors', () => {
    assert.equal(
        classifyLivewireRequestError(419)?.key,
        'session',
    );
    assert.equal(
        classifyLivewireRequestError(500)?.key,
        'server',
    );
    assert.equal(
        classifyLivewireRequestError(599)?.key,
        'server',
    );
    assert.equal(
        classifyLivewireRequestError(422),
        null,
    );
    assert.equal(
        classifyLivewireRequestError(404),
        null,
    );
});

test('it deduplicates repeated notifications inside the configured window', () => {
    const shouldNotify = createRequestErrorDeduplicator(5_000);

    assert.equal(shouldNotify('server', 1_000), true);
    assert.equal(shouldNotify('server', 5_999), false);
    assert.equal(shouldNotify('server', 6_000), true);
    assert.equal(shouldNotify('network', 6_000), true);
});

test('it reports network failures without retrying the request', () => {
    const livewire = fakeLivewire();
    const notifications = [];
    let currentTime = 1_000;

    registerLivewireRequestErrorHandler(
        livewire.api,
        {
            dispatchToast: (payload) => notifications.push(payload),
            now: () => currentTime,
        },
    );

    livewire.request().failure({
        error: new TypeError('Failed to fetch'),
    });
    livewire.request().failure({
        error: new TypeError('Failed to fetch'),
    });

    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].toast.type, 'error');

    currentTime += 5_000;

    livewire.request().failure({
        error: new TypeError('Failed to fetch'),
    });

    assert.equal(notifications.length, 2);
    assert.equal(livewire.interceptedRequests(), 3);
});

test('it handles session expiration once and schedules one reload', () => {
    const livewire = fakeLivewire();
    const notifications = [];
    const scheduled = [];
    let prevented = 0;
    let reloaded = 0;

    registerLivewireRequestErrorHandler(
        livewire.api,
        {
            dispatchToast: (payload) => notifications.push(payload),
            now: () => 1_000,
            reload: () => reloaded++,
            schedule: (callback, delay) => scheduled.push({ callback, delay }),
            sessionReloadDelayMs: 250,
        },
    );

    const expire = () => livewire.request().error({
        response: {
            status: 419,
        },
        preventDefault: () => prevented++,
    });

    expire();
    expire();

    assert.equal(prevented, 2);
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].toast.type, 'warning');
    assert.equal(scheduled.length, 1);
    assert.equal(scheduled[0].delay, 250);

    scheduled[0].callback();

    assert.equal(reloaded, 1);
});

test('it preserves the development error modal and suppresses it in production', () => {
    for (const preventServerErrorModal of [false, true]) {
        const livewire = fakeLivewire();
        const notifications = [];
        let prevented = 0;

        registerLivewireRequestErrorHandler(
            livewire.api,
            {
                dispatchToast: (payload) => notifications.push(payload),
                preventServerErrorModal,
            },
        );

        livewire.request().error({
            response: {
                status: 503,
            },
            preventDefault: () => prevented++,
        });

        assert.equal(notifications.length, 1);
        assert.equal(
            prevented,
            preventServerErrorModal ? 1 : 0,
        );
    }
});

test('it leaves unhandled HTTP errors to Livewire', () => {
    const livewire = fakeLivewire();
    const notifications = [];
    let prevented = 0;

    registerLivewireRequestErrorHandler(
        livewire.api,
        {
            dispatchToast: (payload) => notifications.push(payload),
        },
    );

    livewire.request().error({
        response: {
            status: 422,
        },
        preventDefault: () => prevented++,
    });

    assert.equal(notifications.length, 0);
    assert.equal(prevented, 0);
});

function fakeLivewire() {
    let interceptor = null;
    let requests = 0;

    return {
        api: {
            interceptRequest(callback) {
                interceptor = callback;

                return () => {
                    interceptor = null;
                };
            },
        },

        interceptedRequests() {
            return requests;
        },

        request() {
            requests++;

            let error = null;
            let failure = null;

            interceptor({
                onError: (callback) => {
                    error = callback;
                },
                onFailure: (callback) => {
                    failure = callback;
                },
            });

            return {
                error,
                failure,
            };
        },
    };
}
