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
     * Minimal manual CSV parser (no external dependency): splits on
     * newlines, then columns on commas. Assumes a header row of
     * `name,email,cpf` (cpf optional) and ignores blank lines.
     */
    parseCsv(text) {
        const lines = text.split(/\r\n|\n|\r/).map((line) => line.trim()).filter((line) => line.length > 0);
        if (lines.length === 0) return [];

        const header = lines[0].split(',').map((column) => column.trim().toLowerCase());
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

    setProgress(form, done, total) {
        const wrapper = form.querySelector('[dusk="csv-import-progress-wrapper"]');
        const bar = form.querySelector('[dusk="csv-import-progress-bar"]');
        const text = form.querySelector('[dusk="csv-import-progress-text"]');
        const percentage = total > 0 ? Math.round((done / total) * 100) : 0;

        if (wrapper) wrapper.style.display = 'flex';
        if (bar) bar.style.width = `${percentage}%`;
        if (text) text.textContent = `Lote ${done} de ${total} (${percentage}%)`;
    }

    showResults(form, message, type) {
        const results = form.querySelector('[dusk="csv-import-results"]');
        if (!results) return;

        results.style.display = 'block';
        results.textContent = message;
        results.style.color = type === 'error' ? 'var(--color-accent)' : 'var(--color-text)';
    }
}

export default CsvImporter;
