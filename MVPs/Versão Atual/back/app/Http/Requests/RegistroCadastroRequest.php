<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistroCadastroRequest extends FormRequest
{
    //User é permitido de mandar request
    public function authorize(): bool
    {
        return true;
    }

    
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'nome_agencia' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está em uso no Anjo Nexus.',
            'email.email' => 'Forneça um endereço de e-mail válido.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'nome_agencia.required' => 'O nome da sua consultoria é obrigatório.',
        ];
    }
}