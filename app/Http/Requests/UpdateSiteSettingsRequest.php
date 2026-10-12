<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        // Whitelist: any key not listed here is silently dropped by validated().
        return [
            'hero_badge' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:1000'],
            'about_title' => ['nullable', 'string', 'max:255'],
            'about_body_1' => ['nullable', 'string', 'max:3000'],
            'about_body_2' => ['nullable', 'string', 'max:3000'],
            'stat_1_value' => ['nullable', 'string', 'max:50'],
            'stat_1_label' => ['nullable', 'string', 'max:100'],
            'stat_2_value' => ['nullable', 'string', 'max:50'],
            'stat_2_label' => ['nullable', 'string', 'max:100'],
            'stat_3_value' => ['nullable', 'string', 'max:50'],
            'stat_3_label' => ['nullable', 'string', 'max:100'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'hours_weekday' => ['nullable', 'string', 'max:255'],
            'hours_weekend' => ['nullable', 'string', 'max:255'],
            // JSON array of {platform, url} objects, stored as text
            'social_links' => ['nullable', 'string', 'json', 'max:5000'],
        ];
    }
}
