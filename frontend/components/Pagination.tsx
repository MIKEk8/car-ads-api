import Link from 'next/link';

import styles from './Pagination.module.css';

interface Props {
    page: number;
    pages: number;
}

/**
 * Пагинация ссылками, а не кнопками с состоянием: страница живёт в URL,
 * поэтому её можно скопировать, открыть в новой вкладке и вернуться назад
 * кнопкой браузера. Серверный компонент — клиентского JS не требует вовсе.
 */
export function Pagination({ page, pages }: Props) {
    if (pages <= 1) {
        return null;
    }

    const numbers = pageNumbers(page, pages);

    return (
        <nav className={styles.nav} aria-label="Страницы объявлений">
            <PageLink page={page - 1} disabled={page <= 1} label="Назад" rel="prev" />

            <ol className={styles.list}>
                {numbers.map((item, index) =>
                    item === null ? (
                        <li key={`gap-${index}`} className={styles.gap} aria-hidden="true">
                            …
                        </li>
                    ) : (
                        <li key={item}>
                            <Link
                                href={hrefFor(item)}
                                className={item === page ? styles.current : styles.page}
                                aria-current={item === page ? 'page' : undefined}
                            >
                                {item}
                            </Link>
                        </li>
                    ),
                )}
            </ol>

            <PageLink page={page + 1} disabled={page >= pages} label="Вперёд" rel="next" />
        </nav>
    );
}

function PageLink({
    page,
    disabled,
    label,
    rel,
}: {
    page: number;
    disabled: boolean;
    label: string;
    rel: string;
}) {
    if (disabled) {
        // Неактивный шаг — span, а не ссылка: по нему не должно быть перехода
        // и он не должен попадать в обход с клавиатуры.
        return <span className={styles.stepOff}>{label}</span>;
    }

    return (
        <Link href={hrefFor(page)} className={styles.step} rel={rel}>
            {label}
        </Link>
    );
}

function hrefFor(page: number): string {
    return page <= 1 ? '/' : `/?page=${page}`;
}

/** Окно вокруг текущей страницы с многоточиями; null — разрыв. */
function pageNumbers(page: number, pages: number): (number | null)[] {
    if (pages <= 7) {
        return Array.from({ length: pages }, (_, i) => i + 1);
    }

    const around = new Set<number>([1, pages, page, page - 1, page + 1]);
    const shown = [...around].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b);

    const result: (number | null)[] = [];
    let previous = 0;

    for (const current of shown) {
        if (previous && current - previous > 1) {
            result.push(null);
        }
        result.push(current);
        previous = current;
    }

    return result;
}
