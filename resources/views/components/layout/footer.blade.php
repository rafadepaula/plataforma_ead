<footer style="background: var(--color-surface); border-top: 1px solid var(--color-divider); padding: 20px 24px; font-size: 12px; color: var(--color-neutral-600); margin-top: auto; border-radius: 0px;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; max-width: 1400px; margin: 0 auto;">
        <div>
            © {{ date('Y') }} <strong>{{ session('tenant_name') ?? config('app.name', 'Plataforma EAD') }}</strong>. Todos os direitos reservados.
        </div>
        <div style="display: flex; gap: 16px;">
            <a href="#" style="color: var(--color-neutral-600); text-decoration: none;">Termos de Uso</a>
            <a href="#" style="color: var(--color-neutral-600); text-decoration: none;">Privacidade</a>
            <a href="#" style="color: var(--color-neutral-600); text-decoration: none;">Suporte</a>
        </div>
    </div>
</footer>
