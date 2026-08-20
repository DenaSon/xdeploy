const DEDUPLICATION_WINDOW_MS = 5_000;
const SESSION_RELOAD_DELAY_MS = 1_500;
const BACKGROUND_FAILURE_THRESHOLD = 3;

export const LIVEWIRE_REQUEST_FAILED_EVENT =
    'xdeploy-livewire-request-failed';

const REQUEST_CONTEXTS = Object.freeze({
    foreground: 'foreground',
    initialization: 'initialization',
    poll: 'poll',
});

const REQUEST_ERRORS = Object.freeze({
    network: Object.freeze({
        key: 'network',
        type: 'warning',
        title: 'نتیجه درخواست مشخص نیست',
        description: 'پاسخی از سرور دریافت نشد. اگر عملیات حساسی را آغاز کرده‌اید، پیش از تکرار نتیجه آن را بررسی کنید.',
        css: 'alert-warning',
        progressClass: 'progress-warning',
    }),
    session: Object.freeze({
        key: 'session',
        type: 'warning',
        title: 'نشست شما منقضی شده است',
        description: 'برای ادامه، صفحه دوباره بارگذاری می‌شود.',
        css: 'alert-warning',
        progressClass: 'progress-warning',
    }),
    server: Object.freeze({
        key: 'server',
        type: 'error',
        title: 'درخواست کامل نشد',
        description: 'در پردازش درخواست خطایی رخ داد. وضعیت عملیات را بررسی کنید و سپس دوباره تلاش کنید.',
        css: 'alert-error',
        progressClass: 'progress-error',
    }),
    initialization: Object.freeze({
        key: 'initialization',
        type: 'warning',
        title: 'بارگذاری اطلاعات کامل نشد',
        description: 'دریافت اطلاعات انجام نشد. از گزینه «تلاش دوباره» استفاده کنید.',
        css: 'alert-warning',
        progressClass: 'progress-warning',
    }),
    background: Object.freeze({
        key: 'background',
        type: 'warning',
        title: 'به‌روزرسانی وضعیت با تأخیر انجام می‌شود',
        description: 'آخرین وضعیت معتبر نمایش داده می‌شود و تلاش خودکار ادامه دارد.',
        css: 'alert-warning',
        progressClass: 'progress-warning',
    }),
});

export function classifyLivewireRequestError(status) {
    if (status === 419) {
        return REQUEST_ERRORS.session;
    }

    if (Number.isInteger(status) && status >= 500 && status <= 599) {
        return REQUEST_ERRORS.server;
    }

    return null;
}

export function classifyLivewireRequestContext(request) {
    const actions = livewireRequestActions(request);

    if (actions.length === 0) {
        return REQUEST_CONTEXTS.foreground;
    }

    if (actions.every(isPollingAction)) {
        return REQUEST_CONTEXTS.poll;
    }

    if (actions.every(isInitializationAction)) {
        return REQUEST_CONTEXTS.initialization;
    }

    return REQUEST_CONTEXTS.foreground;
}

export function createRequestErrorDeduplicator(
    windowMs = DEDUPLICATION_WINDOW_MS,
) {
    const lastShownAt = new Map();

    return (key, now = Date.now()) => {
        const previous = lastShownAt.get(key);

        if (previous !== undefined && now - previous < windowMs) {
            return false;
        }

        lastShownAt.set(key, now);

        return true;
    };
}

export function createConsecutiveFailureTracker(
    threshold = BACKGROUND_FAILURE_THRESHOLD,
) {
    const failures = new Map();

    return {
        fail(key) {
            const current = failures.get(key) ?? {
                count: 0,
                notified: false,
            };

            current.count++;

            const shouldNotify =
                ! current.notified
                && current.count >= threshold;

            if (shouldNotify) {
                current.notified = true;
            }

            failures.set(key, current);

            return shouldNotify;
        },

        succeed(key) {
            failures.delete(key);
        },
    };
}

export function createMaryToast(error) {
    return {
        toast: {
            type: error.type,
            title: error.title,
            description: error.description,
            icon: '',
            css: error.css,
            timeout: 5_000,
            noProgress: false,
            progressClass: error.progressClass,
        },
    };
}

export function registerLivewireRequestErrorHandler(
    Livewire,
    {
        dispatchToast = dispatchMaryToast,
        dispatchRequestFailure = dispatchLivewireRequestFailure,
        isOnline = browserIsOnline,
        now = () => Date.now(),
        reload = reloadPage,
        schedule = scheduleTask,
        preventServerErrorModal = true,
        backgroundFailureThreshold = BACKGROUND_FAILURE_THRESHOLD,
        deduplicationWindowMs = DEDUPLICATION_WINDOW_MS,
        sessionReloadDelayMs = SESSION_RELOAD_DELAY_MS,
    } = {},
) {
    if (typeof Livewire?.interceptRequest !== 'function') {
        return () => {};
    }

    const shouldNotify = createRequestErrorDeduplicator(
        deduplicationWindowMs,
    );

    const backgroundFailures = createConsecutiveFailureTracker(
        backgroundFailureThreshold,
    );

    let sessionReloadScheduled = false;

    const notify = (error, deduplicate = true) => {
        if (
            deduplicate
            && ! shouldNotify(error.key, now())
        ) {
            return;
        }

        dispatchToast(
            createMaryToast(error),
        );
    };

    return Livewire.interceptRequest(({
        request,
        onError,
        onFailure,
        onSuccess,
    }) => {
        const context = classifyLivewireRequestContext(request);
        const backgroundScope = livewireRequestScope(request);
        const usesInlineFeedback = livewireRequestUsesInlineFeedback(
            request,
        );

        const reportRequestFailure = (reason, status = null) => {
            dispatchRequestFailure({
                context,
                reason,
                status,
                actions: livewireRequestActions(request)
                    .map((action) => action?.name)
                    .filter((name) => typeof name === 'string'),
            });
        };

        const handleOperationalFailure = (error, reason, status = null) => {
            reportRequestFailure(reason, status);

            if (! isOnline()) {
                return;
            }

            if (context === REQUEST_CONTEXTS.poll) {
                if (backgroundFailures.fail(backgroundScope)) {
                    notify(
                        REQUEST_ERRORS.background,
                        false,
                    );
                }

                return;
            }

            if (context === REQUEST_CONTEXTS.initialization) {
                if (! usesInlineFeedback) {
                    notify(REQUEST_ERRORS.initialization);
                }

                return;
            }

            notify(error);
        };

        onSuccess?.(() => {
            if (context === REQUEST_CONTEXTS.poll) {
                backgroundFailures.succeed(backgroundScope);
            }
        });

        onFailure(() => {
            handleOperationalFailure(
                REQUEST_ERRORS.network,
                isOnline() ? 'network' : 'offline',
            );
        });

        onError(({
            response,
            preventDefault,
        }) => {
            const error = classifyLivewireRequestError(
                response?.status,
            );

            if (error === null) {
                return;
            }

            if (
                response.status === 419
                || preventServerErrorModal
            ) {
                preventDefault();
            }

            if (response.status === 419) {
                notify(error);

                if (! sessionReloadScheduled) {
                    sessionReloadScheduled = true;

                    schedule(
                        reload,
                        sessionReloadDelayMs,
                    );
                }

                return;
            }

            handleOperationalFailure(
                error,
                'server',
                response.status,
            );
        });
    });
}

function livewireRequestActions(request) {
    const messages = Array.from(
        request?.messages ?? [],
    );

    return messages.flatMap(
        (message) => Array.from(message?.actions ?? []),
    );
}

function isPollingAction(action) {
    return action?.metadata?.type === 'poll';
}

function isInitializationAction(action) {
    return action?.origin?.directive?.value === 'init'
        || action?.origin?.el?.dataset?.livewireRequestContext
            === REQUEST_CONTEXTS.initialization;
}

function livewireRequestScope(request) {
    const messages = Array.from(
        request?.messages ?? [],
    );

    const scopes = messages.map((message) => {
        const componentId = message?.component?.id ?? 'unknown';
        const actions = Array.from(message?.actions ?? [])
            .map((action) => action?.name ?? 'unknown')
            .sort()
            .join(',');

        return `${componentId}:${actions}`;
    });

    return scopes.sort().join('|') || 'background';
}

function livewireRequestUsesInlineFeedback(request) {
    const actions = livewireRequestActions(request);

    return actions.length > 0
        && actions.every(
            (action) => action?.origin?.el?.dataset
                ?.livewireRequestFeedback === 'inline',
        );
}

function dispatchMaryToast(payload) {
    window.dispatchEvent(
        new CustomEvent(
            'mary-toast',
            {
                detail: payload,
            },
        ),
    );
}

function dispatchLivewireRequestFailure(detail) {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(
        new CustomEvent(
            LIVEWIRE_REQUEST_FAILED_EVENT,
            {
                detail,
            },
        ),
    );
}

function browserIsOnline() {
    return typeof window === 'undefined'
        || window.navigator?.onLine !== false;
}

function reloadPage() {
    window.location.reload();
}

function scheduleTask(callback, delay) {
    window.setTimeout(
        callback,
        delay,
    );
}
