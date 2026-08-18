/**
 * Форматирование для отображения. Модуль намеренно без 'server-only':
 * теми же функциями пользуется клиентская форма.
 */

const PRICE = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});

const PRICE_WITH_KOPECKS = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    minimumFractionDigits: 2,
});

/** Копейки показываем только когда они есть — иначе это визуальный шум. */
export function formatPrice(value: number): string {
    return Number.isInteger(value) ? PRICE.format(value) : PRICE_WITH_KOPECKS.format(value);
}

export function formatMileage(km: number): string {
    return `${new Intl.NumberFormat('ru-RU').format(km)} км`;
}

/**
 * Бэкенд отдаёт время в формате PostgreSQL «2026-08-17 15:21:24» без зоны.
 * Прогоняем через Date только после замены пробела на «T», иначе Safari
 * такую строку не разбирает и показывает Invalid Date.
 */
export function formatDate(raw: string): string {
    const parsed = new Date(raw.replace(' ', 'T') + 'Z');

    if (Number.isNaN(parsed.getTime())) {
        return raw;
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Europe/Moscow',
    }).format(parsed);
}
