/**
 * Marca d'água mínima do visualizador de PDF (dissuasão e rastreabilidade
 * de vazamento): uma única linha pequena embaixo de cada página renderizada
 * ("Nome do Curso - Nome do Aluno", lido de `data-watermark` do shell), sem
 * interação (`pointer-events: none` no CSS) e invisível a leitores de tela
 * (`aria-hidden` no markup). Não existe faixa de marca no rodapé do
 * visualizador — a marca vive só no documento, página a página.
 *
 * @param {HTMLElement} container `[data-pdf-viewer]`
 * @param {string} text conteúdo de `data-watermark`
 */
export function applyWatermark(container, text) {
    if (!text) {
        return;
    }

    container.querySelectorAll('[data-pdf-page-wrap]').forEach((wrap) => {
        if (wrap.querySelector('[data-pdf-page-watermark]')) {
            return;
        }

        const bar = document.createElement('div');
        bar.className = 'ds-pdf-page-watermark';
        bar.setAttribute('data-pdf-page-watermark', '');
        bar.setAttribute('aria-hidden', 'true');
        bar.textContent = text;

        wrap.append(bar);
    });
}
