<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SentryScrubber;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\UserDataBag;
use Tests\TestCase;

final class SentryScrubberTest extends TestCase
{
    public function test_it_drops_user_body_cookies_query_and_sensitive_headers(): void
    {
        $event = Event::createEvent();
        $event->setUser(new UserDataBag('42', 'owner@example.test', '10.0.0.1', 'owner'));
        $event->setRequest([
            'url' => 'https://acme.example.test/api/v1/login?token=abc123',
            'method' => 'POST',
            'query_string' => 'token=abc123',
            'data' => ['password' => 'hunter2'],
            'cookies' => ['laravel_session' => 'xyz'],
            'headers' => ['Authorization' => 'Bearer 1|secret', 'Cookie' => 'a=b', 'User-Agent' => 'Mozilla', 'X-Store-Id' => '3'],
        ]);

        $result = SentryScrubber::beforeSend($event);

        $this->assertNull($result->getUser());
        $request = $result->getRequest();
        $this->assertSame('https://acme.example.test/api/v1/login', $request['url']);
        $this->assertSame('POST', $request['method']);
        $this->assertArrayNotHasKey('data', $request);
        $this->assertArrayNotHasKey('cookies', $request);
        $this->assertArrayNotHasKey('query_string', $request);
        $this->assertSame(['User-Agent' => 'Mozilla'], $request['headers']);
    }

    public function test_it_redacts_tokens_and_passwords_inside_messages(): void
    {
        $event = Event::createEvent();
        $event->setMessage('failed with Bearer abc.def-123 and password=hunter2 for 15|'.str_repeat('A', 40));
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException('x'), null, null)]);
        $event->getExceptions()[0]->setValue('api_key: SECRETVALUE and Authorization=Basic Zm9vOmJhcg==');

        $result = SentryScrubber::beforeSend($event);

        $text = $result->getMessage().' '.$result->getExceptions()[0]->getValue();
        foreach (['abc.def-123', 'hunter2', 'SECRETVALUE', 'Zm9vOmJhcg==', str_repeat('A', 40)] as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }
        $this->assertStringContainsString('[redacted]', $text);
    }

    public function test_transactions_are_scrubbed_too(): void
    {
        $event = Event::createTransaction();
        $event->setRequest(['url' => 'https://a.test/x?y=1', 'data' => ['k' => 'v']]);

        $result = SentryScrubber::beforeSendTransaction($event);

        $this->assertSame('https://a.test/x', $result->getRequest()['url']);
        $this->assertArrayNotHasKey('data', $result->getRequest());
    }

    public function test_breadcrumbs_and_telegram_urls_are_scrubbed(): void
    {
        $event = Event::createEvent();
        $event->setBreadcrumb([
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_HTTP, 'http', 'POST https://api.telegram.org/bot123456:AAH-secret_x/sendMessage', ['url' => 'https://api.telegram.org/bot123456:AAH-secret_x/sendMessage?chat=1']),
        ]);
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException('x'), null, null)]);
        $event->getExceptions()[0]->setValue('cURL error for https://api.telegram.org/bot123456:AAH-secret_x/sendMessage');

        $result = SentryScrubber::beforeSend($event);

        $crumb = $result->getBreadcrumbs()[0];
        $all = $crumb->getMessage().' '.json_encode($crumb->getMetadata()).' '.$result->getExceptions()[0]->getValue();
        $this->assertStringNotContainsString('AAH-secret_x', $all);
        $this->assertStringNotContainsString('chat=1', $all);
    }
}
