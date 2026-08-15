import '../css/admin-markdown.css';

import {
    registerLivewireRequestErrorHandler,
} from './livewire/request-error-handler.js';

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
