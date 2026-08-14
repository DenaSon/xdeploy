const DEDUPLICATION_WINDOW_MS = 5_000;
const SESSION_RELOAD_DELAY_MS = 1_500;

const REQUEST_ERRORS = Object.freeze({
    network: Object.freeze({
        key: 'network',
        type: 'error',
        title: 'ارتباط با xDeploy برقرار نشد',
        description: 'پاسخی از xDeploy دریافت نشد. پیش از تکرار عملیات، وضعیت آن را بررسی کنید.',
        css: 'alert-error',
        progressClass: 'progress-error',
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
        title: 'xDeploy نتوانست درخواست را کامل کند',
        description: 'خطای غیرمنتظره‌ای رخ داد. وضعیت عملیات را بررسی کرده و سپس دوباره تلاش کنید.',
        css: 'alert-error',
        progressClass: 'progress-error',
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

export function createMaryToast(error) {
    return {
        toast: {
            type: error.type,
            title: error.title,
            description: error.description,
            position: 'toast-top toast-center',
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
        now = () => Date.now(),
        reload = reloadPage,
        schedule = scheduleTask,
        preventServerErrorModal = true,
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

    let sessionReloadScheduled = false;

    const notify = (error) => {
        if (! shouldNotify(error.key, now())) {
            return;
        }

        dispatchToast(
            createMaryToast(error),
        );
    };

    return Livewire.interceptRequest(({
        onError,
        onFailure,
    }) => {
        onFailure(() => {
            notify(REQUEST_ERRORS.network);
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

            notify(error);

            if (
                response.status !== 419
                || sessionReloadScheduled
            ) {
                return;
            }

            sessionReloadScheduled = true;

            schedule(
                reload,
                sessionReloadDelayMs,
            );
        });
    });
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

function reloadPage() {
    window.location.reload();
}

function scheduleTask(callback, delay) {
    window.setTimeout(
        callback,
        delay,
    );
}
