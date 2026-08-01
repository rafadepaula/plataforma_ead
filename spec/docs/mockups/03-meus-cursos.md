# **03. Mockup: Meus Cursos (Visão Aluno)**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Meus Cursos (Visão Aluno)
- **Rota Laravel**: `/meus-cursos` (`route('student.courses.index')`)
- **Layout Base**: `layouts/app.blade.php`
- **Papel (Role)**: Aluno (`role:aluno`)
- **Objetivo**: Listar os cursos em que o aluno está matriculado, indicando status de progresso, categoria e ação primária imediata.

---

## **2. Comportamento nos 3 Breakpoints**

### **2.1. Mobile (390px)**
- **Navegação**: Topbar compacta com botão hambúrguer para abrir a drawer de navegação e título "Meus Cursos".
- **Lista de Cursos**: Coluna única com `padding: 14px; gap: 12px`.
- **Card de Curso**:
  - Imagem de capa: `height: 90px; width: 100%` envolvida por `.grayscale`.
  - Cabeçalho do Card: `.card-kicker` (categoria, ex: "NR") à esquerda e `<x-ui.badge>` de status à direita.
  - Título: `.card-title` em `font-size: 13px; font-weight: 800`.
  - Barra de Progresso: Altura 5px, fundo `var(--color-neutral-200)`, fill em `var(--color-accent)` proporcional ao progresso.
  - Botão CTA: `<x-ui.button variant="primary" block style="height: 30px; font-size: 12px">` ("Continuar", "Iniciar" ou "Revisar").

### **2.2. Tablet (768px)**
- **Navegação Lateral**: Sidebar colapsada em modo ícones (`width: 64px; background: var(--color-neutral-900)`).
- **Topbar**: Marca "Meus Cursos" + Avatar com iniciais "JP" à direita.
- **Grid de Cursos**: 2 colunas (`grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 24px`).
- **Card de Curso**: Imagem de capa em `height: 110px`, card elevação `.elev-sm`.

### **2.3. Desktop (1920px / 1080p)**
- **Navegação Lateral**: Sidebar expandida (`width: 240px; background: var(--color-neutral-900)`).
- **Topbar**: Marca "Meus Cursos" + Bloco com avatar 30px e nome "João Pereira".
- **Cabeçalho de Página**:
  - `<h1>` em `font-size: 26px; font-weight: 800; margin-bottom: 4px`: "Meus Cursos".
  - Subtítulo `.text-muted`: "Você está matriculado em {{ $courses->count() }} cursos."
- **Grid de Cursos**: 3 colunas iguais (`grid-template-columns: repeat(3, 1fr); gap: 20px; padding: 32px 48px`).
- **Anatomia do Card de Curso**:
  - Capa: `<img class="grayscale" style="width:100%; height:150px; object-fit:cover">`.
  - Corpo: `padding: 14px 16px`.
  - Linha Topo: `.card-kicker` ("NR") à esquerda e Badge alinhado à direita.
  - Título: `.card-title` (`font-size: 16px; font-weight: 800; margin-bottom: 8px`).
  - Progresso: Altura 6px, fundo `var(--color-neutral-200)`, preenchimento `var(--color-accent)` em `width: {{ $progress }}%`.
  - CTA: `<x-ui.button variant="primary" block>`Continuar`</x-ui.button>`.

---

## **3. Mapeamento de Componentes Blade**

```blade
<x-slot:title>Meus Cursos — Plataforma EAD</x-slot:title>

<div class="student-courses-container">
    <div style="margin-bottom: 22px;">
        <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 26px; margin-bottom: 4px;">
            Meus Cursos
        </h1>
        <p class="text-muted" style="font-size: 13px;">
            Você está matriculado em {{ count($courses) }} cursos.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
        @foreach($courses as $course)
            <x-ui.card elevate="sm" style="padding: 0; overflow: hidden;">
                {{-- Capa com Grayscale --}}
                <div class="grayscale" style="width: 100%; height: 150px;">
                    <img src="{{ $course->cover_url }}" 
                         alt="{{ $course->title }}" 
                         style="width: 100%; height: 100%; object-fit: cover;" />
                </div>

                {{-- Conteúdo --}}
                <div style="padding: 14px 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span class="card-kicker" style="margin: 0;">{{ $course->category_code }}</span>
                        
                        <x-ui.badge :variant="$course->status_badge_variant">
                            {{ $course->status_label }}
                        </x-ui.badge>
                    </div>

                    <div class="card-title" style="font-size: 16px; margin-bottom: 8px;">
                        {{ $course->title }}
                    </div>

                    {{-- Barra de Progresso --}}
                    <div style="height: 6px; background: var(--color-neutral-200); margin-bottom: 10px;">
                        <div style="height: 100%; width: {{ $course->progress_percent }}%; background: var(--color-accent);"></div>
                    </div>

                    <x-ui.button variant="primary" block :href="route('student.courses.show', $course->slug)">
                        {{ $course->cta_label }}
                    </x-ui.button>
                </div>
            </x-ui.card>
        @endforeach
    </div>
</div>
```

---

## **4. Contrato de Dados (PHP / View Model)**

```php
// StudentCourseController@index
[
    'courses' => [
        [
            'id' => 1,
            'title' => 'NR10 — Segurança em Instalações Elétricas',
            'category_code' => 'NR',
            'status_label' => 'Em andamento',
            'status_badge_variant' => 'accent', // tag-accent
            'progress_percent' => 60,
            'cta_label' => 'Continuar',
            'cover_url' => asset('images/nr10.jpg'),
            'slug' => 'nr10-seguranca-em-instalacoes-eletricas',
        ],
        [
            'id' => 2,
            'title' => 'NR12 — Segurança em Máquinas',
            'category_code' => 'NR',
            'status_label' => 'Obrigatório',
            'status_badge_variant' => 'outline', // tag-outline
            'progress_percent' => 0,
            'cta_label' => 'Iniciar',
            'cover_url' => asset('images/nr12.jpg'),
            'slug' => 'nr12-seguranca-em-maquinas',
        ],
        [
            'id' => 3,
            'title' => 'NR35 — Trabalho em Altura',
            'category_code' => 'NR',
            'status_label' => 'Concluído',
            'status_badge_variant' => 'neutral', // tag-neutral
            'progress_percent' => 100,
            'cta_label' => 'Revisar',
            'cover_url' => asset('images/nr35.jpg'),
            'slug' => 'nr35-trabalho-em-altura',
        ],
    ]
]
```

---

## **5. Guardrails e Testes Laravel Dusk**

```php
public function test_student_can_view_enrolled_courses()
{
    $this->browse(function (Browser $browser) {
        $student = User::factory()->create(['role' => 'aluno']);

        $browser->loginAs($student)
                ->visit('/meus-cursos')
                ->assertSee('Meus Cursos')
                ->assertSee('NR10 — Segurança em Instalações Elétricas')
                ->assertSee('Em andamento')
                ->assertSee('Continuar')
                ->assertSee('NR12 — Segurança em Máquinas')
                ->assertSee('Iniciar');
    });
}
```
