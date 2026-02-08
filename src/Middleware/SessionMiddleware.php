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
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $timeout = 1800, // 30 minutes
        private readonly int $regenerateInterval = 300 // 5 minutes
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
            } catch (SessionException $e) {
                $this->logger->error('Session start failed', ['error' => $e->getMessage()]);
                // If session cannot start, we can't do session security logic
                return $handler->handle($request);
            }
        }

        if ($this->session->isStarted()) {
            try {
                // 1. Session Binding Security (User-Agent)
                if ($this->session->has('user_agent_hash')) {
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    if ($this->session->get('user_agent_hash') !== hash('sha256', $userAgent)) {
                        $this->session->destroy();
                        $this->logger->warning('Session hijacked attempt detected (User-Agent mismatch)');
                        // Depending on policy, we might want to restart a fresh session or just fail
                        // Here we restart a fresh session for the user
                        $this->session->start();
                    }
                }

                // 2. Timeout Management (Last Activity)
                $now = time();
                if ($this->session->has('last_activity')) {
                    $lastActivity = $this->session->get('last_activity');
                    if (($now - $lastActivity) > $this->timeout) {
                        $this->session->destroy();
                        $this->logger->info('Session expired due to inactivity');
                        $this->session->start(); // Start fresh
                    }
                }

                // 3. Periodic Regeneration
                if (!$this->session->has('created_at')) {
                    // Initialize new session
                    $this->session->set('created_at', $now);
                    $this->session->set('last_regenerated', $now);
                    $this->session->set('last_activity', $now);

                    if (!$this->session->has('csrf_token')) {
                        $this->session->set('csrf_token', bin2hex(random_bytes(32)));
                    }

                    if (isset($_SERVER['HTTP_USER_AGENT'])) {
                        $this->session->set('user_agent_hash', hash('sha256', $_SERVER['HTTP_USER_AGENT']));
                    }
                } else {
                    // Update last activity
                    $this->session->set('last_activity', $now);

                    // Check if regeneration is needed
                    $lastRegenerated = $this->session->get('last_regenerated', $this->session->get('created_at'));
                    if (($now - $lastRegenerated) > $this->regenerateInterval) {
                        $this->session->regenerateId();
                        $this->session->set('last_regenerated', $now);
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error('Session security check failed', ['error' => $e->getMessage()]);
            }
        }

        // Add session to request attributes
        $request = $request->withAttribute('session', $this->session);

        return $handler->handle($request);
    }
}
