<?php

declare(strict_types=1);

namespace ResponsiveSk\Slim4Session\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ResponsiveSk\Slim4Session\SessionInterface;

/**
 * Auto-refresh session on user activity.
 * 
 * Addresses the concern about missing auto-refresh functionality
 * by automatically extending session lifetime on user activity.
 */
final class SessionAutoRefreshMiddleware implements MiddlewareInterface
{
    private const LAST_ACTIVITY_KEY = '_session_last_activity';

    public function __construct(
        private readonly SessionInterface $session,
        private readonly int $refreshThreshold = 300, // 5 minutes
        private readonly int $extendBy = 3600 // 1 hour
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if ($this->session->isStarted()) {
            $this->refreshSessionIfNeeded();
        }

        return $handler->handle($request);
    }

    private function refreshSessionIfNeeded(): void
    {
        $now = time();
        /** @var int $lastActivity */
        $lastActivity = $this->session->get(self::LAST_ACTIVITY_KEY, 0);

        // Check if refresh threshold is exceeded
        if (($now - $lastActivity) > $this->refreshThreshold) {
            // Regenerate session ID for security
            $this->session->regenerateId();

            // Update last activity timestamp
            $this->session->set(self::LAST_ACTIVITY_KEY, $now);

            // Extend cookie lifetime
            $this->extendSessionCookie();
        }
    }

    private function extendSessionCookie(): void
    {
        $params = $this->session->getCookieParams();
        $params['lifetime'] = time() + $this->extendBy;

        $this->session->setCookieParams($params);

        // Set the cookie with new expiration
        $sessionName = $this->session->getName();
        $sessionId = $this->session->getId();

        if ($sessionName !== '' && $sessionId !== null) {
            setcookie(
                $sessionName,
                $sessionId,
                /** @phpstan-ignore argument.type */
                [
                    'expires' => (int) $params['lifetime'],
                    /** @phpstan-ignore cast.string */
                    'path' => (string) ($params['path'] ?? '/'),
                    /** @phpstan-ignore cast.string */
                    'domain' => (string) ($params['domain'] ?? ''),
                    'secure' => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    /** @phpstan-ignore cast.string */
                    'samesite' => (string) ($params['samesite'] ?? 'Lax')
                ]
            );
        }
    }
}
