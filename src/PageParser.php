<?php declare(strict_types=1);

namespace App;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ConfigurationProvider;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use Bref\Context\Context;
use Bref\Event\Handler;
use Bref\Event\Sqs\SqsEvent;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/../vendor/autoload.php';

class PageParser extends AbstractParser implements Handler
{
    use SqsHandlerTrait;

    private const CARS_SELECTOR = '.ads-list-photo-item:not(.js-booster-inline) > .ads-list-photo-item-title > a';

    private SqsClient $sqsClient;

    public function __construct()
    {
        parent::__construct();

        $this->sqsClient = new SqsClient(
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

            if (!array_key_exists('page_number', $body) || empty($body['page_number'])) {
                throw new \Exception('No page to parse');
            }

            $this->parsePage((int)$body['page_number']);
        }
    }

    private function parsePage(int $pageNumber): void
    {
        $response = $this->httpClient->request('GET', self::PAGE_PATH . '&page=' . $pageNumber);

        $crawler = new Crawler($response->getContent());
        $crawler->filter(self::CARS_SELECTOR)->each(function(Crawler $node) {
            $link = $node->attr('href');

            if (str_contains($link, 'booster')) {
                return;
            }

            $linkArray = explode('/', $link);
            $carId = end($linkArray);

            $this->logger->debug('Car id: ' . $carId);

            $this->sqsClient->sendMessage(new SendMessageRequest([
                'QueueUrl' => $_ENV['QUEUE_URL'],
                'MessageBody' => json_encode([
                    'car_id' => $carId,
                ]),
            ]));
        });
    }
}

return new PageParser();
