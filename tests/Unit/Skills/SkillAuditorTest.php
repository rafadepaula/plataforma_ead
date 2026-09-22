<?php

namespace Tests\Unit\Skills;

use App\Console\Commands\CheckSkillsCommand;
use Tests\TestCase;

class SkillAuditorTest extends TestCase
{
    /**
     * Test auditing a valid skills directory structure containing the
     * consolidated feature skill (SKILL.md + resource/ references).
     */
    public function test_audits_valid_feature_skills_structure_successfully(): void
    {
        $tempDir = sys_get_temp_dir().'/harness_test_skills_'.uniqid();

        mkdir($tempDir.'/course-maintenance/resource', 0777, true);
        file_put_contents($tempDir.'/course-maintenance/SKILL.md', '# Course');
        file_put_contents($tempDir.'/course-maintenance/resource/architecture.md', '# Course Architecture');
        file_put_contents($tempDir.'/course-maintenance/resource/conventions.md', '# Course Conventions');
        file_put_contents($tempDir.'/course-maintenance/resource/maintenance.md', '# Course Maintenance');
        file_put_contents($tempDir.'/course-maintenance/resource/security.md', '# Course Security');

        $command = new CheckSkillsCommand;
        $result = $command->auditSkillsDirectory($tempDir);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['feature_count']);
        $this->assertEmpty($result['errors']);

        @unlink($tempDir.'/course-maintenance/SKILL.md');
        @unlink($tempDir.'/course-maintenance/resource/architecture.md');
        @unlink($tempDir.'/course-maintenance/resource/conventions.md');
        @unlink($tempDir.'/course-maintenance/resource/maintenance.md');
        @unlink($tempDir.'/course-maintenance/resource/security.md');
        @rmdir($tempDir.'/course-maintenance/resource');
        @rmdir($tempDir.'/course-maintenance');
        @rmdir($tempDir);
    }

    /**
     * Test auditing a skills directory missing required feature references fails.
     */
    public function test_fails_audit_when_feature_is_missing_required_skill(): void
    {
        $tempDir = sys_get_temp_dir().'/harness_test_skills_'.uniqid();

        mkdir($tempDir.'/auth-maintenance/resource', 0777, true);
        file_put_contents($tempDir.'/auth-maintenance/SKILL.md', '# Auth');
        file_put_contents($tempDir.'/auth-maintenance/resource/architecture.md', '# Auth Architecture');

        $command = new CheckSkillsCommand;
        $result = $command->auditSkillsDirectory($tempDir);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('auth', $result['errors'][0]);
        $this->assertStringContainsString('auth-maintenance/resource/conventions.md', $result['errors'][0]);
        $this->assertStringContainsString('auth-maintenance/resource/maintenance.md', $result['errors'][0]);

        @unlink($tempDir.'/auth-maintenance/SKILL.md');
        @unlink($tempDir.'/auth-maintenance/resource/architecture.md');
        @rmdir($tempDir.'/auth-maintenance/resource');
        @rmdir($tempDir.'/auth-maintenance');
        @rmdir($tempDir);
    }

    /**
     * Test auditing non-existent directory returns failure.
     */
    public function test_fails_audit_when_directory_does_not_exist(): void
    {
        $command = new CheckSkillsCommand;
        $result = $command->auditSkillsDirectory('/non/existent/path/'.uniqid());

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['feature_count']);
        $this->assertNotEmpty($result['errors']);
    }
}
