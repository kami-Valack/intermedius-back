<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'intermediarius:create-admin {email} {name} {password}';

    protected $description = 'Cria ou actualiza um administrador (não use o registo público).';

    public function handle(): int
    {
        $user = User::query()->updateOrCreate(
            ['email' => strtolower($this->argument('email'))],
            [
                'name' => $this->argument('name'),
                'phone' => '+244 900 000 000',
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'password' => Hash::make($this->argument('password')),
            ]
        );

        $this->info('Administrador #'.$user->id.' pronto.');

        return self::SUCCESS;
    }
}
