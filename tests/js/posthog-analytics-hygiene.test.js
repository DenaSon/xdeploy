import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const analytics = readProjectFile('resources/js/analytics/posthog.js');
const bootstrap = readProjectFile(
    'resources/views/components/analytics/posthog.blade.php',
);
const adminLayout = readProjectFile(
    'resources/views/layouts/admin.blade.php',
);

test('PostHog identity persists account classification only', () => {
    assert.match(
        analytics,
        /window\.posthog\.identify\([\s\S]*is_internal:\s*isInternalAccount/,
    );
    assert.match(
        bootstrap,
        /name="coreflare-analytics-account-is-internal"/,
    );
});

test('PostHog pageviews carry request-level internal context', () => {
    assert.match(analytics, /route_name:\s*routeName \|\| null/);
    assert.match(analytics, /is_internal:\s*isInternal/);
    assert.match(analytics, /window\.posthog\.capture\(\s*'\$pageview'/);
    assert.match(
        bootstrap,
        /name="coreflare-analytics-is-internal"/,
    );
});

test('frontend analytics carries first and last touch attribution', () => {
    for (const property of [
        'first_touch_source',
        'first_touch_medium',
        'first_touch_campaign',
        'first_touch_content',
        'first_touch_term',
        'last_touch_source',
        'last_touch_medium',
        'last_touch_campaign',
        'last_touch_content',
        'last_touch_term',
    ]) {
        assert.ok(
            analytics.includes(`'${property}'`),
            `missing attribution property: ${property}`,
        );
    }

    assert.match(
        bootstrap,
        /AcquisitionAttribution::class/,
    );
    assert.match(
        bootstrap,
        /coreflare-analytics-attribution-/,
    );
    assert.match(
        analytics,
        /attributionWithPrefix\([\s\S]*'last_touch'/,
    );
    assert.match(
        analytics,
        /window\.posthog\.identify\([\s\S]*'first_touch'/,
    );
    assert.match(
        analytics,
        /\.\.\.currentAttribution\(\)/,
    );
});

test('admin layout refreshes PostHog context explicitly', () => {
    assert.match(adminLayout, /<x-analytics\.posthog\s*\/>/);
});

test('frontend analytics sanitizer blocks raw payload and identity fields', () => {
    for (const fragment of [
        'email',
        'raw_request',
        'raw_response',
        'request_body',
        'response_body',
        'cookie',
        'payload',
        'ip_address',
    ]) {
        assert.ok(
            bootstrap.includes(`'${fragment}'`),
            `missing sensitive fragment: ${fragment}`,
        );
    }
});

function readProjectFile(path) {
    return readFileSync(
        new URL(`../../${path}`, import.meta.url),
        'utf8',
    );
}
