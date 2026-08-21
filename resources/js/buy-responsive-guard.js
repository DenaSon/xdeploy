const BUY_MOBILE_ACTION_SELECTOR = '[data-buy-mobile-action]';
const BUY_MOBILE_MEDIA_QUERY = '(max-width: 767px)';

let observerStarted = false;

function syncBuyMobileActionVisibility() {
    const isMobileViewport = window.matchMedia(
        BUY_MOBILE_MEDIA_QUERY,
    ).matches;

    document
        .querySelectorAll(BUY_MOBILE_ACTION_SELECTOR)
        .forEach((element) => {
            if (isMobileViewport) {
                element.style.removeProperty('display');
                return;
            }

            element.style.setProperty(
                'display',
                'none',
                'important',
            );
        });
}

function startBuyResponsiveGuard() {
    syncBuyMobileActionVisibility();

    if (observerStarted) {
        return;
    }

    observerStarted = true;

    const observer = new MutationObserver(() => {
        syncBuyMobileActionVisibility();
    });

    observer.observe(
        document.documentElement,
        {
            childList: true,
            subtree: true,
        },
    );

    window.addEventListener(
        'resize',
        syncBuyMobileActionVisibility,
        { passive: true },
    );

    document.addEventListener(
        'livewire:navigated',
        syncBuyMobileActionVisibility,
    );
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        startBuyResponsiveGuard,
        { once: true },
    );
} else {
    startBuyResponsiveGuard();
}
