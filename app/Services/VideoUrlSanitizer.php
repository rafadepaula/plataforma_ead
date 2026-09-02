<?php

namespace App\Services;

use App\Exceptions\InvalidVideoUrlException;

/**
 * Contract every video provider sanitizer implements: validates a raw
 * user input is a genuine link of ONE provider and rewrites it to the
 * canonical embeddable form (the only form ever persisted). Implementations
 * must be stateless — they are resolved through the container and reached
 * via {@see VideoUrlSanitizerManager}.
 */
interface VideoUrlSanitizer
{
    /**
     * Validates the URL belongs to this provider and returns the
     * canonical, embeddable URL.
     *
     * @throws InvalidVideoUrlException
     */
    public function sanitize(string $url): string;

    /**
     * Non-throwing counterpart of `sanitize()`: the provider's video id
     * for any supported URL form, or `null` when the value is
     * empty/unrecognizable. Used by consumers that must degrade gracefully
     * (the classroom player) and by data normalization migrations, which
     * must leave unrecognizable rows intact rather than blowing up
     * mid-batch.
     */
    public function extractVideoId(?string $url): ?string;

    /**
     * Non-throwing canonicalization: the embeddable URL for any supported
     * input, or `null` when the value cannot be resolved to a video id.
     */
    public function tryCanonicalize(?string $url): ?string;
}
