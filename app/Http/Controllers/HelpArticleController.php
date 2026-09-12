<?php

namespace App\Http\Controllers;

use App\Enums\Help\HelpAudienceEnum;
use App\Models\HelpArticle;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HelpArticleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', HelpArticle::class);

        $query = HelpArticle::withoutGlobalScopes()->with('organization');

        $activeOrgId = session('active_org_id');
        if ($activeOrgId) {
            $query->where(function ($q) use ($activeOrgId): void {
                $q->whereNull('org_id')
                    ->orWhere('org_id', $activeOrgId);
            });
        }

        $articles = $query->orderBy('category')->orderBy('title')->get();

        return view('help.manage.index', [
            'articles' => $articles,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', HelpArticle::class);

        return view('help.manage.create', [
            'article' => new HelpArticle,
            'categories' => $this->availableCategories(),
            'targetPageKeys' => $this->availableTargetPageKeys(),
            'organizations' => Organization::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', HelpArticle::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', Rule::unique('help_articles', 'slug')],
            'category' => ['required', 'string', 'max:50'],
            'target_page_key' => ['nullable', 'string', 'max:100'],
            'audience' => ['nullable', Rule::enum(HelpAudienceEnum::class)],
            'content' => ['required', 'string'],
            'org_id' => ['nullable', 'exists:organizations,id'],
        ]);

        $validated['audience'] ??= HelpAudienceEnum::ALUNO->value;

        if (empty($validated['org_id'])) {
            $validated['org_id'] = null;
        }
        HelpArticle::withoutEvents(fn () => HelpArticle::create($validated));

        return redirect()->route('org.help.artigos.index')
            ->with('success', 'Artigo de ajuda criado com sucesso.');
    }

    public function edit(Request $request, int $id): View
    {
        $article = $this->findAccessibleArticle($request, $id);
        Gate::authorize('update', $article);

        return view('help.manage.edit', [
            'article' => $article,
            'categories' => $this->availableCategories(),
            'targetPageKeys' => $this->availableTargetPageKeys(),
            'organizations' => Organization::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $article = $this->findAccessibleArticle($request, $id);
        Gate::authorize('update', $article);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', Rule::unique('help_articles', 'slug')->ignore($article->id)],
            'category' => ['required', 'string', 'max:50'],
            'target_page_key' => ['nullable', 'string', 'max:100'],
            'audience' => ['nullable', Rule::enum(HelpAudienceEnum::class)],
            'content' => ['required', 'string'],
            'org_id' => ['nullable', 'exists:organizations,id'],
        ]);

        $validated['audience'] ??= HelpAudienceEnum::ALUNO->value;

        if (empty($validated['org_id'])) {
            $validated['org_id'] = null;
        }
        HelpArticle::withoutEvents(fn () => $article->update($validated));

        return redirect()->route('org.help.artigos.index')
            ->with('success', 'Artigo de ajuda atualizado com sucesso.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $article = $this->findAccessibleArticle($request, $id);
        Gate::authorize('delete', $article);

        $article->delete();

        return redirect()->route('org.help.artigos.index')
            ->with('success', 'Artigo de ajuda removido com sucesso.');
    }

    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('create', HelpArticle::class);

        $content = (string) $request->input('content', '');
        $html = Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return response()->json([
            'html' => $html,
        ]);
    }

    private function findAccessibleArticle(Request $request, int $id): HelpArticle
    {
        return HelpArticle::withoutGlobalScopes()->findOrFail($id);
    }

    /**
     * @return list<string>
     */
    private function availableCategories(): array
    {
        return HelpArticle::withoutGlobalScopes()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function availableTargetPageKeys(): array
    {
        $configured = config('help.target_page_keys');

        if (is_array($configured) && ! empty($configured)) {
            return $configured;
        }

        return collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => ! str_starts_with($name, 'ignition.') && ! str_starts_with($name, '_debugbar'))
            ->sort()
            ->values()
            ->all();
    }
}
