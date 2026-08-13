<footer class="bg-body-secondary border-top py-4 px-5 fs-6 text-body-secondary mt-auto">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            &copy; {{ date('Y') }} <strong>{{ session('tenant_name') ?? config('app.name', 'Plataforma EAD') }}</strong>. Todos os direitos reservados.
        </div>
        <div class="d-flex gap-4">
            <a href="#" class="text-body-secondary text-decoration-none">Termos de Uso</a>
            <a href="#" class="text-body-secondary text-decoration-none">Privacidade</a>
            <a href="#" class="text-body-secondary text-decoration-none">Suporte</a>
        </div>
    </div>
</footer>
