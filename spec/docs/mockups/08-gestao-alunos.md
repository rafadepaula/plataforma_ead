# **08. Mockup: Gestão de Alunos**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Gestão de Alunos (Listagem, Filtros, Ações em Lote)
- **Rota Laravel**: `/admin/alunos` (`route('admin.students.index')`)
- **Layout Base**: `layouts/app.blade.php`
- **Papel (Role)**: Admin (`role:admin`) e Gestor (`role:gestor`)
- **Objetivo**: Gestão completa dos alunos matriculados na organização, permitindo busca por nome/e-mail, importação via CSV e inclusão individual de novo aluno.

---

## **2. Comportamento nos 3 Breakpoints**

### **2.1. Mobile (390px)**
- **Header**: Topbar `.nav` com botão hambúrguer, marca "Alunos" e botão ícone primário `plus` (26x26px) à direita.
- **Lista de Alunos**: Linhas compactas (`padding: 8px 0; border-bottom: 1px solid var(--color-divider)`).
  - Avatar circular de 24px com iniciais do aluno.
  - Nome do aluno em `font-size: 12px`.
  - Badge de status (`tag-accent` "Ativo" / `tag-neutral` "Inativo").

### **2.2. Tablet (768px)**
- **Sidebar**: Modo reduzido em 64px com ícone de usuários ativo.
- **Topbar**: Título "Alunos" + Botões de Ação à direita ("Importar CSV" em `variant="secondary"` e "+ Novo" em `variant="primary"`).
- **Tabela**: Exibe 3 colunas (Nome, E-mail e Status).

### **2.3. Desktop (1920px / 1080p)**
- **Sidebar Esquerda**: `width: 220px; background: var(--color-neutral-900)`. Item "Alunos" destacado com `border-left: 3px solid var(--color-accent)`.
- **Topbar Completa**:
  - Marca: "Gestão de Alunos".
  - Campo de Busca: `<div style="position:relative; width:240px">` com ícone SVG de lupa absoluto à esquerda (`padding-left: 32px; min-height: 32px; font-size: 13px`).
  - Botão Secundário: `<x-ui.button variant="secondary">` com ícone Lucide `upload` ("Importar CSV").
  - Botão Primário: `<x-ui.button variant="primary">` ("+ Novo Aluno").
- **Tabela Principal**:
  - Encapsulada no componente `<x-ui.table>` (`padding: 24px 48px`).
  - Colunas:
    1. Nome (font-weight 600)
    2. E-mail (`.text-muted`)
    3. Cursos matriculados (`.text-muted`, ex: "2 cursos")
    4. Status (`<x-ui.badge variant="accent">` "Ativo" ou `<x-ui.badge variant="neutral">` "Inativo")
    5. Ações (`<x-ui.button variant="ghost" style="height: 28px; font-size: 12px">`Editar`</x-ui.button>`).

---

## **3. Mapeamento de Componentes Blade**

```blade
<x-slot:title>Gestão de Alunos — Plataforma EAD</x-slot:title>

<div class="admin-students-container">
    {{-- Header da Área de Gestão --}}
    <div class="nav" style="border-bottom: 2px solid var(--color-divider); padding: 14px 48px; gap: 12px; margin-bottom: 24px;">
        <span class="nav-brand" style="font-size: 16px; font-weight: 800;">Gestão de Alunos</span>
        
        {{-- Campo de Busca --}}
        <form action="{{ route('admin.students.index') }}" method="GET" style="position: relative; width: 240px; margin-left: 20px;">
            <x-ui.icon name="search" width="14" height="14" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); opacity: 0.5;" />
            <input class="input" 
                   name="q" 
                   value="{{ request('q') }}" 
                   placeholder="Buscar aluno..." 
                   style="padding-left: 32px; min-height: 32px; font-size: 13px;" />
        </form>

        <div style="margin-left: auto; display: flex; gap: 10px;">
            <x-ui.button variant="secondary" :href="route('admin.students.import')">
                <x-ui.icon name="upload" width="14" height="14" />
                <span>Importar CSV</span>
            </x-ui.button>

            <x-ui.button variant="primary" :href="route('admin.students.create')">
                + Novo Aluno
            </x-ui.button>
        </div>
    </div>

    {{-- Tabela de Alunos --}}
    <x-ui.table>
        <x-slot:header>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Cursos matriculados</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </x-slot:header>

        @foreach($students as $student)
            <tr>
                <td style="font-weight: 600;">{{ $student->name }}</td>
                <td class="text-muted">{{ $student->email }}</td>
                <td class="text-muted">{{ $student->enrollments_count }} cursos</td>
                <td>
                    <x-ui.badge :variant="$student->is_active ? 'accent' : 'neutral'">
                        {{ $student->is_active ? 'Ativo' : 'Inativo' }}
                    </x-ui.badge>
                </td>
                <td>
                    <x-ui.button variant="ghost" style="height: 28px; font-size: 12px;" :href="route('admin.students.edit', $student->id)">
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
// AdminStudentController@index
[
    'students' => [
        [
            'id' => 1,
            'name' => 'Ana Souza',
            'email' => 'ana.souza@email.com',
            'enrollments_count' => 2,
            'is_active' => true,
        ],
        [
            'id' => 2,
            'name' => 'João Pereira',
            'email' => 'joao.pereira@email.com',
            'enrollments_count' => 3,
            'is_active' => true,
        ],
        [
            'id' => 3,
            'name' => 'Renata Dias',
            'email' => 'renata.dias@email.com',
            'enrollments_count' => 1,
            'is_active' => false,
        ],
    ]
]
```

---

## **5. Guardrails e Testes Laravel Dusk**

```php
public function test_admin_can_filter_and_list_students()
{
    $this->browse(function (Browser $browser) {
        $admin = User::factory()->create(['role' => 'admin']);

        $browser->loginAs($admin)
                ->visit('/admin/alunos')
                ->assertSee('Gestão de Alunos')
                ->assertSee('Ana Souza')
                ->assertSee('Importar CSV')
                ->type('q', 'João')
                ->keys('input[name="q"]', '{enter}')
                ->assertSee('João Pereira');
    });
}
```
