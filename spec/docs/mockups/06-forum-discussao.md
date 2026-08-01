# **06. Mockup: Fórum de Discussão**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Fórum de Discussão (Lista de Tópicos e Thread Completa)
- **Rota Laravel**: `/cursos/{slug}/forum` e `/cursos/{slug}/forum/{topic}` (`route('student.forum.index')`)
- **Layout Base**: `layouts/app.blade.php`
- **Papel (Role)**: Aluno (`role:aluno`), Gestor (`role:gestor`) e Professor (`role:admin`)
- **Objetivo**: Prover um ambiente de dúvidas e trocas entre alunos e professores por curso, com suporte a visualização split-screen no desktop.

---

## **2. Comportamento nos 3 Breakpoints**

### **2.1. Mobile (390px)**
- **Header**: Topbar `.nav` com título "Fórum · NR10".
- **Ação Principal**: Botão primário full-width `<x-ui.button variant="primary" block style="height: 30px; font-size: 12px; margin-bottom: 10px">` ("+ Novo Tópico").
- **Lista de Tópicos**: Cards compactos empilhados (`.card.elev-sm`).
  - Badge do autor: `<x-ui.badge variant="neutral">` ("Aluno") ou `<x-ui.badge variant="accent">` ("Professor").
  - Título do Tópico: `.card-title` em `font-size: 13px; font-weight: 800`.
  - Metadados: Autor e número de respostas em `font-size: 10px`.

### **2.2. Tablet (768px)**
- **Navegação**: Sidebar colapsada em 64px.
- **Layout Split (Visão Dividida)**:
  - Painel de Tópicos (esquerda, `width: 240px; border-right: 2px solid var(--color-divider)`).
  - Thread Selecionada (direita, flex 1): Exibe o card de abertura do tópico e lista de respostas com campo de envio compacto.

### **2.3. Desktop (1920px / 1080p)**
- **Sidebar**: Navegação global (`width: 240px`).
- **Painel Lateral do Fórum (Lista de Tópicos)**:
  - Largura fixa `320px; border-right: 2px solid var(--color-divider); padding: 20px`.
  - Header: Marca "Fórum · NR10" + Botão `<x-ui.button variant="primary" style="height: 32px; font-size: 13px">+ Novo Tópico</x-ui.button>`.
  - Item de Tópico: `padding: 11px 6px; border-bottom: 1px solid var(--color-divider)`.
    - Badge: `<x-ui.badge variant="neutral">Aluno</x-ui.badge>` ou `<x-ui.badge variant="accent">Professor</x-ui.badge>`.
    - Título: `font-size: 13px; font-weight: 600`.
    - Subtítulo: `font-size: 11px; color: var(--color-neutral-600)` (ex: "João Pereira · Hoje · 2 respostas").
- **Painel Principal da Thread (Detalhe do Tópico)**:
  - Fundo/Padding: `padding: 28px 40px`.
  - **Card de Pergunta Principal**:
    - `.card.elev-sm` com `background: var(--color-accent-100); border: 1px solid var(--color-accent-300); margin-bottom: 18px`.
    - Cabeçalho do Autor: Avatar circular 26px com iniciais "JP", nome "João Pereira", badge `tag-neutral`.
    - Título: `.card-title` ("Dúvida sobre aterramento temporário").
    - Corpo: `.card-body` ("Na aula 3, o equipamento de aterramento temporário deve ser instalado antes ou depois do teste de ausência de tensão?").
  - **Thread de Respostas**:
    - Item de Resposta: `padding: 12px 0; border-bottom: 1px solid var(--color-divider)`.
    - Autor da Resposta: Avatar circular 24px com iniciais ("RL" para Eng. Roberto Lima), nome e badge `tag-accent` ("Professor").
    - Texto da resposta.
  - **Campo de Envio Rápido**:
    - Layout flex horizontal com `<input class="input" placeholder="Escreva uma resposta..." style="flex: 1" />` e `<x-ui.button variant="primary">Enviar Resposta</x-ui.button>`.

---

## **3. Mapeamento de Componentes Blade**

```blade
<div style="display: flex; height: 100%;">
    {{-- Painel Lateral — Lista de Tópicos --}}
    <div style="width: 320px; flex: none; border-right: 2px solid var(--color-divider); overflow-y: auto; padding: 20px;">
        <div class="nav" style="padding: 0 0 12px; border-bottom: 2px solid var(--color-divider); margin-bottom: 12px;">
            <span class="nav-brand" style="font-size: 14px; font-weight: 800;">Fórum · {{ $course->code }}</span>
            
            <x-ui.button variant="primary" style="margin-left: auto; height: 32px; font-size: 13px;" :href="route('student.forum.create', $course->slug)">
                + Novo Tópico
            </x-ui.button>
        </div>

        @foreach($topics as $topic)
            <a href="{{ route('student.forum.show', [$course->slug, $topic->id]) }}" 
               style="display: block; padding: 11px 6px; border-bottom: 1px solid var(--color-divider); text-decoration: none; color: var(--color-text);">
                <x-ui.badge :variant="$topic->author_role === 'Professor' ? 'accent' : 'neutral'" style="padding: 1px 6px; margin-bottom: 4px;">
                    {{ $topic->author_role }}
                </x-ui.badge>

                <div style="font-size: 13px; font-weight: 600; margin-bottom: 2px;">
                    {{ $topic->title }}
                </div>

                <div style="font-size: 11px; color: var(--color-neutral-600);">
                    {{ $topic->author_name }} · {{ $topic->created_at_human }} · {{ $topic->replies_count }} respostas
                </div>
            </a>
        @endforeach
    </div>

    {{-- Painel Principal — Thread Selecionada --}}
    <div style="flex: 1; overflow-y: auto; padding: 28px 40px;">
        @if($activeTopic)
            {{-- Card da Pergunta Principal --}}
            <x-ui.card elevate="sm" style="background: var(--color-accent-100); border: 1px solid var(--color-accent-300); margin-bottom: 18px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <div style="width: 26px; height: 26px; border-radius: 50%; background: var(--color-neutral-800); color: var(--color-neutral-100); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800;">
                        {{ $activeTopic->author->initials }}
                    </div>
                    <span style="font-size: 13px; font-weight: 600;">{{ $activeTopic->author->name }}</span>
                    <x-ui.badge variant="neutral" style="padding: 1px 6px;">{{ $activeTopic->author_role }}</x-ui.badge>
                </div>

                <div class="card-title" style="font-size: 16px; font-weight: 800; margin-bottom: 8px;">
                    {{ $activeTopic->title }}
                </div>

                <div class="card-body" style="font-size: 14px;">
                    {{ $activeTopic->content }}
                </div>
            </x-ui.card>

            {{-- Thread de Respostas --}}
            <div style="display: flex; flex-direction: column;">
                @foreach($activeTopic->replies as $reply)
                    <div style="padding: 12px 0; border-bottom: 1px solid var(--color-divider);">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: {{ $reply->author_role === 'Professor' ? 'var(--color-accent-700)' : 'var(--color-neutral-800)' }}; color: var(--color-neutral-100); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800;">
                                {{ $reply->author->initials }}
                            </div>
                            <span style="font-size: 13px; font-weight: 600;">{{ $reply->author->name }}</span>
                            <x-ui.badge :variant="$reply->author_role === 'Professor' ? 'accent' : 'neutral'" style="padding: 1px 6px;">
                                {{ $reply->author_role }}
                            </x-ui.badge>
                        </div>
                        <div style="font-size: 13px; color: var(--color-text);">
                            {{ $reply->content }}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Formulário de Resposta --}}
            <form action="{{ route('student.forum.reply', [$course->slug, $activeTopic->id]) }}" method="POST" style="display: flex; gap: 10px; margin-top: 16px;">
                @csrf
                <input class="input" name="content" placeholder="Escreva uma resposta..." style="flex: 1;" required />
                <x-ui.button variant="primary" type="submit">Enviar Resposta</x-ui.button>
            </form>
        @endif
    </div>
</div>
```

---

## **4. Guardrails e Testes Laravel Dusk**

```php
public function test_forum_displays_topic_thread_and_allows_reply()
{
    $this->browse(function (Browser $browser) {
        $student = User::factory()->create(['role' => 'aluno']);

        $browser->loginAs($student)
                ->visit('/cursos/nr10/forum')
                ->assertSee('Dúvida sobre aterramento temporário')
                ->assertSee('Eng. Roberto Lima')
                ->type('content', 'Obrigado pelo esclarecimento!')
                ->click('button[type="submit"]')
                ->assertSee('Obrigado pelo esclarecimento!');
    });
}
```
