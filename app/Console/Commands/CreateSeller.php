<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSeller extends Command
{
    protected $signature = 'app:create-seller
                            {--name= : Nome e cognome}
                            {--email= : Email}
                            {--password= : Password}';

    protected $description = 'Creates the seller (administrator) account';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Full name', 'Seller');
        $email = $this->option('email') ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'role' => User::ROLE_SELLER,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info("Seller account created: {$email}");

        return self::SUCCESS;
    }
}
