<?php

declare(strict_types=1);

return [
    /*
    | POS-10: default of the per-branch "require an open shift to sell" setting for branches that never
    | saved POS settings. ON in production; the test suite turns it off so the many sale fixtures do not
    | each need an open shift (tests of the rule itself set it explicitly).
    */
    'require_shift_by_default' => (bool) env('POS_REQUIRE_SHIFT_BY_DEFAULT', true),
];
