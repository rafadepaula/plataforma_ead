<?php

namespace App\Http\Controllers;

use App\Actions\RestoreCertificateAction;
use App\Actions\RevokeCertificateAction;
use App\Http\Requests\RevokeCertificateRequest;
use App\Models\Certificate;
use App\Models\Course;
use App\Services\CertificatePdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        protected RestoreCertificateAction $restoreCertificateAction,
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

        // The write path is shared by the per-course listing and the
        // Gestor's students-directory "Certificados" modal, so we go back
        // to wherever the confirm modal was submitted from (the students
        // directory) with a fallback to the per-course listing.
        return redirect()->back(fallback: route('courses.certificates.index', $certificate->course_id))
            ->with('success', 'Certificado invalidado com sucesso.');
    }

    /**
     * Undo of a logical revocation (`RestoreCertificateAction` clears
     * `revoked_at`/`revoked_by`/`revoke_reason`, never deleting the row).
     * Authorization is `CertificatePolicy::restore()` via plain
     * `Gate::authorize()` — unlike revocation there is no reason to
     * validate, so no Form Request is involved.
     */
    public function restore(Request $request, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('restore', $certificate);

        $this->restoreCertificateAction->execute(
            $certificate,
            $request->user(),
        );

        return redirect()->back(fallback: route('courses.certificates.index', $certificate->course_id))
            ->with('success', 'Certificado validado com sucesso.');
    }

    /**
     * Authorization lives in `CertificatePolicy::download()`: the owning
     * Aluno always, plus Admin (unrestricted) and Gestor/Professor of the
     * certificate's own Org — 403 otherwise (cross-org staff, any other
     * Aluno).
     */
    public function download(Certificate $certificate): Response
    {
        Gate::authorize('download', $certificate);

        // `stream()` renders the PDF inline (`Content-Disposition: inline`),
        // so the browser opens it in its built-in PDF viewer instead of
        // forcing a file download.
        return $this->certificatePdfService->generate($certificate)
            ->stream("certificado-{$certificate->validation_hash}.pdf");
    }
}
