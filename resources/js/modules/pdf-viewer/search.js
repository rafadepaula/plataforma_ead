/**
 * Busca no documento do visualizador de PDF (o "Ctrl+F" da aula).
 *
 * Funciona SEM text layer no DOM — copiar-colar continua impossível —: os
 * termos são lidos via `page.getTextContent()` (memória JS, nunca nós de
 * texto) e as ocorrências são desenhadas como pixels no próprio `<canvas>`
 * (sem seleção possível). Enter vai à próxima, Shift+Enter volta, ESC fecha.
 */
const DEBOUNCE_MS = 250;

const NO_RESULTS_LABEL = 'Nenhum resultado';

export class PdfSearch {
    constructor(viewer, lib) {
        this.viewer = viewer;
        this.lib = lib;
        this.query = '';
        this.matches = [];
        this.flat = [];
        this.current = -1;
        this.textCache = new Map();
        this.debounceTimer = null;
    }

    bindUI() {
        const container = this.viewer.container;

        this.toggleButton = container.querySelector('[data-pdf-search-toggle]');
        this.bar = container.querySelector('[data-pdf-search-bar]');
        this.input = container.querySelector('[data-pdf-search-input]');
        this.count = container.querySelector('[data-pdf-search-count]');
        this.prevButton = container.querySelector('[data-pdf-search-prev]');
        this.nextButton = container.querySelector('[data-pdf-search-next]');

        if (!this.toggleButton || !this.bar || !this.input) {
            return;
        }

        this.toggleButton.addEventListener('click', () => this.toggle());

        this.input.addEventListener('input', () => {
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            this.debounceTimer = setTimeout(() => {
                this.debounceTimer = null;
                void this.execute(this.input.value);
            }, DEBOUNCE_MS);
        });

        this.input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();

                if (event.shiftKey) {
                    void this.prev();
                } else {
                    void this.next();
                }
            }
        });

        this.prevButton?.addEventListener('click', () => void this.prev());
        this.nextButton?.addEventListener('click', () => void this.next());
    }

    isOpen() {
        return !!this.bar && !this.bar.classList.contains('d-none');
    }

    barContains(node) {
        return !!this.bar && !!node && this.bar.contains(node);
    }

    open() {
        if (!this.bar || !this.input) {
            return;
        }

        this.bar.classList.remove('d-none');
        this.toggleButton?.setAttribute('aria-expanded', 'true');
        this.input.focus();
    }

    toggle() {
        if (this.isOpen()) {
            void this.close();
            return;
        }

        this.open();
    }

    async close() {
        if (this.input) {
            this.input.value = '';
        }

        this.query = '';
        this.matches = [];
        this.flat = [];
        this.current = -1;

        this.bar?.classList.add('d-none');
        this.toggleButton?.setAttribute('aria-expanded', 'false');
        this.updateCount();

        await this.viewer.repaint();
    }

    /**
     * Roda a busca do termo (case-insensitive) em todas as páginas,
     * redesenha os destaques e revela a primeira ocorrência.
     */
    async execute(rawQuery) {
        this.query = rawQuery.trim().toLowerCase();

        if (!this.query) {
            this.matches = [];
            this.flat = [];
            this.current = -1;
            this.updateCount();
            await this.viewer.repaint();
            return;
        }

        this.matches = [];

        const totalPages = this.viewer.pdf?.numPages ?? 0;

        for (let pageNumber = 1; pageNumber <= totalPages; pageNumber += 1) {
            this.matches.push(...await this.pageMatches(pageNumber));
        }

        this.flat = this.matches;
        this.current = this.flat.length > 0 ? 0 : -1;

        await this.viewer.repaint();
        this.updateCount();

        if (this.current >= 0) {
            this.viewer.goToPage(this.flat[this.current].pageNumber);
        }
    }

    async next() {
        if (this.flat.length === 0) {
            return;
        }

        this.current = (this.current + 1) % this.flat.length;

        await this.viewer.repaint();
        this.updateCount();
        this.viewer.goToPage(this.flat[this.current].pageNumber);
    }

    async prev() {
        if (this.flat.length === 0) {
            return;
        }

        this.current = (this.current - 1 + this.flat.length) % this.flat.length;

        await this.viewer.repaint();
        this.updateCount();
        this.viewer.goToPage(this.flat[this.current].pageNumber);
    }

    updateCount() {
        if (!this.count) {
            return;
        }

        if (!this.query) {
            this.count.textContent = '';
            return;
        }

        this.count.textContent = this.flat.length === 0
            ? NO_RESULTS_LABEL
            : `${this.current + 1} de ${this.flat.length}`;
    }

    async pageMatches(pageNumber) {
        const found = [];
        const cached = this.textCache.get(pageNumber);

        let items;
        let viewport;

        if (cached) {
            ({ items, viewport } = cached);
        } else {
            const page = await this.viewer.pdf.getPage(pageNumber);
            const textContent = await page.getTextContent();

            viewport = page.getViewport({ scale: this.viewer.renderScale() });
            items = textContent.items;
            this.textCache.set(pageNumber, { items, viewport });
        }

        for (const item of items) {
            if (!item.str || !item.str.toLowerCase().includes(this.query)) {
                continue;
            }

            const rect = this.itemRect(viewport, item);

            if (rect) {
                found.push({ pageNumber, rect });
            }
        }

        return found;
    }

    /**
     * Caixa do item de texto em pixels do canvas (mesmo espaço do render).
     * Só texto horizontal — vertical/rotacionado é raro em apostila e cai
     * fora da busca em vez de ganhar destaque desalinhado.
     *
     * `item.width` já vem em pontos (com o corpo da fonte aplicado), então
     * a escala é só a do viewport (`tx[0]` carrega corpo × escala e
     * duplicaria o corpo se multiplicado direto).
     */
    itemRect(viewport, item) {
        const tx = this.lib.Util.transform(viewport.transform, item.transform);

        if (tx[1] !== 0 || tx[2] !== 0) {
            return null;
        }

        const fontSize = item.transform[0];
        const scale = fontSize !== 0 ? tx[0] / fontSize : this.viewer.renderScale();
        const fontHeight = Math.hypot(tx[2], tx[3]);
        const width = Math.max(1, item.width * scale);

        return {
            x: tx[4],
            y: tx[5] - fontHeight,
            w: width,
            h: fontHeight,
        };
    }

    /**
     * Desenha as ocorrências sobre os canvases já renderizados: todas em
     * menta suave, a atual em destaque. Pixels, nunca DOM — nada selecionável.
     */
    drawHighlights() {
        if (this.flat.length === 0) {
            return;
        }

        const byPage = new Map();

        this.flat.forEach((match, index) => {
            if (!byPage.has(match.pageNumber)) {
                byPage.set(match.pageNumber, []);
            }

            byPage.get(match.pageNumber).push({ ...match, index });
        });

        byPage.forEach((matches, pageNumber) => {
            const canvas = this.viewer.container.querySelector(
                `[data-page-number="${pageNumber}"] canvas`
            );

            if (!canvas) {
                return;
            }

            const ctx = canvas.getContext('2d');

            matches.forEach((match) => {
                ctx.fillStyle = match.index === this.current
                    ? 'rgba(46, 158, 107, 0.55)'
                    : 'rgba(76, 111, 231, 0.28)';
                ctx.fillRect(match.rect.x, match.rect.y, match.rect.w, match.rect.h);
            });
        });
    }
}

export default PdfSearch;
