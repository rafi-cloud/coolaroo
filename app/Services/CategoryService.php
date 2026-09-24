<?php

namespace App\Services;

use App\Models\MenuCategory;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(array $data): MenuCategory
    {
        $category = MenuCategory::create($data);

        $this->auditLogger->log(null, 'category_create', $category);

        return $category;
    }

    public function update(MenuCategory $category, array $data): MenuCategory
    {
        $category->update($data);

        $this->auditLogger->log(null, 'category_update', $category);

        return $category;
    }

    public function deactivate(MenuCategory $category, Staff $actor, ?string $reason = null): void
    {
        $category->update(['is_active' => false]);

        $this->auditLogger->snapshot($actor, 'category_deactivate', $category, $reason);
    }

    public function reactivate(MenuCategory $category, Staff $actor): void
    {
        $category->update(['is_active' => true]);

        $this->auditLogger->log($actor, 'category_reactivate', $category);
    }

    public function delete(MenuCategory $category): void
    {
        if (! $category->isEmpty()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has items or subcategories.',
            ]);
        }

        $category->delete();
    }
}
