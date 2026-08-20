import assert from 'node:assert/strict';
import test from 'node:test';

import {
    classifyLivewireRequestContext,
    classifyLivewireRequestError,
    createConsecutiveFailureTracker,
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

test('it distinguishes poll, initialization, and foreground requests', () => {
    assert.equal(
        classifyLivewireRequestContext(
            requestWithActions(
                pollingAction('refreshOrder'),
            ),
        ),
        'poll',
    );

    assert.equal(
        classifyLivewireRequestContext(
            requestWithActions(
                initializationAction('loadCatalog'),
            ),
        ),
        'initialization',
    );

    assert.equal(
        classifyLivewireRequestContext(
            requestWithActions(
                annotatedInitializationAction('reloadCatalog'),
            ),
        ),
        'initialization',
    );

    assert.equal(
        classifyLivewireRequestContext(
            requestWithActions(
                foregroundAction('purchase'),
            ),
        ),
        'foreground',
    );

    assert.equal(
        classifyLivewireRequestContext(
            requestWithActions(
                pollingAction('refreshOrder'),
                foregroundAction('purchase'),
            ),
        ),
        'foreground',
    );
});

test('it deduplicates repeated notifications inside the configured window', () => {
    const shouldNotify = createRequestErrorDeduplicator(5_000);

    assert.equal(shouldNotify('server', 1_000), true);
    assert.equal(shouldNotify('server', 5_999), false);
    assert.equal(shouldNotify('server', 6_000), true);
    assert.equal(shouldNotify('network', 6_000), true);
});

test('it notifies once per consecutive failure sequence', () => {
    const failures = createConsecutiveFailureTracker(3);

    assert.equal(failures.fail('orders:refresh'), false);
    assert.equal(failures.fail('orders:refresh'), false);
    assert.equal(failures.fail('orders:refresh'), true);
    assert.equal(failures.fail('orders:refresh'), false);

    failures.succeed('orders:refresh');

    assert.equal(failures.fail('orders:refresh'), false);
    assert.equal(failures.fail('orders:refresh'), false);
    assert.equal(failures.fail('orders:refresh'), true);
});

test('it reports an ambiguous foreground network failure without retrying', () => {
    const livewire = fakeLivewire();
    const notifications = [];
    const failures = [];
    let currentTime = 1_000;

    registerLivewireRequestErrorHandler(
        livewire.api,
        {
            dispatchToast: (payload) => notifications.push(payload),
            dispatchRequestFailure: (detail) => failures.push(detail),
            now: () => currentTime,
        },
    );

    const request = requestWithActions(
        foregroundAction('purchase'),
    );

    livewire.request(request).failure({
        error: new TypeError('Failed to fetch'),
    });
    livewire.request(request).failure({
        error: new TypeError('Failed to fetch'),
    });

    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].toast.type, 'warning');
    assert.equal(
        notifications[0].toast.title,
        'نتیجه درخواست مشخص نیست',
    );
    assert.equal(
        Object.hasOwn(notifications[0].toast, 'position'),
        false,
    );
    assert.deepEqual(
        failures[0],
        {
            context: 'foreground',
            reason: 'network',
            status: null,
            actions: ['purchase'],
        },
    );

    currentTime += 5_000;

    livewire.request(request).failure({
        error: new TypeError('Failed to fetch'),
    });

    assert.equal(notifications.length, 2);
    assert.equal(livewire.interceptedRequests(), 3);
});

test('it delays poll feedback until repeated failures and resets after success', () => {
    const livewire = fakeLivewire();
    const notifications = [];
    const request = requestWithActions(
        pollingAction('refreshOrder'),
    );

    registerLivewireRequestErrorHandler(
        livewire.api,
        {
            backgroundFailureThreshold: 3,
            dispatchToast: (payload) => notifications.push(payload),
        },
    );

    for (let attempt = 0; attempt < 4; attempt++) {
        livewire.request(request).error({
            response: {
                status: 503,
            },
            preventDefault: () => {},
        });
    }

    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].toast.type, 'warning');
    assert.equal(
        notifications[0].toast.title,
        'به‌روزرسانی وضعیت با تأخیر انجام می‌شود',
    );

    livewire.request(request).success({});

    for (let attempt = 0; attempt < 3; attempt++) {
        livewire.request(request).failure({
            error: new TypeError('Failed to fetch'),
        });
    }

    assert.equal(notifications.length, 2);
});

test('it reports initialization failures as retryable warnings', () => {
    const livewire = fakeLivewire();
    const notifications = [];
    const failures = [];
    let prevented = 0;

    registerLivewireRequestErrorHandler(
        livewire.api,
        {
            dispatchToast: (payload) => notifications.push(payload),
            dispatchRequestFailure: (detail) => failures.push(detail),
        },
    );

    livewire.request(
        requestWithActions(
            initializationAction('loadCatalog'),
        ),
    ).error({
        response: {
            status: 500,
        },
        preventDefault: () => prevented++,
    });

    assert.equal(prevented, 1);
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].toast.type, 'warning');
    assert.deepEqual(
        failures[0],
        {
            context: 'initialization',
            reason: 'server',
            status: 500,
            actions: ['loadCatalog'],
        },
    );
});

test('it lets an initialization surface own its inline recovery message', () => {
    const livewire = fakeLivewire();
    const notifications = [];
    const failures = [];

    registerLivewireRequestErrorHandler(
        livewire.api,
        {
            dispatchToast: (payload) => notifications.push(payload),
            dispatchRequestFailure: (detail) => failures.push(detail),
        },
    );

    livewire.request(
        requestWithActions(
            inlineInitializationAction('loadCatalog'),
        ),
    ).failure({
        error: new TypeError('Failed to fetch'),
    });

    assert.equal(notifications.length, 0);
    assert.equal(failures.length, 1);
    assert.equal(failures[0].context, 'initialization');
    assert.deepEqual(failures[0].actions, ['loadCatalog']);
});

test('it relies on the offline indicator instead of adding another toast', () => {
    const livewire = fakeLivewire();
    const notifications = [];
    const failures = [];

    registerLivewireRequestErrorHandler(
        livewire.api,
        {
            dispatchToast: (payload) => notifications.push(payload),
            dispatchRequestFailure: (detail) => failures.push(detail),
            isOnline: () => false,
        },
    );

    livewire.request(
        requestWithActions(
            foregroundAction('purchase'),
        ),
    ).failure({
        error: new TypeError('Failed to fetch'),
    });

    assert.equal(notifications.length, 0);
    assert.equal(failures[0].reason, 'offline');
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

function requestWithActions(...actions) {
    return {
        messages: new Set([
            {
                component: {
                    id: 'component-1',
                },
                actions: new Set(actions),
            },
        ]),
    };
}

function foregroundAction(name) {
    return {
        name,
        metadata: {},
        origin: {
            directive: {
                value: 'click',
            },
        },
    };
}

function pollingAction(name) {
    return {
        name,
        metadata: {
            type: 'poll',
        },
        origin: {
            directive: {
                value: 'poll',
            },
        },
    };
}

function initializationAction(name) {
    return {
        name,
        metadata: {},
        origin: {
            directive: {
                value: 'init',
            },
        },
    };
}

function annotatedInitializationAction(name) {
    return {
        name,
        metadata: {},
        origin: {
            directive: {
                value: 'click',
            },
            el: {
                dataset: {
                    livewireRequestContext: 'initialization',
                },
            },
        },
    };
}

function inlineInitializationAction(name) {
    return {
        name,
        metadata: {},
        origin: {
            directive: {
                value: 'init',
            },
            el: {
                dataset: {
                    livewireRequestContext: 'initialization',
                    livewireRequestFeedback: 'inline',
                },
            },
        },
    };
}

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

        request(request = {
            messages: new Set(),
        }) {
            requests++;

            let error = null;
            let failure = null;
            let success = null;

            interceptor({
                request,
                onError: (callback) => {
                    error = callback;
                },
                onFailure: (callback) => {
                    failure = callback;
                },
                onSuccess: (callback) => {
                    success = callback;
                },
            });

            return {
                error,
                failure,
                success,
            };
        },
    };
}
