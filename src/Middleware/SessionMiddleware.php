<?php

declare(strict_types=1);

namespace ResponsiveSk\Slim4Session\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ResponsiveSk\Slim4Session\SessionInterface;
use ResponsiveSk\Slim4Session\Exceptions\SessionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PSR-15 Session Middleware for Slim 4.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly bool $autoStart = true,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Auto-start session if enabled
        if ($this->autoStart && !$this->session->isStarted()) {
            try {
                $this->session->start();

                // Session Migration: Initialize default values if missing
                if (!$this->session->has('last_activity')) {
                    $now = time();
                    $this->session->set('last_activity', $now);
                    $this->session->set('created_at', $now);

                    // Generate new CSRF token if missing
                    if (!$this->session->has('csrf_token')) {
                        $this->session->set('csrf_token', bin2hex(random_bytes(32)));
                    }

                    // Bind to User-Agent
                    if (isset($_SERVER['HTTP_USER_AGENT'])) {
                        $this->session->set('user_agent_hash', hash('sha256', $_SERVER['HTTP_USER_AGENT']));
                    }
                }
            } catch (SessionException $e) {
                // Log error but continue - session is optional
                $this->logger->warning('Session start failed in middleware', [
                    'error' => $e->getMessage(),
                    'request_uri' => $request->getUri()->getPath()
                ]);
            }
        }

        // Add session to request attributes
        $request = $request->withAttribute('session', $this->session);

        return $handler->handle($request);
    }
}
