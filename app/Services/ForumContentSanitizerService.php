<?php

namespace App\Services;

/**
 * strips every HTML tag from forum topic/reply content and
 * report reasons before persisting them. No HTML-purifier package is
 * installed (see CLAUDE.md's "no dependencies without approval"), so
 * `strip_tags()` is the entire write-side XSS defense; Blade's default
 * `{{ }}` escaping in every forum view (never `{!! !!}`) is the
 * complementary read-side defense-in-depth layer (see the plan's
 * description). Reused by every store/update path that persists
 * user-authored forum text — never duplicate a bare `strip_tags()` call
 * elsewhere.
 */
class ForumContentSanitizerService
{
    public function sanitize(string $content): string
    {
        return trim(strip_tags($content));
    }
}
