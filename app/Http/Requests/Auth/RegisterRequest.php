<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'email'      => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at'),],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'phone'      => ['nullable', 'string', 'unique:users,phone'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
        ];
    }
}
