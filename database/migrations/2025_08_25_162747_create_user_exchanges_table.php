<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('exchange'); // bybit, mexc
            $table->text('api_credentials_encrypted'); // зашифрованные API ключи
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable(); // время последней синхронизации
            $table->json('sync_settings')->nullable(); // настройки синхронизации
            $table->timestamps();
            
            // Уникальность пользователь + биржа
            $table->unique(['user_id', 'exchange']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_exchanges');
    }
};
