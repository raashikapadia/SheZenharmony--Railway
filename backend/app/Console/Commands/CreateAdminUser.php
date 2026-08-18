<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'shezen:create-admin {email?} {--name=}';

    protected $description = 'Create or promote a SheZen administrator account';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email address');
        $name = $this->option('name') ?: $this->ask('Display name');
        $password = $this->secret('Password (minimum 12 characters)');

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
        ])->save();

        $user->assignRole(User::ROLE_ADMIN);

        $this->info("Administrator account ready for {$user->email}.");

        return self::SUCCESS;
    }
}
