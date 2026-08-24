<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id') ?? $this->route('category');
        $category = is_numeric($id) ? Category::find((int)$id) : null;

        return $category
            ? ($this->user()?->can('update', $category) ?? false)
            : ($this->user()?->hasRole('admin') || $this->user()?->can('items.edit') ?? false);
    }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'required', 'string', 'max:100'],
            'icon'       => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
