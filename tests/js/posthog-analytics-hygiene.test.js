import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const analytics = readProjectFile('resources/js/analytics/posthog.js');
const bootstrap = readProjectFile(
    'resources/views/components/analytics/posthog.blade.php',
);

test('PostHog identity persists the internal traffic classification', () => {
    assert.match(
        analytics,
        /window\.posthog\.identify\([\s\S]*is_internal:\s*isInternal/,
    );
    assert.match(
        bootstrap,
        /name="coreflare-analytics-is-internal"/,
    );
});

test('PostHog pageviews carry route and internal traffic context', () => {
    assert.match(analytics, /route_name:\s*routeName \|\| null/);
    assert.match(analytics, /is_internal:\s*isInternal/);
    assert.match(analytics, /window\.posthog\.capture\(\s*'\$pageview'/);
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
