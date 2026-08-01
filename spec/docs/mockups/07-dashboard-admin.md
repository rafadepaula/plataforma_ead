# **07. Mockup: Dashboard Admin**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Dashboard Administrativo / Gestor
- **Rota Laravel**: `/admin/dashboard` (`route('admin.dashboard')`)
- **Layout Base**: `layouts/app.blade.php`
- **Papel (Role)**: Admin (`role:admin`) e Gestor (`role:gestor`)
- **Objetivo**: Apresentar visões consolidadas das métricas da organização (total de alunos ativos, certificados emitidos, taxa de conclusão e cursos) e tabela de matrículas recentes.

---

## **2. Comportamento nos 3 Breakpoints**

### **2.1. Mobile (390px)**
- **Header**: Topbar `.nav` com botão hambúrguer para a drawer lateral e título "Dashboard".
- **Cards de Métricas (Stat Cards)**: Grid de 2 colunas (`grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px`).
  - `.card.elev-sm` em padding 10px.
  - `.card-kicker` em 9px uppercase.
  - Valor principal em `font-family: var(--font-heading); font-weight: 800; font-size: 18px`.
- **Matrículas Recentes**: Lista de linhas compactas com nome do aluno à esquerda e `<x-ui.badge>` de status à direita.

### **2.2. Tablet (768px)**
- **Sidebar**: Modo reduzido em 64px (`background: var(--color-neutral-900)`).
- **Cards de Métricas**: Grid de 4 colunas (`grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px`).
  - Valor principal em `font-size: 20px; font-weight: 800`.
- **Matrículas Recentes**: Lista com nome do aluno, curso e badge.

### **2.3. Desktop (1920px / 1080p)**
- **Sidebar Esquerda**: `width: 220px; background: var(--color-neutral-900)`. Rótulo uppercase "Administração". Item "Dashboard" ativo com `border-left: 3px solid var(--color-accent)`.
- **Conteúdo Principal**: `padding: 28px 40px`.
- **Título `<h1>`**: `font-size: 26px; font-weight: 800; margin-bottom: 20px`: "Dashboard".
- **Grid de Stat Cards**: 4 colunas iguais (`grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px`).
  - Cada card `<x-ui.stat-card>`: `.card.elev-sm` contendo `.card-kicker` (label), valor numérico de impacto em `font-size: 30px; font-weight: 800; font-family: var(--font-heading)` e delta percentual opcional em `.card-meta` com `<x-ui.badge variant="accent">` ("+4,2%").
- **Tabela de Matrículas Recentes**:
  - `<h4>` em `margin-bottom: 12px`: "Matrículas recentes".
  - Componente `<x-ui.table>` renderizando a tabela `.table` com 3 colunas: Nome, Curso e Status.

---

## **3. Mapeamento de Componentes Blade**

```blade
<x-slot:title>Dashboard Administrativo — Plataforma EAD</x-slot:title>

<div class="admin-dashboard-container">
    <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 26px; margin-bottom: 20px;">
        Dashboard
    </h1>

    {{-- Grid de 4 Stat Cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <x-ui.stat-card kicker="Alunos ativos" value="{{ $stats['active_students'] }}" delta="+4,2%" />
        <x-ui.stat-card kicker="Certificados" value="{{ $stats['certificates_issued'] }}" delta="+12%" />
        <x-ui.stat-card kicker="Conclusão" value="{{ $stats['completion_rate'] }}%" />
        <x-ui.stat-card kicker="Cursos" value="{{ $stats['courses_count'] }}" />
    </div>

    {{-- Tabela de Matrículas Recentes --}}
    <h4 style="font-family: var(--font-heading); font-weight: 800; margin-bottom: 12px;">
        Matrículas recentes
    </h4>

    <x-ui.table>
        <x-slot:header>
            <tr>
                <th>Nome</th>
                <th>Curso</th>
                <th>Status</th>
            </tr>
        </x-slot:header>

        @foreach($recentEnrollments as $enrollment)
            <tr>
                <td style="font-weight: 600;">{{ $enrollment->student_name }}</td>
                <td class="text-muted">{{ $enrollment->course_name }}</td>
                <td>
                    <x-ui.badge :variant="$enrollment->status_badge_variant">
                        {{ $enrollment->status_label }}
                    </x-ui.badge>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</div>
```

---

## **4. Contrato de Dados (PHP / View Model)**

```php
// AdminDashboardController@index
[
    'stats' => [
        'active_students' => 318,
        'certificates_issued' => 142,
        'completion_rate' => 84,
        'courses_count' => 6,
    ],
    'recentEnrollments' => [
        [
            'student_name' => 'João Pereira',
            'course_name' => 'NR12 — Segurança em Máquinas',
            'status_label' => 'Nova',
            'status_badge_variant' => 'accent', // tag-accent
        ],
        [
            'student_name' => 'Ana Costa',
            'course_name' => 'NR35 — Trabalho em Altura',
            'status_label' => 'Concluído',
            'status_badge_variant' => 'neutral', // tag-neutral
        ],
        [
            'student_name' => 'Marcos Silva',
            'course_name' => 'NR10 — Segurança em Instalações Elétricas',
            'status_label' => 'Em risco',
            'status_badge_variant' => 'accent-2', // tag-accent-2
        ],
    ]
]
```

---

## **5. Guardrails e Testes Laravel Dusk**

```php
public function test_admin_dashboard_renders_metrics_and_table()
{
    $this->browse(function (Browser $browser) {
        $admin = User::factory()->create(['role' => 'admin']);

        $browser->loginAs($admin)
                ->visit('/admin/dashboard')
                ->assertSee('Dashboard')
                ->assertSee('Alunos ativos')
                ->assertSee('318')
                ->assertSee('Matrículas recentes')
                ->assertSee('João Pereira');
    });
}
```
