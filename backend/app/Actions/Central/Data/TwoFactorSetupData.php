<?php

declare(strict_types=1);

namespace App\Actions\Central\Data;

/**
 * What an operator needs to register the authenticator app (IDEN-1.12 enable step):
 * the base32 secret for manual entry, the otpauth:// URL and its QR code as SVG.
 */
final class TwoFactorSetupData
{
    public function __construct(
        public readonly string $secret,
        public readonly string $otpauthUrl,
        public readonly string $qrCodeSvg,
    ) {}
}
