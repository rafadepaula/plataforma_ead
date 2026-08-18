<?php

namespace App\Http\Controllers;

use App\Actions\RevokeCertificateAction;
use App\Enums\Permissions\RolesEnum;
use App\Http\Requests\RevokeCertificateRequest;
use App\Models\Certificate;
use App\Models\Course;
use App\Services\CertificatePdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * the Gestor/Admin certificate management screen:
 * a per-course list (`index`), revocation (`revoke`, delegating the write
 * path to `RevokeCertificateAction`/`CertificatePolicy`), and PDF download
 * (`download`, delegating to `CertificatePdfService`). Certificates are
 * never created/edited here — issuance is fully automatic via
 * `IssueCertificateAction` .
 */
class CertificateController extends Controller
{
    public function __construct(
        protected RevokeCertificateAction $revokeCertificateAction,
        protected CertificatePdfService $certificatePdfService,
    ) {}

    /**
     * `Course`'s own `OrgScope` already confines the route-model-bound
     * `{course}` to the acting user's tenant (mirrors
     * `CourseController::index()`'s reliance on the scope), so `view` here
     * only needs `CoursePolicy`'s plain role check.
     */
    public function index(Course $course): View
    {
        Gate::authorize('view', $course);

        $certificates = $course->certificates()
            ->with('user')
            ->latest('issued_at')
            ->paginate(15);

        return view('certificates.index', ['course' => $course, 'certificates' => $certificates]);
    }

    public function revoke(RevokeCertificateRequest $request, Certificate $certificate): RedirectResponse
    {
        $this->revokeCertificateAction->execute(
            $certificate,
            $request->user(),
            $request->validated('revoke_reason'),
        );

        return redirect()->route('courses.certificates.index', $certificate->course_id)
            ->with('success', 'Certificado revogado com sucesso.');
    }

    public function download(Certificate $certificate): Response
    {
        $this->authorizeDownloadAccess($certificate);

        return $this->certificatePdfService->generate($certificate)
            ->download("certificado-{$certificate->validation_hash}.pdf");
    }

    /**
     * `Certificate` has no dedicated `view`/`download` Policy method —
     * `CertificatePolicy` (Bucket A) only defines `revoke` — so this
     * inline check mirrors that Policy's own role/org logic (Admin
     * unrestricted, Gestor only within their own Org) rather than
     * expanding the Policy's contract for a single Controller action.
     * `Course` is read `withoutGlobalScopes()` for the same reason
     * `CertificatePolicy::parentCourse()` does: `Certificate` carries no
     * scope of its own, so a cross-org Gestor must see the REAL owning
     * Course to be correctly denied, not `null`.
     *
     * UC13 — also grants the Aluno who OWNS the certificate (`user_id`
     * match), since `certificates.download` sits behind plain `auth` (see
     * `routes/web.php`) to let the classroom's "baixar certificado" link
     * work for the student themselves, without opening the door to any
     * other Aluno's certificate.
     */
    private function authorizeDownloadAccess(Certificate $certificate): void
    {
        $user = request()->user();

        abort_unless($user, 403);

        if ((int) $user->id === (int) $certificate->user_id) {
            return;
        }

        abort_unless($user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::GESTOR->value]), 403);

        if ($user->hasRole(RolesEnum::GESTOR->value)) {
            $course = $certificate->course()->withoutGlobalScopes()->firstOrFail();

            abort_unless((int) $user->org_id === (int) $course->org_id, 403);
        }
    }
}
