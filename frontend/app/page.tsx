import Link from 'next/link';

import { CarCard } from '@/components/CarCard';
import { Pagination } from '@/components/Pagination';
import { ApiError, fetchCars, type CarList } from '@/lib/api';

import styles from './page.module.css';

// Список меняется при каждом размещении объявления — статикой быть не может.
export const dynamic = 'force-dynamic';

interface Props {
    searchParams: Promise<{ page?: string }>;
}

export default async function CarListPage({ searchParams }: Props) {
    const { page: rawPage } = await searchParams;
    const page = parsePage(rawPage);

    // В try/catch только запрос. Разметка собирается за его пределами:
    // React рендерит возвращённый JSX позже, и ошибки рендера в этот catch
    // всё равно не попали бы — защита выглядела бы шире, чем она есть.
    let list: CarList | null = null;
    let failure: string | null = null;

    try {
        list = await fetchCars(page);
    } catch (error) {
        failure =
            error instanceof ApiError ? error.message : 'Не удалось загрузить объявления';
    }

    if (list === null) {
        // Витрина не должна падать целиком из-за недоступного бэкенда.
        return (
            <div className={styles.problem} role="alert">
                <h1 className={styles.title}>Объявления недоступны</h1>
                <p className={styles.problemText}>{failure}</p>
                <Link href="/" className={styles.retry}>
                    Попробовать снова
                </Link>
            </div>
        );
    }

    const { data, pagination } = list;

    return (
        <>
            <header className={styles.head}>
                <h1 className={styles.title}>Объявления</h1>
                <p className={styles.count}>
                    {pagination.total > 0
                        ? `${pagination.total} ${plural(pagination.total)}`
                        : 'Пока пусто'}
                </p>
            </header>

            {data.length > 0 ? (
                <ul className={styles.grid}>
                    {data.map((car) => (
                        <li key={car.id}>
                            <CarCard car={car} />
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState page={page} />
            )}

            <Pagination page={pagination.page} pages={pagination.pages} />
        </>
    );
}

function EmptyState({ page }: { page: number }) {
    // Пустая вторая страница и пустая база — разные ситуации, и говорить
    // о них надо по-разному.
    if (page > 1) {
        return (
            <div className={styles.empty}>
                <p>На этой странице объявлений нет.</p>
                <Link href="/" className={styles.retry}>
                    К первой странице
                </Link>
            </div>
        );
    }

    return (
        <div className={styles.empty}>
            <p>Объявлений пока нет — разместите первое.</p>
            <Link href="/cars/new" className={styles.retry}>
                Разместить объявление
            </Link>
        </div>
    );
}

function parsePage(raw: string | undefined): number {
    const value = Number.parseInt(raw ?? '1', 10);

    // Бэкенд на ?page=abc отвечает 400, поэтому мусор чиним здесь,
    // не отправляя заведомо неверный запрос.
    return Number.isFinite(value) && value > 0 ? value : 1;
}

function plural(count: number): string {
    const mod100 = count % 100;
    const mod10 = count % 10;

    if (mod100 >= 11 && mod100 <= 14) return 'объявлений';
    if (mod10 === 1) return 'объявление';
    if (mod10 >= 2 && mod10 <= 4) return 'объявления';
    return 'объявлений';
}
