/**
 * ThemeManager — Gerencia a alternância de temas (Light / Dark mode).
 * Persiste a preferência em localStorage e sincroniza com o atributo data-bs-theme.
 */
export class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'theme';
        this.initialized = false;
    }

    init() {
        if (this.initialized) {
            return;
        }

        const currentTheme = this.getTheme();
        this.applyTheme(currentTheme, false);

        document.addEventListener('click', (event) => {
            const toggleBtn = event.target.closest('[data-theme-toggle]');
            if (toggleBtn) {
                event.preventDefault();
                this.toggle();
            }
        });

        this.initialized = true;
    }

    getTheme() {
        try {
            const saved = localStorage.getItem(this.STORAGE_KEY);
            if (saved === 'dark' || saved === 'light') {
                return saved;
            }
        } catch (_) {
            // localStorage pode estar inacessível em contextos restritos
        }

        const currentAttr = document.documentElement.getAttribute('data-bs-theme');
        if (currentAttr === 'dark' || currentAttr === 'light') {
            return currentAttr;
        }

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        return 'light';
    }

    setTheme(theme) {
        if (theme !== 'dark' && theme !== 'light') {
            return;
        }
        this.applyTheme(theme, true);
    }

    toggle() {
        const current = this.getTheme();
        const next = current === 'dark' ? 'light' : 'dark';
        this.setTheme(next);
    }

    applyTheme(theme, save = true) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        if (save) {
            try {
                localStorage.setItem(this.STORAGE_KEY, theme);
            } catch (_) {
                // localStorage pode estar inacessível em contextos restritos
            }
        }

        this.updateButtons(theme);

        window.dispatchEvent(new CustomEvent('theme-changed', {
            detail: { theme }
        }));
    }

    updateButtons(theme) {
        const buttons = document.querySelectorAll('[data-theme-toggle]');
        const isDark = theme === 'dark';
        const label = isDark ? 'Ativar modo claro' : 'Ativar modo escuro';

        buttons.forEach((btn) => {
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            btn.setAttribute('data-current-theme', theme);

            const darkIcon = btn.querySelector('.theme-icon-dark');
            const lightIcon = btn.querySelector('.theme-icon-light');

            if (darkIcon && lightIcon) {
                if (isDark) {
                    darkIcon.classList.add('d-none');
                    lightIcon.classList.remove('d-none');
                } else {
                    darkIcon.classList.remove('d-none');
                    lightIcon.classList.add('d-none');
                }
            }
        });
    }
}

const instance = new ThemeManager();
export default instance;
