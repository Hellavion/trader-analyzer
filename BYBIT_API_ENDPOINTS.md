# Bybit API v5 - Торговые данные и альтернативы execution/list

## Проблема текущего подхода
Сейчас используем `/v5/execution/list` который дает низкоуровневые executions, и пытаемся сами восстанавливать логику позиций. Это сложно и ошибкопроно.

## Готовые API endpoints для торговых данных

### 1. 🎯 `/v5/position/closed-pnl` - КЛЮЧЕВОЙ ENDPOINT
**Описание**: "Query user's closed profit and loss records"  
**Преимущества**: Дает готовые данные о закрытых сделках с прибылью/убытком

**Параметры запроса:**
- `category` (обязательный): linear, inverse, option
- `symbol` (опционально): BTCUSDT, ETHUSDT, etc
- `startTime` (опционально): timestamp начала периода
- `endTime` (опционально): timestamp конца периода  
- `limit` (опционально): 1-100, по умолчанию 50
- `cursor` (опционально): для пагинации

**Данные ответа:**
- Symbol (символ)
- Side (сторона сделки)
- Order details (детали ордера)
- Closed position metrics (метрики закрытой позиции)
- Fees (комиссии)
- **Profit/Loss calculation** (готовый расчет прибыли/убытка!)
- Timestamps (время)

**Ограничения:**
- 730 дней истории для UTA аккаунтов
- Сортировка по `updatedTime` или `createdTime`

### 2. 📊 `/v5/position/list` - Текущие позиции
**Описание**: "Query real-time position data, such as position size, cumulative realized PNL, etc."

**Дает:**
- Размер текущих позиций
- Накопленный реализованный PnL
- Real-time данные позиций

### 3. 🏛️ `/v5/position/close-position` - Закрытые опционы
**Описание**: "Get Closed Options Positions (6 months)"
- Специально для опционов
- 6 месяцев истории

## Другие полезные endpoints

### Order Management - `/v5/order/`
- Управление ордерами
- История ордеров
- Статус ордеров

### Account Management - `/v5/account/`
- Данные аккаунта
- Балансы
- Отчеты по торговле

### Asset Management - `/v5/asset/`
- Управление активами
- Переводы
- История операций

## Рекомендуемый новый подход

### Вместо сложной логики с executions:

1. **Для закрытых сделок**: Использовать `/v5/position/closed-pnl`
   - Получаем готовые данные с PnL
   - Не нужно самим вычислять прибыль/убыток
   - Не нужно агрегировать executions

2. **Для открытых позиций**: Использовать `/v5/position/list`
   - Получаем текущие открытые позиции
   - Real-time размеры и статус

3. **Инкрементальная синхронизация**:
   - Для closed-pnl: использовать startTime/endTime
   - Для positions: периодически обновлять список

### Преимущества нового подхода:
✅ **Простота**: Не нужно восстанавливать логику биржи  
✅ **Точность**: Bybit сам рассчитывает PnL правильно  
✅ **Надежность**: Нет ошибок в агрегации executions  
✅ **Скорость**: Меньше вычислений на нашей стороне  

### Что убираем:
❌ Сложную логику `handlePositionOpen/Close`  
❌ Попытки угадать что открывает/закрывает по `closed_size`  
❌ FIFO логику поиска позиций для закрытия  
❌ Самодельные вычисления PnL  

## Структура новых моделей

### ClosedTrade (из closed-pnl)
```php
- symbol
- side  
- size
- entry_price
- exit_price
- entry_time
- exit_time
- realized_pnl    // готовое от API!
- fees
- raw_data        // полный ответ API
```

### OpenPosition (из position/list)  
```php
- symbol
- side
- size  
- entry_price
- unrealized_pnl  // готовое от API!
- margin_used
- last_updated
```

## Следующие шаги
1. Протестировать `/v5/position/closed-pnl` endpoint
2. Изучить формат ответа детально
3. Создать новую логику синхронизации без executions
4. Упростить модели данных