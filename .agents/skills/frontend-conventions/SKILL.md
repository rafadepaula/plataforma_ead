---
name: frontend-conventions
description: Padrões de Código, Diretrizes do Modernist Design System, Convenções Blade e JavaScript SOLID.
---

# Frontend Conventions (`frontend-conventions`)

## Visão Geral e Guardrails Visuais

Todo o desenvolvimento frontend da Plataforma EAD deve seguir rigorosamente as convenções do **Modernist Design System** (Claude Design) e a arquitetura de micro-componentes Blade.

---

## Diretrizes do Modernist Design System

1. **Regra Zero-Radius (`--radius-sm: 0px`)**:
   - **MANDATÓRIO**: Todos os cantos de botões, cards, modais, inputs, badges e containers devem possuir `border-radius: 0px`.
   - NUNCA adicionar `rounded`, `rounded-md` ou qualquer arredondamento de canto.

2. **Proibição de Hex Hardcoded nas Views**:
   - Nenhuma view Blade ou arquivo CSS deve conter cores hexadecimais brutas.
   - Sempre utilizar tokens globais CSS: `var(--color-bg)`, `var(--color-surface)`, `var(--color-text)`, `var(--color-accent)`, `var(--color-accent-2)`, `var(--color-neutral-100...900)`.

3. **Alinhamento Flush-Left**:
   - Rótulos de botões largos (`.btn-block`), títulos de hero e textos de cabeçalho devem ser sempre alinhados à esquerda (`text-align: left`).

4. **Fotografia Conteudista em Preto-e-Branco (`.grayscale`)**:
   - Avatares de alunos, thumbnails de cursos e fotos de instrutores devem obrigatoriamente estar envolvidos em wrapper `.grayscale` (`filter: grayscale(1) contrast(1.08)`).
   - *Exceção*: Logos de Organização (`organizations.logo_path`) mantêm suas cores originais da marca.

5. **Ícones Lucide Inline**:
   - Não utilizar Bootstrap Icons (`bi-*`) nem carregar CDNs externos. Usar `<x-ui.icon name="search|bell|user|check..." />`.

---

## Convenções de Componentes Blade `<x-ui.*>`

### Botão (`<x-ui.button>`)
```blade
<x-ui.button variant="primary" block icon="plus">
    Salvar Aluno
</x-ui.button>
```

### Tag/Badge (`<x-ui.badge>`)
- Mapeamento semântico de status sem introdução de cores extras:
  - `variant="accent"`: Ativo / Concluído / Publicado (positivo)
  - `variant="outline"`: Em andamento / Obrigatório / Em revisão
  - `variant="neutral"`: Pendente / Rascunho / Aluno
  - `variant="accent-2"`: Em risco / Vencido / Inativo (negativo)

```blade
<x-ui.badge variant="accent">Concluído</x-ui.badge>
```

### Modal / Diálogo (`<x-ui.modal>`)
```blade
<x-ui.modal id="confirm-delete-modal" title="Confirmar Exclusão">
    <p>Tem certeza de que deseja remover este aluno?</p>
    <x-slot:actions>
        <x-ui.button variant="ghost" data-modal-dismiss="true">Cancelar</x-ui.button>
        <x-ui.button variant="primary" id="btn-confirm-delete">Excluir</x-ui.button>
    </x-slot:actions>
</x-ui.modal>
```

---

## Convenções Módulos JavaScript

- **Importação e Registro Global**: Importar módulos em `resources/js/app.js` e expô-los em `window` (`window.HttpClient`, `window.ModalManager`, `window.NotificationService`).
- **CSRF Token**: `HttpClient` extrai automaticamente a meta tag `csrf-token`. Toda requisição AJAX mutativa deve incluir `X-CSRF-TOKEN`.
- **Acessibilidade de Modais**: Todo modal aberto via `ModalManager.open()` deve receber `aria-modal="true"` e foco no primeiro elemento interativo.
