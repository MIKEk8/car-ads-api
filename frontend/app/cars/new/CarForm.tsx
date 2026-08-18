'use client';

import Link from 'next/link';
import { useActionState, useId, useState } from 'react';

import { createCarAction } from './actions';
import { EMPTY_STATE, type FormState } from './form-state';

import styles from './page.module.css';

export function CarForm() {
    const [state, action, pending] = useActionState<FormState, FormData>(createCarAction, EMPTY_STATE);

    // Блок характеристик по ТЗ необязателен. Состояние переживает отказ
    // сервера: если пользователь его открыл и получил 422, он должен
    // остаться открытым вместе с введёнными значениями. Источник правды при
    // этом один — ответ сервера, поэтому и локальное состояние, и
    // defaultChecked поля берутся из него.
    const [withOptions, setWithOptions] = useState(() => state.values.withOptions === 'on');

    const optionsId = useId();
    const hasOptionErrors = Object.keys(state.errors).some((key) => key.startsWith('options'));

    // Нативную проверку не отключаем: `required` ловит пустое поле сразу,
    // без похода на сервер. Все остальные правила остаются за сервером,
    // чтобы существовать в одном месте, а не дублироваться здесь.
    return (
        <form action={action} className={styles.form}>
            {state.message ? (
                <p className={styles.alert} role="alert">
                    {state.message}
                </p>
            ) : null}

            <Field
                name="title"
                label="Заголовок"
                hint="Например: Toyota Camry 3.5 V6 2019"
                state={state}
                required
            />

            <Field name="price" label="Цена, ₽" hint="Например: 2790000" state={state} required inputMode="decimal" />

            <Field name="contacts" label="Контакты" hint="Телефон или способ связи" state={state} required />

            <Field name="photo_url" label="Ссылка на фотографию" hint="Необязательно" state={state} />

            <Field name="description" label="Описание" state={state} multiline />

            <fieldset className={styles.fieldset}>
                <legend className={styles.legend}>Технические характеристики</legend>

                <label className={styles.toggle} htmlFor={optionsId}>
                    {/* Неуправляемый, как и остальные поля: React 19 после
                        завершения action сбрасывает форму, и управляемый
                        чекбокс расходился с состоянием — DOM обнулялся, React
                        считал галочку стоящей, а следующая отправка молча
                        теряла весь блок характеристик. defaultChecked берётся
                        из ответа сервера, поэтому сброс восстанавливает то же
                        значение. */}
                    <input
                        id={optionsId}
                        type="checkbox"
                        name="withOptions"
                        defaultChecked={withOptions}
                        onChange={(event) => setWithOptions(event.target.checked)}
                    />
                    <span>
                        Указать характеристики
                        <em className={styles.toggleHint}>
                            Блок необязательный, но если он заполняется — обязательны все поля
                        </em>
                    </span>
                </label>

                {withOptions ? (
                    <div className={styles.optionGrid}>
                        <Field name="brand" label="Марка" state={state} errorKey="options.brand" required />
                        <Field name="model" label="Модель" state={state} errorKey="options.model" required />
                        <Field
                            name="year"
                            label="Год выпуска"
                            state={state}
                            errorKey="options.year"
                            required
                            inputMode="numeric"
                        />
                        <Field name="body" label="Кузов" state={state} errorKey="options.body" required />
                        <Field
                            name="mileage"
                            label="Пробег, км"
                            state={state}
                            errorKey="options.mileage"
                            required
                            inputMode="numeric"
                        />
                    </div>
                ) : null}

                {/* Ошибка может прийти на весь блок целиком — например,
                    когда options пришёл не объектом. */}
                {state.errors.options ? <p className={styles.error}>{state.errors.options}</p> : null}

                {!withOptions && hasOptionErrors ? (
                    <p className={styles.error}>
                        Сервер вернул ошибки по характеристикам — раскройте блок и проверьте поля.
                    </p>
                ) : null}
            </fieldset>

            <div className={styles.actions}>
                <button type="submit" className={styles.submit} disabled={pending}>
                    {pending ? 'Сохраняем…' : 'Разместить объявление'}
                </button>
                <Link href="/" className={styles.cancel}>
                    Отмена
                </Link>
            </div>
        </form>
    );
}

interface FieldProps {
    name: string;
    label: string;
    state: FormState;
    hint?: string;
    /** Ключ ошибки на стороне бэкенда, если он отличается от имени поля. */
    errorKey?: string;
    required?: boolean;
    multiline?: boolean;
    inputMode?: 'numeric' | 'decimal';
}

function Field({ name, label, state, hint, errorKey, required, multiline, inputMode }: FieldProps) {
    const id = useId();
    const error = state.errors[errorKey ?? name];
    const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined;

    const shared = {
        id,
        name,
        defaultValue: state.values[name] ?? '',
        required,
        'aria-invalid': error ? (true as const) : undefined,
        'aria-describedby': describedBy,
        className: error ? `${styles.control} ${styles.controlError}` : styles.control,
    };

    return (
        <div className={styles.field}>
            <label htmlFor={id} className={styles.label}>
                {label}
                {required ? <span className={styles.required} aria-hidden="true"> *</span> : null}
            </label>

            {multiline ? (
                <textarea {...shared} rows={5} />
            ) : (
                <input {...shared} type="text" inputMode={inputMode} />
            )}

            {error ? (
                <p id={`${id}-error`} className={styles.error}>
                    {error}
                </p>
            ) : hint ? (
                <p id={`${id}-hint`} className={styles.hint}>
                    {hint}
                </p>
            ) : null}
        </div>
    );
}
