<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $this->user->id],
            'password' => ['required', 'string', 'min:8'],
            'estado' => ['required', 'boolean'], 
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
            'estado' => 'estado',
        ];
    }
}