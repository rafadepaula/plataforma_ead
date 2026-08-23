/**
 * DashboardFilter - Handles async period filtering on the Dashboard.
 *
 * Intercepts clicks on filter chips ('7d', '30d', 'year'), executes an AJAX
 * GET to /admin/dashboard with the selected period, and dynamically updates
 * StatCard values and deltas without a full page refresh.
 */
export class DashboardFilter {
    constructor(httpClient) {
        this.httpClient = httpClient;
        this.root = null;
        this.filterBar = null;
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
        this.root = document.querySelector('[data-dashboard-root]');
        this.filterBar = document.querySelector('[data-dashboard-filter-bar]');

        if (!this.root || !this.filterBar) return;

        this.filterBar.addEventListener('click', (event) => {
            const chip = event.target.closest('[data-period]');
            if (!chip) return;

            event.preventDefault();
            const period = chip.getAttribute('data-period');
            if (!period) return;

            this.setPeriod(period, chip);
        });
    }

    async setPeriod(period, activeChip) {
        // 1. Update aria-pressed state on chips
        this.filterBar.querySelectorAll('[data-period]').forEach((chip) => {
            chip.setAttribute('aria-pressed', chip === activeChip ? 'true' : 'false');
        });

        // 2. Fetch updated stats
        try {
            const url = `/admin/dashboard?period=${encodeURIComponent(period)}`;
            const response = await this.httpClient.get(url);

            if (response && response.data && response.data.stats) {
                this.updateStats(response.data.stats);
            }
        } catch (error) {
            console.error('[DashboardFilter] Falha ao atualizar métricas:', error);
        }
    }

    updateStats(stats) {
        // Alunos ativos
        const activeStudentsCard = this.root.querySelector('[data-stat-active-students]');
        if (activeStudentsCard) {
            const valEl = activeStudentsCard.querySelector('.stat-card-value');
            if (valEl) valEl.textContent = String(stats.active_students);
        }

        // Certificados emitidos
        const certCard = this.root.querySelector('[data-stat-certificates-issued]');
        if (certCard) {
            const valEl = certCard.querySelector('.stat-card-value');
            if (valEl) valEl.textContent = String(stats.certificates_issued);
        }

        // Taxa de conclusão
        const compCard = this.root.querySelector('[data-stat-completion-rate]');
        if (compCard) {
            const valEl = compCard.querySelector('.stat-card-value');
            if (valEl) valEl.textContent = `${stats.completion_rate}%`;
        }

        // Cursos publicados
        const coursesCard = this.root.querySelector('[data-stat-courses-count]');
        if (coursesCard) {
            const valEl = coursesCard.querySelector('.stat-card-value');
            if (valEl) valEl.textContent = String(stats.courses_count);
        }
    }
}

export default DashboardFilter;
