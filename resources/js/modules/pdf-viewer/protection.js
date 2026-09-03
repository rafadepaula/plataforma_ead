/**
 * Neutraliza os vetores de download/cópia ao redor do stage de PDF.
 * Escopo fechado no container do visualizador — nada aqui toca o documento
 * inteiro, exceto o atalho de teclado, que só age com o modal aberto.
 *
 * @param {HTMLElement} container `[data-pdf-viewer]`
 * @param {() => boolean} isFullscreenOpen lê o estado do modal deste viewer
 */
export function applyProtection(container, isFullscreenOpen) {
    container.addEventListener('contextmenu', (event) => {
        event.preventDefault();
    });

    container.addEventListener('dragstart', (event) => {
        if (event.target instanceof HTMLCanvasElement) {
            event.preventDefault();
        }
    });

    document.addEventListener('keydown', (event) => {
        const saveOrPrint = (event.ctrlKey || event.metaKey)
            && (event.key === 's' || event.key === 'S' || event.key === 'p' || event.key === 'P');

        if (!saveOrPrint) {
            return;
        }

        if (typeof isFullscreenOpen === 'function' && !isFullscreenOpen()) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
    });
}
