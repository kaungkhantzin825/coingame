<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdmin extends Command
{
    protected $signature = 'make:admin {email} {password} {name=Admin}';

    protected $description = 'Create or promote an admin user for the Filament panel';

    public function handle(): int
    {
        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name' => $this->argument('name'),
                'password' => Hash::make($this->argument('password')),
                'is_admin' => true,
                'status' => 'active',
            ],
        );

        $this->info("Admin ready: {$user->email} (id {$user->id})");
        $this->line('Login at: '.config('app.url').'/admin/login');

        return self::SUCCESS;
    }
}
