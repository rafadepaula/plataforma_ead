/**
 * EnrollmentSearch - async student autocomplete for the Course
 * enrollments panel.
 *
 * Replaces the old "type a raw User ID" box: the Gestor types part of a
 * name, e-mail or CPF, the module debounces a GET to the JSON
 * `courses.enrollments.search` endpoint and renders up to 10 candidate
 * Alunos as a dropdown. Picking one fills the hidden `user_id` input the
 * existing `POST courses.enrollments.store` endpoint expects, so the
 * backend contract (including `cancelled` reactivation) is unchanged.
 *
 * Root contract (Blade side):
 *   [data-enrollment-search]          form wrapper, carries data-search-url
 *   [data-enrollment-search-input]    visible search text input
 *   [data-enrollment-search-results]  dropdown container (toggled via `hidden`)
 *   [data-enrollment-user-id]         hidden input submitted as `user_id`
 *   [data-enrollment-selected]        selected-student chip (toggled via `hidden`)
 *   [data-enrollment-submit]          submit button, disabled until a pick
 */
export class EnrollmentSearch {
    constructor(httpClient) {
        this.httpClient = httpClient;
        this.root = null;
        this.input = null;
        this.results = null;
        this.hidden = null;
        this.selected = null;
        this.submit = null;
        this.abortController = null;
        this.debounceTimer = null;
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
        this.root = document.querySelector('[data-enrollment-search]');
        if (!this.root) return;

        this.input = this.root.querySelector('[data-enrollment-search-input]');
        this.results = this.root.querySelector('[data-enrollment-search-results]');
        this.hidden = this.root.querySelector('[data-enrollment-user-id]');
        this.selected = this.root.querySelector('[data-enrollment-selected]');
        this.submit = this.root.querySelector('[data-enrollment-submit]');

        if (!this.input || !this.results || !this.hidden || !this.selected || !this.submit) return;

        this.input.addEventListener('input', () => this.onInput());
        this.input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.clear();
            }
        });

        this.selected.addEventListener('click', (event) => {
            if (event.target.closest('[data-enrollment-clear]')) {
                this.clearSelection();
                this.input.focus();
            }
        });

        // Clicking anywhere outside closes the dropdown without dropping
        // an already-confirmed selection.
        document.addEventListener('click', (event) => {
            if (!this.root.contains(event.target)) {
                this.hideResults();
            }
        });

        this.root.addEventListener('submit', () => this.hideResults());
    }

    onInput() {
        const term = this.input.value.trim();

        clearTimeout(this.debounceTimer);

        if (term.length < 2) {
            this.hideResults();
            return;
        }

        this.debounceTimer = setTimeout(() => this.search(term), 300);
    }

    async search(term) {
        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = new AbortController();

        try {
            const url = `${this.root.dataset.searchUrl}?q=${encodeURIComponent(term)}`;
            // HttpClient envelopa a resposta: `response.data` é o corpo
            // JSON (`{ data: [...] }`), logo a lista está em `.data.data`.
            const response = await this.httpClient.get(url, { signal: this.abortController.signal });
            this.render(response?.data?.data ?? []);
        } catch (error) {
            if (error?.name !== 'AbortError') {
                console.error('[EnrollmentSearch] Falha ao buscar alunos:', error);
            }
        }
    }

    render(students) {
        this.results.innerHTML = '';

        if (students.length === 0) {
            this.results.innerHTML = '<div class="list-group-item text-body-secondary small">'
                +'Nenhum aluno encontrado. Use "Cadastrar novo aluno" para criar a conta.</div>';
            this.results.hidden = false;
            return;
        }

        students.forEach((student) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2';
            item.setAttribute('data-enrollment-option', String(student.id));

            const info = document.createElement('div');
            info.className = 'min-w-0';
            const name = document.createElement('div');
            name.className = 'fw-semibold';
            name.textContent = student.name;
            const meta = document.createElement('div');
            meta.className = 'small text-body-secondary';
            meta.textContent = `${student.email} · ${this.formatCpf(student.cpf)}`;
            info.append(name, meta);

            item.append(info);

            if (student.enrollment_status === 'cancelled') {
                // Par sólido do design system (`--success`/`--on-secondary`,
                // mesmo do `.btn-success`) com a métrica do `.ds-badge` — o
                // `text-bg-secondary` do Bootstrap rendia texto escuro,
                // pequeno e sem respiro.
                const badge = document.createElement('span');
                badge.className = 'badge ds-badge ds-badge-plain ds-enrollment-badge flex-shrink-0';
                badge.textContent = 'matrícula cancelada';
                item.append(badge);
            }

            item.addEventListener('click', () => this.select(student, item));

            this.results.append(item);
        });

        this.results.hidden = false;
    }

    select(student, item) {
        this.hidden.value = student.id;
        this.submit.disabled = false;

        this.selected.innerHTML = '';

        const box = document.createElement('div');
        box.className = 'border rounded p-2 d-flex align-items-center justify-content-between gap-3 w-100';
        const info = document.createElement('div');
        info.className = 'min-w-0';
        const name = document.createElement('div');
        name.className = 'fw-semibold text-truncate';
        name.textContent = student.name;
        const meta = document.createElement('div');
        meta.className = 'small text-body-secondary text-truncate';
        meta.textContent = `${student.email} · ${this.formatCpf(student.cpf)}`;
        info.append(name, meta);

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'btn-close';
        clear.setAttribute('aria-label', 'Remover seleção');
        clear.setAttribute('data-enrollment-clear', '');

        box.append(info, clear);
        this.selected.append(box);
        this.selected.hidden = false;

        this.input.value = '';
        this.hideResults();
    }

    clear() {
        this.input.value = '';
        this.clearSelection();
        this.hideResults();
    }

    clearSelection() {
        this.hidden.value = '';
        this.selected.innerHTML = '';
        this.selected.hidden = true;
        this.submit.disabled = true;
    }

    hideResults() {
        this.results.innerHTML = '';
        this.results.hidden = true;
    }

    formatCpf(value) {
        const digits = String(value ?? '').replace(/\D/g, '');
        if (digits.length !== 11) {
            return value || '—';
        }

        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
    }
}

export default EnrollmentSearch;
