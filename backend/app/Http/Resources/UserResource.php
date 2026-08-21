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
