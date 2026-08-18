import Link from 'next/link';

import styles from './page.module.css';

export default function CarNotFound() {
    return (
        <div className={styles.missing}>
            <h1 className={styles.title}>Объявление не найдено</h1>
            <p>Возможно, его сняли с публикации или ссылка неверна.</p>
            <Link href="/" className={styles.back}>
                ← Ко всем объявлениям
            </Link>
        </div>
    );
}
