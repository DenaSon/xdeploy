import 'easymde/dist/easymde.min.css';
import EasyMDE from 'easymde';

import '../css/admin-markdown.css';
import '../css/coreflare-theme-contract.css';
import '../css/landing.css';
import './passkeys.js';

import {
    registerPostHogNavigationTracking,
} from './analytics/posthog.js';
import {
    registerLivewireRequestErrorHandler,
} from './livewire/request-error-handler.js';

window.EasyMDE = EasyMDE;

registerPostHogNavigationTracking();

document.addEventListener(
    'livewire:init',
    () => {
        registerLivewireRequestErrorHandler(
            window.Livewire,
            {
                preventServerErrorModal:
                    import.meta.env.PROD,
            },
        );
    },
    {
        once: true,
    },
);
