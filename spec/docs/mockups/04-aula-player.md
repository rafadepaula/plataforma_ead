# **04. Mockup: Aula — Player de Vídeo**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Aula — Player de Vídeo e Navegação de Módulo
- **Rota Laravel**: `/cursos/{slug}/aulas/{lesson}` (`route('student.lessons.show')`)
- **Layout Base**: `layouts/app.blade.php`
- **Papel (Role)**: Aluno (`role:aluno`)
- **Objetivo**: Renderizar o player de vídeo da lição (vídeo YouTube não-listado), exibir informações detalhadas da aula e permitir a navegação sequencial entre as lições do módulo.

---

## **2. Comportamento nos 3 Breakpoints**

### **2.1. Mobile (390px)**
- **Header**: Topbar `.nav` com botão hambúrguer, botão voltar (`arrow-left`), e título compacto "NR10 — Aula 3".
- **Player de Vídeo**: Bloco em topo de página (`height: 180px; width: 100%; background: var(--color-neutral-900)`).
- **Abas de Conteúdo**: Sub-navegação com links "Sobre" (ativo), "Materiais" e "Fórum".
- **Lista de Lições**: Exibida abaixo do conteúdo da aula, empilhada verticalmente com indicação de estado (Concluída = check, Atual = play + destaque accent, Bloqueada = cadeado).

### **2.2. Tablet (768px)**
- **Sidebar**: Modo reduzido em 64px com ícone ativo do curso.
- **Player de Vídeo**: Fundo escuro com `height: 280px`.
- **Navegação de Lições**: Exibida em formato de chips/cards horizontais abaixo do player.

### **2.3. Desktop (1920px / 1080p)**
- **Sidebar Esquerda**: Menu de navegação do aluno (`width: 240px`).
- **Coluna Central (Player & Conteúdo)**:
  - Breadcrumb: `Meus Cursos / NR10 — Segurança em Instalações Elétricas`.
  - Player de Vídeo: Container de alta definição (`height: 380px; background: var(--color-neutral-900)`).
  - Título `<h2>`: "Aula 3 — Riscos de Choque Elétrico".
  - Metadados: `Módulo 1 · 10 min`.
  - Abas: Navegação inferior por "Sobre", "Materiais", "Fórum".
  - Texto descritivo da aula em `max-width: 70ch`.
- **Sidebar Direita (Índice de Lições)**:
  - Painel lateral fixo (`width: 280px; border-left: 2px solid var(--color-divider)`).
  - Título da seção: `Módulo 1 — Fundamentos`.
  - Lista de Aulas:
    - Aula Concluída: Ícone `check` (SVG inline), texto normal.
    - Aula Atual: Ícone `play`, `border-left: 3px solid var(--color-accent); background: var(--color-accent-100); color: var(--color-accent-700)`.
    - Aula Bloqueada: Ícone `lock`, texto/cor em `var(--color-neutral-500)`.

---

## **3. Mapeamento de Componentes Blade**

```blade
<div style="display: flex; height: 100%;">
    {{-- Coluna Central do Player --}}
    <div style="flex: 1; overflow-y: auto; padding: 28px 40px;">
        <nav class="nav" style="padding: 0; border-bottom: 2px solid var(--color-divider); margin-bottom: 12px;">
            <a href="{{ route('student.courses.index') }}">Meus Cursos</a>
            <span class="text-muted" style="font-size: 13px;">/ {{ $course->title }}</span>
        </nav>

        {{-- Container do Player de Vídeo --}}
        <div style="width: 100%; height: 380px; background: var(--color-neutral-900); margin: 18px 0;">
            @if($lesson->video_url)
                <iframe src="{{ $lesson->embed_video_url }}" 
                        style="width: 100%; height: 100%; border: 0;" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            @endif
        </div>

        <h2 style="font-family: var(--font-heading); font-weight: 800; margin-bottom: 6px;">
            {{ $lesson->title }}
        </h2>
        
        <p class="text-muted" style="font-size: 13px; margin-bottom: 16px;">
            {{ $lesson->module->title }} · {{ $lesson->duration_minutes }} min
        </p>

        <nav class="nav" style="padding: 0; border-bottom: 2px solid var(--color-divider); gap: 24px; margin-bottom: 16px;">
            <a href="#sobre" aria-current="page">Sobre</a>
            <a href="#materiais">Materiais</a>
            <a href="#forum">Fórum</a>
        </nav>

        <div style="font-size: 14px; max-width: 70ch; color: var(--color-text);">
            {!! $lesson->description !!}
        </div>
    </div>

    {{-- Sidebar Direita — Índice de Lições --}}
    <div style="width: 280px; flex: none; border-left: 2px solid var(--color-divider); overflow-y: auto; padding: 20px;">
        <h6 style="color: var(--color-neutral-600); margin-bottom: 12px; font-weight: 800;">
            {{ $module->title }}
        </h6>

        <div style="display: flex; flex-direction: column; gap: 4px;">
            @foreach($module->lessons as $item)
                @php
                    $isCurrent = $item->id === $lesson->id;
                    $isCompleted = $item->is_completed_by_user;
                    $isLocked = $item->is_locked_for_user;
                @endphp

                <a href="{{ $isLocked ? '#' : route('student.lessons.show', [$course->slug, $item->id]) }}"
                   style="display: flex; align-items: center; gap: 10px; padding: 10px 8px; border-left: 3px solid {{ $isCurrent ? 'var(--color-accent)' : 'transparent' }}; background: {{ $isCurrent ? 'var(--color-accent-100)' : 'transparent' }}; text-decoration: none;">
                    
                    @if($isCompleted)
                        <x-ui.icon name="check" width="16" height="16" style="color: var(--color-accent);" />
                    @elseif($isLocked)
                        <x-ui.icon name="lock" width="16" height="16" style="color: var(--color-neutral-500);" />
                    @else
                        <x-ui.icon name="play" width="16" height="16" style="color: {{ $isCurrent ? 'var(--color-accent)' : 'var(--color-neutral-700)' }};" />
                    @endif

                    <div style="font-size: 13px; color: {{ $isCurrent ? 'var(--color-accent-700)' : ($isLocked ? 'var(--color-neutral-500)' : 'var(--color-text)') }}; font-weight: {{ $isCurrent ? '600' : '400' }};">
                        {{ $item->title }}
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
```

---

## **4. Guardrails e Testes Laravel Dusk**

```php
public function test_student_can_access_and_play_lesson()
{
    $this->browse(function (Browser $browser) {
        $student = User::factory()->create(['role' => 'aluno']);

        $browser->loginAs($student)
                ->visit('/cursos/nr10/aulas/3')
                ->assertSee('Aula 3 — Riscos de Choque Elétrico')
                ->assertSee('Módulo 1 — Fundamentos')
                ->assertPresent('iframe');
    });
}
```
