# Plano — Certificados: download do PDF pelo painel do gestor/professor/admin

> Fonte: ClickUp task 86e3d1fzb (refinado 2026-09-22).

## 1. Produto final

1. Gestor abre diretório de alunos, clica "Certificados" na linha do aluno.
2. No modal, cada linha ganha botão "Baixar PDF" ao lado de Validar/Invalidar.
3. PDF abre em nova aba — mesmo artefato de `CertificatePdfService`, com QR code.
4. Professor e admin também baixam certificados de alunos da organização.

## 2. Estado atual

**Já existe:**
- Rota `GET /certificates/{certificate}/download` (`certificates.download`, `routes/web.php:263-266`), `CertificateController::download()` (`app/Http/Controllers/CertificateController.php:89`), `authorizeDownloadAccess()` (:117-134): dono/aluno, admin, gestor mesma org (403 cross-org em :132).
- PDF via dompdf: `app/Services/CertificatePdfService.php:44`, QR em :71 (`certificates.verify`, host da org emissora).
- Modelo UI: `resources/views/certificates/index.blade.php:88` tem "Visualizar PDF".
- Painel-alvo: botão "Certificados" em `resources/views/gestor/students/index.blade.php:112-117` abre modal (`certificates-modal-{id}`, :167); coluna Ação com Validar/Invalidar (:181-206). Sem botão PDF.

**Gaps:**
- Sem botão download no modal do diretório de alunos.
- Professor NEGADO hoje (`CertificateController.php:127` só admin/gestor).

## 3. Implementação

### Task 1 — Policy + professor

1. Extrair `authorizeDownloadAccess()` para `CertificatePolicy::download()` (mesma fronteira role/org, acrescentar `PROFESSOR`).
2. Controller chama `Gate::authorize('download', $certificate)` (ou `$this->authorize('download', $certificate)`); remover método inline + docblock.
3. Testes: `tests/Feature/CertificateControllerTest.php` — adicionar caso professor own-org permitido; garantir cross-org professor 403 e aluno não-dono 403 continuam.

### Task 2 — Botão no modal

1. `resources/views/gestor/students/index.blade.php`, coluna Ação (~:191): `<x-ui.button href="{{ route('certificates.download', $certificate) }}" target="_blank" dusk="download-certificate-{{ $certificate->id }}">Baixar PDF</x-ui.button>` (seguir markup do modelo `certificates/index.blade.php:88`).
2. Manter botão para certificado revogado (badge sinaliza estado).
3. Teste: `tests/Feature/GestorStudentManagementTest.php` — assert link/dusk no modal (gestor own-org; certificados de outra org ausentes).
4. Atualizar snapshot Dusk se aplicável (`tests/fixtures/dusk-selectors-snapshot.json`).

## Global Constraints

- Rota e resposta de `certificates.download` inalteradas (stream do mesmo PDF).
- 403 cross-org permanece (gestor/professor de outra org); aluno não-dono 403.
- `CertificatePdfService` intocado — PDF usa org emissora real, não org ativa da sessão.
- Botão exibido também para certificado revogado.
- Comandos via `vendor/bin/sail`; Pint `vendor/bin/sail bin pint --dirty --format agent`; PHPUnit (não Pest).

## Definição de Pronto

1. Gestor e admin baixam PDF pelo modal (nova aba).
2. Professor baixa da própria org; cross-org e aluno não-dono 403.
3. Suítes verdes (feature + Dusk quando aplicável) e Pint limpo.
