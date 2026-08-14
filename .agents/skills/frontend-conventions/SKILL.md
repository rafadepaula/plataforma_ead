---
name: frontend-conventions
description: Padrões de código, regras do Modernist Design System, convenções Blade e JavaScript SOLID.
---

# Frontend Conventions (`frontend-conventions`)

Todo frontend segue **Modernist Design System** (Claude Design) + arquitetura de micro-componentes Blade.

---

## Regras do Modernist Design System

1. **Zero-radius (`--radius-sm: 0px`)**
   - MANDATÓRIO: botão, card, modal, input, badge, container = `border-radius: 0px`.
   - NUNCA `rounded`, `rounded-md`, nem qualquer arredondamento.

2. **Proibido hex hardcoded na view**
   - Nenhuma Blade ou CSS com cor hexadecimal crua.
   - Usar token: `var(--color-bg)`, `var(--color-surface)`, `var(--color-text)`, `var(--color-accent)`, `var(--color-accent-2)`, `var(--color-neutral-100...900)`.

3. **Flush-left**
   - Label de botão largo (`.btn-block`), título de hero e cabeçalho sempre `text-align: left`.

4. **Foto preto-e-branco (`.grayscale`)**
   - Avatar de aluno, thumbnail de curso, foto de instrutor sempre dentro de wrapper `.grayscale` (`filter: grayscale(1) contrast(1.08)`).
   - Exceção: logo de Organização (`organizations.logo_path`) mantém cor da marca.

5. **Ícone Lucide inline**
   - Sem Bootstrap Icons (`bi-*`), sem CDN externo. Usar `<x-ui.icon name="search|bell|user|check..." />`.

---

## Componentes `<x-ui.*>`

### Botão
```blade
<x-ui.button variant="primary" block icon="plus">
    Salvar Aluno
</x-ui.button>
```

### Badge
Status semântico, sem cor nova:
- `variant="accent"`: Ativo / Concluído / Publicado (positivo)
- `variant="outline"`: Em andamento / Obrigatório / Em revisão
- `variant="neutral"`: Pendente / Rascunho / Aluno
- `variant="accent-2"`: Em risco / Vencido / Inativo (negativo)

```blade
<x-ui.badge variant="accent">Concluído</x-ui.badge>
```

### Modal
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

## Módulos JavaScript

- **Registro global**: importar em `resources/js/app.js` e expor em `window` (`window.HttpClient`, `window.ModalManager`, `window.NotificationService`).
- **CSRF**: `HttpClient` lê meta tag `csrf-token`. Toda requisição AJAX mutativa manda `X-CSRF-TOKEN`.
- **Acessibilidade de modal**: modal aberto por `ModalManager.open()` recebe `aria-modal="true"` e foco no primeiro elemento interativo.
