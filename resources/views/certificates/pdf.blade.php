{{--
    Certificado de Conclusão Profissional — Plataforma EAD
    Renderizado via Barryvdh\DomPDF\PDF (Dompdf 3.1.6).
    Design institucional contemporâneo A4 Paisagem (297 x 210 mm).
    Subconjunto CSS 2.1 compatível com Dompdf, página única estrita por construção.
    Consome Presentation Contract v1.1 (/tmp/certificados_contrato_2026090503.md).
--}}
@extends('layouts.print')

@section('title')Certificado — {{ $certificate->course->title }}@endsection

@section('styles')
    <style>
        @page {
            size: a4 landscape;
            margin: 5mm 8mm 5mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', sans-serif;
            color: #183247;
            background-color: #FCFBF7;
            -webkit-print-color-adjust: exact;
        }

        /* Moldura institucional dupla perimetral via tabela para contenção exata no Dompdf */
        .frame-outer {
            width: 100%;
            border: 1.8pt solid #A88445;
            border-collapse: collapse;
            background-color: #FCFBF7;
            page-break-inside: avoid;
        }
        .frame-outer-td {
            padding: 1.2mm;
            vertical-align: top;
        }
        .frame-inner {
            width: 100%;
            border: 0.75pt solid #C5B48D;
            border-collapse: collapse;
            background-color: #FCFBF7;
            page-break-inside: avoid;
        }
        .frame-inner-td {
            padding: 2.2mm 5mm 1.8mm 5mm;
            vertical-align: top;
        }

        /* Cabeçalho: Tabela de Logo e Organização */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1mm;
        }
        .header-table td {
            vertical-align: middle;
        }
        .header-logo-td {
            width: 48mm;
            height: 18mm;
            text-align: left;
        }
        .header-org-td {
            width: 190mm;
            max-width: 190mm;
            text-align: right;
        }
        .logo-fallback {
            border-left: 2pt solid #A88445;
            padding-left: 2mm;
        }
        .logo-fallback-kicker {
            font-size: 6pt;
            font-weight: bold;
            color: #A88445;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .logo-fallback-name {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7.5pt;
            font-weight: normal;
            color: #183247;
            line-height: 1.2;
        }
        /* Organização: DejaVu Sans, peso normal conforme Contrato v1.1 */
        .org-name-header {
            font-family: 'DejaVu Sans', sans-serif;
            font-weight: normal;
            color: #183247;
        }
        .org-meta-header {
            font-size: 6.5pt;
            color: #46515C;
            margin-top: 0.3mm;
        }

        /* Banner de Revogação */
        .revoked-banner {
            background-color: #FDF2F2;
            border: 0.8pt solid #D9534F;
            color: #A94442;
            padding: 0.8mm 2mm;
            text-align: center;
            margin: 0 auto 0.6mm auto;
            width: 100%;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .revoked-banner-title {
            color: #C9302C;
            text-transform: uppercase;
        }
        .revoked-reason {
            font-size: 6.5pt;
            font-weight: normal;
            margin-top: 0.2mm;
            color: #555555;
        }

        /* Título e Kicker */
        .title-area {
            text-align: center;
            margin-top: 0;
            margin-bottom: 0.6mm;
        }
        .kicker {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7.5pt;
            font-weight: bold;
            color: #A88445;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            margin-bottom: 0.2mm;
        }
        .main-title {
            font-family: 'DejaVu Serif', serif;
            font-size: 19pt;
            font-weight: bold;
            color: #183247;
            letter-spacing: 1px;
            margin: 0;
            line-height: 1.0;
        }
        .intro-text {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            color: #46515C;
            margin-top: 0.3mm;
            letter-spacing: 0.2px;
        }

        /* Área do Aluno: Caixa 249 x 55 mm, DejaVu Serif normal conforme Contrato v1.1 */
        .student-area {
            text-align: center;
            width: 249mm;
            max-width: 249mm;
            margin: 0.3mm auto 0.3mm auto;
        }
        .student-box {
            font-family: 'DejaVu Serif', serif;
            font-weight: normal;
            color: #183247;
            margin: 0 auto;
        }
        .student-divider {
            width: 130mm;
            height: 0.75pt;
            background-color: #C5B48D;
            margin: 0.8mm auto 0.8mm auto;
        }

        /* Seção do Curso: Caixa 249 x 27 mm, DejaVu Serif normal conforme Contrato v1.1 */
        .course-section {
            text-align: center;
            width: 249mm;
            max-width: 249mm;
            margin: 0 auto 0.6mm auto;
            color: #333333;
        }
        .course-intro {
            font-size: 8.5pt;
            color: #46515C;
            margin-bottom: 0.2mm;
        }
        .course-title-box {
            font-family: 'DejaVu Serif', serif;
            font-weight: normal;
            color: #183247;
        }
        .course-concluding {
            font-size: 8pt;
            color: #333333;
            margin-top: 0.3mm;
        }

        /* Grade de Metadados */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.6mm;
            margin-bottom: 0.8mm;
        }
        .meta-td {
            background-color: #F5F2EA;
            border: 0.5pt solid #D8CEB9;
            padding: 0.6mm 2mm;
            text-align: center;
        }
        .meta-label {
            font-size: 5.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #7D6B4E;
            margin-bottom: 0.2mm;
        }
        .meta-val {
            font-size: 7pt;
            font-weight: bold;
            color: #183247;
            line-height: 1.2;
        }

        /* Rodapé de Autenticação */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.6mm;
        }
        .footer-table td {
            vertical-align: bottom;
        }
        .footer-left {
            width: 58%;
            text-align: left;
            padding-right: 3mm;
        }
        .footer-right {
            width: 42%;
            text-align: right;
        }
        .auth-title {
            font-size: 6pt;
            font-weight: bold;
            color: #183247;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.2mm;
        }
        .legal-notice {
            font-size: 6pt;
            color: #55606E;
            line-height: 1.15;
        }
        .verify-lookup-url {
            font-size: 10pt;
            font-weight: bold;
            margin: 0.3mm 0;
        }
        .verify-link {
            color: #183247;
            text-decoration: underline;
        }
        .legal-notice-sub {
            font-size: 5.5pt;
            color: #7D8B96;
            line-height: 1.15;
            margin-top: 0.2mm;
        }
        .auth-box {
            border: 0.75pt solid #C5B48D;
            background-color: #FFFFFF;
            padding: 1mm 2mm;
            text-align: left;
        }
        .auth-box-header {
            font-size: 5.5pt;
            font-weight: bold;
            color: #A88445;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 0.2mm;
        }
        .auth-hash-code {
            font-family: 'DejaVu Sans', monospace;
            font-size: 5.5pt;
            color: #183247;
            word-break: break-all;
            line-height: 1.15;
            letter-spacing: 0.2px;
        }
    </style>
@endsection

@section('content')
@php
    $studentName = $certificate->user->name ?? 'Aluno';
    $studentLines = $presentation['student']['lines'] ?? [$studentName];
    $studentFontSize = $presentation['student']['fontSize'] ?? 32.0;

    $courseTitle = $certificate->course->title ?? 'Curso';
    $courseLines = $presentation['course']['lines'] ?? [$courseTitle];
    $courseFontSize = $presentation['course']['fontSize'] ?? 16.0;

    $orgName = $certificate->course->organization->name ?? 'Instituição de Ensino';
    $orgLines = $presentation['organization']['lines'] ?? [$orgName];
    $orgFontSize = $presentation['organization']['fontSize'] ?? 11.0;

    $isRevoked = $certificate->isRevoked();
@endphp

<table class="frame-outer">
    <tr>
        <td class="frame-outer-td">
            <table class="frame-inner">
                <tr>
                    <td class="frame-inner-td">

                        {{-- Cabeçalho: Logo da Organização e Metadados Institucionais (suporta até 3 linhas de Org) --}}
                        <table class="header-table">
                            <tr>
                                <td class="header-logo-td">
                                    @if(!empty($logo['src']))
                                        <img src="{{ $logo['src'] }}" style="width: {{ $logo['widthMm'] }}mm; height: {{ $logo['heightMm'] }}mm; display: block;" alt="{{ $orgName }}">
                                    @else
                                        {{-- Fallback tipográfico institucional elegante --}}
                                        <div class="logo-fallback">
                                            <div class="logo-fallback-kicker">ORGANIZAÇÃO EMISSORA</div>
                                            <div class="logo-fallback-name">{{ $orgName }}</div>
                                        </div>
                                    @endif
                                </td>
                                <td class="header-org-td">
                                    <div class="org-name-header" style="font-size: {{ $orgFontSize }}pt; line-height: {{ $orgFontSize * 1.5 }}pt;">
                                        @foreach($orgLines as $line)
                                            <div>{{ $line }}</div>
                                        @endforeach
                                    </div>
                                    <div class="org-meta-header">
                                        @if(!empty($certificate->course->organization->cnpj ?? null))
                                            CNPJ: {{ $certificate->course->organization->cnpj }} &nbsp;|&nbsp;
                                        @endif
                                        Emitido em {{ $certificate->issued_at ? $certificate->issued_at->format('d/m/Y') : now()->format('d/m/Y') }}
                                    </div>
                                </td>
                            </tr>
                        </table>

                        {{-- Alerta visual se o certificado estiver revogado --}}
                        @if($isRevoked)
                            <div class="revoked-banner">
                                <span class="revoked-banner-title">DOCUMENTO REVOGADO</span> &mdash;
                                Este certificado foi cancelado/revogado em {{ $certificate->revoked_at ? $certificate->revoked_at->format('d/m/Y') : '' }}.
                                @if(!empty($certificate->revoke_reason))
                                    <div class="revoked-reason">Motivo da revogação: {{ $certificate->revoke_reason }}</div>
                                @endif
                            </div>
                        @endif

                        {{-- Área do Título --}}
                        <div class="title-area">
                            <div class="kicker">Certificado de Conclusão</div>
                            <h1 class="main-title">CERTIFICADO</h1>
                            <div class="intro-text">Certificamos para os devidos fins que</div>
                        </div>

                        {{-- Área do Aluno: Caixa 249 x 55 mm, DejaVu Serif normal, line-height 1.2 --}}
                        <div class="student-area">
                            <div class="student-box" style="font-size: {{ $studentFontSize }}pt; line-height: {{ $studentFontSize * 1.2 }}pt;">
                                @foreach($studentLines as $line)
                                    <div>{{ $line }}</div>
                                @endforeach
                            </div>
                            <div class="student-divider"></div>
                        </div>

                        {{-- Bloco do Curso e Carga Horária: Caixa 249 x 27 mm, DejaVu Serif normal, line-height 1.5 --}}
                        <div class="course-section">
                            <div class="course-intro">concluiu com aproveitamento satisfatório o curso</div>
                            <div class="course-title-box" style="font-size: {{ $courseFontSize }}pt; line-height: {{ $courseFontSize * 1.5 }}pt;">
                                @foreach($courseLines as $line)
                                    <div>{{ $line }}</div>
                                @endforeach
                            </div>
                            <div class="course-concluding">
                                com carga horária total de <strong>{{ $certificate->course->workload_hours ?? 0 }} horas</strong>,
                                promovido por <strong>{{ $orgName }}</strong>.
                            </div>
                        </div>

                        {{-- Grade de Metadados --}}
                        <table class="meta-table">
                            <tr>
                                <td class="meta-td" style="width: 33.3%;">
                                    <div class="meta-label">Carga Horária</div>
                                    <div class="meta-val">{{ $certificate->course->workload_hours ?? 0 }} Horas</div>
                                </td>
                                <td class="meta-td" style="width: 33.4%; border-left: none; border-right: none;">
                                    <div class="meta-label">Data de Conclusão</div>
                                    <div class="meta-val">{{ $certificate->issued_at ? $certificate->issued_at->format('d/m/Y') : '-' }}</div>
                                </td>
                                <td class="meta-td" style="width: 33.3%;">
                                    <div class="meta-label">Registro Institucional</div>
                                    <div class="meta-val">{{ $orgName }}</div>
                                </td>
                            </tr>
                        </table>

                        {{-- Rodapé: Autenticação e Consulta Pública --}}
                        <table class="footer-table">
                            <tr>
                                <td class="footer-left">
                                    <div class="auth-title">Validação de Autenticidade Institucional</div>
                                    <div class="legal-notice">
                                        A veracidade deste documento pode ser verificada a qualquer momento através do endereço:
                                    </div>
                                    <div class="verify-lookup-url">
                                        <a class="verify-link" href="{{ $verificationUrl }}">{{ $verificationLookupUrl ?? ($verificationUrl ?? url('/validar-certificado')) }}</a>
                                    </div>
                                    <div class="legal-notice-sub">
                                        Certificado emitido digitalmente pela plataforma acadêmica com validação por chave criptográfica SHA-256 inviolável.
                                    </div>
                                </td>
                                <td class="footer-right">
                                    <div class="auth-box">
                                        <div class="auth-box-header">Chave de Validação Criptográfica</div>
                                        <div class="auth-hash-code">{{ $certificate->validation_hash }}</div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
