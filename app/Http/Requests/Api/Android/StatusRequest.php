<?php

namespace App\Http\Requests\Api\Android;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sms_log_id' => ['required', 'string', 'exists:sms_logs,id'],
            'status' => ['required', 'string', 'max:50'],
            'raw_response' => ['nullable', 'string'],
            'external_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
