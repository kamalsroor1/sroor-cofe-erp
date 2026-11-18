<?php

declare(strict_types=1);

/*
| PLAN-12: per-shop limit overrides and feature grant / revoke (platform console).
*/

return [
    'limit_saved' => 'The shop limit was saved.',
    'feature_saved' => 'The shop feature setting was saved.',
    'removed' => 'The override was removed. The plan applies again.',
    'not_found' => 'This shop has no such override.',
    'feature_unknown' => 'This feature is not in the catalog.',
    'toggle_reason' => 'Enabled manually by the platform operator',
    'legacy_migrated_reason' => 'Migrated from the old manual feature override',
];
