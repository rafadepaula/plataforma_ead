<?php

namespace Tests\Unit\Skills;

use App\Console\Commands\CheckSkillsCommand;
use Tests\TestCase;

class SkillAuditorTest extends TestCase
{
    /**
     * Test auditing a valid skills directory structure containing all 3 required skills per feature.
     */
    public function test_audits_valid_feature_skills_structure_successfully(): void
    {
        $tempDir = sys_get_temp_dir().'/harness_test_skills_'.uniqid();

        mkdir($tempDir.'/course-architecture', 0777, true);
        file_put_contents($tempDir.'/course-architecture/SKILL.md', '# Course Architecture');

        mkdir($tempDir.'/course-conventions', 0777, true);
        file_put_contents($tempDir.'/course-conventions/SKILL.md', '# Course Conventions');

        mkdir($tempDir.'/course-maintenance', 0777, true);
        file_put_contents($tempDir.'/course-maintenance/SKILL.md', '# Course Maintenance');

        $command = new CheckSkillsCommand;
        $result = $command->auditSkillsDirectory($tempDir);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['feature_count']);
        $this->assertEmpty($result['errors']);

        @unlink($tempDir.'/course-architecture/SKILL.md');
        @rmdir($tempDir.'/course-architecture');
        @unlink($tempDir.'/course-conventions/SKILL.md');
        @rmdir($tempDir.'/course-conventions');
        @unlink($tempDir.'/course-maintenance/SKILL.md');
        @rmdir($tempDir.'/course-maintenance');
        @rmdir($tempDir);
    }

    /**
     * Test auditing a skills directory missing required feature skills fails.
     */
    public function test_fails_audit_when_feature_is_missing_required_skill(): void
    {
        $tempDir = sys_get_temp_dir().'/harness_test_skills_'.uniqid();

        mkdir($tempDir.'/auth-architecture', 0777, true);
        file_put_contents($tempDir.'/auth-architecture/SKILL.md', '# Auth Architecture');

        mkdir($tempDir.'/auth-conventions', 0777, true);
        file_put_contents($tempDir.'/auth-conventions/SKILL.md', '# Auth Conventions');

        $command = new CheckSkillsCommand;
        $result = $command->auditSkillsDirectory($tempDir);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('auth', $result['errors'][0]);
        $this->assertStringContainsString('auth-maintenance/SKILL.md', $result['errors'][0]);

        @unlink($tempDir.'/auth-architecture/SKILL.md');
        @rmdir($tempDir.'/auth-architecture');
        @unlink($tempDir.'/auth-conventions/SKILL.md');
        @rmdir($tempDir.'/auth-conventions');
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
