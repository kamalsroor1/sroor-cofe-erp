<?php

return [

    /*
    | Content-Security-Policy of the tenant / central-hub SPA shell (SpaSecurityHeaders).
    | CSP_REPORT_ONLY=true sends Content-Security-Policy-Report-Only instead of enforcing
    | (roll-out / debugging switch). Default: enforced. There is no report-uri endpoint.
    | The platform-console (admin host) policy is always enforced (AdminSecurityHeaders).
    */
    'csp' => [
        'report_only' => filter_var(env('CSP_REPORT_ONLY', false), FILTER_VALIDATE_BOOL),
    ],

];
