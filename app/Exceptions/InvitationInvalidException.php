<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * thrown by `InvitationController@show`/`@store` when a
 * `/convite/{token}` link cannot be resolved to a usable
 * `StudentInvitation` (not found, expired, revoked, or already used).
 * Must never surface as a raw 500 — mapped globally in `bootstrap/app.php`
 * to a content-negotiated response, the same pattern used for
 * `CourseHasActiveEnrollmentsException`/`UnresolvedOrgContextException`.
 *
 * Carries a typed reason so the public error screen can state *why* the
 * link no longer works instead of one catch-all sentence: the internal
 * `getMessage()` keeps the token for the log, while {@see self::userMessage()}
 * returns the visitor-facing copy.
 */
class InvitationInvalidException extends RuntimeException
{
    public const REASON_NOT_FOUND = 'not_found';

    public const REASON_EXPIRED = 'expired';

    public const REASON_REVOKED = 'revoked';

    public const REASON_USED = 'used';

    public const REASON_INACTIVE = 'inactive';

    /**
     * Visitor-facing copy per reason. Deliberately free of any blame or
     * alert wording: clicking an old link is not the student's mistake.
     *
     * @var array<string, string>
     */
    private const USER_MESSAGES = [
        self::REASON_NOT_FOUND => 'Este convite não foi encontrado.',
        self::REASON_EXPIRED => 'Este convite expirou.',
        self::REASON_REVOKED => 'Este convite foi cancelado.',
        self::REASON_USED => 'Este convite já foi utilizado.',
        self::REASON_INACTIVE => 'Esta conta está inativa. Procure o gestor da sua organização.',
    ];

    private readonly string $reason;

    /**
     * An unrecognised reason degrades to the not-found copy instead of
     * turning the global 404 handler into a 500 on an undefined key.
     */
    public function __construct(string $message = '', string $reason = self::REASON_NOT_FOUND, int $code = 0, ?Throwable $previous = null)
    {
        $this->reason = array_key_exists($reason, self::USER_MESSAGES) ? $reason : self::REASON_NOT_FOUND;

        parent::__construct($message, $code, $previous);
    }

    public static function notFound(string $token): self
    {
        return self::forReason(self::REASON_NOT_FOUND, $token);
    }

    public static function expired(string $token): self
    {
        return self::forReason(self::REASON_EXPIRED, $token);
    }

    public static function revoked(string $token): self
    {
        return self::forReason(self::REASON_REVOKED, $token);
    }

    public static function used(string $token): self
    {
        return self::forReason(self::REASON_USED, $token);
    }

    /**
     * Builds the exception straight from a {@see StudentInvitation::unusableReason()}
     * verdict, so callers never have to branch on the reason themselves.
     * An unknown reason degrades to the not-found copy rather than
     * throwing a second, unrelated error at the visitor.
     */
    public static function forReason(string $reason, string $token): self
    {
        $reason = array_key_exists($reason, self::USER_MESSAGES) ? $reason : self::REASON_NOT_FOUND;

        return new self("Convite '{$token}' indisponível ({$reason}).", $reason);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function userMessage(): string
    {
        return self::USER_MESSAGES[$this->reason];
    }
}
