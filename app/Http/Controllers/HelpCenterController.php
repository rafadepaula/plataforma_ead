<?php

namespace App\Http\Controllers;

use App\Services\HelpArticleResolverService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpCenterController extends Controller
{
    public function __construct(
        protected HelpArticleResolverService $resolver
    ) {}

    public function index(Request $request): View
    {
        $query = $this->resolver->queryAccessibleArticles();

        if ($request->filled('q')) {
            $search = '%'.$request->input('q').'%';
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', $search)
                    ->orWhere('content', 'like', $search);
            });
        }

        $articles = $query->orderBy('category')->orderBy('title')->get();
        $grouped = $articles->groupBy(fn ($article): string => $article->category ?: 'Geral');

        return view('help.index', [
            'groupedArticles' => $grouped,
            'search' => $request->input('q', ''),
        ]);
    }

    public function show(string $slug): View
    {
        $article = $this->resolver->findAccessibleBySlug($slug);

        if (! $article) {
            abort(404);
        }

        return view('help.show', [
            'article' => $article,
        ]);
    }
}
