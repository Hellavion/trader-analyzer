# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### PHP/Laravel Commands
- `composer run dev` - Start development environment (server + queue + Vite)
- `composer run test` - Run test suite
- `php artisan serve` - Start Laravel development server  
- `php artisan queue:listen --tries=1` - Start queue worker
- `php artisan test` - Run PHPUnit/Pest tests

### Frontend Commands  
- `npm run dev` - Start Vite development server
- `npm run build` - Build for production
- `npm run build:ssr` - Build with SSR support
- `npm run lint` - Run ESLint with auto-fix
- `npm run types` - Type check TypeScript
- `npm run format` - Format code with Prettier

## Project Architecture

This is a Laravel 12 + React application for analyzing cryptocurrency trading using Smart Money concepts. It uses Inertia.js as a bridge between Laravel and React.

### Tech Stack
- **Backend**: Laravel 12, PHP 8.2+, SQLite (development)
- **Frontend**: React 19, TypeScript, Tailwind CSS v4, Vite
- **Testing**: Pest (PHP), ESLint (TypeScript)
- **State Management**: Inertia.js with React hooks
- **UI Components**: Headless UI, Radix UI components

### Key Directory Structure
```
app/
├── Http/Controllers/       # API and web controllers
├── Models/                # Eloquent models  
├── Providers/             # Service providers
└── Services/              # Business logic services

resources/js/
├── components/            # Reusable React components
├── pages/                # Page components (Inertia pages)
├── layouts/              # Layout components
├── hooks/                # Custom React hooks
└── types/                # TypeScript type definitions
```

### Authentication & Authorization
- Uses Laravel's built-in authentication
- Inertia.js handles SPA-like navigation
- Session-based authentication for web routes

### Database
- SQLite for development (see database/database.sqlite)
- Migrations in database/migrations/
- Uses Laravel's factory system for testing

### Key Features Architecture
Based on the existing CLAUDE.md, this application is designed for:
- Smart Money trading analysis (Order Blocks, Liquidity, FVG detection)
- Exchange integration (Bybit/MEXC APIs)  
- Real-time trade analysis and scoring
- React-based dashboard with TradingView integration

### Testing Strategy
- **PHP**: Pest framework with Feature and Unit test suites
- **Frontend**: Type checking with TypeScript
- Test database uses in-memory SQLite
- Authentication and settings functionality already tested

### Code Style
- **PHP**: PSR standards, document all methods
- **TypeScript**: ESLint + Prettier configuration
- **React**: Functional components with hooks
- **Styling**: Tailwind CSS with custom component system

## Обзор проекта
PHP/Laravel приложение для анализа крипто-трейдинга с использованием Smart Money концепций (Order Blocks, Ликвидность, FVG). Помогает трейдерам выявлять паттерны ошибок и улучшать качество входов.

**Целевой пользователь**: Smart Money/ICT крипто-трейдеры на Bybit/MEXC
**Основная ценность**: Автоматический анализ качества сделок через призму институциональной торговли

## Технический стек и архитектура

### Backend (PHP Laravel 11)
```
- Фреймворк: Laravel 11 + PHP 8.3
- База данных: PostgreSQL 15
- Кэш/Очереди: Redis 7
- Авторизация: Sanctum
- API: RESTful + WebSocket
```

### Движок анализа данных (PHP 8.3)
```
- API бирж: HTTP клиенты или ccxt-php
- Анализ данных: Нативные PHP массивы + математические функции
- Технический анализ: trader PHP расширение
- Машинное обучение: php-ml библиотека (в будущем)
- Очереди задач: Laravel Queue + Horizon
- WebSocket: Laravel Broadcasting + Pusher
```

### Frontend (React 18)
```
- Фреймворк: React 18 + Vite + TypeScript
- Графики: TradingView Charting Library
- UI: Tailwind CSS + HeadlessUI
- Состояние: Zustand
- HTTP: Axios
```

## Схема базы данных

### Основные таблицы
```sql
-- Пользователи и настройки
users (id, email, subscription_plan, created_at)
user_exchanges (user_id, exchange, api_credentials_encrypted, is_active)
user_settings (user_id, analysis_settings_json, notification_prefs_json)

-- Торговые данные
trades (id, user_id, exchange, symbol, side, size, entry_price, exit_price, timestamp, external_id)
trade_analysis (trade_id, smart_money_score, entry_context_json, exit_context_json, patterns_json)
market_structure (symbol, timeframe, timestamp, order_blocks_json, liquidity_levels_json, fvg_zones_json)

-- Аналитика
analysis_reports (user_id, period_start, period_end, metrics_json, recommendations_json)
pattern_templates (name, description, detection_rules_json)
```

### Ключевые индексы
```sql
CREATE INDEX idx_trades_user_symbol_time ON trades (user_id, symbol, timestamp);
CREATE INDEX idx_market_structure_lookup ON market_structure (symbol, timeframe, timestamp);
CREATE INDEX idx_trade_analysis_score ON trade_analysis (smart_money_score);
```

## Основные функции и алгоритмы

### 1. Интеграция с биржами
**Файлы**: `app/Services/ExchangeService.php`, `app/Jobs/SyncTradesJob.php`
- Интеграция с Bybit/MEXC API через ccxt
- Безопасное хранение API ключей (шифрование AES-256)
- Синхронизация сделок с дедупликацией
- Rate limiting и обработка ошибок

### 2. Движок анализа Smart Money
**Файлы**: `app/Services/Analysis/`, `app/Jobs/AnalysisJobs/`

#### Детекция Order Block
```php
class OrderBlockDetector 
{
    public function detectOrderBlocks(array $ohlcData, array $volumeData): array
    {
        // Находим импульсные свечи с высоким объемом
        $impulseBars = $this->findImpulseBars($ohlcData, $volumeData);
        
        // Определяем консолидацию после импульса
        $consolidationZones = $this->findConsolidation($impulseBars);
        
        // Валидируем зоны ретеста
        return $this->validateOrderBlocks($consolidationZones);
    }
    
    private function findImpulseBars(array $ohlc, array $volume): array
    {
        $impulses = [];
        foreach ($ohlc as $i => $candle) {
            $bodySize = abs($candle['close'] - $candle['open']);
            $avgVolume = $this->getAverageVolume($volume, $i, 20);
            
            if ($bodySize > $this->getAverageBodySize($ohlc, $i, 20) * 2 
                && $volume[$i] > $avgVolume * 1.5) {
                $impulses[] = [
                    'index' => $i,
                    'type' => $candle['close'] > $candle['open'] ? 'bullish' : 'bearish',
                    'high' => $candle['high'],
                    'low' => $candle['low'],
                    'volume' => $volume[$i]
                ];
            }
        }
        return $impulses;
    }
}
```

#### Анализ ликвидности
```php
class LiquidityAnalyzer 
{
    public function identifyLiquidityZones(array $ohlcData): array
    {
        $zones = [];
        
        // Детекция равных максимумов/минимумов
        $zones = array_merge($zones, $this->findEqualHighsLows($ohlcData));
        
        // Круглые числа
        $zones = array_merge($zones, $this->findRoundNumbers($ohlcData));
        
        // Максимумы/минимумы сессий
        $zones = array_merge($zones, $this->findSessionLevels($ohlcData));
        
        return $this->filterAndRankZones($zones);
    }
    
    public function detectLiquiditySweep(array $priceData, array $zones): array
    {
        $sweeps = [];
        foreach ($zones as $zone) {
            $sweep = $this->checkForSweep($priceData, $zone);
            if ($sweep) {
                $sweeps[] = $sweep;
            }
        }
        return $sweeps;
    }
    
    private function findEqualHighsLows(array $ohlc): array
    {
        $levels = [];
        $tolerance = 0.001; // 0.1% допуск
        
        for ($i = 2; $i < count($ohlc) - 2; $i++) {
            // Проверяем двойные вершины
            if ($this->isLocalHigh($ohlc, $i)) {
                for ($j = $i + 5; $j < count($ohlc) - 2; $j++) {
                    if ($this->isLocalHigh($ohlc, $j)) {
                        $diff = abs($ohlc[$i]['high'] - $ohlc[$j]['high']) / $ohlc[$i]['high'];
                        if ($diff <= $tolerance) {
                            $levels[] = [
                                'type' => 'equal_highs',
                                'level' => ($ohlc[$i]['high'] + $ohlc[$j]['high']) / 2,
                                'strength' => $this->calculateLevelStrength($ohlc, $i, $j),
                                'touches' => [$i, $j]
                            ];
                        }
                    }
                }
            }
        }
        
        return $levels;
    }
}
```

#### Детекция Fair Value Gap
```php
class FVGDetector 
{
    public function findFairValueGaps(array $ohlcData): array
    {
        $gaps = [];
        
        for ($i = 1; $i < count($ohlcData) - 1; $i++) {
            $prev = $ohlcData[$i - 1];
            $current = $ohlcData[$i];  
            $next = $ohlcData[$i + 1];
            
            // Бычий FVG: предыдущий максимум < следующий минимум
            if ($prev['high'] < $next['low']) {
                $gaps[] = [
                    'type' => 'bullish',
                    'start_index' => $i - 1,
                    'end_index' => $i + 1,
                    'gap_high' => $next['low'],
                    'gap_low' => $prev['high'],
                    'is_filled' => false,
                    'fill_index' => null
                ];
            }
            
            // Медвежий FVG: предыдущий минимум > следующий максимум
            if ($prev['low'] > $next['high']) {
                $gaps[] = [
                    'type' => 'bearish',
                    'start_index' => $i - 1,
                    'end_index' => $i + 1,
                    'gap_high' => $prev['low'],
                    'gap_low' => $next['high'],
                    'is_filled' => false,
                    'fill_index' => null
                ];
            }
        }
        
        return $this->trackGapFills($gaps, $ohlcData);
    }
    
    private function trackGapFills(array $gaps, array $ohlc): array
    {
        foreach ($gaps as &$gap) {
            for ($i = $gap['end_index'] + 1; $i < count($ohlc); $i++) {
                if ($gap['type'] === 'bullish' && $ohlc[$i]['low'] <= $gap['gap_high']) {
                    $gap['is_filled'] = true;
                    $gap['fill_index'] = $i;
                    break;
                } elseif ($gap['type'] === 'bearish' && $ohlc[$i]['high'] >= $gap['gap_low']) {
                    $gap['is_filled'] = true;
                    $gap['fill_index'] = $i;
                    break;
                }
            }
        }
        
        return $gaps;
    }
}
```

### 3. Система оценки качества сделок
**Алгоритм**: Оценка сделок от 1 до 10 на основе Smart Money критериев
- Вход относительно Order Blocks (30% веса)
- Время ликвидационного свипа (25% веса)
- Контекст рыночной структуры (25% веса)
- Взаимодействие с FVG (20% веса)

### 4. Распознавание паттернов и инсайты
- Детекция частых ошибок
- Идентификация лучших сетапов
- Персонализированные рекомендации
- Анализ поведенческих паттернов

## API эндпоинты

### Авторизация
```
POST /api/auth/login
POST /api/auth/register  
POST /api/auth/logout
```

### Управление биржами
```
GET /api/exchanges
POST /api/exchanges/{exchange}/connect
DELETE /api/exchanges/{exchange}/disconnect
POST /api/exchanges/sync-trades
```

### Анализ
```
GET /api/trades?period=30d&symbol=BTCUSDT
GET /api/trades/{id}/analysis
POST /api/analysis/generate-report
GET /api/dashboard/metrics
```

### Настройки
```
GET /api/settings
PUT /api/settings
POST /api/settings/analysis-preferences  
```

## Этапы разработки

### Этап 1: Основа (Недели 1-8)
**Спринт 1-2: Настройка проекта**
- Инициализация Laravel проекта
- Миграции базы данных
- Базовая авторизация (Sanctum)
- Docker окружение для разработки

**Спринт 3-4: Интеграция с биржами**
- Интеграция с Bybit API
- Job синхронизации сделок
- Сервис шифрования API ключей
- Базовая модель сделок и API

**Спринт 5-6: PHP движок анализа**
- Настройка PHP сервисов анализа
- Базовая детекция Order Block
- Laravel Queue jobs для анализа
- Сбор рыночных данных через HTTP

**Спринт 7-8: Базовый Frontend**
- React приложение с TypeScript
- Компонент списка сделок
- Страница настроек для API ключей
- Базовая разметка дашборда

### Этап 2: Основной анализ (Недели 9-16)
**Спринт 9-10: Smart Money алгоритмы**
- Улучшенные алгоритмы детекции OB
- Алгоритм оценки сделок
- Анализ контекста входа/выхода
- Основы детекции паттернов

**Спринт 11-12: Визуализация**
- Интеграция TradingView Charting Library
- Отображение сделок на графике
- Визуализация Order Blocks
- Интерактивные фильтры

**Спринт 13-14: Движок инсайтов**
- Генерация персонализированных рекомендаций
- Отчеты по анализу паттернов
- Расчет метрик производительности
- Система уведомлений

**Спринт 15-16: Тестирование и полировка**
- Интеграция с MEXC
- Оптимизация производительности
- Улучшения UI/UX
- Бета-тестирование с реальными пользователями

### Этап 3: Продвинутые функции (Недели 17-24)
- Мульти-тайм фрейм анализ
- Имплементация детекции FVG
- ML-основанное распознавание паттернов
- Мобильная адаптивность
- Продвинутые функции отчетности

## Структура файлов

```
project-root/
├── app/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Trade.php  
│   │   ├── TradeAnalysis.php
│   │   └── UserExchange.php
│   ├── Services/
│   │   ├── ExchangeService.php
│   │   ├── Analysis/
│   │   │   ├── OrderBlockDetector.php
│   │   │   ├── LiquidityAnalyzer.php
│   │   │   ├── FVGDetector.php
│   │   │   └── MarketStructureAnalyzer.php
│   │   ├── ApiKeyService.php
│   │   └── InsightsService.php
│   ├── Jobs/
│   │   ├── SyncTradesJob.php
│   │   ├── AnalyzeTradeJob.php
│   │   └── GenerateReportJob.php
│   └── Http/Controllers/Api/
│       ├── AuthController.php
│       ├── TradeController.php
│       ├── ExchangeController.php
│       └── AnalysisController.php
├── database/migrations/
├── resources/js/
│   ├── components/
│   │   ├── Dashboard/
│   │   ├── Trades/
│   │   ├── Charts/
│   │   └── Settings/
│   ├── pages/
│   ├── hooks/
│   └── utils/
├── docker-compose.yml
└── claude.md (этот файл)
```

## Тестовые данные

### Тестовый аккаунт для разработки
- **Email**: hellavion@gmail.com  
- **Password**: avi197350
- **Описание**: Основной тестовый аккаунт с подключением к Bybit и реальными данными для разработки

## Настройка окружения

### Laravel окружение
```env
DB_CONNECTION=pgsql
DB_DATABASE=smart_money_analyzer
REDIS_HOST=redis
QUEUE_CONNECTION=redis

BYBIT_API_URL=https://api.bybit.com
MEXC_API_URL=https://api.mexc.com

APP_KEY=base64:... # для шифрования
```

## Ключевые заметки по реализации

### Безопасность
- API ключи шифруются через Laravel Crypt фасад
- Только read-only разрешения API
- Rate limiting на все внешние API вызовы
- Валидация и санитизация входных данных

### Производительность
- Redis кэширование для рыночных данных
- Индексирование базы данных для time-series запросов
- Фоновая обработка заданий для анализа
- Пагинация для больших наборов данных

### Обработка ошибок
- Circuit breaker паттерн для API бирж
- Retry логика с экспоненциальным backoff
- Комплексное логирование
- Понятные пользователю сообщения об ошибках

## Стратегия тестирования
- Unit тесты для алгоритмов анализа
- Интеграционные тесты для API бирж
- Feature тесты для API эндпоинтов
- E2E тесты для критических пользовательских флоу

## Развертывание
- Docker контейнеризация
- GitHub Actions CI/CD
- Конфигурации под разные окружения
- Стратегии резервного копирования БД

## Метрики успеха
- Точность синхронизации сделок: >99%
- Время обработки анализа: <5 секунд
- Удержание пользователей: >60% ежемесячно
- Корреляция анализа с улучшением: >0.7