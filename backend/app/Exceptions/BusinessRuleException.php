<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A domain rule refused the operation (not enough stock, document already cancelled, shift
 * already open, ...). Carries a translation key and its params; rendered by the exception
 * handler as `{success:false, message, error_code}` with HTTP 422 (invalid input / rule) or
 * 409 (state conflict). It is deliberately NOT an HttpException, and it is never reported:
 * a refused business operation is not a server fault.
 *
 * The message is translated at construction (so logs stay readable) and re-translated at
 * render time so the request locale wins.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $params
     */
    final protected function __construct(
        public readonly string $translationKey,
        public readonly array $params = [],
        public readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ) {
        parent::__construct((string) __($translationKey, $params));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function make(string $key, array $params = []): static
    {
        return new static($key, $params, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function conflict(string $key, array $params = []): static
    {
        return new static($key, $params, Response::HTTP_CONFLICT);
    }

    /**
     * Role/permission refusal raised from a service (403).
     *
     * @param  array<string, mixed>  $params
     */
    public static function forbidden(string $key, array $params = []): static
    {
        return new static($key, $params, Response::HTTP_FORBIDDEN);
    }

    public function errorCode(): string
    {
        return $this->translationKey;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function translatedMessage(): string
    {
        return (string) __($this->translationKey, $this->params);
    }

    /**
     * Business refusals are not server faults: keep them out of the log/Telegram reporters.
     */
    public function report(): bool
    {
        return false;
    }
}
