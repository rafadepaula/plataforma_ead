<?php

namespace Tests\Feature\Tenancy;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\StudentInvitation;
use App\Models\User;
use App\Services\UserImportService;
use Tests\TestCase;

class PublicFlowsHostScopeTest extends TestCase
{
    public function test_convite_responde_no_host_da_org_e_404_no_host_errado(): void
    {
        $orgA = Organization::factory()->create(['host' => 'portal.acme.test']);
        $orgB = Organization::factory()->create(['host' => 'portal.bsb.test']);
        $student = User::factory()->aluno()->create();
        Credential::factory()->pending()->create(['user_id' => $student->id, 'org_id' => $orgA->id]);
        $invitation = StudentInvitation::factory()->create([
            'org_id' => $orgA->id,
            'user_id' => $student->id,
        ]);

        $this->onHost('portal.acme.test')
            ->get("/convite/{$invitation->token}")
            ->assertOk();

        $this->onHost('portal.bsb.test')
            ->get("/convite/{$invitation->token}")
            ->assertNotFound();
    }

    public function test_csv_import_provisiona_credential_por_org(): void
    {
        $orgA = Organization::factory()->create(['host' => 'portal.acme.test']);
        $orgB = Organization::factory()->create(['host' => 'portal.bsb.test']);
        $courseB = Course::factory()->for($orgB)->create();
        $this->actingAsOrgUser($orgB);

        // A pessoa já estuda no portal A (conta lá); importo o CSV no portal B.
        $existingUser = User::factory()->aluno()->create(['email' => 'ana@acme.test']);
        Credential::factory()->create(['user_id' => $existingUser->id, 'org_id' => $orgA->id]);

        $service = app(UserImportService::class);
        $service->importChunk([
            ['name' => 'Ana Silva', 'email' => 'ana@acme.test'],
        ], $courseB->id, $orgB->id);

        // A mesma pessoa agora tem contas nas duas orgs.
        $user = User::query()->where('email', 'ana@acme.test')->firstOrFail();

        $this->assertSame(1, Credential::query()->forOrg($orgA->id)->where('user_id', $user->id)->count());
        $this->assertSame(1, Credential::query()->forOrg($orgB->id)->where('user_id', $user->id)->count());
    }

    public function test_certificado_verifica_no_host_da_org_e_404_no_outro(): void
    {
        $orgA = Organization::factory()->create(['host' => 'portal.acme.test']);
        $orgB = Organization::factory()->create(['host' => 'portal.bsb.test']);
        $course = Course::factory()->for($orgA)->published()->create();
        $student = User::factory()->aluno()->create();
        Credential::factory()->create(['user_id' => $student->id, 'org_id' => $orgA->id]);

        $certificate = Certificate::factory()
            ->for($student, 'user')
            ->for($course, 'course')
            ->create();

        $this->onHost('portal.acme.test')
            ->get("/validar-certificado/{$certificate->validation_hash}")
            ->assertOk();

        $this->onHost('portal.bsb.test')
            ->get("/validar-certificado/{$certificate->validation_hash}")
            ->assertNotFound();
    }
}
