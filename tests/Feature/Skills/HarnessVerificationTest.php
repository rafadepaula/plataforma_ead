<?php

namespace Tests\Feature\Skills;

use Tests\TestCase;

class HarnessVerificationTest extends TestCase
{
    /**
     * Test that harness:check-skills command executes successfully against existing .agents/skills.
     */
    public function test_artisan_check_skills_command_executes_successfully(): void
    {
        $this->artisan('harness:check-skills')
            ->assertExitCode(0);
    }

    /**
     * Test that harness:check-skills returns non-zero status code when given invalid path.
     */
    public function test_artisan_check_skills_command_fails_with_invalid_path(): void
    {
        $invalidPath = sys_get_temp_dir().'/invalid_skills_'.uniqid();

        $this->artisan('harness:check-skills', ['--path' => $invalidPath])
            ->assertExitCode(1);
    }

    /**
     * Test that harness:check-skills returns status code 0 when given valid path with 3 skills.
     */
    public function test_artisan_check_skills_command_passes_with_custom_valid_path(): void
    {
        $tempDir = sys_get_temp_dir().'/harness_valid_skills_'.uniqid();

        mkdir($tempDir.'/quiz-architecture', 0777, true);
        file_put_contents($tempDir.'/quiz-architecture/SKILL.md', '# Quiz Architecture');

        mkdir($tempDir.'/quiz-conventions', 0777, true);
        file_put_contents($tempDir.'/quiz-conventions/SKILL.md', '# Quiz Conventions');

        mkdir($tempDir.'/quiz-maintenance', 0777, true);
        file_put_contents($tempDir.'/quiz-maintenance/SKILL.md', '# Quiz Maintenance');

        $this->artisan('harness:check-skills', ['--path' => $tempDir])
            ->expectsOutputToContain('Harness skill audit passed')
            ->assertExitCode(0);

        @unlink($tempDir.'/quiz-architecture/SKILL.md');
        @rmdir($tempDir.'/quiz-architecture');
        @unlink($tempDir.'/quiz-conventions/SKILL.md');
        @rmdir($tempDir.'/quiz-conventions');
        @unlink($tempDir.'/quiz-maintenance/SKILL.md');
        @rmdir($tempDir.'/quiz-maintenance');
        @rmdir($tempDir);
    }
}
