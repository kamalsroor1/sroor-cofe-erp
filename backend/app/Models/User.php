<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'api_token',
        'last_login_at',
        'is_active',
        'default_store_id',
        'theme_preference',
        'show_print_subtitle',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'show_print_subtitle' => 'boolean',
        ];
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_user')->withTimestamps();
    }

    public function defaultStore()
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    public function getCurrentStore(): ?Store
    {
        if (session()->has('current_store_id')) {
            $sessionStore = Store::find(session('current_store_id'));
            if ($sessionStore && $sessionStore->is_active) {
                return $sessionStore;
            }
        }

        if ($this->defaultStore && $this->defaultStore->is_active) {
            return $this->defaultStore;
        }

        return $this->stores()->where('is_active', true)->first() ?? Store::getMainStore();
    }
}
