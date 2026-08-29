const ANALYTICS_USER_STORAGE_KEY = 'coreflare.analytics.user';

let lastNavigationSignature = null;

function metaContent(name) {
    return document
        .querySelector(`meta[name="${name}"]`)
        ?.getAttribute('content')
        ?.trim() ?? '';
}

function enabled() {
    return metaContent('coreflare-posthog-enabled') === '1'
        && window.posthog
        && typeof window.posthog.capture === 'function';
}

function currentUrlWithoutQuery() {
    return `${window.location.origin}${window.location.pathname}`;
}

function currentInternalFlag() {
    return metaContent('coreflare-analytics-is-internal') === '1';
}

function syncIdentity() {
    const userId = metaContent('coreflare-analytics-user-id');
    const isInternal = currentInternalFlag();
    const previousIdentity = window.sessionStorage.getItem(
        ANALYTICS_USER_STORAGE_KEY,
    );

    if (userId !== '') {
        const identitySignature = [
            userId,
            isInternal ? '1' : '0',
        ].join('|');

        if (
            previousIdentity !== identitySignature
            && typeof window.posthog.identify === 'function'
        ) {
            window.posthog.identify(
                userId,
                {
                    is_internal: isInternal,
                },
            );
        }

        window.sessionStorage.setItem(
            ANALYTICS_USER_STORAGE_KEY,
            identitySignature,
        );

        return;
    }

    if (
        previousIdentity !== null
        && typeof window.posthog.reset === 'function'
    ) {
        window.posthog.reset();
        window.sessionStorage.removeItem(
            ANALYTICS_USER_STORAGE_KEY,
        );
    }
}

function captureNavigation() {
    if (!enabled()) {
        return;
    }

    const routeName = metaContent(
        'coreflare-analytics-route',
    );
    const userId = metaContent(
        'coreflare-analytics-user-id',
    );
    const isInternal = currentInternalFlag();
    const signature = [
        window.location.href,
        routeName,
        userId,
        isInternal ? '1' : '0',
    ].join('|');

    if (signature === lastNavigationSignature) {
        return;
    }

    lastNavigationSignature = signature;

    syncIdentity();

    const properties = {
        route_name: routeName || null,
        is_internal: isInternal,
        $current_url: currentUrlWithoutQuery(),
        event_source: 'frontend',
        analytics_schema_version: 1,
    };

    window.posthog.capture(
        '$pageview',
        properties,
    );

    if (routeName === 'home') {
        window.posthog.capture(
            'landing_viewed',
            properties,
        );
    }

    if (routeName === 'panel.servers.buy') {
        window.posthog.capture(
            'buy_viewed',
            properties,
        );
    }
}

function captureProviderSelection(event) {
    if (
        !enabled()
        || metaContent('coreflare-analytics-route')
            !== 'panel.servers.buy'
    ) {
        return;
    }

    const target = event.target instanceof Element
        ? event.target.closest('[wire\\:click^="selectProvider("]')
        : null;

    if (!(target instanceof Element)) {
        return;
    }

    const action = target.getAttribute('wire:click') ?? '';
    const match = action.match(
        /^selectProvider\(['"]([^'"]+)['"]\)$/,
    );

    if (!match) {
        return;
    }

    window.posthog.capture(
        'provider_selected',
        {
            provider_public_code: match[1],
            route_name: 'panel.servers.buy',
            is_internal: currentInternalFlag(),
            $current_url: currentUrlWithoutQuery(),
            event_source: 'frontend',
            analytics_schema_version: 1,
        },
    );
}

export function registerPostHogNavigationTracking() {
    document.addEventListener(
        'livewire:navigated',
        captureNavigation,
    );

    document.addEventListener(
        'DOMContentLoaded',
        captureNavigation,
        {
            once: true,
        },
    );

    document.addEventListener(
        'click',
        captureProviderSelection,
    );
}
