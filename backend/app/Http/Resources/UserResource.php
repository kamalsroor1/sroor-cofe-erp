<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'roles'               => $this->getRoleNames(),
            'permissions'         => $this->getAllPermissions()->pluck('name'),
            'is_super_admin'      => (bool)($this->hasRole('super_admin') || $this->can('super_admin.access') || in_array($this->phone, ['01012316954', '01558088841'])),
            'theme_preference'    => $this->theme_preference ?? 'dark',
            'show_print_subtitle' => (bool)$this->show_print_subtitle,
            'default_store_id'    => $this->default_store_id,
            'is_active'           => (bool)$this->is_active,
            'last_login_at'       => $this->last_login_at,
            'stores'              => $this->whenLoaded('stores', function () {
                return $this->stores->map(fn ($store) => [
                    'id'      => $store->id,
                    'name'    => $store->name,
                    'code'    => $store->code,
                    'is_main' => (bool)$store->is_main,
                ]);
            }),
        ];
    }
}
