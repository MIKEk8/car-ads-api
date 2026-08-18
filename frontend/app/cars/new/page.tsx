import type { Metadata } from 'next';

import { CarForm } from './CarForm';

import styles from './page.module.css';

export const metadata: Metadata = {
    title: 'Новое объявление',
    description: 'Размещение объявления о продаже автомобиля.',
};

export default function NewCarPage() {
    return (
        <div className={styles.page}>
            <header className={styles.head}>
                <h1 className={styles.title}>Новое объявление</h1>
                <p className={styles.subtitle}>
                    Поля со звёздочкой обязательны. Проверку выполняет сервер — его сообщения
                    появятся под соответствующими полями.
                </p>
            </header>

            <CarForm />
        </div>
    );
}
