import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';

import { CarPhoto } from '@/components/CarPhoto';
import { ApiError, fetchCar, type Car } from '@/lib/api';
import { formatDate, formatMileage, formatPrice } from '@/lib/format';

import styles from './page.module.css';

export const dynamic = 'force-dynamic';

interface Props {
    params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
    const car = await load((await params).id);

    return car ? { title: car.title, description: car.description } : { title: 'Объявление не найдено' };
}

export default async function CarPage({ params }: Props) {
    const car = await load((await params).id);

    if (!car) {
        notFound();
    }

    return (
        <article>
            <Link href="/" className={styles.back}>
                ← Ко всем объявлениям
            </Link>

            <div className={styles.layout}>
                <div className={styles.media}>
                    <CarPhoto
                        src={car.photo_url}
                        alt={car.title}
                        className={styles.photo}
                        placeholderClassName={styles.noPhoto}
                        placeholder="Фотография не приложена"
                        eager
                    />
                </div>

                <div className={styles.info}>
                    <h1 className={styles.title}>{car.title}</h1>
                    <p className={styles.price}>{formatPrice(car.price)}</p>

                    <dl className={styles.contacts}>
                        <dt>Контакты</dt>
                        <dd>{car.contacts}</dd>
                    </dl>

                    <p className={styles.published}>Опубликовано {formatDate(car.created_at)}</p>
                </div>
            </div>

            <section className={styles.block}>
                <h2 className={styles.blockTitle}>Описание</h2>
                <p className={styles.description}>{car.description || 'Продавец не оставил описания.'}</p>
            </section>

            <section className={styles.block}>
                <h2 className={styles.blockTitle}>Технические характеристики</h2>
                {car.options ? (
                    <dl className={styles.specs}>
                        <Spec label="Марка" value={car.options.brand} />
                        <Spec label="Модель" value={car.options.model} />
                        <Spec label="Год выпуска" value={String(car.options.year)} />
                        <Spec label="Кузов" value={car.options.body} />
                        <Spec label="Пробег" value={formatMileage(car.options.mileage)} />
                    </dl>
                ) : (
                    /* По ТЗ блок необязателен, и это штатная ситуация,
                       а не ошибка — сообщаем нейтрально. */
                    <p className={styles.noSpecs}>
                        Продавец не указал характеристики. Уточните их по контактам выше.
                    </p>
                )}
            </section>
        </article>
    );
}

function Spec({ label, value }: { label: string; value: string }) {
    return (
        <div className={styles.spec}>
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

/** Возвращает null на 404 и пробрасывает остальное — 500 не должен выглядеть как «не найдено». */
async function load(rawId: string): Promise<Car | null> {
    const id = Number.parseInt(rawId, 10);

    if (!Number.isFinite(id) || id <= 0) {
        return null;
    }

    try {
        return await fetchCar(id);
    } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
            return null;
        }
        throw error;
    }
}
