import type { Metadata } from 'next';
import Link from 'next/link';

import './globals.css';
import styles from './layout.module.css';

export const metadata: Metadata = {
    title: {
        default: 'Автообъявления',
        template: '%s — Автообъявления',
    },
    description: 'Витрина объявлений о продаже автомобилей поверх REST API на PHP 8 и Yii2.',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
    return (
        <html lang="ru">
            <body>
                <header className={styles.header}>
                    <div className={styles.bar}>
                        <Link href="/" className={styles.brand}>
                            <span className={styles.mark} aria-hidden="true" />
                            Автообъявления
                        </Link>

                        <Link href="/cars/new" className={styles.cta}>
                            Разместить объявление
                        </Link>
                    </div>
                </header>

                <main className={styles.main}>{children}</main>

                <footer className={styles.footer}>
                    <div className={styles.footerInner}>
                        <span>Next.js 16 · React 19 · TypeScript</span>
                        <span>Данные — REST API на PHP 8 и Yii2</span>
                    </div>
                </footer>
            </body>
        </html>
    );
}
