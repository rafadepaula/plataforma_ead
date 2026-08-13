# Demandas do módulo Admin (001)

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** PARCIAL. Este arquivo é o log bruto de demandas e já foi integralmente triado em 6 artefatos (UX-001..004, SPEC-001, SPEC-002 + BUG-005); todas as demandas seguem válidas contra o código atual, com uma única exceção: o sub-item "fazer o botão de esconder mensagem funcionar", dentro da demanda de remoção de Organização, já está atendido no markup migrado (`resources/views/components/ui/alert.blade.php:44-49` emite `data-bs-dismiss="alert"` e o bundle do Bootstrap é carregado em `resources/js/app.js:12`) — a verificação formal pertence ao `BUG-004`, que ainda consta como `OPEN`.

## Mapa de revalidação (2026-08-13)

| Demanda neste arquivo | Artefato dono | Veredicto | Evidência no código atual |
|---|---|---|---|
| Menu admin (reduzir escopo + subseção "Impersonate") | `spec/ux_fixes/UX-001-admin-sidebar-scope-and-impersonate-section.md` | AINDA VÁLIDO | `app/Services/Navigation/NavigationRegistry.php:38-145` ainda declara 9 itens; `courses`/`quiz-attempts`/`forum-moderation` seguem em `roles: ['admin','gestor']` sem gate de impersonate (linhas 68-96); não existe seção "Impersonate" em `sectionOrder()` (linha 28) |
| Top bar admin (remover busca, badge de impersonate) | `spec/ux_fixes/UX-002-admin-topbar-remove-search-add-impersonation-badge.md` | AINDA VÁLIDO | Campo de busca inoperante persiste em `resources/views/components/layout/topbar.blade.php:44-52`; nenhum badge de Organização ativa na topbar — o único aviso continua dentro de `/organizations` (`resources/views/organizations/index.blade.php:11-19`) |
| Dashboard admin (resumo das Organizações) | `spec/to_refine/specs/SPEC-001-admin-dashboard-organizations-summary-table.md` | AINDA VÁLIDO | `resources/views/dashboard/index.blade.php:12-66` traz só os 4 stat cards + matrículas recentes + exports; `app/Services/DashboardMetricsService.php:36-49` não agrega por Organização |
| Lista de organizações — modal de confirmação ao remover | `spec/ux_fixes/UX-003-organization-delete-confirmation-modal.md` | AINDA VÁLIDO | `resources/views/organizations/index.blade.php:50-56` ainda submete o `DELETE` direto; o comentário nas linhas 47-50 registra que a adoção do modal foi adiada. `<x-ui.confirm-modal>` já existe e está em uso em `resources/views/users/index.blade.php:43` |
| Lista de organizações — botão de esconder mensagem quebrado | `spec/bugs/BUG-004-alert-dismiss-button-inert.md` | **JÁ ATENDIDO (a confirmar no BUG-004)** | `resources/views/components/ui/alert.blade.php:29,44-49` — classe `fade show` + `<button data-bs-dismiss="alert">`; `resources/js/app.js:12` importa o bundle completo do Bootstrap, registrando o listener de data-api. O flash de sucesso passa por `resources/views/components/layout/alerts.blade.php:2-6` com `dismissable` |
| Editar organização — logo não renderizado | `spec/ux_fixes/UX-004-organization-logo-preview-not-rendered.md` | AINDA VÁLIDO | `resources/views/organizations/_form.blade.php:40-42` ainda imprime `Logo atual: {{ $organization->logo_path }}` como texto, sem `<img>` |
| Lista de alunos e usuários — Admin não acessa | `spec/bugs/BUG-005-users-index-unreachable-for-admin-without-impersonation.md` | AINDA VÁLIDO | `app/Http/Controllers/UserController.php:35` mantém `resolveOrgId()` estrito; `app/Http/Controllers/Concerns/ResolvesOrgContext.php:18-26` lança `UnresolvedOrgContextException` para Admin sem `active_org_id`; o item de menu segue exposto em `app/Services/Navigation/NavigationRegistry.php:59-67` |
| Lista de alunos e usuários — tela exclusiva de administração | `spec/to_refine/specs/SPEC-002-admin-global-user-management-screen.md` | AINDA VÁLIDO | Nenhuma rota `admin/users` em `artisan route:list`; `resources/views/users/index.blade.php` segue single-org, sem `<x-ui.filter-bar>` e sem ações de ciclo de vida |

**Divisão de propriedade da última demanda:** o `BUG-005` é dono de *tornar `users.index` alcançável* pelo Admin; o `SPEC-002` é dono da *tela administrativa nova* (cross-org, filtros, ativar/desativar/ver perfil). Nenhum arquivo de código é disputado entre os dois.

**Nota de vocabulário:** todos os artefatos derivados deste arquivo devem ser implementados com os componentes atuais (`<x-layout.page-header>`, `<x-ui.data-table>`, `<x-ui.filter-bar>`, `<x-ui.empty-state>`, `<x-ui.pagination>`, `<x-ui.confirm-modal>`, `<x-ui.badge>`, `<x-ui.button>`), sem `style=`, Tailwind, `rounded*`, `var(--color-*)` ou hex. Lembrete de teste: `.badge` renderiza em CAIXA ALTA e o Selenium lê o texto renderizado.

# Modelo

Dado que estou logado com perfil:
Estou na tela: 
Estou vendo:

Gostaria de estar vendo:

# Menu admin
Dado que estou logado com perfil: Admin
Estou na tela: Qualquer uma tela do sistema
Estou vendo:
    - links:
        - Dashboard
        - Organizações
        - Alunos & Usuários
        - Cursos
        - Redações pendentes
        - Moderação do fórum
        - Auditoria
        - Configurações
        - Meus Cursos

Gostaria de estar vendo:
    - links:
        - Dashboard
        - Organizações
        - Alunos & Usuários
        - Auditoria
        - Configurações

    - ou seja, diminuir o escopo de menu do admin por padrão
    - quando ativar o impersonate, deveria aparecer em uma subsessão:
        - "Impersonate"
            - Cursos
            - Redações pendentes
            - Moderações do fórum

# Top bar admin
Dado que estou logado com perfil: Admin
Estou na tela: Qualquer uma tela do sistema
Estou vendo:
    - barra de busca
    - botão de ajuda
    - meu perfil
    - sair

Gostaria de estar vendo:
    - se impersonate estiver ativo, mostrar badge da organização ativa e opção para desabilitar impersonate
        - quando desabilitar impersonate, automaticamente redirecionar para admin/dashboard
    - botão de ajuda
    - meu perfil
    - sair


# Dashboard admin
Dado que estou logado com perfil: Admin
Estou na tela: Dashboard
Estou vendo: 
    - alunos ativos, certificados, porcentagem de conclusão, número de cursos.
    - matrículas recentes
    - exportar matrículas e certificados

Gostaria de estar vendo:
    - resumo das organizações, uma tabela mostrando uma lista de organizações que estão cadastradas na plataforma e alguns dados sobre elas: número de alunos, cursos, certificado emitidos, etc.

# Lista de organizações - fluxo de remover
Dado que estou logado com perfil: admin
Estou na tela: /organizations
Estou vendo: 
    - botão de remover na tabela de listagem
    - ao clicar em remover, automaticamente a organização é deletada
    - quando deleta, uma mensagem "Organização removida com sucesso" é mostrada no topo depois de recarregar a página
    - existe um botão para esconder a mensagem, mas o botão não funciona e a mensagem nunca desaparece

Gostaria de estar vendo:
    - botão de remover na tabela de listagem
    - ao clicar em remover, mostrar modal de confirmação
    - ao confirmar, mostrar mensagem de sucesso e remover a organização da tabela
    - fazer o botão de esconder mensagem funcionar (corrigir bug)
    - ao cancelar, não remover a organização e não dar nenhum feedback, simplesmente fechar a modal

# Editar organização - visualização de logo desconfigurada
Dado que estou logado com perfil: admin
Estou na tela: /organizations/{id}/edit
Estou vendo: 
    - formulário completo de edição da organização
    - campo de logo está com legenda "logo atual: {nome do arquivo}"
    - não está mostrando o logo da organização

Gostaria de estar vendo:
    - formulário completo
    - logo da organização abaixo do campo de logo

# Lista de alunos e usuários - admin
Dado que estou logado com perfil: admin
Estou na tela: (não consigo acessar)
Estou vendo:
    - nada, pois não está sendo possível acessar
    - a tela de lista de alunos e usuários está visível apenas quando impersonate ativo

Gostaria de estar vendo:
    - lista todal de usuários e alunos de todo o sistema
    - visualização e tela exclusiva para módulo de admin
    - ou seja, uma tela exclusiva para a administração do sistema, com opções e filtros exclusivos
    - filtros:
        - nome
        - email
        - organização
        - status (ativo, inativo, pendente)
        - tipo (admin, aluno, professor, etc)
        - data de criação
    - opções exclusivas:
        - desativar usuário
        - ativar usuário
        - excluir usuário
        - ver perfil completo do usuário
        - editar perfil completo do usuário