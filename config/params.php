<?php

declare(strict_types=1);

use app\Env;

return [
    // Количество объявлений на страницу в GET /car/list.
    'car.list.perPage' => Env::int('CAR_LIST_PER_PAGE', 20),
];
