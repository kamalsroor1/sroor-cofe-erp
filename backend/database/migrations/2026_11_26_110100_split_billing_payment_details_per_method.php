<?php

declare(strict_types=1);

use App\Services\Branding\PlatformBranding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #232 spec F / UI spec 6b (CENTRAL DB) data migration: the payment details became per method.
 * The old SHARED values are copied into the new per-method keys:
 *  - billing_account_name  -> billing_bank_account_holder;
 *  - billing_payment_note  -> billing_vodafone_cash_note, billing_instapay_note, billing_bank_note.
 *
 * Safe and idempotent:
 *  - a target that already holds a non-blank value is never overwritten (a re-run, or a value an
 *    operator already typed, wins);
 *  - blank sources copy nothing;
 *  - the OLD rows are left in place and stay readable (PlatformBranding still serves them in the
 *    legacy `payment_instructions` block), so nothing is lost if this is rolled back;
 *  - copied rows are written with `updated_by` NULL. down() removes only rows still in that state
 *    and still equal to their source value, so an operator's later edit survives a rollback.
 * The branding cache is bumped so tenants see the copy at once.
 */
return new class extends Migration
{
    /** source key => target keys */
    private const MAP = [
        'billing_account_name' => ['billing_bank_account_holder'],
        'billing_payment_note' => ['billing_vodafone_cash_note', 'billing_instapay_note', 'billing_bank_note'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $now = now();

        foreach ($this->sources() as $source => $value) {
            foreach (self::MAP[$source] as $target) {
                $row = DB::table('platform_settings')->where('key', $target)->first(['id', 'value']);

                if ($row === null) {
                    DB::table('platform_settings')->insert([
                        'key' => $target,
                        'value' => $value,
                        'type' => 'string',
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                if (trim((string) $row->value) === '') {
                    DB::table('platform_settings')->where('id', $row->id)->update([
                        'value' => $value,
                        'updated_by' => null,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        PlatformBranding::forget();
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        foreach ($this->sources() as $source => $value) {
            DB::table('platform_settings')
                ->whereIn('key', self::MAP[$source])
                ->where('value', $value)
                ->whereNull('updated_by')
                ->delete();
        }

        PlatformBranding::forget();
    }

    /**
     * @return array<string, string> source key => its trimmed, non-blank value
     */
    private function sources(): array
    {
        $values = [];

        foreach (DB::table('platform_settings')->whereIn('key', array_keys(self::MAP))->get(['key', 'value']) as $row) {
            $value = trim((string) $row->value);
            if ($value !== '') {
                $values[(string) $row->key] = $value;
            }
        }

        return $values;
    }
};
