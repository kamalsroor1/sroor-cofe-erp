<?php

declare(strict_types=1);

namespace App\Support;

use Sentry\Event;
use Sentry\EventHint;

/**
 * OPS-6: last line of defence before an event leaves the server. Sentry only ever receives the
 * exception, its stack trace and route/method/URL path: never credentials, request bodies, cookies,
 * query strings, the signed-in user or bearer tokens that ended up inside a message.
 *
 * Wired as `before_send` / `before_send_transaction` in config/sentry.php as a static callable
 * array (a Closure there would break `config:cache`).
 */
final class SentryScrubber
{
    private const REDACTED = '[redacted]';

    /** Headers kept in the event; every other header is dropped. */
    private const SAFE_HEADERS = ['host', 'user-agent', 'accept', 'accept-language', 'content-type', 'content-length', 'x-requested-with'];

    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        return self::scrub($event);
    }

    public static function beforeSendTransaction(Event $event, ?EventHint $hint = null): Event
    {
        return self::scrub($event);
    }

    private static function scrub(Event $event): Event
    {
        $event->setUser(null);

        $request = $event->getRequest();
        if ($request !== []) {
            unset($request['data'], $request['cookies'], $request['query_string'], $request['env']);

            $headers = [];
            foreach ((array) ($request['headers'] ?? []) as $name => $value) {
                if (in_array(strtolower((string) $name), self::SAFE_HEADERS, true)) {
                    $headers[$name] = $value;
                }
            }
            $request['headers'] = $headers;

            if (isset($request['url']) && is_string($request['url'])) {
                $request['url'] = self::stripQuery($request['url']);
            }

            $event->setRequest($request);
        }

        $message = $event->getMessage();
        if ($message !== null) {
            $event->setMessage(self::redactSecrets($message), $event->getMessageParams(), $event->getMessageFormatted() === null ? null : self::redactSecrets($event->getMessageFormatted()));
        }

        $breadcrumbs = [];
        foreach ($event->getBreadcrumbs() as $crumb) {
            $crumb = $crumb->withMessage($crumb->getMessage() === null ? null : self::redactSecrets($crumb->getMessage()));
            foreach (['url', 'http.url', 'full_url'] as $key) {
                $url = $crumb->getMetadata()[$key] ?? null;
                if (is_string($url)) {
                    $crumb = $crumb->withMetadata($key, self::redactSecrets(self::stripQuery($url)));
                }
            }
            $breadcrumbs[] = $crumb;
        }
        $event->setBreadcrumb($breadcrumbs);

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::redactSecrets($exception->getValue()));
        }

        return $event;
    }

    private static function stripQuery(string $url): string
    {
        $position = strpos($url, '?');

        return $position === false ? $url : substr($url, 0, $position);
    }

    private static function redactSecrets(string $text): string
    {
        // The heartbeat URL is itself the secret; Telegram bot tokens live in the request path.
        $heartbeat = (string) config('health.heartbeat.url', '');
        if ($heartbeat !== '') {
            $text = str_replace($heartbeat, self::REDACTED, $text);
        }
        $text = (string) preg_replace('#/bot\d+:[A-Za-z0-9_\-]+#', '/bot'.self::REDACTED, $text);
        $text = (string) preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/=]+/i', '$1 '.self::REDACTED, $text);
        $text = (string) preg_replace('/\b\d+\|[A-Za-z0-9]{30,}\b/', self::REDACTED, $text); // Sanctum plain-text token
        $text = (string) preg_replace('/\b(password|passwd|secret|token|api[_-]?key|authorization)(["\']?\s*[=:]\s*["\']?)[^\s"\'&,;]+/i', '$1$2'.self::REDACTED, $text);

        return $text;
    }
}
