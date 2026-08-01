<?php

/**
 * Skill Auditor Script per SPEC-03 Agentic Harness standards.
 *
 * Programmatically parses .agents/skills/ and verifies that required
 * feature modules contain the mandatory 3-skill triad:
 *  - [feature]-architecture/SKILL.md
 *  - [feature]-conventions/SKILL.md
 *  - [feature]-maintenance/SKILL.md
 *
 * Exit codes:
 *  - 0: All checked feature module triads are complete and valid.
 *  - 1: Missing skills directory, incomplete triad, or missing/empty SKILL.md.
 *
 * Usage:
 *   php scripts/check-skills.php [--dir=.agents/skills] [--modules=frontend,tenancy,testing]
 */

declare(strict_types=1);

$options = getopt('', ['dir::', 'skills-dir::', 'path::', 'modules::', 'help']);

if (isset($options['help'])) {
    fwrite(STDOUT, "Auditor de Skills (SPEC-03 Agentic Harness)\n");
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
    // Auto-discover feature modules from .agents/skills/
    // Matches directories like {module}-(architecture|conventions|maintenance)
    $items = scandir($skillsDir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($skillsDir.'/'.$item)) {
                if (preg_match('/^([a-z0-9-]+)-(architecture|conventions|maintenance)$/', $item, $matches)) {
                    $modulesToCheck[] = $matches[1];
                }
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

$requiredSkillSuffixes = ['architecture', 'conventions', 'maintenance'];
$hasErrors = false;
$passedCount = 0;
$totalModules = count($modulesToCheck);

fwrite(STDOUT, sprintf("Iniciando auditoria de skills em '%s'...\n", $skillsDir));
fwrite(STDOUT, sprintf("Módulos a serem auditados (%d): %s\n\n", $totalModules, implode(', ', $modulesToCheck)));

foreach ($modulesToCheck as $module) {
    $moduleErrors = [];

    foreach ($requiredSkillSuffixes as $suffix) {
        $skillDirName = sprintf('%s-%s', $module, $suffix);
        $skillPath = sprintf('%s/%s/SKILL.md', $skillsDir, $skillDirName);

        if (! file_exists($skillPath)) {
            $moduleErrors[] = sprintf('Skill ausente: %s/SKILL.md', $skillDirName);
        } elseif (filesize($skillPath) === 0) {
            $moduleErrors[] = sprintf('Skill vazia: %s/SKILL.md', $skillDirName);
        }
    }

    if (! empty($moduleErrors)) {
        $hasErrors = true;
        fwrite(STDERR, sprintf("[FALHA] Módulo '%s' com tríade incompleta:\n", $module));
        foreach ($moduleErrors as $err) {
            fwrite(STDERR, sprintf("  - %s\n", $err));
        }
    } else {
        $passedCount++;
        fwrite(STDOUT, sprintf("[OK] Módulo '%s': Tríade de 3 skills completa (architecture, conventions, maintenance).\n", $module));
    }
}

fwrite(STDOUT, "\n--------------------------------------------------\n");

if ($hasErrors) {
    fwrite(STDERR, sprintf("FALHA AUDITORIA: %d de %d módulo(s) possuem tríade de skills incompleta ou ausente.\n", $totalModules - $passedCount, $totalModules));
    exit(1);
}

fwrite(STDOUT, sprintf("SUCESSO AUDITORIA: Todos os %d módulo(s) contêm a tríade completa de skills (exit 0).\n", $passedCount));
exit(0);
