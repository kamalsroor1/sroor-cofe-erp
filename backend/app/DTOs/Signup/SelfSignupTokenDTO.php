<?php

declare(strict_types=1);

namespace App\DTOs\Signup;

use SensitiveParameter;

/**
 * SIGN-11: the opaque self-signup token sent back by the browser, plus the code on /verify.
 */
final readonly class SelfSignupTokenDTO
{
    public function __construct(
        #[SensitiveParameter] public string $token,
        #[SensitiveParameter] public ?string $code = null,
    ) {}

    /**
     * @param  array{token: string, code?: string|null}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        $code = $data['code'] ?? null;

        return new self(
            token: trim($data['token']),
            code: is_string($code) && $code !== '' ? $code : null,
        );
    }
}
