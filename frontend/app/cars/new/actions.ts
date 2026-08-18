'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';

import { ApiError, createCar, ValidationError, type CreateCarInput } from '@/lib/api';
import type { FormState } from './form-state';


const FIELDS = ['title', 'description', 'price', 'photo_url', 'contacts'] as const;
const OPTION_FIELDS = ['brand', 'model', 'year', 'body', 'mileage'] as const;

/**
 * Создание объявления.
 *
 * Правила валидации здесь намеренно не дублируются: единственный их источник —
 * бэкенд, который выводит границы полей из описания схемы. Форма лишь
 * раскладывает присланную карту `поле → сообщение` обратно по своим полям,
 * поэтому новое правило на сервере появляется в интерфейсе само, без правок
 * фронтенда. Атрибуты `required` в разметке — только для мгновенной подсказки
 * браузера, авторитет остаётся за сервером.
 */
export async function createCarAction(_prev: FormState, formData: FormData): Promise<FormState> {
    const values = collectValues(formData);
    const withOptions = formData.get('withOptions') === 'on';

    const input: CreateCarInput = {
        title: values.title ?? '',
        description: values.description ?? '',
        price: values.price ?? '',
        // Пустое поле — это «фотографии нет», а не пустая строка в базе.
        photo_url: values.photo_url ? values.photo_url : null,
        contacts: values.contacts ?? '',
        options: withOptions
            ? {
                  brand: values.brand ?? '',
                  model: values.model ?? '',
                  // Приводим к числу: бэкенд принимает и строку, но контракт
                  // в TypeScript описан числом, и нарушать его не будем.
                  year: Number.parseInt(values.year ?? '', 10) || 0,
                  body: values.body ?? '',
                  mileage: Number.parseInt(values.mileage ?? '', 10) || 0,
              }
            : null,
    };

    let createdId: number;

    try {
        const car = await createCar(input);
        createdId = car.id;
    } catch (error) {
        if (error instanceof ValidationError) {
            return { errors: error.errors, message: null, values };
        }

        const message =
            error instanceof ApiError
                ? `Не удалось сохранить объявление: ${error.message}`
                : 'Не удалось сохранить объявление. Попробуйте ещё раз.';

        return { errors: {}, message, values };
    }

    // Список закешированного вывода обязан увидеть новое объявление.
    revalidatePath('/');
    // redirect бросает управляющее исключение — вызывать его внутри try
    // нельзя, иначе он был бы перехвачен как ошибка сохранения.
    redirect(`/cars/${createdId}`);
}

function collectValues(formData: FormData): Record<string, string> {
    const values: Record<string, string> = {};

    for (const name of [...FIELDS, ...OPTION_FIELDS]) {
        const raw = formData.get(name);
        if (typeof raw === 'string') {
            values[name] = raw.trim();
        }
    }

    if (formData.get('withOptions') === 'on') {
        values.withOptions = 'on';
    }

    return values;
}
