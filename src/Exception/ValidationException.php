<?php

declare(strict_types=1);

namespace app\Exception;

/**
 * Ошибка валидации входных данных. Несёт карту ошибок по полям,
 * которую контроллер отдаёт клиенту с HTTP-кодом 422.
 */
final class ValidationException extends \RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed');
    }

    /** @return array<string,string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
