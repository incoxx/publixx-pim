<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleProductVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled by controller policy check
    }

    public function rules(): array
    {
        return [
            'publish_at' => ['required', 'date', 'after:now'],
        ];
    }
}
