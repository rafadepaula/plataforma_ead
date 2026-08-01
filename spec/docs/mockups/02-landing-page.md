# **02. Mockup: Landing Page Pública**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Landing Page Pública
- **Rota Laravel**: `/` (`route('landing')`)
- **Layout Base**: `layouts/guest.blade.php`
- **Papel (Role)**: Visitante / Pauta Pública (`guest`)
- **Objetivo**: Apresentar a plataforma de capacitação do conselho e prover acessos de login e catálogo público de cursos.

---

## **2. Comportamento nos 3 Breakpoints**

### **2.1. Mobile (390px)**
- **Header**: Topbar `.nav` com fundo `--color-surface`, `padding: 10px 14px`. Marca `.nav-brand` à esquerda e botão hambúrguer SVG inline à direita.
- **Imagem Hero**: Imagem de destaque em topo de página (`height: 150px; width: 100%`) encapsulada em `.grayscale` (`filter: grayscale(1) contrast(1.08)`).
- **Conteúdo Hero**:
  - `<h1>` com `font-size: 20px; line-height: 1.2; margin-bottom: 8px`: "Capacitação continuada para eletricistas associados".
  - Parágrafo `.text-muted` (`font-size: 12px; margin-bottom: 14px`).
  - Botões empilhados em largura total:
    - `<x-ui.button variant="primary" block>`Acessar Cursos`</x-ui.button>`
    - `<x-ui.button variant="secondary" block>`Entrar`</x-ui.button>`

### **2.2. Tablet (768px)**
- **Header**: Topbar `.nav` com `padding: 12px 24px`, exibe marca, links de navegação estática ("Sobre", "Central de Ajuda") e botão "Entrar" à direita.
- **Layout Principal**: Grid de 2 colunas iguais (`grid-template-columns: 1fr 1fr; gap: 24px; padding: 28px 32px`).
- **Coluna da Esquerda**:
  - Divisor Accent: Barra horizontal de destaque (`width: 40px; height: 2px; background: var(--color-accent); margin-bottom: 12px`).
  - Headline `<h1>`: `font-size: 26px; line-height: 1.15; margin-bottom: 10px`.
  - Parágrafo: `font-size: 13px; margin-bottom: 18px`.
  - Botão CTA: `<x-ui.button variant="primary">Acessar Cursos</x-ui.button>`.
- **Coluna da Direita**: Imagem hero grayscale (`height: 220px; width: 100%`).

### **2.3. Desktop (1920px / 1080p)**
- **Header**: Topbar `.nav` com `padding: 16px 48px; gap: 28px`. Marca em 16px 800, links "Sobre a Capacitação" e "Central de Ajuda", e CTA "Entrar na Plataforma" à direita.
- **Layout Principal**: Grid de 2 colunas (`grid-template-columns: 1fr 1fr; gap: 56px; padding: 56px 64px; align-items: center`).
- **Coluna da Esquerda**:
  - Divisor Accent: `width: 56px; height: 2px; background: var(--color-accent); margin-bottom: 20px`.
  - Headline `<h1>`: `font-size: 44px; line-height: 1.1; max-width: 11ch; font-weight: 800; margin-bottom: 18px`.
  - Subtítulo: `font-size: 16px; max-width: 44ch; color: var(--color-neutral-700); margin-bottom: 26px`.
  - Grupo de Botões (flex horizontal, gap 12px):
    - `<x-ui.button variant="primary">Acessar Cursos</x-ui.button>`
    - `<x-ui.button variant="secondary">Central de Ajuda</x-ui.button>`
- **Coluna da Direita**: Imagem hero grayscale (`height: 340px; width: 100%`).

---

## **3. Mapeamento Blade e Elementos do Design System**

```blade
<div class="landing-page-container">
    {{-- Navigation Header --}}
    <nav class="nav" style="border-bottom: 2px solid var(--color-divider); padding: 16px 48px; gap: 28px;">
        <span class="nav-brand" style="font-size: 16px; font-weight: 800; font-family: var(--font-heading);">
            {{ $tenant->name ?? 'Conselho EAD' }}
        </span>
        
        <div class="nav-links d-none d-md-flex" style="gap: 20px;">
            <a href="#sobre" style="color: var(--color-text); text-decoration: none; font-size: 14px;">Sobre a Capacitação</a>
            <a href="#ajuda" style="color: var(--color-text); text-decoration: none; font-size: 14px;">Central de Ajuda</a>
        </div>

        <div style="margin-left: auto;">
            <x-ui.button variant="primary" :href="route('login')">
                Entrar na Plataforma
            </x-ui.button>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items: center; gap: 56px; padding: 56px 64px;">
        <div>
            <div style="width: 56px; height: 2px; background: var(--color-accent); margin-bottom: 20px;"></div>
            
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 44px; line-height: 1.1; max-width: 11ch; margin-bottom: 18px; color: var(--color-text);">
                Capacitação continuada para eletricistas associados
            </h1>
            
            <p class="text-muted" style="font-size: 16px; max-width: 44ch; margin-bottom: 26px;">
                Cursos, avaliações interativas, fórum de discussão e certificados com validade reconhecida — 100% online.
            </p>
            
            <div style="display: flex; gap: 12px;">
                <x-ui.button variant="primary" :href="route('courses.index')">
                    Acessar Cursos
                </x-ui.button>
                <x-ui.button variant="secondary" href="#ajuda">
                    Central de Ajuda
                </x-ui.button>
            </div>
        </div>

        <div class="grayscale" style="height: 340px; width: 100%; overflow: hidden;">
            <img src="{{ asset('images/hero-eletricista.jpg') }}" 
                 alt="Eletricista em campo" 
                 style="width: 100%; height: 100%; object-fit: cover; filter: grayscale(1) contrast(1.08);" />
        </div>
    </section>
</div>
```

---

## **4. Guardrails e Testes Laravel Dusk**

```php
public function test_landing_page_renders_correctly()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
                ->assertSee('Conselho EAD')
                ->assertSee('Capacitação continuada para eletricistas')
                ->assertSeeLink('Entrar na Plataforma')
                ->assertSeeLink('Acessar Cursos');
    });
}
```
