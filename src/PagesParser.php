<?php declare(strict_types=1);

namespace App;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ConfigurationProvider;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use Bref\Context\Context;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/../vendor/autoload.php';

class PagesParser extends AbstractParser
{
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

    public function handle($event, Context $context)
    {
        $this->parse($event['limit'] ?? null);
    }

    public function parse(?int $limit): void
    {
        $response = $this->httpClient->request('GET', self::PAGE_PATH);
        $crowler = new Crawler($response->getContent());
        $lastPageLink = $crowler->filter('li.is-last-page > a')->first();

        if (!$lastPageLink) {
            throw new \Exception('Last page link not found.');
        }

        parse_str(parse_url($lastPageLink->attr('href'), PHP_URL_QUERY), $queryParams);

        $lastPage = (int)$queryParams['page'];
        $lastPage = $limit ?? $lastPage; // Manual limit from event

        $this->logger->debug('Result', ['pages' => $lastPage]);

        for ($i = 1; $i <= $lastPage; $i++) {
            $this->sqsClient->sendMessage(new SendMessageRequest([
                'QueueUrl' => $_ENV['QUEUE_URL'],
                'MessageBody' => json_encode([
                    'page_number' => $i,
                ]),
            ]));
        }
    }
}

return new PagesParser();
