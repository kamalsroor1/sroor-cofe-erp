<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

/**
 * PRD-06: what an import wizard run creates. The value is the `{type}` URL segment.
 */
enum ImportType: string
{
    case Items = 'items';
    case Customers = 'customers';
    case Suppliers = 'suppliers';
    case Categories = 'categories';

    /** Route constraint for `{type}`. */
    public static function routePattern(): string
    {
        return implode('|', array_map(static fn (self $type): string => $type->value, self::cases()));
    }

    /**
     * Same authorization as creating the records one by one (admin role always passes).
     * Categories follow CategoryPolicy::create (items.create).
     */
    public function authorizes(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return match ($this) {
            self::Items, self::Categories => $user->can('items.create'),
            self::Customers => $user->can('customers.manage'),
            self::Suppliers => $user->can('suppliers.manage'),
        };
    }
}
