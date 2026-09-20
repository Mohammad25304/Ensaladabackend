<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncMenuItemBranchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Body: { "branches": [{"branch_id": 1, "price": 10, "is_available": true}, ...] }
     * A branch left out of the array simply means this item isn't
     * offered there.
     */
    public function rules(): array
    {
        return [
            'branches' => ['present', 'array'],
            'branches.*.branch_id' => ['required', 'exists:branches,id'],
            'branches.*.price' => ['required', 'numeric', 'min:0'],
            'branches.*.is_available' => ['nullable', 'boolean'],
        ];
    }
}
