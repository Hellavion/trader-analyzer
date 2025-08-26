/**
 * Утилиты для работы со временем
 */

/**
 * Переводит английские временные интервалы на русский язык
 */
export function translateTimeAgo(englishTime: string): string {
    // Убираем лишние пробелы и приводим к нижнему регистру
    const time = englishTime.trim().toLowerCase();

    // Секунды
    if (time.includes('second')) {
        const match = time.match(/(\d+)\s*second/);
        const count = match ? parseInt(match[1]) : 1;
        if (count === 1) return 'секунду назад';
        if (count < 5) return `${count} секунды назад`;
        return `${count} секунд назад`;
    }

    // Минуты
    if (time.includes('minute')) {
        const match = time.match(/(\d+)\s*minute/);
        const count = match ? parseInt(match[1]) : 1;
        if (count === 1) return 'минуту назад';
        if (count < 5) return `${count} минуты назад`;
        return `${count} минут назад`;
    }

    // Часы
    if (time.includes('hour')) {
        const match = time.match(/(\d+)\s*hour/);
        const count = match ? parseInt(match[1]) : 1;
        if (count === 1) return 'час назад';
        if (count < 5) return `${count} часа назад`;
        return `${count} часов назад`;
    }

    // Дни
    if (time.includes('day')) {
        const match = time.match(/(\d+)\s*day/);
        const count = match ? parseInt(match[1]) : 1;
        if (count === 1) return 'день назад';
        if (count < 5) return `${count} дня назад`;
        return `${count} дней назад`;
    }

    // Недели
    if (time.includes('week')) {
        const match = time.match(/(\d+)\s*week/);
        const count = match ? parseInt(match[1]) : 1;
        if (count === 1) return 'неделю назад';
        if (count < 5) return `${count} недели назад`;
        return `${count} недель назад`;
    }

    // Месяцы
    if (time.includes('month')) {
        const match = time.match(/(\d+)\s*month/);
        const count = match ? parseInt(match[1]) : 1;
        if (count === 1) return 'месяц назад';
        if (count < 5) return `${count} месяца назад`;
        return `${count} месяцев назад`;
    }

    // Годы
    if (time.includes('year')) {
        const match = time.match(/(\d+)\s*year/);
        const count = match ? parseInt(match[1]) : 1;
        if (count === 1) return 'год назад';
        if (count < 5) return `${count} года назад`;
        return `${count} лет назад`;
    }

    // Особые случаи
    if (time.includes('just now') || time === 'now') return 'только что';
    if (time.includes('yesterday')) return 'вчера';
    if (time.includes('today')) return 'сегодня';

    // Если не удалось распознать, возвращаем как есть
    return englishTime;
}

/**
 * Форматирует дату для отображения в интерфейсе
 */
export function formatDisplayDate(date: string | Date): string {
    const d = new Date(date);
    
    const now = new Date();
    const diffInMinutes = Math.floor((now.getTime() - d.getTime()) / (1000 * 60));
    
    // Если меньше минуты
    if (diffInMinutes < 1) return 'только что';
    
    // Если меньше часа
    if (diffInMinutes < 60) {
        if (diffInMinutes === 1) return 'минуту назад';
        if (diffInMinutes < 5) return `${diffInMinutes} минуты назад`;
        return `${diffInMinutes} минут назад`;
    }
    
    // Если меньше суток
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) {
        if (diffInHours === 1) return 'час назад';
        if (diffInHours < 5) return `${diffInHours} часа назад`;
        return `${diffInHours} часов назад`;
    }
    
    // Если меньше недели
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) {
        if (diffInDays === 1) return 'день назад';
        if (diffInDays < 5) return `${diffInDays} дня назад`;
        return `${diffInDays} дней назад`;
    }
    
    // Для более старых дат показываем полную дату
    return d.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}