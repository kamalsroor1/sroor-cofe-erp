<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * SHOP-01: both POS checkout endpoints require an idempotency key (`client_uuid` in the body or the
 * Idempotency-Key header). Tests that are not about idempotency get a fresh key per request, the way
 * the POS sends one per payment attempt, so they keep testing what they are about. A test that sends
 * its own key (body key present, even null, or the header) is left untouched; a test that checks the
 * missing-key rejection calls withoutCheckoutKey() first.
 */
trait SendsCheckoutIdempotencyKey
{
    private bool $autoCheckoutKey = true;

    protected function withoutCheckoutKey(): static
    {
        $this->autoCheckoutKey = false;

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $headers
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        if ($this->autoCheckoutKey
            && strtoupper((string) $method) === 'POST'
            && preg_match('#/api/v1/(invoices|pos/checkout)/?(\?.*)?$#', (string) $uri) === 1
            && ! array_key_exists('client_uuid', $data)
            && ! $this->sendsIdempotencyHeader($headers)) {
            $data['client_uuid'] = (string) Str::uuid();
        }

        return parent::json($method, $uri, $data, $headers, $options);
    }

    /** @param  array<string, mixed>  $headers */
    private function sendsIdempotencyHeader(array $headers): bool
    {
        foreach (array_merge($this->defaultHeaders, $headers) as $name => $_) {
            if (strcasecmp((string) $name, 'Idempotency-Key') === 0) {
                return true;
            }
        }

        return false;
    }
}
