<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplyRequest extends FormRequest
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
            'event' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
            'data.phone' => ['required', 'string', 'max:20'],
            'data.message' => ['nullable', 'string', 'max:1600'],
            'data.external_id' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'raw_response' => ['nullable'],
            'replied_at' => ['nullable', 'string', 'max:255'],
        ];
    }
}
