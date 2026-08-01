# **01. Mockup: Menu Lateral Mobile (Drawer Aberta - Aluno & Admin)**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Menu Lateral Mobile (Drawer Deslizante)
- **Componente Blade**: `<x-layout.sidebar>` / `<x-layout.topbar>`
- **Layout Base**: `layouts/app.blade.php` (Renderização condicional mobile)
- **Breakpoints Relevantes**: Mobile (< 768px, especificado em 390px)
- **Papeis (Roles)**: `role:aluno` e `role:admin` / `role:gestor`

---

## **2. Anatomia Visual e Comportamento Responsivo**

### **2.1. Visão Aluno (Mobile · 390px)**
1. **Backdrop/Overlay de Fundo**:
   - `position: absolute; inset: 0; background: color-mix(in srgb, var(--color-neutral-900) 55%, transparent); z-index: 40;`
   - O conteúdo subjacente fica visível porém escurecido e desfocado (`opacity: 0.4; filter: blur(1px)`).
2. **Contêiner da Drawer**:
   - `width: 280px; height: 100%; background: var(--color-neutral-900); color: var(--color-neutral-400); display: flex; flex-direction: column; padding: 18px 0; box-shadow: var(--shadow-lg); z-index: 50;`
3. **Header da Drawer**:
   - Título da Organização: "Conselho EAD" (`font-family: var(--font-heading); font-weight: 800; font-size: 14px; color: var(--color-neutral-100)`).
   - Botão Fechar: Ícone X Lucide (SVG inline `stroke: var(--color-neutral-400)`, 16x16px).
4. **Bloco do Usuário**:
   - Avatar circular 32x32px (`background: var(--color-neutral-800); color: var(--color-neutral-100); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800`), contendo iniciais "JP".
   - Nome: "João Pereira" (`font-size: 13px; font-weight: 600; color: var(--color-neutral-100)`).
   - Subtítulo/Role: "Aluno" (`font-size: 11px; color: var(--color-neutral-400)`).
   - Divisor inferior: `border-bottom: 1px solid var(--color-neutral-800); margin-bottom: 12px`.
5. **Navegação Principal (Aluno)**:
   - Items: "Meus Cursos" (Ativo), "Fórum", "Certificados", "Perfil".
   - Item Ativo: `color: var(--color-neutral-100); border-left: 3px solid var(--color-accent); background: color-mix(in srgb, var(--color-accent) 18%, transparent)`.
   - Item Inativo: `color: var(--color-neutral-400); border-left: 3px solid transparent`.
6. **Seção "Continuar Estudando"**:
   - Header da seção: `font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: 18px 20px 8px`.
   - Itens de Curso Rápido: Título do curso 12px + Barra de progresso (altura 4px, fundo `var(--color-neutral-800)`, preenchimento `var(--color-accent)`).
7. **Seção "Lembretes"**:
   - Header da seção: `font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: 18px 20px 8px`.
   - Itens de Lembrete: Ícone Lucide (`bell` ou `clock` em `var(--color-accent)`) + Texto 12px em `var(--color-neutral-400)`.

### **2.2. Visão Admin (Mobile · 390px)**
1. **Estrutura de Drawer**: Idêntica à visão de Aluno (`width: 280px`, fundo `var(--color-neutral-900)`).
2. **Seção "Administração"**:
   - Header da seção: `font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: 0 20px 8px`.
3. **Navegação Admin por Role Spatie**:
   - Items: "Dashboard" (Ativo), "Alunos", "Cursos e Módulos", "Convites", "Certificados", "Relatórios", "Configurações".
   - Mapeamento de ícones Lucide: `dashboard` (home/grid), `users` (alunos), `courses` (book-open), `link` (convites), `awards` (certificados), `reports` (bar-chart), `settings` (engrenagem).

---

## **3. Mapeamento de Componentes Blade**

```blade
{{-- Overlay Backdrop Mobile --}}
<div x-show="sidebarOpen" 
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="sidebarOpen = false"
     class="mobile-sidebar-backdrop"
     style="position: fixed; inset: 0; background: color-mix(in srgb, var(--color-neutral-900) 55%, transparent); z-index: 40;">
</div>

{{-- Drawer Content --}}
<aside x-show="sidebarOpen"
       x-transition:enter="transition ease-out duration-200 transform"
       x-transition:enter-start="-translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-150 transform"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="-translate-x-full"
       class="mobile-sidebar-drawer"
       style="position: fixed; left: 0; top: 0; bottom: 0; width: 280px; background: var(--color-neutral-900); color: var(--color-neutral-400); display: flex; flex-direction: column; padding: 18px 0; box-shadow: var(--shadow-lg); z-index: 50;">
    
    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0 20px 18px;">
        <span style="font-family: var(--font-heading); font-weight: 800; font-size: 14px; color: var(--color-neutral-100);">
            {{ $tenant->name ?? 'Conselho EAD' }}
        </span>
        <button @click="sidebarOpen = false" class="btn btn-ghost btn-icon" style="color: var(--color-neutral-400);" aria-label="Fechar menu">
            <x-ui.icon name="x" width="16" height="16" />
        </button>
    </div>

    {{-- Bloco Usuário --}}
    <div style="display: flex; align-items: center; gap: 10px; padding: 0 20px 18px; border-bottom: 1px solid var(--color-neutral-800); margin-bottom: 12px;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--color-neutral-800); color: var(--color-neutral-100); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">
            {{ auth()->user()->initials ?? 'JP' }}
        </div>
        <div>
            <div style="font-size: 13px; font-weight: 600; color: var(--color-neutral-100);">{{ auth()->user()->name }}</div>
            <div style="font-size: 11px; color: var(--color-neutral-400);">{{ auth()->user()->role_label }}</div>
        </div>
    </div>

    {{-- Itens de Menu --}}
    <nav style="display: flex; flex-direction: column;">
        @foreach($menuItems as $item)
            <a href="{{ route($item['route']) }}" 
               class="sidebar-item {{ $item['active'] ? 'active' : '' }}"
               style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 14px; color: {{ $item['active'] ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ $item['active'] ? 'var(--color-accent)' : 'transparent' }}; background: {{ $item['active'] ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }}; text-decoration: none;">
                <x-ui.icon :name="$item['icon']" width="17" height="17" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</aside>
```

---

## **4. Contrato de Dados (PHP / View Model)**

```php
// Retornado por ViewComposer ou Layout Service
[
    'menuItems' => [
        ['label' => 'Meus Cursos', 'route' => 'student.courses.index', 'icon' => 'home', 'active' => true],
        ['label' => 'Fórum', 'route' => 'student.forum.index', 'icon' => 'message', 'active' => false],
        ['label' => 'Certificados', 'route' => 'student.certificates.index', 'icon' => 'award', 'active' => false],
        ['label' => 'Perfil', 'route' => 'student.profile.edit', 'icon' => 'user', 'active' => false],
    ],
    'quickCourses' => [
        ['title' => 'NR10 — Riscos de Choque Elétrico', 'progress' => '60%'],
        ['title' => 'NR12 — Segurança em Máquinas', 'progress' => '0%'],
    ],
    'reminders' => [
        ['icon' => 'bell', 'text' => 'Quiz do Módulo 1 (NR10) disponível'],
        ['icon' => 'clock', 'text' => 'Certificado NR35 vence em 11 meses'],
    ]
]
```

---

## **5. Guardrails de Testes E2E (Laravel Dusk)**

```php
public function test_mobile_sidebar_drawer_opens_and_closes()
{
    $this->browse(function (Browser $browser) {
        $user = User::factory()->create(['role' => 'aluno']);

        $browser->resize(390, 844)
                ->loginAs($user)
                ->visit('/meus-cursos')
                ->assertDontSeeIn('.mobile-sidebar-drawer', 'Conselho EAD')
                ->click('@mobile-menu-button')
                ->waitFor('.mobile-sidebar-drawer')
                ->assertSeeIn('.mobile-sidebar-drawer', 'Conselho EAD')
                ->assertSeeIn('.mobile-sidebar-drawer', 'João Pereira')
                ->assertSeeIn('.mobile-sidebar-drawer', 'Continuar estudando')
                ->click('.mobile-sidebar-drawer button[aria-label="Fechar menu"]')
                ->waitUntilMissing('.mobile-sidebar-drawer');
    });
}
```
