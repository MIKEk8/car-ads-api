import Link from 'next/link';

import { CarPhoto } from '@/components/CarPhoto';
import type { Car } from '@/lib/api';
import { formatMileage, formatPrice } from '@/lib/format';

import styles from './CarCard.module.css';

/**
 * Карточка в списке. Характеристики необязательны, поэтому у карточки два
 * состояния — со строкой спецификаций и без неё. Пустой блок не рисуем:
 * отсутствие данных должно читаться как отсутствие, а не как пропуск в вёрстке.
 */
export function CarCard({ car }: { car: Car }) {
    const { options } = car;

    return (
        <article className={styles.card}>
            <Link href={`/cars/${car.id}`} className={styles.link}>
                <div className={styles.media}>
                    <CarPhoto
                        src={car.photo_url}
                        alt=""
                        className={styles.photo}
                        placeholderClassName={styles.noPhoto}
                        placeholder="Без фотографии"
                    />
                </div>

                <div className={styles.body}>
                    <h2 className={styles.title}>{car.title}</h2>
                    <p className={styles.price}>{formatPrice(car.price)}</p>

                    {options ? (
                        <p className={styles.specs}>
                            <span>{options.year}</span>
                            <span className={styles.dot} aria-hidden="true" />
                            <span>{options.body}</span>
                            <span className={styles.dot} aria-hidden="true" />
                            <span>{formatMileage(options.mileage)}</span>
                        </p>
                    ) : (
                        <p className={styles.noSpecs}>Характеристики не указаны</p>
                    )}

                    <p className={styles.excerpt}>{car.description}</p>
                </div>
            </Link>
        </article>
    );
}
