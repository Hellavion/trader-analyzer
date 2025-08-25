<?php

namespace Tests\Feature;

use App\Jobs\CollectBybitMarketDataJob;
use App\Jobs\SyncBybitTradesJob;
use App\Models\User;
use App\Models\UserExchange;
use App\Services\Exchange\BybitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BybitIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function can_test_bybit_connection()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/bybit/test-connection', [
                'api_key' => 'test_key',
                'secret' => 'test_secret',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
        ]);
    }

    /** @test */
    public function can_connect_to_bybit_with_valid_credentials()
    {
        // Мокаем успешный тест подключения
        $this->mock(BybitService::class, function ($mock) {
            $mock->shouldReceive('testConnection')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => []
                ]);
        });

        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/bybit/connect', [
                'api_key' => 'valid_key',
                'secret' => 'valid_secret',
                'sync_settings' => [
                    'auto_sync' => true,
                    'sync_interval_hours' => 2,
                ]
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Bybit exchange connected successfully'
        ]);

        // Проверяем, что создалось подключение
        $this->assertDatabaseHas('user_exchanges', [
            'user_id' => $this->user->id,
            'exchange' => 'bybit',
            'is_active' => true,
        ]);

        // Проверяем, что запустился job синхронизации
        Queue::assertPushed(SyncBybitTradesJob::class);
    }

    /** @test */
    public function cannot_connect_with_invalid_credentials()
    {
        $this->mock(BybitService::class, function ($mock) {
            $mock->shouldReceive('testConnection')
                ->once()
                ->andReturn([
                    'success' => false,
                    'message' => 'Invalid API key',
                    'data' => null
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson('/api/bybit/connect', [
                'api_key' => 'invalid_key',
                'secret' => 'invalid_secret',
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
        ]);
    }

    /** @test */
    public function can_disconnect_from_bybit()
    {
        $exchange = UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'exchange' => 'bybit',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/bybit/disconnect');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Bybit exchange disconnected successfully'
        ]);

        $this->assertDatabaseHas('user_exchanges', [
            'id' => $exchange->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function can_get_connection_status()
    {
        UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'exchange' => 'bybit',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/bybit/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'is_connected',
                'is_active',
                'has_valid_credentials',
                'last_sync_at',
                'needs_sync',
                'sync_settings',
            ]
        ]);
    }

    /** @test */
    public function can_sync_trades_manually()
    {
        $exchange = UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'exchange' => 'bybit',
            'is_active' => true,
        ]);

        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/bybit/sync-trades', [
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-31',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Trades synchronization started'
        ]);

        Queue::assertPushed(SyncBybitTradesJob::class);
    }

    /** @test */
    public function can_collect_market_data()
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/bybit/collect-market-data', [
                'symbols' => ['BTCUSDT', 'ETHUSDT'],
                'timeframes' => ['1h', '4h'],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Market data collection started'
        ]);

        Queue::assertPushed(CollectBybitMarketDataJob::class);
    }

    /** @test */
    public function can_update_sync_settings()
    {
        $exchange = UserExchange::factory()->create([
            'user_id' => $this->user->id,
            'exchange' => 'bybit',
            'is_active' => true,
            'sync_settings' => [
                'auto_sync' => false,
                'sync_interval_hours' => 1,
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/bybit/sync-settings', [
                'auto_sync' => true,
                'sync_interval_hours' => 4,
                'symbols_filter' => ['BTCUSDT', 'ETHUSDT'],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Sync settings updated successfully'
        ]);

        $exchange->refresh();
        $this->assertEquals([
            'auto_sync' => true,
            'sync_interval_hours' => 4,
            'symbols_filter' => ['BTCUSDT', 'ETHUSDT'],
        ], $exchange->sync_settings);
    }

    /** @test */
    public function requires_authentication_for_api_endpoints()
    {
        $response = $this->postJson('/api/bybit/test-connection');
        $response->assertStatus(401);

        $response = $this->postJson('/api/bybit/connect');
        $response->assertStatus(401);

        $response = $this->getJson('/api/bybit/status');
        $response->assertStatus(401);
    }

    /** @test */
    public function validates_request_data()
    {
        // Тест подключения без API ключа
        $response = $this->actingAs($this->user)
            ->postJson('/api/bybit/test-connection', [
                'secret' => 'test_secret',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['api_key']);

        // Тест подключения без секрета
        $response = $this->actingAs($this->user)
            ->postJson('/api/bybit/connect', [
                'api_key' => 'test_key',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['secret']);
    }
}