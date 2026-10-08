<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * تطبيق نطاق عزل المستأجر تلقائياً على كل الاستعلامات.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = static::getTenantId();

        if ($tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }

    /**
     * استخراج معرف المستأجر الحالي من السياق المتاح:
     * 1. حزمة stancl/tenancy المهيأة
     * 2. الجلسة session('current_tenant_id')
     * 3. المستخدم المسجل حالياً auth()->user()->tenant_id
     */
    public static function getTenantId(): ?string
    {
        // 1. من خلال Stancl Tenancy
        if (function_exists('tenant') && tenant('id')) {
            return (string) tenant('id');
        }

        // 2. من خلال الجلسة
        if (session()->has('current_tenant_id')) {
            return (string) session('current_tenant_id');
        }

        // 3. من خلال المستخدم المسجل
        if (auth()->check() && ! empty(auth()->user()->tenant_id)) {
            return (string) auth()->user()->tenant_id;
        }

        return null;
    }
}
