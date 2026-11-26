<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminGlobalSeeder extends Seeder
{
    /**
     * Cria ou atualiza a conta principal do Administrador Global do Anjo Nexus.
     */
    public function run(): void
    {
        $email = env('ADMIN_GLOBAL_EMAIL', 'admin@anjonexus.com.br');
        $senha = env('ADMIN_GLOBAL_PASSWORD', 'AnjoNexus@2026Admin!');

        User::updateOrCreate(
            ['email' => strtolower(trim($email))],
            [
                'name' => 'Administrador Global Nexus',
                'password' => Hash::make($senha),
                'perfil_acesso' => 'admin_global',
                'role' => 'admin_global',
                'status' => 'ativo',
                'email_verified_at' => now(),
            ]
        );
    }
}
