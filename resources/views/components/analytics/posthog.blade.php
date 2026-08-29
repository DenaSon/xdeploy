@php
    $postHogEnabled = (bool) config(
        'services.posthog.enabled',
        false,
    );
    $postHogApiKey = trim(
        (string) config(
            'services.posthog.api_key',
            '',
        ),
    );
    $postHogHost = rtrim(
        (string) config(
            'services.posthog.host',
            'https://us.i.posthog.com',
        ),
        '/',
    );
    $analyticsContext = app(
        \App\Infrastructure\Analytics\AnalyticsContext::class,
    );
    $acquisitionAttribution = app(
        \App\Infrastructure\Analytics\AcquisitionAttribution::class,
    );
    $analyticsRouteName = $analyticsContext->routeName();
    $analyticsUserId = auth()->id();
    $analyticsIsInternal = $analyticsContext->currentTrafficIsInternal();
    $analyticsAccountIsInternal = $analyticsContext->currentAccountIsInternal();
    $analyticsAttribution = $acquisitionAttribution->eventProperties();
@endphp

@if($postHogEnabled && $postHogApiKey !== '')
    <meta
        name="coreflare-posthog-enabled"
        content="1"
    >
    <meta
        name="coreflare-posthog-api-key"
        content="{{ $postHogApiKey }}"
    >
    <meta
        name="coreflare-posthog-host"
        content="{{ $postHogHost }}"
    >
    <meta
        name="coreflare-analytics-route"
        content="{{ $analyticsRouteName ?? '' }}"
    >

    @foreach($analyticsAttribution as $property => $value)
        <meta
            name="coreflare-analytics-attribution-{{ str_replace('_', '-', $property) }}"
            content="{{ $value }}"
        >
    @endforeach

    @if($analyticsUserId !== null)
        <meta
            name="coreflare-analytics-user-id"
            content="user:{{ $analyticsUserId }}"
        >
        <meta
            name="coreflare-analytics-is-internal"
            content="{{ $analyticsIsInternal === true ? '1' : '0' }}"
        >
        <meta
            name="coreflare-analytics-account-is-internal"
            content="{{ $analyticsAccountIsInternal === true ? '1' : '0' }}"
        >
    @endif

    <script data-navigate-once>
        (() => {
            if (window.__coreflarePostHogInitialized) {
                return;
            }

            window.__coreflarePostHogInitialized = true;

            const apiKey = @json($postHogApiKey);
            const apiHost = @json($postHogHost);

            !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture identify reset get_distinct_id set_config opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);

            const sensitiveFragments = [
                'password',
                'credential',
                'secret',
                'token',
                'otp',
                'phone',
                'email',
                'authorization',
                'merchant',
                'gateway_reference',
                'gateway_transaction_id',
                'api_key',
                'access_key',
                'private_key',
                'raw_request',
                'raw_response',
                'request_body',
                'response_body',
                'cookie',
                'csrf',
                'payload',
                'username',
                'first_name',
                'last_name',
                'full_name',
                'address',
                'ip_address',
                'signature',
            ];

            const sanitizeUrl = (value) => {
                if (typeof value !== 'string' || value === '') {
                    return value;
                }

                try {
                    const parsed = new URL(value, window.location.origin);

                    return `${parsed.origin}${parsed.pathname}`;
                } catch {
                    return null;
                }
            };

            posthog.init(apiKey, {
                api_host: apiHost,
                defaults: '2026-05-30',
                autocapture: false,
                capture_pageview: false,
                capture_pageleave: false,
                disable_session_recording: true,
                person_profiles: 'identified_only',
                before_send: (event) => {
                    if (!event || !event.properties) {
                        return event;
                    }

                    for (const key of Object.keys(event.properties)) {
                        const normalizedKey = key.toLowerCase();

                        if (
                            normalizedKey === 'token'
                            && event.properties[key] === apiKey
                        ) {
                            continue;
                        }

                        if (
                            normalizedKey === 'code'
                            || sensitiveFragments.some(
                                (fragment) => normalizedKey.includes(fragment),
                            )
                        ) {
                            delete event.properties[key];
                        }
                    }

                    if ('$current_url' in event.properties) {
                        event.properties.$current_url = sanitizeUrl(
                            event.properties.$current_url,
                        );
                    }

                    if ('$referrer' in event.properties) {
                        event.properties.$referrer = sanitizeUrl(
                            event.properties.$referrer,
                        );
                    }

                    return event;
                },
            });
        })();
    </script>
@endif
