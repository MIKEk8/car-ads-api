'use client';

import { useState } from 'react';

interface Props {
    src: string | null;
    alt: string;
    className: string;
    placeholderClassName: string;
    placeholder: string;
    eager?: boolean;
}

/**
 * Фотография объявления с запасным вариантом.
 *
 * На доске объявлений ссылка на фото живёт своей жизнью: домен исчезает,
 * файл удаляют, отдаётся 404. Без обработки браузер рисует значок битой
 * картинки — вид, который читается как поломка сайта, а не как отсутствие
 * фотографии. Поэтому при ошибке загрузки показываем ту же заглушку, что и
 * у объявлений вовсе без фото.
 *
 * Клиентский компонент здесь неизбежен: onError доступен только в браузере.
 */
export function CarPhoto({ src, alt, className, placeholderClassName, placeholder, eager }: Props) {
    const [failed, setFailed] = useState(false);

    if (!src || failed) {
        return <span className={placeholderClassName}>{placeholder}</span>;
    }

    return (
        // Домены фотографий заранее неизвестны, поэтому обычный img, а не
        // next/image с его списком разрешённых хостов.
        // eslint-disable-next-line @next/next/no-img-element
        <img
            src={src}
            alt={alt}
            className={className}
            loading={eager ? 'eager' : 'lazy'}
            onError={() => setFailed(true)}
        />
    );
}
