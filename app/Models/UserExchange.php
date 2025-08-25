<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

/**
 * Модель подключения пользователя к бирже
 * 
 * @property int $id
 * @property int $user_id
 * @property string $exchange
 * @property string $api_credentials_encrypted
 * @property bool $is_active
 * @property Carbon $last_sync_at
 * @property array $sync_settings
 */
class UserExchange extends Model
{
    protected $fillable = [
        'user_id',
        'exchange',
        'api_credentials_encrypted',
        'is_active',
        'last_sync_at',
        'sync_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
        'sync_settings' => 'array',
    ];

    protected $hidden = [
        'api_credentials_encrypted',
    ];

    /**
     * Пользователь, которому принадлежит подключение
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Устанавливает API ключи с шифрованием
     */
    public function setApiCredentials(array $credentials): void
    {
        $this->api_credentials_encrypted = Crypt::encryptString(json_encode($credentials));
    }

    /**
     * Получает расшифрованные API ключи
     */
    public function getApiCredentials(): array
    {
        try {
            return json_decode(Crypt::decryptString($this->api_credentials_encrypted), true);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Проверяет, есть ли валидные API ключи
     */
    public function hasValidCredentials(): bool
    {
        $credentials = $this->getApiCredentials();
        return !empty($credentials['api_key']) && !empty($credentials['secret']);
    }

    /**
     * Обновляет время последней синхронизации
     */
    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    /**
     * Проверяет, активно ли подключение
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->hasValidCredentials();
    }

    /**
     * Деактивирует подключение
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Активирует подключение
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Получает название биржи для отображения
     */
    public function getDisplayNameAttribute(): string
    {
        return match($this->exchange) {
            'bybit' => 'Bybit',
            'mexc' => 'MEXC',
            default => ucfirst($this->exchange)
        };
    }

    /**
     * Проверяет, давно ли была синхронизация
     */
    public function needsSync(int $hoursThreshold = 1): bool
    {
        if (!$this->last_sync_at) {
            return true;
        }

        return $this->last_sync_at->diffInHours(now()) >= $hoursThreshold;
    }
}
