<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserExchange;
use Illuminate\Console\Command;
use PDO;

class MigrateUserData extends Command
{
    protected $signature = 'migrate:user-data {--email=hellavion@gmail.com}';
    protected $description = 'Migrate user and exchange data from SQLite to current database';

    public function handle()
    {
        $email = $this->option('email');
        $sqliteDb = new PDO('sqlite:database/database.sqlite');
        
        $this->info("Migrating user data for: {$email}");
        
        // Получаем пользователя из SQLite
        $stmt = $sqliteDb->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $this->error("User {$email} not found in SQLite");
            return 1;
        }
        
        // Проверяем существует ли в текущей базе
        $existingUser = User::where('email', $email)->first();
        
        if ($existingUser) {
            $this->warn("User already exists (ID: {$existingUser->id})");
            $userId = $existingUser->id;
        } else {
            $newUser = User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
                'email_verified_at' => $user['email_verified_at'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ]);
            
            $this->info("✅ User created (ID: {$newUser->id})");
            $userId = $newUser->id;
        }
        
        // Миграция подключений к биржам
        $stmt = $sqliteDb->prepare("SELECT * FROM user_exchanges WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($exchanges as $exchange) {
            $existing = UserExchange::where('user_id', $userId)
                ->where('exchange', $exchange['exchange'])
                ->first();
                
            if ($existing) {
                $this->warn("Exchange {$exchange['exchange']} already exists");
                continue;
            }
            
            UserExchange::create([
                'user_id' => $userId,
                'exchange' => $exchange['exchange'],
                'api_credentials_encrypted' => $exchange['api_credentials_encrypted'],
                'is_active' => $exchange['is_active'],
                'last_sync_at' => $exchange['last_sync_at'],
                'sync_settings' => $exchange['sync_settings'],
                'created_at' => $exchange['created_at'],
                'updated_at' => $exchange['updated_at'],
            ]);
            
            $this->info("✅ Exchange {$exchange['exchange']} migrated");
        }
        
        $this->info('🎉 Migration completed!');
        return 0;
    }
}
