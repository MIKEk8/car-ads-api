<?php

declare(strict_types=1);

namespace app\Web;

/**
 * Запрос с нормализованным `Content-Type`.
 *
 * Единственная задача — отдать заголовок в каноническом виде, потому что
 * {@see \yii\web\Request::getBodyParams()} использует результат этого метода
 * для регистрозависимого поиска парсера. После нормализации
 * `APPLICATION/JSON` и `application/json ; charset=utf-8` находят
 * `JsonParser` так же, как канонический `application/json`.
 *
 * Подключается в `config/web.php` как класс компонента `request`.
 */
final class Request extends \yii\web\Request
{
    public function getContentType(): string
    {
        return MediaType::normalizeHeader((string)parent::getContentType());
    }
}
