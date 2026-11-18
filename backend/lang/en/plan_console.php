<?php

declare(strict_types=1);

/*
| PLAN-14: full plan control in the platform console (free flag, impact confirmation,
| change history, shops near a limit, signup settings).
*/

return [
    'free_plan_label' => 'Permanent free plan',
    'free_plan_hint' => 'Shops on this plan never receive invoices and never expire. It needs no trial days and every price must be 0.',
    'impact_title' => 'This change affects :count shops',
    'impact_text' => 'The new settings apply at once to every shop on this plan (:count). Save the changes?',
    'impact_confirm' => 'Save and apply',
    'unit_mb' => 'MB',

    'history' => [
        'tab' => 'History',
        'title' => 'Recent changes to this plan',
        'empty' => 'No changes recorded for this plan yet.',
        'load_failed' => 'Could not load the plan history.',
        'created' => 'Plan created',
        'updated' => 'Plan updated',
        'unknown_operator' => 'Unknown operator',
        'changed_fields' => 'Changed fields',
    ],

    'near_limit' => [
        'title' => 'Shops near their limit',
        'subtitle' => 'Shops using :percent% or more of a plan limit. Good candidates for an upgrade.',
        'col_shop' => 'Shop',
        'col_plan' => 'Plan',
        'col_resource' => 'Resource',
        'col_usage' => 'Usage',
        'over_limit' => 'Over the limit',
        'empty_title' => 'No shop is close to a limit',
        'empty_description' => 'Shops that reach :percent% of any limit will show up here.',
        'load_failed' => 'Could not load the shops near their limit.',
        'refresh' => 'Refresh',
        'scanned' => 'Checked :count shops',
        'capped' => 'Only the first :count shops were checked.',
        'failed' => ':count shops could not be read and were skipped.',
        'updated_at' => 'Updated :time',
        'open_shop' => 'Open the shop :name',
    ],

    'signup' => [
        'title' => 'Signup and free plan',
        'subtitle' => 'Which plan new shops start on, where they land after the trial, and how many accounts are created per day.',
        'default_plan' => 'Default signup plan',
        'default_plan_hint' => 'The plan a new self-signup shop starts on.',
        'default_plan_placeholder' => 'Not set',
        'fallback_plan' => 'Plan after the trial ends',
        'fallback_plan_hint' => 'A shop that does not pay by the end of its trial moves to this free plan. Only permanent free plans are listed.',
        'fallback_plan_placeholder' => 'Use the catalog free plan',
        'effective_fallback' => 'Applies now: :plan',
        'effective_fallback_none' => 'No free plan is configured: shops stay read-only after the trial.',
        'self_signup_enabled' => 'Self-signup',
        'self_signup_enabled_hint' => 'Let new shops sign up from the public page without approval.',
        'daily_cap' => 'Daily automatic account cap',
        'daily_cap_hint' => 'Maximum accounts created automatically each day. Leave empty for the default.',
        'daily_cap_placeholder' => 'Default',
        'plans_unavailable' => 'The plan list could not be loaded, so the plans cannot be changed here.',
    ],
];
