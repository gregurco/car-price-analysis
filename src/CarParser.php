<?php declare(strict_types=1);

namespace App;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ConfigurationProvider;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Bref\Context\Context;
use Bref\Event\Handler;
use Bref\Event\Sqs\SqsEvent;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/../vendor/autoload.php';

class CarParser extends AbstractParser implements Handler
{
    private DynamoDbClient $dynamoDb;

    public function __construct()
    {
        parent::__construct();

        $this->dynamoDb = new DynamoDbClient(
            Configuration::create(['region' => self::AWS_REGION]),
            new ConfigurationProvider(),
            HttpClient::create(),
            $this->logger
        );
    }

    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            $this->wait();

            $body = json_decode($record->getBody(), true);

            if (!array_key_exists('car_id', $body) || empty($body['car_id'])) {
                throw new \Exception('No car to parse');
            }

            $this->parseCar((int)$body['car_id']);
        }
    }

    public function handle($event, Context $context): void
    {
        $this->handleSqs(new SqsEvent($event), $context);
    }

    private function parseCar(int $carId): void
    {
        $this->logger->debug('Parse car: ' . $carId);

        $response = $this->httpClient->request('GET', self::HOST . '/ru/' . $carId);
        $crowler = new Crawler($response->getContent());

        $mileage = null;
        $mileageKm = null;
        $year = null;

        $crowler->filter('.adPage__content__features .m-value')->each(function(Crawler $node) use (&$mileage, &$mileageKm, &$year) {
            $key = trim($node->filter('.adPage__content__features__key')->text());

            if ($key === 'Пробег') {
                $mileage = trim($node->filter('.adPage__content__features__value')->text());

                $mileageKm = (int)str_replace(' ', '', $mileage);
                if (str_contains($mileage, 'mi')) {
                    $mileageKm *= 1.60934;
                }
            } elseif ($key === 'Год выпуска') {
                $year = trim($node->filter('.adPage__content__features__value')->text());
            }
        });

        if ($mileage && $year && $mileageKm > 1000) {
            $ages = 2021 - $year;
            $kmPerYear = floor($mileageKm / $ages);

            $this->dynamoDb->putItem(new PutItemInput([
                'TableName' => 'cars',
                'Item' => [
                    'car_id' => new AttributeValue(['S' => (string)$carId]),
                    'year' => new AttributeValue(['N' => $year]),
                    'mileage_raw' => new AttributeValue(['S' => $mileage]),
                    'mileage_km' => new AttributeValue(['N' => (string)$mileageKm]),
                    'km_per_year' => new AttributeValue(['N' => (string)$kmPerYear]),
                    'ages' => new AttributeValue(['N' => (string)$ages]),
                ],
                // 'ConditionExpression' => 'attribute_not_exists(car_id)'
            ]));
        } else {
            $this->logger->debug('Skip car', [
                'id' => $carId,
                'mileage' => $mileage,
                'year' => $year,
            ]);
        }
    }
}

return new CarParser();
