<?php declare(strict_types=1);

namespace App;

use Bref\Event\Handler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractParser implements Handler
{
    protected const AWS_REGION = 'eu-central-1';

    protected const HOST = 'https://999.md';

    protected Logger $logger;

    protected HttpClientInterface $httpClient;

    public function __construct()
    {
        $this->httpClient = HttpClient::create([
            'base_uri' => self::HOST,
        ]);

        $this->logger = new Logger('main');
        $this->logger->pushHandler(new StreamHandler('php://stderr'));
    }

    public function wait(): void
    {
        sleep(rand(1, 10));
    }
}
