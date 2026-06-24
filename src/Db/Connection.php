<?php

declare(strict_types=1);

namespace app\Db;

/**
 * Тонкий подкласс соединения БД.
 *
 * Нужен, чтобы компонент приложения `db` имел собственный тип и не конфликтовал
 * с определением контейнера для базового {@see \yii\db\Connection} (иначе создание
 * компонента уходит в рекурсию через autowiring).
 */
final class Connection extends \yii\db\Connection
{
}
