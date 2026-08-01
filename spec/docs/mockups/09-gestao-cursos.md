# **09. Mockup: Gestão de Cursos e Módulos**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Gestão de Cursos e Módulos
- **Rota Laravel**: `/admin/cursos` (`route('admin.courses.index')`)
- **Layout Base**: `layouts/app.blade.php`
- **Papel (Role)**: Admin (`role:admin`) e Gestor (`role:gestor`)
- **Objetivo**: Gestão do catálogo de cursos da organização, permitindo criar novos cursos, estruturar módulos, ordenar a sequência de exibição via drag-and-drop e alternar status entre Publicado e Rascunho.

---

## **2. Comportamento nos 3 Breakpoints**

### **2.1. Mobile (390px)**
- **Header**: Topbar `.nav` com botão hambúrguer, marca "Cursos" e botão ícone `plus` (26x26px) à direita.
- **Lista de Cursos**: Linhas compactas com título do curso à esquerda e `<x-ui.badge>` de status à direita.

### **2.2. Tablet (768px)**
- **Sidebar**: Modo reduzido em 64px com ícone de cursos ativo.
- **Topbar**: Título "Cursos e Módulos" + Botão "+ Novo Curso" (`variant="primary"`).
- **Grid de Cards**: 2 colunas (`grid-template-columns: 1fr 1fr; gap: 12px; padding: 16px 24px`).
  - Cards `.card.elev-sm` exibindo título, badge de status e metadados (quantidade de módulos e carga horária).

### **2.3. Desktop (1920px / 1080p)**
- **Sidebar Esquerda**: `width: 220px; background: var(--color-neutral-900)`. Item "Cursos e Módulos" ativo (`border-left: 3px solid var(--color-accent)`).
- **Topbar**: Marca "Gestão de Cursos e Módulos" + Botão primário `<x-ui.button variant="primary">` ("+ Novo Curso") à direita.
- **Tabela de Cursos**:
  - Componente `<x-ui.table>` (`padding: 24px 48px`).
  - Colunas:
    1. Reordenação (ícone de arrastar `⠿` em `color: var(--color-neutral-500); width: 24px`)
    2. Curso (Título em font-weight 600)
    3. Módulos (`.text-muted`, ex: "4 módulos")
    4. Carga horária (`.text-muted`, ex: "4h")
    5. Status (`<x-ui.badge variant="accent">` "Publicado" ou `<x-ui.badge variant="neutral">` "Rascunho").

---

## **3. Mapeamento de Componentes Blade**

```blade
<x-slot:title>Gestão de Cursos — Plataforma EAD</x-slot:title>

<div class="admin-courses-container">
    {{-- Topbar de Ações --}}
    <div class="nav" style="border-bottom: 2px solid var(--color-divider); padding: 14px 48px; gap: 12px; margin-bottom: 24px;">
        <span class="nav-brand" style="font-size: 16px; font-weight: 800;">Gestão de Cursos e Módulos</span>
        
        <div style="margin-left: auto;">
            <x-ui.button variant="primary" :href="route('admin.courses.create')">
                + Novo Curso
            </x-ui.button>
        </div>
    </div>

    {{-- Tabela de Cursos --}}
    <x-ui.table>
        <x-slot:header>
            <tr>
                <th style="width: 24px;"></th>
                <th>Curso</th>
                <th>Módulos</th>
                <th>Carga horária</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </x-slot:header>

        @foreach($courses as $course)
            <tr>
                <td style="color: var(--color-neutral-500); cursor: grab;">⠿</td>
                <td style="font-weight: 600;">{{ $course->title }}</td>
                <td class="text-muted">{{ $course->modules_count }} módulos</td>
                <td class="text-muted">{{ $course->workload_hours }}h</td>
                <td>
                    <x-ui.badge :variant="$course->is_published ? 'accent' : 'neutral'">
                        {{ $course->is_published ? 'Publicado' : 'Rascunho' }}
                    </x-ui.badge>
                </td>
                <td>
                    <x-ui.button variant="ghost" style="height: 28px; font-size: 12px;" :href="route('admin.courses.edit', $course->id)">
                        Editar
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</div>
```

---

## **4. Contrato de Dados (PHP / View Model)**

```php
// AdminCourseController@index
[
    'courses' => [
        [
            'id' => 1,
            'title' => 'NR10 — Segurança em Instalações Elétricas',
            'modules_count' => 4,
            'workload_hours' => 4,
            'is_published' => true,
        ],
        [
            'id' => 2,
            'title' => 'NR12 — Segurança em Máquinas',
            'modules_count' => 3,
            'workload_hours' => 3,
            'is_published' => true,
        ],
        [
            'id' => 3,
            'title' => 'Onboarding do Associado',
            'modules_count' => 2,
            'workload_hours' => 1,
            'is_published' => false,
        ],
    ]
]
```

---

## **5. Guardrails e Testes Laravel Dusk**

```php
public function test_admin_can_view_course_management_list()
{
    $this->browse(function (Browser $browser) {
        $admin = User::factory()->create(['role' => 'admin']);

        $browser->loginAs($admin)
                ->visit('/admin/cursos')
                ->assertSee('Gestão de Cursos e Módulos')
                ->assertSee('NR10 — Segurança em Instalações Elétricas')
                ->assertSee('Publicado')
                ->assertSee('+ Novo Curso');
    });
}
```
