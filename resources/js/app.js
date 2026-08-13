// -----------------------------------------------------------------------------
// Entrypoint. Estável por design: adicionar/remover um módulo NÃO toca este
// arquivo — edite `modules/index.js`. Isso elimina a serialização de PRs em
// torno de um arquivo compartilhado.
// -----------------------------------------------------------------------------

// Bootstrap COMPLETO (com Popper). Necessário para que os listeners de
// data-api (`data-bs-toggle`, `data-bs-dismiss`) sejam registrados: cada
// componente só responde a data-attributes se seu módulo tiver sido avaliado.
// Verificado em node_modules/bootstrap/js/dist/modal.js:284 e alert.js:79.
import * as bootstrap from 'bootstrap/dist/js/bootstrap.bundle.min.js';

import registry from './modules/index.js';

// `window.bootstrap` é o contrato que a suíte Dusk usa para dirigir modais e
// toasts programaticamente (`bootstrap.Modal.getOrCreateInstance(...)`).
window.bootstrap = bootstrap;

// Tooltip e Popover são opt-in por decisão de performance do Bootstrap:
// `data-bs-toggle="tooltip"` NÃO auto-inicializa. Init explícito aqui.
const initOptIns = () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
    document.querySelectorAll('[data-bs-toggle="popover"]')
        .forEach((el) => bootstrap.Popover.getOrCreateInstance(el));
};

const boot = () => {
    initOptIns();

    Object.entries(registry).forEach(([name, instance]) => {
        window[name] = instance;
        if (typeof instance.init === 'function') {
            try {
                instance.init();
            } catch (error) {
                console.error(`[app] falha ao inicializar ${name}`, error);
            }
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
