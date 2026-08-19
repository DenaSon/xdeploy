import 'easymde/dist/easymde.min.css';
import EasyMDE from 'easymde';

import '../css/admin-markdown.css';
import '../css/landing.css';
import './passkeys.js';

import {
    registerLivewireRequestErrorHandler,
} from './livewire/request-error-handler.js';

window.EasyMDE = EasyMDE;

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
