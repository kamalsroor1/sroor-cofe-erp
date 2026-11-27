<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Pulse caches its card results (Collections of stdClass rows with Carbon dates) through
 * Cache::remember(). With `cache.serializable_classes = false` they came back as
 * __PHP_Incomplete_Class and every Pulse poll answered 500 in production (redis store).
 */
final class PulseCacheSerializationTest extends TestCase
{
    public function test_pulse_card_results_survive_a_serializing_cache_store(): void
    {
        $store = Cache::store('file');
        $row = (object) ['key' => 'select 1', 'count' => 2, 'at' => CarbonImmutable::now()];

        $store->put('pulse-serialization-test', [collect([$row]), 1.5, now()->toDateTimeString()], 60);
        [$value] = $store->get('pulse-serialization-test');
        $store->forget('pulse-serialization-test');

        $this->assertSame('select 1', $value->first()->key);
        $this->assertInstanceOf(CarbonImmutable::class, $value->first()->at);
    }

    public function test_other_classes_are_still_refused(): void
    {
        $store = Cache::store('file');
        $store->put('pulse-serialization-test-2', new \ArrayObject([1]), 60);
        $value = $store->get('pulse-serialization-test-2');
        $store->forget('pulse-serialization-test-2');

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $value);
    }
}
