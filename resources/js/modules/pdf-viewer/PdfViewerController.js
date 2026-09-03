import { applyProtection } from './protection';
import { applyWatermark } from './watermark';
import { PdfSearch } from './search';

/**
 * Dirige UM container `[data-pdf-viewer]`: busca os bytes no endpoint
 * gated (`data-pdf-url`, same-origin com `X-Requested-With` via
 * `HttpClient.getBinary`), abre com pdf.js (core API, nunca o viewer
 * padrão) e renderiza página a página em `<canvas>` — sem text layer, sem
 * URL de documento no DOM, sem toolbar nativa. A busca (Ctrl+F) lê o texto
 * via `getTextContent` e desenha as ocorrências no canvas: pesquisável,
 * nunca selecionável.
 *
 * Espelho de `lesson-player/PlayerController.js`: montagem passiva no
 * `DOMContentLoaded` (o `import('pdfjs-dist')` dinâmico adia o custo de rede
 * para as aulas que realmente têm PDF) e visibilidade só via `.d-none`.
 * Tela cheia move o chrome do visualizador (toolbar, busca e stage) para o
 * `.modal-body` do `<x-ui.modal>` irmão (preserva canvases/estado, sem
 * refetch) e o devolve ao fechar.
 */
export class PdfViewerController {
    constructor(container, httpClient) {
        this.container = container;
        this.httpClient = httpClient;
        this.pdfUrl = container.getAttribute('data-pdf-url');
        this.lessonId = container.getAttribute('data-lesson-id');
        this.watermarkText = container.getAttribute('data-watermark') ?? '';

        this.toolbar = container.querySelector('[data-pdf-toolbar]');
        this.searchBar = container.querySelector('[data-pdf-search-bar]');
        this.stage = container.querySelector('[data-pdf-stage]');
        this.errorNotice = container.querySelector('[data-pdf-error]');
        this.pageLabel = container.querySelector('[data-pdf-page]');
        this.prevButton = container.querySelector('[data-pdf-prev]');
        this.nextButton = container.querySelector('[data-pdf-next]');
        this.modeToggle = container.querySelector('[data-pdf-mode-toggle]');

        this.pdf = null;
        this.pdfjs = null;
        this.search = null;
        this.currentPage = 1;
        this.booting = false;
        this.fullscreenOpen = false;
        this.movedNodes = [];
        this.modalEl = null;
        this.pageObserver = null;
    }

    /**
     * Escala única do render — a busca usa a mesma para converter o texto
     * em coordenadas de canvas.
     */
    renderScale() {
        return 1.5;
    }

    mount() {
        if (!this.pdfUrl || !this.stage) {
            return;
        }

        applyProtection(this.container, () => this.fullscreenOpen);
        this.bindSearchKeys();
        this.modeToggle?.addEventListener('click', () => this.openFullscreen());
        this.prevButton?.addEventListener('click', () => this.goToPage(this.currentPage - 1));
        this.nextButton?.addEventListener('click', () => this.goToPage(this.currentPage + 1));

        void this.boot();
    }

    /**
     * Atalhos da busca: Ctrl/Cmd+F abre a barra quando o foco está no
     * visualizador ou o modal está aberto; ESC com o foco na busca fecha
     * só a busca (capture + stopPropagation para o modal continuar aberto).
     */
    bindSearchKeys() {
        document.addEventListener('keydown', (event) => {
            const mod = event.ctrlKey || event.metaKey;

            if (mod && (event.key === 'f' || event.key === 'F')) {
                if (!this.search) {
                    return;
                }

                if (this.fullscreenOpen || this.container.contains(document.activeElement)) {
                    event.preventDefault();
                    this.search.open();
                }

                return;
            }

            if (event.key === 'Escape' && this.search?.isOpen()) {
                if (this.search.barContains(document.activeElement)) {
                    event.stopPropagation();
                    void this.search.close();
                }
            }
        }, { capture: true });
    }

    async boot() {
        if (this.booting || this.pdf) {
            return;
        }

        this.booting = true;

        try {
            const pdfjs = await import('pdfjs-dist');
            const lib = pdfjs.default ?? pdfjs;

            const worker = new Worker(
                new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url),
                { type: 'module' },
            );

            if (lib.GlobalWorkerOptions) {
                lib.GlobalWorkerOptions.workerPort = worker;
            }

            const bytes = await this.httpClient.getBinary(this.pdfUrl);
            this.pdf = await lib.getDocument({ data: bytes }).promise;
            this.pdfjs = lib;
            this.search = new PdfSearch(this, lib);
            this.search.bindUI();

            await this.repaint();

            // Visibilidade segue a regra da casa: `.d-none`, nunca `hidden`.
            this.toolbar?.classList.remove('d-none');
            this.updateNav();
        } catch (error) {
            this.showUnavailable();
        } finally {
            this.booting = false;
        }
    }

    /**
     * Re-render limpo + overlays (marca d'água e destaques de busca),
     * preservando a rolagem do stage.
     */
    async repaint() {
        const top = this.stage?.scrollTop ?? 0;

        await this.renderAll();
        this.paintOverlays();

        if (this.stage) {
            this.stage.scrollTop = top;
        }
    }

    paintOverlays() {
        applyWatermark(this.container, this.watermarkText);
        this.search?.drawHighlights();
    }

    async renderAll() {
        if (!this.pdf || !this.stage) {
            return;
        }

        this.stage.textContent = '';

        for (let pageNumber = 1; pageNumber <= this.pdf.numPages; pageNumber += 1) {
            const page = await this.pdf.getPage(pageNumber);
            const viewport = page.getViewport({ scale: this.renderScale() });

            const wrap = document.createElement('div');
            wrap.className = 'ds-pdf-page';
            wrap.setAttribute('data-pdf-page-wrap', '');
            wrap.setAttribute('data-page-number', String(pageNumber));

            const canvas = document.createElement('canvas');
            canvas.className = 'ds-pdf-canvas';
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.setAttribute('aria-label', `Página ${pageNumber} de ${this.pdf.numPages}`);

            wrap.append(canvas);
            this.stage.append(wrap);

            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        }

        this.observePages();
    }

    /**
     * Mantém "Página N de M" fiel à rolagem manual: observa qual página
     * cruza a faixa central do stage e adota a mais visível como atual.
     * `rootMargin` em vez de `threshold` alto — página alta pode nunca
     * ocupar 50% do stage, mas sempre cruza o meio ao passar por ele.
     */
    observePages() {
        this.pageObserver?.disconnect();

        if (!this.stage || typeof IntersectionObserver === 'undefined') {
            return;
        }

        this.pageObserver = new IntersectionObserver(
            (entries) => {
                let best = null;

                entries.forEach((entry) => {
                    if (entry.isIntersecting && (!best || entry.intersectionRatio > best.intersectionRatio)) {
                        best = entry;
                    }
                });

                if (best) {
                    this.setCurrentPage(Number(best.target.getAttribute('data-page-number')));
                }
            },
            { root: this.stage, rootMargin: '-45% 0px -45% 0px', threshold: [0] }
        );

        this.stage.querySelectorAll('[data-pdf-page-wrap]').forEach((wrap) => {
            this.pageObserver.observe(wrap);
        });
    }

    setCurrentPage(pageNumber) {
        if (!Number.isFinite(pageNumber) || pageNumber === this.currentPage) {
            return;
        }

        const total = this.pdf?.numPages ?? 1;

        this.currentPage = Math.min(total, Math.max(1, pageNumber));
        this.updateNav();
    }

    goToPage(pageNumber) {
        if (!this.pdf) {
            return;
        }

        const clamped = Math.min(this.pdf.numPages, Math.max(1, pageNumber));
        this.currentPage = clamped;

        // Rola SÓ o stage (`scrollTop` do container): `scrollIntoView()`
        // rolaria todos os ancestrais roláveis, inclusive a página inteira.
        const target = this.stage?.querySelector(`[data-page-number="${clamped}"]`);

        if (target && this.stage) {
            const stageRect = this.stage.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();

            this.stage.scrollTo({
                top: this.stage.scrollTop + (targetRect.top - stageRect.top),
                behavior: 'smooth',
            });
        }

        this.updateNav();
    }

    updateNav() {
        const total = this.pdf?.numPages ?? 1;

        if (this.pageLabel) {
            this.pageLabel.textContent = `Página ${this.currentPage} de ${total}`;
        }

        if (this.prevButton) {
            this.prevButton.disabled = this.currentPage <= 1;
        }

        if (this.nextButton) {
            this.nextButton.disabled = this.currentPage >= total;
        }
    }

    resolveModal() {
        if (this.modalEl?.isConnected) {
            return this.modalEl;
        }

        const sibling = this.container.nextElementSibling;

        if (sibling?.matches?.('.modal')) {
            this.modalEl = sibling;
            return this.modalEl;
        }

        this.modalEl = document.querySelector(`[id^="pdf-fullscreen-${this.lessonId}"]`);
        return this.modalEl;
    }

    /**
     * Nós que viajam ao modal: o chrome inteiro do visualizador (toolbar,
     * busca e stage), na mesma ordem — a busca continua alcançável em tela
     * cheia e tudo volta ao lugar ao fechar.
     */
    movableNodes() {
        return [this.toolbar, this.searchBar, this.stage].filter(
            (node) => node instanceof HTMLElement
        );
    }

    openFullscreen() {
        const modalEl = this.resolveModal();
        const nodes = this.movableNodes();

        if (!modalEl || nodes.length === 0 || this.fullscreenOpen) {
            return;
        }

        const slot = modalEl.querySelector('[data-pdf-modal-slot]');

        if (!slot) {
            return;
        }

        this.movedNodes = nodes.map((node) => ({
            node,
            parent: node.parentNode,
            nextSibling: node.nextSibling,
        }));

        nodes.forEach((node) => slot.append(node));
        this.fullscreenOpen = true;
        this.modeToggle?.classList.add('is-fullscreen');

        modalEl.addEventListener('hidden.bs.modal', () => this.restoreInline(), { once: true });

        window.bootstrap?.Modal?.getOrCreateInstance(modalEl)?.show();
    }

    restoreInline() {
        this.movedNodes.forEach(({ node, parent, nextSibling }) => {
            if (parent) {
                parent.insertBefore(node, nextSibling);
            }
        });
        this.movedNodes = [];

        this.fullscreenOpen = false;
        this.modeToggle?.classList.remove('is-fullscreen');
    }

    showUnavailable() {
        this.toolbar?.classList.add('d-none');
        this.errorNotice?.classList.remove('d-none');
    }
}

export default PdfViewerController;
