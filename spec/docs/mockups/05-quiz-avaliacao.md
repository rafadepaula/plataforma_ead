# **05. Mockup: Quiz de Avaliação**

---

## **1. Visão Geral e Contexto**

- **Identificação da Tela**: Quiz de Avaliação Interativa
- **Rota Laravel**: `/cursos/{slug}/quizzes/{quiz}` (`route('student.quizzes.show')`)
- **Layout Base**: `layouts/app.blade.php`
- **Papel (Role)**: Aluno (`role:aluno`)
- **Objetivo**: Apresentar questões de múltipla escolha com cronômetro regressivo, indicador de progresso e estado ativo das opções selecionadas.

---

## **2. Comportamento nos 3 Breakpoints**

### **2.1. Mobile (390px)**
- **Header**: Topbar `.nav` com título "Quiz · NR10" e cronômetro "06:12" alinhado à direita.
- **Progresso**: Contador "Questão 2 de 6" + Barra de progresso (altura 5px, fundo `var(--color-neutral-200)`, preenchimento `var(--color-accent)` em 33%).
- **Pergunta**: Headline `<h4>` em `font-size: 14px; font-weight: 800; margin-bottom: 12px`.
- **Opções de Resposta**:
  - Empilhadas verticalmente com `gap: 6px`.
  - Opção selecionada: `border: 1px solid var(--color-accent); background: var(--color-accent-100)`.
  - Opção não selecionada: `border: 1px solid var(--color-divider); background: var(--color-surface)`.
- **Navegação**: Botão primário em largura total `<x-ui.button variant="primary" block style="height: 32px; font-size: 12px">` ("Próxima").

### **2.2. Tablet (768px)**
- **Contêiner**: Centralizado em largura de `520px`.
- **Opções de Resposta**: `padding: 12px 14px; font-size: 13px`.
- **Ações**: Flex horizontal com botão "Anterior" (`ghost`) à esquerda e "Próxima" (`primary`) à direita.

### **2.3. Desktop (1920px / 1080p)**
- **Topbar**: Título "Quiz · NR10 — Segurança em Instalações Elétricas" + Timer "06:12".
- **Contêiner Central**: Largura fixa de `680px` (`padding: 40px 24px`).
- **Cabeçalho da Questão**:
  - Flex space-between com `<h6>` "Questão 2 de 6" (`var(--color-neutral-600)`) e `<x-ui.badge variant="outline">` ("Única escolha").
  - Barra de progresso com altura 6px.
- **Título da Pergunta `<h3>`**: `font-size: 20px; font-weight: 800; margin-bottom: 22px`.
- **Opções de Resposta**:
  - Card de opção radio expandido: `padding: 14px 16px; font-size: 14px; border: 1px solid var(--color-divider); cursor: pointer`.
  - Estado Selecionado: `border-color: var(--color-accent); background: var(--color-accent-100)`.
- **Barra de Ação Inferior**:
  - Esquerda: `<x-ui.button variant="ghost">Anterior</x-ui.button>`.
  - Direita: `<x-ui.button variant="primary">Próxima questão</x-ui.button>`.

---

## **3. Mapeamento de Componentes Blade**

```blade
<div style="display: flex; justify-content: center; padding: 40px 24px;">
    <div style="width: 680px; max-width: 100%;">
        
        {{-- Header da Questão --}}
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h6 style="color: var(--color-neutral-600); margin: 0; font-weight: 800;">
                Questão {{ $currentQuestionIndex }} de {{ $totalQuestions }}
            </h6>
            
            <x-ui.badge variant="outline">
                {{ $question->type_label }}
            </x-ui.badge>
        </div>

        {{-- Barra de Progresso --}}
        <div style="height: 6px; background: var(--color-neutral-200); margin-bottom: 26px;">
            <div style="height: 100%; width: {{ ($currentQuestionIndex / $totalQuestions) * 100 }}%; background: var(--color-accent);"></div>
        </div>

        {{-- Pergunta --}}
        <h3 style="font-family: var(--font-heading); font-weight: 800; font-size: 20px; margin-bottom: 22px; color: var(--color-text);">
            {{ $question->statement }}
        </h3>

        {{-- Opções de Resposta --}}
        <form action="{{ route('student.quizzes.answer', [$course->slug, $quiz->id]) }}" method="POST">
            @csrf
            <input type="hidden" name="question_id" value="{{ $question->id }}" />

            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px;">
                @foreach($question->options as $option)
                    @php
                        $isSelected = old('selected_option', $userAnswer) == $option->id;
                    @endphp
                    
                    <label class="radio-card" 
                           style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid {{ $isSelected ? 'var(--color-accent)' : 'var(--color-divider)' }}; background: {{ $isSelected ? 'var(--color-accent-100)' : 'var(--color-surface)' }}; font-size: 14px; cursor: pointer;">
                        <input type="radio" 
                               name="selected_option" 
                               value="{{ $option->id }}" 
                               {{ $isSelected ? 'checked' : '' }} 
                               style="accent-color: var(--color-accent);" />
                        <span>{{ $option->text }}</span>
                    </label>
                @endforeach
            </div>

            {{-- Navegação --}}
            <div style="display: flex; justify-content: space-between; align-items: center;">
                @if($hasPrevious)
                    <x-ui.button variant="ghost" :href="route('student.quizzes.show', [$course->slug, $quiz->id, 'page' => $currentQuestionIndex - 1])">
                        Anterior
                    </x-ui.button>
                @else
                    <div></div>
                @endif

                <x-ui.button variant="primary" type="submit">
                    {{ $isLastQuestion ? 'Finalizar Quiz' : 'Próxima questão' }}
                </x-ui.button>
            </div>
        </form>
    </div>
</div>
```

---

## **4. Guardrails e Testes Laravel Dusk**

```php
public function test_student_can_answer_quiz_question()
{
    $this->browse(function (Browser $browser) {
        $student = User::factory()->create(['role' => 'aluno']);

        $browser->loginAs($student)
                ->visit('/cursos/nr10/quizzes/1')
                ->assertSee('Questão 2 de 6')
                ->assertSee('Qual EPI é obrigatório para trabalho em painéis energizados?')
                ->radio('selected_option', '1')
                ->click('button[type="submit"]')
                ->assertSee('Próxima questão');
    });
}
```
