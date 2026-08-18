<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CheckSkillsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harness:check-skills {--path= : Path to skills directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify feature skill coverage and mandatory harness skills';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $scriptPath = base_path('scripts/check-skills.php');

        if (file_exists($scriptPath)) {
            $cmd = [PHP_BINARY, $scriptPath];
            if ($path = $this->option('path')) {
                $cmd[] = "--path={$path}";
            }

            $process = new Process($cmd, base_path());
            $process->run();

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());

            if (! empty($output)) {
                $this->line($output);
            }

            if (! empty($errorOutput)) {
                $this->error($errorOutput);
            }

            if (! $process->isSuccessful()) {
                $this->error('Skill audit failed via scripts/check-skills.php.');

                return Command::FAILURE;
            }

            $this->info('Harness skill audit passed: All feature skills verified successfully via scripts/check-skills.php.');

            return Command::SUCCESS;
        }

        // Fallback audit logic when scripts/check-skills.php is executed directly in Artisan
        $skillsDir = $this->option('path') ?? base_path('.agents/skills');

        if (! is_dir($skillsDir)) {
            $this->error("Skills directory not found at [{$skillsDir}].");

            return Command::FAILURE;
        }

        $auditResult = $this->auditSkillsDirectory($skillsDir);

        if (! $auditResult['success']) {
            foreach ($auditResult['errors'] as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Harness skill audit passed: %d feature(s) verified with required 3-skill coverage.',
            $auditResult['feature_count']
        ));

        return Command::SUCCESS;
    }

    /**
     * Audit skills directory for 3 mandatory skills per feature.
     *
     * @return array{success: bool, feature_count: int, errors: array<int, string>}
     */
    public function auditSkillsDirectory(string $skillsDir): array
    {
        if (! is_dir($skillsDir)) {
            return [
                'success' => false,
                'feature_count' => 0,
                'errors' => ["Directory [{$skillsDir}] does not exist."],
            ];
        }

        $entries = scandir($skillsDir) ?: [];
        $features = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $skillsDir.'/'.$entry;
            if (! is_dir($fullPath)) {
                continue;
            }

            if (preg_match('/^([a-z0-9_-]+)-(architecture|conventions|maintenance)$/', $entry, $matches)) {
                $feature = $matches[1];
                $type = $matches[2];
                $features[$feature][$type] = file_exists($fullPath.'/SKILL.md');
            }
        }

        $errors = [];
        $validFeatures = 0;

        foreach ($features as $feature => $types) {
            $requiredTypes = ['architecture', 'conventions', 'maintenance'];
            $missing = [];

            foreach ($requiredTypes as $required) {
                if (empty($types[$required])) {
                    $missing[] = "{$feature}-{$required}/SKILL.md";
                }
            }

            if (! empty($missing)) {
                $errors[] = sprintf('Feature [%s] is missing required skill file(s): %s', $feature, implode(', ', $missing));
            } else {
                $validFeatures++;
            }
        }

        return [
            'success' => empty($errors),
            'feature_count' => $validFeatures,
            'errors' => $errors,
        ];
    }
}
