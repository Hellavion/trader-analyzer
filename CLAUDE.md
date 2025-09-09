# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Текущий статус задачи
**ТЕКУЩАЯ ЗАДАЧА:** Доработка страницы детализации сделки

## Инструкции по обновлению статуса
**ДЛЯ CLAUDE:** Всегда обновляй строку "ТЕКУЩАЯ ЗАДАЧА" при переходе к новой основной задаче. Используй Edit tool для замены содержимого. Формат: краткое описание задачи (3-7 слов). Примеры:
- "Создание страницы детализации сделки"
- "Исправление синхронизации Bybit API"  
- "Добавление Smart Money анализа"
- "Настройка WebSocket подключения"

## Общие правила взаимодействия

1. **Честность превыше угождения**: Если не знаешь ответ или решение, говори прямо об этом. Не придумывай и не угадывай.

2. **Проактивные предложения**: Предлагай решения или технологии только если они уместны в контексте и могут реально улучшить код.

3. **Язык общения**: Всегда пиши и общайся на русском языке.

4. **НИКОГДА НЕ ТРОГАТЬ GIT БЕЗ ЧЕТКОГО УКАЗАНИЯ**: Запрещено выполнять любые git команды (git checkout, git reset, git stash, git commit, etc.) без явного разрешения пользователя. Это может привести к потере работы.

## Development Commands

### PHP/Laravel Commands
- `composer run dev` - Start development environment (server + queue + Vite)
- `composer run dev:ssr` - Start development with SSR (server side rendering)
- `composer run test` - Run test suite with config clear
- `php artisan serve` - Start Laravel development server  
- `php artisan queue:work --daemon --tries=3` - Start queue worker (рекомендуется для продакшена)
- `php artisan queue:listen --tries=1` - Start queue worker (для разработки)
- `php artisan test` - Run Pest/PHPUnit tests
- `php artisan sync:all-users` - Manual sync all users data
- `php artisan sync:all-users --force` - Force sync even recently synced users
- `php artisan pail --timeout=0` - Real-time log monitoring

### Synchronization Commands
- `start-queue-worker.bat` - Windows batch file to start queue worker as service
- `start-scheduler.bat` - Windows batch file to start Laravel scheduler
- `php artisan schedule:run` - Run scheduled tasks once (normally runs every minute via cron/scheduler)

### Frontend Commands  
- `npm run dev` - Start Vite development server
- `npm run build` - Build for production
- `npm run build:ssr` - Build with SSR support
- `npm run lint` - Run ESLint with auto-fix
- `npm run types` - Type check TypeScript
- `npm run format` - Format code with Prettier
- `npm run format:check` - Check formatting without changing files

## Project Architecture

This is a Laravel 12 + React application for analyzing cryptocurrency trading using Smart Money concepts. It uses Inertia.js as a bridge between Laravel and React.

### Tech Stack
- **Backend**: Laravel 12, PHP 8.2+, SQLite (development)
- **Frontend**: React 19, TypeScript, Tailwind CSS v4, Vite
- **Testing**: Pest (PHP), ESLint (TypeScript)
- **State Management**: Inertia.js with React hooks
- **UI Components**: Headless UI, Radix UI components
- **Real-time**: Laravel Wave for WebSocket connections
- **Navigation**: Ziggy for Laravel route generation in JavaScript
- **Charts**: Recharts for data visualization

### Key Directory Structure
```
app/
├── Http/Controllers/       # API and web controllers
│   ├── Api/               # API controllers for SPA
│   ├── Auth/              # Authentication controllers
│   └── Settings/          # Settings management controllers
├── Models/                # Eloquent models (Trade, UserExchange, etc.)
├── Services/              # Business logic services
│   └── Exchange/          # Exchange-specific services (Bybit, etc.)
├── Jobs/                  # Queue jobs for sync and analysis
├── Console/Commands/      # Artisan commands
├── Events/                # Laravel events for real-time updates
└── Providers/             # Service providers

resources/js/
├── components/            # Reusable React components
│   ├── ui/               # Base UI components (Button, Card, etc.)
│   └── dashboard/        # Dashboard-specific components
├── pages/                # Page components (Inertia pages)
│   ├── trades/           # Trade-related pages
│   ├── analysis/         # Analysis pages
│   └── exchanges/        # Exchange management pages
├── layouts/              # Layout components
├── hooks/                # Custom React hooks
├── services/             # API service layer
├── types/                # TypeScript type definitions
└── actions/              # Generated Ziggy route actions
```

### Authentication & Authorization
- Uses Laravel's built-in authentication
- Inertia.js handles SPA-like navigation
- Session-based authentication for web routes
- API routes use web middleware for SPA authentication

### Database
- SQLite for development (see database/database.sqlite)
- Migrations in database/migrations/
- Uses Laravel's factory system for testing
- Key models: Trade, UserExchange, Execution, TradeAnalysis, MarketStructure

### Key Features Architecture
This application is designed for:
- **Smart Money trading analysis**: Order Blocks, Liquidity, FVG detection
- **Exchange integration**: Bybit API with extensible architecture for MEXC
- **Real-time updates**: WebSocket integration via Laravel Wave
- **Trade management**: Comprehensive trade tracking and analysis
- **Dashboard analytics**: Performance metrics and charts
- **API-first design**: SPA architecture with dedicated API controllers

### Real-time System Architecture
- **Laravel Wave**: WebSocket server for real-time updates
- **Events**: TradeExecuted, RealTradeUpdate for broadcasting
- **Queue system**: Background job processing for sync operations
- **API endpoints**: RESTful API for frontend communication

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

## API Architecture

### Key API Endpoints
```
/api/exchanges          # Exchange management
/api/trades             # Trade data and statistics
/api/analysis           # Market analysis and data
/api/dashboard          # Dashboard metrics and widgets
/api/bybit              # Legacy Bybit-specific endpoints
```

### Route Structure
- **Web routes**: Inertia.js pages with server-side rendering
- **API routes**: JSON endpoints for SPA functionality
- **Authentication**: Session-based for both web and API routes

## Real-time Features

### WebSocket Integration
- **Laravel Wave**: Provides WebSocket functionality
- **Broadcasting**: Uses Redis for scalable real-time updates
- **Events**: Custom events for trade updates and notifications

### Queue System
- **Jobs**: SyncBybitTradesJob, QuickSyncBybitJob, CollectBybitMarketDataJob
- **Queue driver**: Database or Redis
- **Processing**: Background job processing for data synchronization

## Development Workflow

### Local Development Setup
1. **Environment**: Copy `.env.example` to `.env`
2. **Database**: SQLite database automatically created
3. **Dependencies**: `composer install` and `npm install`
4. **Development**: Use `composer run dev` for full stack

### Testing
- **Backend**: `composer run test` or `php artisan test`
- **Frontend**: `npm run types` for TypeScript checking
- **Linting**: `npm run lint` for code quality

### Code Organization
- **Models**: Follow Laravel Eloquent conventions
- **Controllers**: API controllers return JSON, web controllers use Inertia
- **Services**: Business logic separated into service classes
- **Components**: React components follow functional pattern with hooks

## Методология решения проблем

### Принцип №1: ДИАГНОСТИКА ПЕРЕД ИСПРАВЛЕНИЯМИ
❌ **НЕ ДЕЛАЙ**: Сразу пытаться "исправить" проблему методом проб и ошибок  
✅ **ДЕЛАЙ**: Сначала **точно диагностируй корень проблемы**, затем исправляй

### Алгоритм диагностики:
1. **Воспроизведи проблему** - убедись что она стабильно повторяется
2. **Изучи документацию** - как должно работать по спецификации  
3. **Проверь логи и ошибки** - что именно падает и где
4. **Изолируй компоненты** - тестируй каждую часть отдельно
5. **Найди минимальный воспроизводимый случай** - убери все лишнее
6. **Определи архитектурную причину** - это баг или неправильное использование?

### Принцип №2: АРХИТЕКТУРНЫЕ РЕШЕНИЯ > КОСТЫЛИ
Если для исправления нужно много "хаков" - скорее всего используется неправильный инструмент.

### Принцип №3: ДОКУМЕНТАЦИЯ > ПРЕДПОЛОЖЕНИЯ  
Всегда сначала изучи официальную документацию, прежде чем придумывать свои решения.

### Real-time система: Правильная архитектура
- **Development**: Laravel Sail (Docker) или Nginx + PHP-FPM
- **НЕ ИСПОЛЬЗУЙ**: `php artisan serve` для SSE/WebSocket - он однопоточный!
- **Laravel Wave**: Требует Redis + многопоточный веб-сервер

## Тестовые данные

### Тестовый аккаунт для разработки
- **Email**: hellavion@gmail.com  
- **Password**: avi197350
- **Описание**: Основной тестовый аккаунт с подключением к Bybit и реальными данными для разработки

## Important Implementation Notes

### Exchange Integration
- **Primary**: Bybit API integration is functional
- **Architecture**: Extensible design for adding MEXC and other exchanges
- **Security**: API credentials encrypted and stored securely
- **Rate limiting**: Implemented to prevent API quota issues

### Data Synchronization
- **Background jobs**: Async processing of trade synchronization
- **Incremental sync**: Only fetch new data to optimize performance
- **Error handling**: Robust error handling with retry mechanisms
- **Real-time updates**: WebSocket broadcasts for immediate UI updates

### Performance Considerations
- **Caching**: Redis caching for frequently accessed data
- **Database**: Optimized queries with proper indexing
- **Frontend**: Code splitting and lazy loading for performance
- **API**: Pagination and filtering for large datasets