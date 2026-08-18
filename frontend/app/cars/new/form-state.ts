/**
 * Состояние формы вынесено из actions.ts намеренно.
 *
 * Модуль с директивой 'use server' может экспортировать только асинхронные
 * функции: всё остальное превращается на клиенте в undefined. Пока константа
 * начального состояния жила рядом с экшеном, форма падала на первом же
 * обращении к state.values — сборка Next поймала это на пререндере.
 */

import type { FieldErrors } from '@/lib/api';

export interface FormState {
    /** Ключи — те же, что присылает бэкенд: `title`, `options.year` и так далее. */
    errors: FieldErrors;
    /** Общая ошибка, не привязанная к полю: сервис недоступен, 500. */
    message: string | null;
    /** Введённые значения, чтобы форма не обнулялась после отказа. */
    values: Record<string, string>;
}

export const EMPTY_STATE: FormState = { errors: {}, message: null, values: {} };
