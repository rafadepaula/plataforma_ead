<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Categorias Oficiais da Central de Ajuda
    |--------------------------------------------------------------------------
    |
    | Lista autoritativa de categorias temáticas para organização da wiki e
    | classificação de artigos no formulário de criação/edição.
    |
    */
    'categories' => [
        'Acesso e Segurança',
        'Administração',
        'Avaliações',
        'Central de Ajuda',
        'Certificados',
        'Cursos',
        'Fórum',
        'Matrículas e Convites',
        'Minha Conta',
        'Para Alunos',
        'Pessoas',
        'Professor',
        'Provas',
        'Público',
    ],

    /*
    |--------------------------------------------------------------------------
    | Chaves Standalone de Telas Públicas
    |--------------------------------------------------------------------------
    |
    | Telas com layouts públicos independentes que utilizam chaves literais
    | fixas para resolução contextual de artigos.
    |
    */
    'standalone_keys' => [
        'invitation.show',
        'certificates.verify',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotas de Serviço e Utilitárias Ignoradas
    |--------------------------------------------------------------------------
    |
    | Rotas GET que não renderizam telas Blade de visualização completa
    | (ex.: downloads de arquivos binários, APIs JSON auxiliares, endpoints
    | internos de teste e verificações de integridade).
    |
    */
    'ignored_routes' => [
        // Página institucional sem ajuda contextual por design.
        'landing.show',
        // Superfícies secundárias da tela de prova do aluno (histórico e
        // detalhe de tentativa); o contexto de ajuda já é coberto pelos
        // artigos de student.quizzes.show e student.quizzes.result.
        'student.quizzes.history',
        'student.quizzes.attempt-result',
        'dusk.login',
        'dusk.logout',
        'dusk.user',
        '_dusk.login',
        '_dusk.logout',
        '_dusk.user',
        'admin.audit-logs.export',
        'reports.export',
        'certificates.download',
        'courses.enrollments.search',
        'forum-replies.fetch',
        'lessons.pdf.show',
        'notifications.unread-count',
        'storage.local',
        'up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de Chaves por Módulo (100% de Cobertura)
    |--------------------------------------------------------------------------
    |
    | Mapeamento estruturado de target_page_key por módulo funcional.
    |
    */
    'modules' => [
        'academic' => [
            'courses.index',
            'courses.create',
            'courses.edit',
            'courses.modules.index',
            'courses.modules.create',
            'modules.edit',
            'modules.lessons.index',
            'modules.lessons.create',
            'lessons.edit',
            'courses.enrollments.index',
            'courses.enrollments.create',
            'courses.professors.index',
            'gestor.students.index',
            'gestor.students.create',
            'gestor.students.edit',
            'gestor.professors.index',
            'gestor.professors.create',
            'gestor.professors.edit',
            'users.import.create',
            'users.index',
            'users.create',
            'users.edit',
        ],
        'evaluations' => [
            'quizzes.create',
            'quizzes.edit',
            'quiz-attempts.pending',
            'quiz-attempts.show',
            'courses.completion-rules.index',
            'courses.certificates.index',
            'forum-moderation.index',
        ],
        'student' => [
            'student.courses.index',
            'classroom.show',
            'classroom.lesson',
            'student.quizzes.show',
            'student.quizzes.result',
            'forum.index',
            'forum.create',
            'forum.show',
            'forum.edit',
            'forum-replies.edit',
            'profile.edit',
        ],
        'admin_public' => [
            'professor.dashboard',
            'professor.courses.index',
            'admin.dashboard',
            'organizations.index',
            'organizations.create',
            'organizations.edit',
            'admin.users.index',
            'admin.users.show',
            'admin.users.edit',
            'admin.audit-logs.index',
            'settings.edit',
            'org.help.artigos.index',
            'org.help.artigos.create',
            'org.help.artigos.edit',
            'help.index',
            'help.show',
            'login',
            'password.request',
            'password.reset',
            'invitation.show',
            'certificates.verify',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lista Global Unificada de target_page_keys
    |--------------------------------------------------------------------------
    |
    | Consolidação de todas as chaves esperadas para garantia mecânica de teste.
    |
    */
    'target_page_keys' => [
        'admin.audit-logs.index',
        'admin.dashboard',
        'admin.users.edit',
        'admin.users.index',
        'admin.users.show',
        'certificates.verify',
        'classroom.lesson',
        'classroom.show',
        'courses.certificates.index',
        'courses.completion-rules.index',
        'courses.create',
        'courses.edit',
        'courses.enrollments.create',
        'courses.enrollments.index',
        'courses.index',
        'courses.modules.create',
        'courses.modules.index',
        'courses.professors.index',
        'forum-moderation.index',
        'forum-replies.edit',
        'forum.create',
        'forum.edit',
        'forum.index',
        'forum.show',
        'gestor.professors.create',
        'gestor.professors.edit',
        'gestor.professors.index',
        'gestor.students.create',
        'gestor.students.edit',
        'gestor.students.index',
        'help.index',
        'help.show',
        'invitation.show',
        'lessons.edit',
        'login',
        'modules.edit',
        'modules.lessons.create',
        'modules.lessons.index',
        'org.help.artigos.create',
        'org.help.artigos.edit',
        'org.help.artigos.index',
        'organizations.create',
        'organizations.edit',
        'organizations.index',
        'password.request',
        'password.reset',
        'professor.courses.index',
        'professor.dashboard',
        'profile.edit',
        'quiz-attempts.pending',
        'quiz-attempts.show',
        'quizzes.create',
        'quizzes.edit',
        'settings.edit',
        'student.courses.index',
        'student.quizzes.result',
        'student.quizzes.show',
        'users.create',
        'users.edit',
        'users.import.create',
        'users.index',
    ],
];
