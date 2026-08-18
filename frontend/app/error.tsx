'use client';

import { useEffect } from 'react';

import styles from './page.module.css';

/**
 * Граница ошибок маршрута. Ловит то, что не поймал бы try/catch вокруг
 * запроса: сбои во время самого рендера.
 */
export default function RouteError({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        // Подробности остаются в консоли браузера и в логах сервера,
        // пользователю показываем только то, что ему полезно.
        console.error('Ошибка при отрисовке страницы', error);
    }, [error]);

    return (
        <div className={styles.problem} role="alert">
            <h1 className={styles.title}>Что-то пошло не так</h1>
            <p className={styles.problemText}>
                Страницу не удалось отобразить. Попробуйте повторить — если не поможет,
                вернитесь к списку объявлений.
            </p>
            <button type="button" onClick={reset} className={styles.retry}>
                Повторить
            </button>
        </div>
    );
}
