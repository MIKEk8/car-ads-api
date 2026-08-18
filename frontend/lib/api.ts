/**
 * Клиент Car Ads API.
 *
 * Работает только на сервере: страницы — серверные компоненты, форма
 * отправляется через Server Action. Браузер к API напрямую не ходит, поэтому
 * CORS не нужен вовсе, а адрес бэкенда не попадает в клиентский бандл.
 * В кластере это ещё и короче: обращение идёт к Service внутри namespace,
 * не выходя наружу через ingress.
 */

import 'server-only';

const BASE_URL = (process.env.CAR_API_URL ?? 'https://car-api.mikek8.ru').replace(/\/+$/, '');

/** Технические характеристики. По ТЗ блок необязательный. */
export interface CarOption {
    brand: string;
    model: string;
    year: number;
    body: string;
    mileage: number;
}

export interface Car {
    id: number;
    title: string;
    description: string;
    price: number;
    photo_url: string | null;
    contacts: string;
    created_at: string;
    options: CarOption | null;
}

export interface Pagination {
    page: number;
    perPage: number;
    total: number;
    pages: number;
}

export interface CarList {
    data: Car[];
    pagination: Pagination;
}

/** Карта «поле → сообщение», как её отдаёт бэкенд с кодом 422. */
export type FieldErrors = Record<string, string>;

/** Ошибка валидации — единственная, которую форма показывает по полям. */
export class ValidationError extends Error {
    constructor(readonly errors: FieldErrors) {
        super('Ошибка валидации');
        this.name = 'ValidationError';
    }
}

/** Сервис ответил, но не так, как ожидалось: 5xx, недоступность, битый JSON. */
export class ApiError extends Error {
    constructor(
        message: string,
        readonly status: number,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
    let response: Response;

    try {
        response = await fetch(`${BASE_URL}${path}`, {
            ...init,
            headers: { Accept: 'application/json', ...init?.headers },
            // Объявления добавляются и меняются, кешировать список нельзя.
            cache: 'no-store',
        });
    } catch (cause) {
        // Сеть не дошла до сервиса: DNS, отказ соединения, таймаут.
        throw new ApiError(`Сервис объявлений недоступен: ${String(cause)}`, 503);
    }

    if (response.status === 404) {
        throw new ApiError('Объявление не найдено', 404);
    }

    let payload: unknown;
    try {
        payload = await response.json();
    } catch {
        throw new ApiError(`Сервис вернул не JSON (код ${response.status})`, response.status);
    }

    if (response.status === 422) {
        const errors = (payload as { errors?: FieldErrors }).errors;
        throw new ValidationError(errors ?? {});
    }

    if (!response.ok) {
        // На 500 бэкенд намеренно не раскрывает подробностей — показываем
        // то, что есть, а разбираться нужно по его журналу.
        const message = (payload as { message?: string }).message ?? 'Неизвестная ошибка';
        throw new ApiError(message, response.status);
    }

    return payload as T;
}

export function fetchCars(page: number): Promise<CarList> {
    return request<CarList>(`/car/list?page=${encodeURIComponent(page)}`);
}

export function fetchCar(id: number): Promise<Car> {
    return request<Car>(`/car/${encodeURIComponent(id)}`);
}

/** Тело запроса на создание — ровно то, что описано в ТЗ. */
export interface CreateCarInput {
    title: string;
    description: string;
    price: string;
    photo_url: string | null;
    contacts: string;
    options: CarOption | null;
}

export function createCar(input: CreateCarInput): Promise<Car> {
    return request<Car>('/car/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(input),
    });
}
