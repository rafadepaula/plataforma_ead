# **10. Mockup: Tela de Login e Convite (Guest Layout)**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Login de Usuário / Aceite de Convite de Organização
- **Rotas Laravel**: `/login` (`route('login')`) e `/convites/{token}` (`route('invitations.show')`)
- **Layout Base**: `layouts/guest.blade.php`
- **Papel (Role)**: Visitante (`guest`)
- **Objetivo**: Prover a autenticação de alunos e administradores e o aceite de convites de cadastro com layout split (escuro/claro) característico da marca Modernist.

---

## **2. Comportamento Responsivo e Breakpoints**

### **2.1. Mobile (390px)**
- **Layout**: Coluna única em fundo claro (`var(--color-bg)`). O painel institucional escuro é ocultado ou reduzido a um banner de topo.
- **Formulário**: Largura total com padding lateral 16px. Inputs `.field > label + .input` e botão primário full-width `<x-ui.button variant="primary" block>`.

### **2.2. Desktop (1920px / 1080p)**
- **Estrutura Split Screen**: Dividido em 2 painéis (`display: flex; height: 100vh`).
- **Painel Esquerdo Institucional (42% de largura)**:
  - Fundo: `background: var(--color-neutral-900); color: var(--color-neutral-100); padding: 48px`.
  - Marca: "Conselho EAD" (`font-family: var(--font-heading); font-weight: 800; font-size: 20px`).
  - Indicador Accent: Tracinho horizontal (`width: 56px; height: 2px; background: var(--color-accent); margin: 32px 0 20px`).
  - Headline `<h1>`: `font-family: var(--font-heading); font-weight: 800; font-size: 36px; line-height: 1.1; max-width: 9ch; margin-bottom: 16px`: "Acesse a plataforma".
  - Texto Institucional: `font-size: 15px; color: var(--color-neutral-400); max-width: 32ch`.
  - Rodapé Institucional: `margin-top: auto; font-size: 12px; color: var(--color-neutral-600)` (ex: "© 2026 Plataforma EAD. Todos os direitos reservados.").
- **Painel Direito de Formulário (58% de largura)**:
  - Fundo: `background: var(--color-bg); display: flex; align-items: center; justify-content: center; padding: 48px`.
  - Container de Formulário: Largura fixa de `380px; max-width: 100%`.
  - Header do Form: `<h2>` em `font-size: 24px; font-weight: 800; margin-bottom: 24px`: "Entrar na sua conta".
  - Campos do Formulário (`.field`):
    - Campo E-mail: `<x-ui.input type="email" name="email" label="E-mail profissional" required />`
    - Campo Senha: `<x-ui.input type="password" name="password" label="Sua senha" required />`
  - Opções Adicionais: Checkbox "Manter conectado" + Link "Esqueceu a senha?" (`color: var(--color-accent)`).
  - Botão Principal: `<x-ui.button variant="primary" block style="margin-top: 20px;">`Entrar`</x-ui.button>`.
  - Divisor "OU": Linha horizontal `.hr` com texto centralizado em `font-size: 12px; color: var(--color-neutral-500)`.
  - Botão SSO (opcional): `<x-ui.button variant="secondary" block>`Entrar com SSO da Organização`</x-ui.button>`.

---

## **3. Mapeamento de Componentes Blade**

```blade
<div style="display: flex; min-height: 100vh; width: 100%;">
    {{-- Painel Esquerdo Escuro (42%) --}}
    <div class="d-none d-lg-flex" 
         style="width: 42%; flex: none; background: var(--color-neutral-900); color: var(--color-neutral-100); flex-direction: column; padding: 56px;">
        
        <span style="font-family: var(--font-heading); font-weight: 800; font-size: 20px;">
            {{ $tenant->name ?? 'Conselho EAD' }}
        </span>

        <div style="margin-top: auto; margin-bottom: auto;">
            <div style="width: 56px; height: 2px; background: var(--color-accent); margin-bottom: 24px;"></div>
            
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 36px; line-height: 1.1; max-width: 9ch; margin-bottom: 16px; color: var(--color-neutral-100);">
                Acesse a plataforma
            </h1>
            
            <p style="font-size: 15px; color: var(--color-neutral-400); max-width: 32ch;">
                Capacitação técnica continuada, provas interativas e emissão de certificados oficiais.
            </p>
        </div>

        <div style="font-size: 12px; color: var(--color-neutral-600);">
            © {{ date('Y') }} Plataforma EAD. Todos os direitos reservados.
        </div>
    </div>

    {{-- Painel Direito Claro (58%) --}}
    <div style="flex: 1; background: var(--color-bg); display: flex; align-items: center; justify-content: center; padding: 32px 24px;">
        <div style="width: 380px; max-width: 100%;">
            <h2 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin-bottom: 24px; color: var(--color-text);">
                Entrar na sua conta
            </h2>

            <form action="{{ route('login') }}" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
                @csrf

                <div class="field">
                    <label for="email" style="font-size: 13px; font-weight: 600; margin-bottom: 6px; display: block;">E-mail</label>
                    <x-ui.input type="email" id="email" name="email" :value="old('email')" required autofocus />
                </div>

                <div class="field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="password" style="font-size: 13px; font-weight: 600;">Senha</label>
                        <a href="{{ route('password.request') }}" style="font-size: 12px; color: var(--color-accent); text-decoration: none;">Esqueceu a senha?</a>
                    </div>
                    <x-ui.input type="password" id="password" name="password" required />
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="remember" name="remember" style="accent-color: var(--color-accent);" />
                    <label for="remember" style="font-size: 13px; color: var(--color-text);">Manter conectado</label>
                </div>

                <x-ui.button variant="primary" block type="submit" style="margin-top: 8px;">
                    Entrar
                </x-ui.button>
            </form>
        </div>
    </div>
</div>
```

---

## **4. Guardrails e Testes Laravel Dusk**

```php
public function test_guest_can_render_login_page_and_authenticate()
{
    $this->browse(function (Browser $browser) {
        $user = User::factory()->create([
            'email' => 'aluno@conselho.org.br',
            'password' => bcrypt('secret123'),
        ]);

        $browser->visit('/login')
                ->assertSee('Acesse a plataforma')
                ->assertSee('Entrar na sua conta')
                ->type('email', 'aluno@conselho.org.br')
                ->type('password', 'secret123')
                ->click('button[type="submit"]')
                ->assertPathIs('/meus-cursos');
    });
}
```
