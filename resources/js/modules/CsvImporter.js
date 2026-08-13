/**
 * CsvImporter - SOLID JavaScript module for chunked (50-row) AJAX CSV
 * student import (RF05/RN09). Reads the selected File client-side via
 * FileReader, manually splits it into rows (no external CSV parsing
 * dependency), and POSTs sequential 50-row batches through the shared
 * HttpClient module so the server never has to buffer the whole file.
 */
export class CsvImporter {
    constructor(httpClient) {
        this.httpClient = httpClient;
        this.chunkSize = 50;
        this.requiredColumns = ['name', 'email'];
    }

    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }
    }

    bind() {
        const form = document.querySelector('[dusk="csv-import-form"]');
        if (!form) return;

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.handleSubmit(form);
        });
    }

    async handleSubmit(form) {
        const fileInput = form.querySelector('[dusk="csv-file-input"]');
        const courseSelect = form.querySelector('[dusk="csv-course-select"]');
        const chunkUrl = form.getAttribute('data-chunk-url');
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;

        if (!file || !courseSelect || !courseSelect.value) {
            this.showResults(form, 'Selecione um curso e um arquivo CSV.', 'error');
            return;
        }

        const text = await this.readFileAsText(file);
        const header = this.extractHeader(text);

        const missingColumns = this.requiredColumns.filter((column) => !header.includes(column));
        if (missingColumns.length > 0) {
            this.showResults(
                form,
                `Cabeçalho inválido: coluna(s) obrigatória(s) ausente(s) [${missingColumns.join(', ')}]. `
                    + `Cabeçalho esperado: ${this.requiredColumns.join(', ')} (cpf opcional). `
                    + `Cabeçalho encontrado: ${header.length > 0 ? header.join(', ') : '(vazio)'}.`,
                'error'
            );
            return;
        }

        const rows = this.parseCsv(text);

        if (rows.length === 0) {
            this.showResults(form, 'O arquivo CSV está vazio ou não pôde ser lido.', 'error');
            return;
        }

        const chunks = this.chunkRows(rows, this.chunkSize);
        const totals = { created: 0, enrolled: 0, skipped: 0 };

        this.setProgress(form, 0, chunks.length);

        for (let i = 0; i < chunks.length; i++) {
            try {
                const response = await this.httpClient.post(chunkUrl, {
                    course_id: courseSelect.value,
                    filename: file.name,
                    rows: chunks[i],
                });

                totals.created += response.data.created || 0;
                totals.enrolled += response.data.enrolled || 0;
                totals.skipped += (response.data.skipped || []).length;
            } catch (error) {
                this.showResults(form, `Falha ao processar lote ${i + 1}: ${error.message}`, 'error');
                return;
            }

            this.setProgress(form, i + 1, chunks.length);
        }

        this.showResults(
            form,
            `Importação concluída: ${totals.created} usuário(s) criado(s), ${totals.enrolled} matrícula(s) realizada(s), ${totals.skipped} linha(s) ignorada(s).`,
            'success'
        );
    }

    readFileAsText(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result || ''));
            reader.onerror = () => reject(reader.error);
            reader.readAsText(file);
        });
    }

    /**
     * Splits raw CSV text into non-blank lines, ignoring the header row.
     */
    splitLines(text) {
        return text.split(/\r\n|\n|\r/).map((line) => line.trim()).filter((line) => line.length > 0);
    }

    /**
     * Normalizes the CSV header row (trim + lowercase each column) so it
     * can be validated and matched against `requiredColumns` before any
     * row is parsed or sent to the server. Returns an empty array when
     * the file has no lines at all.
     */
    extractHeader(text) {
        const lines = this.splitLines(text);
        if (lines.length === 0) return [];

        return lines[0].split(',').map((column) => column.trim().toLowerCase());
    }

    /**
     * Minimal manual CSV parser (no external dependency): splits on
     * newlines, then columns on commas. Assumes a header row of
     * `name,email,cpf` (cpf optional) and ignores blank lines.
     */
    parseCsv(text) {
        const lines = this.splitLines(text);
        if (lines.length === 0) return [];

        const header = this.extractHeader(text);
        const rows = [];

        for (let i = 1; i < lines.length; i++) {
            const columns = lines[i].split(',').map((value) => value.trim());
            const row = {};
            header.forEach((columnName, index) => {
                row[columnName] = columns[index] ?? '';
            });
            rows.push(row);
        }

        return rows;
    }

    chunkRows(rows, size) {
        const chunks = [];
        for (let i = 0; i < rows.length; i += size) {
            chunks.push(rows.slice(i, i + size));
        }
        return chunks;
    }

    /**
     * Reveals the progress block and updates the Bootstrap progress bar.
     *
     * Visibility is driven by the `.d-none` utility (never `style.display`:
     * `.d-none` is `display: none !important` and would win over an inline
     * declaration). The bar's `style.width` is the single authorized inline
     * style in the project — Bootstrap has no utility for an arbitrary
     * runtime percentage — and the `.progress` wrapper's `aria-valuenow` is
     * kept in sync with it on every update.
     */
    setProgress(form, done, total) {
        const wrapper = form.querySelector('[dusk="csv-import-progress-wrapper"]');
        const track = form.querySelector('[dusk="csv-import-progress"]');
        const bar = form.querySelector('[dusk="csv-import-progress-bar"]')
            || form.querySelector('[data-progress-bar]');
        const text = form.querySelector('[dusk="csv-import-progress-text"]');
        const percentage = total > 0 ? Math.round((done / total) * 100) : 0;

        if (wrapper) wrapper.classList.remove('d-none');
        if (bar) bar.style.width = `${percentage}%`;
        if (track) track.setAttribute('aria-valuenow', String(percentage));
        if (text) text.textContent = `Lote ${done} de ${total} (${percentage}%)`;
    }

    /**
     * Reveals the results block with `.d-none` removal and signals the error
     * state with the `.text-primary` utility (the theme's #ec3013 accent),
     * removing it again on the success path.
     */
    showResults(form, message, type) {
        const results = form.querySelector('[dusk="csv-import-results"]');
        if (!results) return;

        results.classList.remove('d-none');
        results.textContent = message;

        if (type === 'error') {
            results.classList.add('text-primary');
        } else {
            results.classList.remove('text-primary');
        }
    }
}

export default CsvImporter;
