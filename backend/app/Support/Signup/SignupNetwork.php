<?php

declare(strict_types=1);

namespace App\Support\Signup;

/**
 * Security review (SIGN-13): the client network a self-signup came from, for the per-network
 * daily cap of automatic provisionings: IPv4 /signup.network_cap.ipv4_prefix (default 24),
 * IPv6 /signup.network_cap.ipv6_prefix (default 48); an IPv4-mapped IPv6 address counts as IPv4.
 * Stored in `signup_requests.ip_network` ("203.0.113.0/24", "2001:db8:1::/48"). Null for a
 * missing or unparsable address (such a row is never network-capped).
 */
final class SignupNetwork
{
    public static function of(?string $ip): ?string
    {
        $packed = @inet_pton(trim((string) $ip));
        if ($packed === false) {
            return null;
        }

        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $packed = substr($packed, 12);
        }

        $isV4 = strlen($packed) === 4;
        $bits = $isV4 ? 32 : 128;
        $prefix = (int) config($isV4 ? 'signup.network_cap.ipv4_prefix' : 'signup.network_cap.ipv6_prefix', $isV4 ? 24 : 48);
        $prefix = max(1, min($bits, $prefix));

        $masked = '';
        foreach (str_split($packed) as $index => $byte) {
            $keep = max(0, min(8, $prefix - ($index * 8)));
            $mask = $keep === 0 ? 0 : (0xFF << (8 - $keep)) & 0xFF;
            $masked .= chr(ord($byte) & $mask);
        }

        $network = inet_ntop($masked);

        return $network === false ? null : $network.'/'.$prefix;
    }
}
