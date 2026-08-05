<?php

namespace Tests\Unit\Middleware;

use App\Enums\Permissions\RolesEnum;
use App\Http\Middleware\EnsureStudentIsEnrolled;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * SPEC-07 RF20 — `EnsureStudentIsEnrolled::resolveCourse()`'s
 * `$courseParam instanceof Course` short-circuit (used whenever a route
 * type-hints `Course $course` and Laravel's own `SubstituteBindings`
 * middleware already resolved it, unlike every current `student.enrolled`
 * route — forum and classroom alike — which deliberately keeps `{course}`
 * a plain `int` param instead, see `EnsureStudentIsEnrolled`'s own
 * docblock). Exercised directly against the middleware rather than
 * through a real HTTP route: an actual implicit-bound route would run
 * `SubstituteBindings` (part of the `web` group) before `student.enrolled`,
 * and `Course`'s `OrgScope` would 404 it for a multi-org Aluno before this
 * branch is ever reached.
 */
class EnsureStudentIsEnrolledTest extends TestCase
{
    public function test_resolve_course_returns_an_already_bound_course_instance_unchanged(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $route = new Route('GET', '_test/probe/{course}', []);
        $route->bind($request = Request::create('/_test/probe'));
        $route->setParameter('course', $course);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $student);

        $middleware = new EnsureStudentIsEnrolled;
        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }
}
