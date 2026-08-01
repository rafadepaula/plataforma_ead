---
name: frontend-maintenance
description: Guia de Manutenção, Compilação de Assets, Execução de Testes Dusk E2E e Resolução de Problemas.
---

# Frontend Maintenance (`frontend-maintenance`)

## Overview

Este guia estabelece os procedimentos para manutenção, solução de problemas e verificação automatizada da camada de frontend da Plataforma EAD.

---

## Procedimentos de Compilação e Build

### 1. Compilação via Vite em Ambiente Sail
```bash
# Build de produção de CSS e JS
vendor/bin/sail npm run build

# Execução em ambiente de desenvolvimento (Hot Module Replacement)
vendor/bin/sail npm run dev
```

---

## Suíte de Testes Automatizados E2E Laravel Dusk

A suíte de testes de interface garante a integridade de layouts e componentes Blade.

### Execução dos Testes Dusk via Sail
```bash
# Executar todos os testes de navegador Dusk
vendor/bin/sail artisan dusk

# Executar especificamente o teste de renderização de layout
vendor/bin/sail artisan dusk --filter=LayoutRenderingTest

# Executar especificamente o teste de componentes Blade UI
vendor/bin/sail artisan dusk --filter=BladeComponentsTest
```

---

## Resolução de Problemas Frequentes (Troubleshooting)

### 1. Teste Dusk Falhando por `border-radius` Divergente
- **Sintoma**: `LayoutRenderingTest` falha indicando `Expected border-radius to enforce 0px`.
- **Causa**: Inclusão indevida de regras CSS com `border-radius` maior que zero ou inclusão do CSS Reboot do Bootstrap.
- **Solução**: Garantir que `app.css` mantenha `--radius-sm: 0px`, `--radius-md: 0px`, `--radius-lg: 0px` e que o import do Bootstrap seja restrito a `grid` e `utilities`.

### 2. Requisições AJAX com Erro 419 (CSRF Token Mismatch)
- **Sintoma**: Chamadas efetuadas via `HttpClient` retornam erro HTTP 419.
- **Causa**: Meta tag `<meta name="csrf-token" content="{{ csrf_token() }}">` ausente do `<head>` da view layout master (`app.blade.php`).
- **Solução**: Verificar presença da meta tag no layout principal.

### 3. Modais Não Fecham ao Pressionar `Escape` ou Clicar no Backdrop
- **Sintoma**: Clique fora da caixa de diálogo não oculta o modal.
- **Causa**: Faltando estrutura `.dialog-backdrop` contendo o modal `.dialog` ou evento listeners desvinculados no `ModalManager`.
- **Solução**: Confirmar inicialização de `ModalManager` em `app.js` e estrutura HTML de backdrop.
