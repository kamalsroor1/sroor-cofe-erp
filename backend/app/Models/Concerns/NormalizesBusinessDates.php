<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * DATE-03: business dates are cast as 'date:Y-m-d' so they serialize as the plain business
 * date. Eloquent only normalizes inbound values for the plain 'date'/'datetime' casts, so a
 * custom-format cast would store whatever was assigned: '2026-06-12' from a request but
 * '2026-06-12 00:00:00' from a Carbon. On drivers that keep the raw text (SQLite) that breaks
 * ordering and range filters on the column. Normalize them exactly as the plain cast did.
 */
trait NormalizesBusinessDates
{
    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        if ($value !== null && $value !== '' && $this->isDateCastableWithCustomFormat($key)) {
            $value = $this->fromDateTime($value);
        }

        return parent::setAttribute($key, $value);
    }
}
