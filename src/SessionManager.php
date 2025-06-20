<?php

declare(strict_types=1);

namespace ResponsiveSk\Slim4Session;

use Odan\Session\SessionManagerInterface;

/**
 * Session Manager
 * 
 * Wrapper around Odan\Session\SessionManagerInterface that implements
 * our extended SessionInterface with additional methods.
 */
final class SessionManager implements SessionInterface
{
    public function __construct(
        private readonly SessionManagerInterface $sessionManager
    ) {
    }

    // === Extended methods (missing from Odan\Session) ===

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function start(): bool
    {
        if ($this->isStarted()) {
            return true;
        }

        return session_start();
    }

    public function destroy(): bool
    {
        if (!$this->isStarted()) {
            return true;
        }

        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $sessionName = session_name();
            if ($sessionName !== false) {
                setcookie(
                    $sessionName,
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
        }

        return session_destroy();
    }

    public function getId(): ?string
    {
        $id = session_id();
        return ($id !== false && $id !== '') ? $id : null;
    }

    public function regenerateId(): bool
    {
        return session_regenerate_id(true);
    }

    public function getName(): string
    {
        $name = session_name();
        return $name !== false ? $name : '';
    }

    public function has(string $key): bool
    {
        // Use direct $_SESSION access since Odan interface doesn't have has() method
        return isset($_SESSION[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> $session */
        $session = $_SESSION ?? [];
        return $session;
    }

    public function clear(): void
    {
        // Use direct $_SESSION clearing since Odan interface doesn't have clear() method
        $_SESSION = [];
    }

    public function flash(string $key, mixed $value): void
    {
        // Use direct flash storage since getFlash() method may not exist
        if (!isset($_SESSION['__flash']) || !is_array($_SESSION['__flash'])) {
            $_SESSION['__flash'] = [];
        }
        /** @var array<string, mixed> $flashData */
        $flashData = $_SESSION['__flash'];
        $flashData[$key] = $value;
        $_SESSION['__flash'] = $flashData;
    }

    public function getFlash(): \Odan\Session\FlashInterface
    {
        // This method requires Odan Flash interface - implement basic version
        throw new \RuntimeException('Flash interface not available. Use flash() method instead.');
    }

    public function getFlashMessage(string $key, mixed $default = null): mixed
    {
        $flashData = $_SESSION['__flash'] ?? [];
        if (!is_array($flashData)) {
            return $default;
        }
        /** @var array<string, mixed> $typedFlashData */
        $typedFlashData = $flashData;
        return $typedFlashData[$key] ?? $default;
    }

    public function hasFlash(string $key): bool
    {
        $flashData = $_SESSION['__flash'] ?? [];
        if (!is_array($flashData)) {
            return false;
        }
        /** @var array<string, mixed> $typedFlashData */
        $typedFlashData = $flashData;
        return isset($typedFlashData[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFlashBag(): array
    {
        // Get all flash data from session
        $flashData = $_SESSION['__flash'] ?? [];
        /** @var array<string, mixed> $typedFlashData */
        $typedFlashData = is_array($flashData) ? $flashData : [];
        return $typedFlashData;
    }

    // === Delegated methods from Odan\Session\SessionInterface ===

    public function set(string $key, mixed $value): void
    {
        // Use direct $_SESSION access if Odan methods not available
        if (method_exists($this->sessionManager, 'set')) {
            $this->sessionManager->set($key, $value);
        } else {
            $_SESSION[$key] = $value;
        }

        // Debug logging
        error_log("SessionManager::set() - key: {$key}, value: " . json_encode($value) . ", session_id: " . $this->getId());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // Use direct $_SESSION access if Odan methods not available
        if (method_exists($this->sessionManager, 'get')) {
            $value = $this->sessionManager->get($key, $default);
        } else {
            $value = $_SESSION[$key] ?? $default;
        }

        // Debug logging
        error_log("SessionManager::get() - key: {$key}, value: " . json_encode($value) . ", session_id: " . $this->getId());

        return $value;
    }

    public function remove(string $key): void
    {
        // Use direct $_SESSION access if Odan methods not available
        if (method_exists($this->sessionManager, 'remove')) {
            $this->sessionManager->remove($key);
        } else {
            unset($_SESSION[$key]);
        }
    }

    public function setFlash(string $key, mixed $value): void
    {
        // Use our flash implementation
        $this->flash($key, $value);
    }

    // Note: getFlash is already implemented above with different signature

    /**
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->all());
    }

    public function count(): int
    {
        return count($this->all());
    }

    // === MISSING INTERFACE METHODS ===

    public function setName(string $name): void
    {
        session_name($name);
    }

    public function getSavePath(): string
    {
        return session_save_path() ?: '';
    }

    public function setSavePath(string $path): void
    {
        session_save_path($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCookieParams(): array
    {
        return session_get_cookie_params();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function setCookieParams(array $params): void
    {
        // Validate and convert params to expected format
        $validParams = [];

        if (isset($params['lifetime']) && is_int($params['lifetime'])) {
            $validParams['lifetime'] = $params['lifetime'];
        }
        if (isset($params['path']) && is_string($params['path'])) {
            $validParams['path'] = $params['path'];
        }
        if (array_key_exists('domain', $params)) {
            $domainValue = $params['domain'];
            if (is_string($domainValue) || $domainValue === null) {
                $validParams['domain'] = $domainValue;
            }
        }
        if (isset($params['secure']) && is_bool($params['secure'])) {
            $validParams['secure'] = $params['secure'];
        }
        if (isset($params['httponly']) && is_bool($params['httponly'])) {
            $validParams['httponly'] = $params['httponly'];
        }
        if (isset($params['samesite']) && is_string($params['samesite'])) {
            // Validate samesite values
            $allowedSamesite = ['Lax', 'lax', 'None', 'none', 'Strict', 'strict'];
            if (in_array($params['samesite'], $allowedSamesite, true)) {
                $validParams['samesite'] = $params['samesite'];
            }
        }

        session_set_cookie_params($validParams);
    }

    public function getStatus(): int
    {
        return session_status();
    }

    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    // === ODAN SESSION INTERFACE METHODS ===

    /**
     * Set multiple values at once.
     *
     * @param array<string, mixed> $values
     */
    public function setValues(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Delete session value (alias for remove).
     */
    public function delete(string $key): void
    {
        $this->remove($key);
    }
}
