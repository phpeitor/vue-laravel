<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'username' => ['required', 'string', 'max:255', 'unique:users_laravel,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users_laravel,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'exists:roles,name'],

            'channels' => ['nullable', 'array'],
            'channels.*' => [
                'integer',
                Rule::exists('communication_channels', 'id')
                    ->where(fn ($q) => $q->where('status', 'ACTIVO')),
            ],

            'room_assignments' => ['nullable', 'array'],
            'room_assignments.*' => [
                'nullable',
                'integer',
                Rule::exists('room', 'id')->where(fn ($q) => $q->where('estado', true)),
            ],
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'name',
            'username' => 'username',
            'email' => 'email',
            'password' => 'password',
            'role' => 'role',
        ];
    }
}
