<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class VerifyDevDbPreservedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harness:verify-dev-db-preserved
        {subcommand : snapshot or verify}
        {--env= : Path to the .env file to read MySQL credentials from}
        {--database= : Database to inspect (defaults to DB_DATABASE from the given .env)}
        {--out= : Path to save/read the snapshot JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Guarda de preservação do banco de desenvolvimento: snapshot/verify via scripts/verify-dev-db-preserved.php';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $scriptPath = base_path('scripts/verify-dev-db-preserved.php');

        if (! file_exists($scriptPath)) {
            $this->error("Script não encontrado em {$scriptPath}.");

            return Command::FAILURE;
        }

        $cmd = [PHP_BINARY, $scriptPath, $this->argument('subcommand')];

        foreach (['env', 'database', 'out'] as $option) {
            $value = $this->option($option);
            if ($value !== null && $value !== '') {
                $cmd[] = "--{$option}={$value}";
            }
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

        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
