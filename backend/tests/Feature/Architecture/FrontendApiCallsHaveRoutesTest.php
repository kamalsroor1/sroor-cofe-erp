<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TenantTestCase;

/**
 * Demo trial 2026-11-26 (cards #214, #215): the customer "collect" and supplier "pay"
 * buttons posted to /customers/{id}/payments and /suppliers/{id}/payments, which were
 * never registered (the API names them collect-payment and pay), so both flows 404'd.
 *
 * Guard: every literal `api.<verb>('/path')` call in the SPA that targets the two
 * contact-payment flows must resolve to a registered /api/v1 route with that verb.
 */
final class FrontendApiCallsHaveRoutesTest extends TenantTestCase
{
    /** Composables whose every literal api call must resolve to a route. */
    private const GUARDED_FILES = [
        'resources/js/Composables/useCustomers.js',
        'resources/js/Composables/useSuppliers.js',
    ];

    public function test_contact_payment_calls_resolve_to_registered_routes(): void
    {
        $unresolved = [];
        $checked = 0;

        foreach (self::GUARDED_FILES as $relative) {
            foreach ($this->literalCalls(base_path($relative)) as [$verb, $path]) {
                $checked++;
                if (! $this->resolves($verb, $path)) {
                    $unresolved[] = strtoupper($verb).' /api/v1'.$path.'  ('.$relative.')';
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'No api calls found — the scanner regex is stale.');
        $this->assertSame([], $unresolved, "Frontend calls with no backend route:\n".implode("\n", $unresolved));
    }

    public function test_customer_collect_and_supplier_pay_use_the_registered_paths(): void
    {
        $customers = (string) file_get_contents(base_path('resources/js/Composables/useCustomers.js'));
        $suppliers = (string) file_get_contents(base_path('resources/js/Composables/useSuppliers.js'));

        $this->assertStringContainsString('/collect-payment`', $customers);
        $this->assertStringContainsString('}/pay`', $suppliers);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function literalCalls(string $file): array
    {
        $source = (string) file_get_contents($file);
        preg_match_all('/\bapi\.(get|post|put|patch|delete)\(\s*([`\'"])(\/[^`\'"]*)\2/', $source, $m, PREG_SET_ORDER);

        $calls = [];
        foreach ($m as $match) {
            // `${expr}` placeholders become a numeric id; drop any query string.
            $path = preg_replace('/\$\{[^}]*\}/', '1', $match[3]) ?? $match[3];
            $path = explode('?', $path, 2)[0];
            $calls[] = [$match[1], $path];
        }

        return $calls;
    }

    private function resolves(string $verb, string $path): bool
    {
        try {
            Route::getRoutes()->match(Request::create('/api/v1'.$path, strtoupper($verb)));

            return true;
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }
    }
}
