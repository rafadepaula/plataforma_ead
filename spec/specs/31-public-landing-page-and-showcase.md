# **31. Landing Page Pública e Vitrine de Componentes (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos**

* **Objetivo:** Refatorar a Landing Page pública da plataforma (`landing/show.blade.php`) com o padrão Material Bootstrap: layout em faixas alternadas azul (`--blue-50`) e branco (`--surface`), Hero institucional com raio de 36px (`--radius-2xl`), vitrine visual construída exclusivamente com os componentes reais do próprio Design System (cards de cursos, certificados e fórum, sem fotos stock genéricas), fluxo em 4 passos "Como funciona" e âncora de contato institucional.
* **Roles Cobertas:** Visitantes anônimos (público) e usuários autenticados.
* **Referência de Design:** `DESIGN.md` §4.12, `_ds/Landing Page.dc.html`, `_ds/Pagina publica - Anatomia.dc.html`.

---

## **2. Arquitetura em Faixas Alternadas (Bands)**

```text
landing/show.blade.php (Layout <x-layout.public surface="white">)
├── 1. Header Público (76px · Brand Mark · Botão "Entrar" [dusk="landing-login-link"])
├── 2. Hero Band (Azul --blue-50 · Raio 36px · Display 44px · CTA "Acessar plataforma" [dusk="landing-cta-login"])
├── 3. Capacidades / 3 Pilares (Branco · Cards Cursos, Provas, Certificados)
├── 4. Como Funciona / 4 Passos (Azul --blue-50 · Círculos numerados 1 a 4 com menta na conclusão)
├── 5. Vitrine de Componentes Reais (Branco · Card de Curso, Certificado Emitido, Pergunta do Fórum)
├── 6. Contato Institucional (Azul --blue-50 · Âncora #contato · Botão tonal "Fale conosco")
└── 7. Rodapé Público (Branco · Copyright e Validação Pública de Certificados)
```

---

## **3. Detalhamento das Faixas & Componentes**

### 3.1 Hero Section
- Fundo em `--blue-50`, raio 36px, padding vertical 64px.
- Badge no topo: `.ds-badge` lg *"Educação a Distância"*.
- Headline (`h1`, `dusk="landing-headline"`): *"Capacitação técnica continuada, do jeito certo"*.
- Lead (`p`): *"Cursos, provas interativas e certificados oficiais em uma única plataforma, pensada para organizações que levam a formação de suas equipes a sério."*.
- CTA Primário: Botão lg *"Acessar plataforma"* (`dusk="landing-cta-login"`).

### 3.2 Vitrine de Componentes Reais (Sem Fotos Stock)
1. **Card de Curso em Andamento:** Header de mídia 168px em pastel wash, chip *"Em andamento"*, título *"Segurança do trabalho — NR 35"*, barra de progresso 62% em menta.
2. **Card de Certificado Oficial:** Quadrado menta de 52px com ícone `award`, número do registro com hash, chips *"Válido"* e *"Validação pública"*, botão ghost *"Baixar certificado"*.
3. **Card do Fórum de Dúvidas:** Avatar de 52px com iniciais, pergunta da turma, trecho de texto e contador com 7 respostas.

### 3.3 Rodapé Institucional & Validação Pública
- Copyright dinâmico da plataforma.
- Link direto para a rota de **Validação Pública de Certificados** (`/validar-certificado`).

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="landing-headline"`: Título principal do Hero.
* `dusk="landing-cta-login"`: Botão primário "Acessar plataforma" no Hero.
* `dusk="landing-login-link"`: Botão "Entrar" no topo da página pública.

---

## **5. Checklist de Implementação & Testes**

- [ ] View `resources/views/landing/show.blade.php` refatorada no padrão Material Bootstrap.
- [ ] Faixas com alternância correta de cores e raio de 36px no Hero.
- [ ] Vitrine construída com componentes nativos do sistema.
- [ ] Teste Feature: `LandingPageControllerTest.php` cobrindo renderização e redirecionamentos.
- [ ] Teste Dusk: `LandingPageDuskTest.php` cobrindo headline, links de CTA e responsividade mobile.
