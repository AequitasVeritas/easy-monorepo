<?php

declare(strict_types=1);

namespace EonX\EasyWebhook\Middleware;

use EonX\EasyWebhook\Exceptions\InvalidWebhookUrlException;
use EonX\EasyWebhook\Interfaces\StackInterface;
use EonX\EasyWebhook\Interfaces\WebhookInterface;
use EonX\EasyWebhook\Interfaces\WebhookResultInterface;
use EonX\EasyWebhook\WebhookResult;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SendWebhookMiddleware extends AbstractMiddleware
{
    /**
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient, ?int $priority = null)
    {
        $this->httpClient = $httpClient;

        parent::__construct($priority);
    }

    public function process(WebhookInterface $webhook, StackInterface $stack): WebhookResultInterface
    {
        $method = $webhook->getMethod() ?? WebhookInterface::DEFAULT_METHOD;
        $url = $webhook->getUrl();

        if (empty($url)) {
            throw new InvalidWebhookUrlException('Webhook URL required');
        }

        $response = null;
        $throwable = null;

        try {
            $response = $this->httpClient->request($method, $url, $webhook->getHttpClientOptions() ?? []);
            // Trigger exception on bad response
            $response->getContent();
        } catch (\Throwable $throwable) {
            if ($throwable instanceof HttpExceptionInterface) {
                $response = $throwable->getResponse();
            }
        }

        return new WebhookResult($webhook, $response, $throwable);
    }
}
