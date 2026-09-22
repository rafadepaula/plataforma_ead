<?php

/**
 * Skill Auditor Script per Agentic Harness standards.
 *
 * Programmatically parses .agents/skills/ and verifies that each feature
 * module is a single consolidated skill:
 *  [feature]-maintenance/SKILL.md
 *  [feature]-maintenance/resource/architecture.md
 *  [feature]-maintenance/resource/conventions.md
 *  [feature]-maintenance/resource/maintenance.md
 * (extra references such as resource/security.md or resource/frontend-legacy.md
 *  are allowed and not audited for content.)
 *
 * Exit codes:
 *  - 0: All checked feature modules are complete and valid.
 *  - 1: Missing skills directory, incomplete skill, or missing/empty file.
 *
 * Usage:
 *   php scripts/check-skills.php [--dir=.agents/skills] [--modules=tenancy,testing]
 */

declare(strict_types=1);

$options = getopt('', ['dir::', 'skills-dir::', 'path::', 'modules::', 'help']);

if (isset($options['help'])) {
    fwrite(STDOUT, "Auditor de Skills (Agentic Harness)\n");
    fwrite(STDOUT, "Uso: php scripts/check-skills.php [opções]\n\n");
    fwrite(STDOUT, "Opções:\n");
    fwrite(STDOUT, "  --dir=<caminho>       Caminho para o diretório de skills (padrão: .agents/skills)\n");
    fwrite(STDOUT, "  --modules=<lista>     Lista separada por vírgulas de módulos específicos para auditar\n");
    fwrite(STDOUT, "  --help                Exibe esta mensagem de ajuda\n");
    exit(0);
}

$skillsDirOption = $options['path'] ?? $options['dir'] ?? $options['skills-dir'] ?? null;
$skillsDir = $skillsDirOption !== null
    ? rtrim((string) $skillsDirOption, '/\\')
    : __DIR__.'/../.agents/skills';

if (! is_dir($skillsDir)) {
    fwrite(STDERR, sprintf("Erro: Diretório de skills não encontrado em '%s'.\n", $skillsDir));
    exit(1);
}

$modulesToCheck = [];

if (! empty($options['modules'])) {
    $rawModules = explode(',', (string) $options['modules']);
    foreach ($rawModules as $mod) {
        $trimmed = trim($mod);
        if ($trimmed !== '') {
            $modulesToCheck[] = $trimmed;
        }
    }
} else {
    // Auto-discover feature modules: {module}-maintenance/ dirs carrying a resource/ folder
    $items = scandir($skillsDir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($skillsDir.'/'.$item.'/resource') && preg_match('/^([a-z0-9-]+)-maintenance$/', $item, $matches)) {
                $modulesToCheck[] = $matches[1];
            }
        }
    }
    $modulesToCheck = array_unique($modulesToCheck);
    sort($modulesToCheck);
}

if (empty($modulesToCheck)) {
    fwrite(STDERR, sprintf("FALHA AUDITORIA: Nenhum módulo de feature foi identificado no diretório '%s'.\n", $skillsDir));
    exit(1);
}

$requiredFiles = [
    'SKILL.md',
    'resource/architecture.md',
    'resource/conventions.md',
    'resource/maintenance.md',
];
$hasErrors = false;
$passedCount = 0;
$totalModules = count($modulesToCheck);

fwrite(STDOUT, sprintf("Iniciando auditoria de skills em '%s'...\n", $skillsDir));
fwrite(STDOUT, sprintf("Módulos a serem auditados (%d): %s\n\n", $totalModules, implode(', ', $modulesToCheck)));

foreach ($modulesToCheck as $module) {
    $moduleErrors = [];
    $skillDirName = sprintf('%s-maintenance', $module);

    foreach ($requiredFiles as $file) {
        $filePath = sprintf('%s/%s/%s', $skillsDir, $skillDirName, $file);

        if (! is_file($filePath)) {
            $moduleErrors[] = sprintf('Arquivo ausente: %s/%s', $skillDirName, $file);
        } elseif (filesize($filePath) === 0) {
            $moduleErrors[] = sprintf('Arquivo vazio: %s/%s', $skillDirName, $file);
        }
    }

    if (! empty($moduleErrors)) {
        $hasErrors = true;
        fwrite(STDERR, sprintf("[FALHA] Módulo '%s' com skill incompleta:\n", $module));
        foreach ($moduleErrors as $err) {
            fwrite(STDERR, sprintf("  - %s\n", $err));
        }
    } else {
        $passedCount++;
        fwrite(STDOUT, sprintf("[OK] Módulo '%s': skill única completa (SKILL.md + resource/architecture.md, conventions.md, maintenance.md).\n", $module));
    }
}

fwrite(STDOUT, "\n--------------------------------------------------\n");

if ($hasErrors) {
    fwrite(STDERR, sprintf("FALHA AUDITORIA: %d de %d módulo(s) possuem skill incompleta ou ausente.\n", $totalModules - $passedCount, $totalModules));
    exit(1);
}

fwrite(STDOUT, sprintf("SUCESSO AUDITORIA: Todos os %d módulo(s) contêm a skill única completa (exit 0).\n", $passedCount));
exit(0);
