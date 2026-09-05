<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use App\Services\CertificatePresentationBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

$prefix = $argv[1] ?? 'depois';
$outputDir = storage_path('app/private/certificate-previews');
if (! is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Scenarios definition
$scenarios = [
    'cenario1_normal' => [
        'student' => 'Carlos Eduardo da Silva Santos',
        'course' => 'Desenvolvimento Web Fullstack com Laravel e Bootstrap',
        'workload' => 60,
        'org_name' => 'Instituto de Tecnologia Educacional do Brasil',
        'org_cnpj' => '12.345.678/0001-90',
        'logo_path' => 'organizations/logos/4XjPridqXqqUmOkPQwsFbYp32GJ8BIPRLFWGTZ7x.png',
        'issued_at' => Carbon::parse('2026-08-15 14:30:00'),
        'revoked_at' => null,
        'revoke_reason' => null,
        'hash' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    ],
    'cenario2_sem_logo' => [
        'student' => 'Mariana de Souza Oliveira',
        'course' => 'Gestão Estratégica de Projetos Educacionais',
        'workload' => 45,
        'org_name' => 'Academia Nacional de Educação Corporativa',
        'org_cnpj' => '98.765.432/0001-10',
        'logo_path' => null,
        'issued_at' => Carbon::parse('2026-07-20 10:00:00'),
        'revoked_at' => null,
        'revoke_reason' => null,
        'hash' => '8f434346648f6b96df89dda901c5176b10a6d83961dd3c1ac88b59b2dc327aa4',
    ],
    'cenario3_textos_extremos' => [
        // 255 chars total
        'student' => 'Prof. Dr. Washington Wellington Williams Wenceslau de Albuquerque e Castro Silveira Vasconcelos de Alencar Barreto Siqueira da Silva Souza Ramos Monteiro Guimarães Fontes Brandão Castello Branco Pereira Cavalcanti Medeiros Coutinho de Arruda Botelho WWWWWWWWWW',
        // 200 chars total
        'course' => 'Programa Avançado de Especialização em Arquitetura de Software Distribuído, Microsserviços Resilientes, Computação em Nuvem e Segurança da Informação Aplicada a Grandes Ecossistemas Corporativos 2026',
        'workload' => 120,
        // 150 chars total
        'org_name' => 'Confederação Nacional das Instituições de Ensino Superior, Pesquisa Científica, Tecnologia Avançada e Desenvolvimento Tecnológico Regional do Brasil Sul',
        'org_cnpj' => '11.222.333/0001-44',
        'logo_path' => 'organizations/logos/4XjPridqXqqUmOkPQwsFbYp32GJ8BIPRLFWGTZ7x.png',
        'issued_at' => Carbon::parse('2026-09-01 08:00:00'),
        'revoked_at' => null,
        'revoke_reason' => null,
        'hash' => 'a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e',
    ],
    'cenario4_revogado' => [
        'student' => 'Lucas Ferreira Guimarães',
        'course' => 'Compliance e Proteção de Dados (LGPD)',
        'workload' => 30,
        'org_name' => 'Instituto de Tecnologia Educacional do Brasil',
        'org_cnpj' => '12.345.678/0001-90',
        'logo_path' => 'organizations/logos/4XjPridqXqqUmOkPQwsFbYp32GJ8BIPRLFWGTZ7x.png',
        'issued_at' => Carbon::parse('2026-05-10 16:00:00'),
        'revoked_at' => Carbon::parse('2026-06-01 09:30:00'),
        'revoke_reason' => 'Cancelamento de matrícula por descumprimento de prazos regimentais.',
        'hash' => '7110eda4d09e062aa5e4a390b0a572ac0d2c0220ac527491dd81bcce51a503e4',
    ],
    'cenario5_sem_espacos' => [
        'student' => 'Maria-'.str_repeat('W', 50).'-Silva',
        'course' => 'Formacao-'.str_repeat('W', 45).'-Intensiva',
        'workload' => 40,
        'org_name' => 'Organizacao-'.str_repeat('W', 35).'-Educacional',
        'org_cnpj' => '12.345.678/0001-90',
        'logo_path' => 'organizations/logos/4XjPridqXqqUmOkPQwsFbYp32GJ8BIPRLFWGTZ7x.png',
        'issued_at' => Carbon::parse('2026-09-02 11:00:00'),
        'revoked_at' => null,
        'revoke_reason' => null,
        'hash' => 'bcda4346648f6b96df89dda901c5176b10a6d83961dd3c1ac88b59b2dc327ff1',
    ],
];

foreach ($scenarios as $key => $data) {
    $user = new User(['name' => $data['student']]);
    $org = new Organization([
        'name' => $data['org_name'],
        'cnpj' => $data['org_cnpj'],
        'logo_path' => $data['logo_path'],
    ]);
    $course = new Course([
        'title' => $data['course'],
        'workload_hours' => $data['workload'],
    ]);
    $course->setRelation('organization', $org);

    $certificate = new Certificate([
        'validation_hash' => $data['hash'],
        'issued_at' => $data['issued_at'],
        'revoked_at' => $data['revoked_at'],
        'revoke_reason' => $data['revoke_reason'],
    ]);
    $certificate->setRelation('user', $user);
    $certificate->setRelation('course', $course);

    $verificationUrl = 'http://localhost/validar-certificado/'.$data['hash'];
    $verificationLookupUrl = 'http://localhost/validar-certificado';

    $logoArray = null;
    if ($data['logo_path']) {
        $absPath = public_path('storage/'.$data['logo_path']);
        if (file_exists($absPath)) {
            $logoArray = [
                'src' => $absPath,
                'widthMm' => 45.0,
                'heightMm' => 18.0,
            ];
        }
    }

    $builder = app(CertificatePresentationBuilder::class);
    $built = $builder->build($certificate);

    $viewData = [
        'certificate' => $certificate,
        'verificationUrl' => $verificationUrl,
        'verificationLookupUrl' => $verificationLookupUrl,
        'qrCodeDataUri' => null,
        'logo' => $built['logo'] ?? $logoArray,
        'presentation' => $built['presentation'],
    ];

    $pdf = Pdf::loadView('certificates.pdf', $viewData);
    $pdf->setPaper('a4', 'landscape');

    $pdfPath = "{$outputDir}/{$prefix}_{$key}.pdf";
    file_put_contents($pdfPath, $pdf->output());

    echo "Gerado: {$pdfPath}\n";
}

echo "Finalizado para prefixo: {$prefix}\n";
