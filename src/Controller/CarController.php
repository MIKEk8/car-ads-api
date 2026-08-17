<?php

declare(strict_types=1);

namespace app\Controller;

use app\Entity\Car;
use app\Exception\ValidationException;
use app\Service\CarService;
use app\Web\MediaType;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UnsupportedMediaTypeHttpException;

/**
 * REST-контроллер объявлений автомобилей.
 *
 *  POST /car/create  — создать объявление (201);
 *  GET  /car/{id}    — получить объявление (200/404);
 *  GET  /car/list    — список с пагинацией (?page).
 *
 * Контроллер — тонкий: разбирает HTTP-запрос, делегирует {@see CarService}
 * и сериализует доменные сущности в JSON.
 */
final class CarController extends Controller
{
    /** Единственный тип тела, который принимает API. */
    private const JSON_MEDIA_TYPE = 'application/json';

    public $enableCsrfValidation = false;

    public function __construct(
        $id,
        $module,
        private readonly CarService $carService,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Правила в urlManager намеренно не привязаны к методу: сюда доходит любой
     * запрос по адресу, а VerbFilter отвечает 405 с заголовком Allow. Иначе
     * `GET /car/create` выглядел бы как несуществующий адрес (404).
     */
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['POST'],
                    'view' => ['GET', 'HEAD'],
                    'list' => ['GET', 'HEAD'],
                ],
            ],
        ];
    }

    /**
     * @throws UnsupportedMediaTypeHttpException
     */
    public function actionCreate(): array
    {
        $this->assertJsonRequest();

        $data = Yii::$app->request->getBodyParams();

        try {
            $car = $this->carService->create($data);
        } catch (ValidationException $e) {
            Yii::$app->response->statusCode = 422;
            return [
                'message' => 'Ошибка валидации.',
                'errors' => $e->getErrors(),
            ];
        }

        Yii::$app->response->statusCode = 201;
        return $this->serializeCar($car);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): array
    {
        $car = $this->carService->getById($id);
        if ($car === null) {
            throw new NotFoundHttpException("Объявление с id=$id не найдено.");
        }

        return $this->serializeCar($car);
    }

    public function actionList(int $page = 1): array
    {
        $perPage = (int)Yii::$app->params['car.list.perPage'];
        $result = $this->carService->list($page, $perPage);

        return [
            'data' => array_map([$this, 'serializeCar'], $result['items']),
            'pagination' => [
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'total' => $result['total'],
                'pages' => $result['pages'],
            ],
        ];
    }

    /**
     * Тело принимается только как application/json.
     *
     * Без этой проверки запрос с чужим Content-Type уходит в разбор $_POST:
     * JSON-тело теряется целиком, и клиент получает 422 «поле title
     * обязательно» — сообщение, уводящее от настоящей причины. Заодно
     * закрывается приём form-urlencoded, которого у JSON-API быть не должно.
     *
     * @throws UnsupportedMediaTypeHttpException
     */
    private function assertJsonRequest(): void
    {
        $contentType = MediaType::extract((string)Yii::$app->request->getContentType());

        if ($contentType !== self::JSON_MEDIA_TYPE) {
            throw new UnsupportedMediaTypeHttpException(
                'Тело запроса должно передаваться с Content-Type: application/json.'
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeCar(Car $car): array
    {
        $out = [
            'id' => $car->id,
            'title' => $car->title,
            'description' => $car->description,
            // По ТЗ price — число. Точность при этом не страдает: сервис уже
            // ограничил цену диапазоном numeric(12,2), а такие значения float
            // представляет без потери значащих знаков.
            'price' => (float)$car->price,
            'photo_url' => $car->photoUrl,
            'contacts' => $car->contacts,
            'created_at' => $car->createdAt,
            'options' => null,
        ];

        if ($car->option !== null) {
            $out['options'] = [
                'brand' => $car->option->brand,
                'model' => $car->option->model,
                'year' => $car->option->year,
                'body' => $car->option->body,
                'mileage' => $car->option->mileage,
            ];
        }

        return $out;
    }
}
