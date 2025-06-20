<?php

declare(strict_types=1);

namespace ResponsiveSk\Slim4Session;

use Odan\Session\PhpSession;
use Odan\Session\SessionManagerInterface;

/**
 * Session Factory
 * 
 * Factory for creating session instances with proper configuration.
 */
final class SessionFactory
{
    /**
     * Create session manager with default configuration.
     * 
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): SessionInterface
    {
        $defaultConfig = [
            'name' => 'app_session',
            'cache_expire' => 180,
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']),
            'cookie_samesite' => 'Lax',
            'use_cookies' => true,
            'use_only_cookies' => true,
            'use_strict_mode' => true,
        ];

        $config = array_merge($defaultConfig, $config);

        // Configure PHP session settings
        foreach ($config as $key => $value) {
            if (str_starts_with($key, 'cookie_') || in_array($key, ['name', 'cache_expire', 'use_cookies', 'use_only_cookies', 'use_strict_mode'])) {
                // Safe casting for ini_set
                if (is_string($value)) {
                    $valueString = $value;
                } elseif (is_bool($value)) {
                    $valueString = $value ? '1' : '0';
                } elseif (is_int($value)) {
                    $valueString = (string) $value;
                } else {
                    $valueString = '';
                }
                ini_set("session.{$key}", $valueString);
            }
        }

        // Create Odan session manager
        $odanSession = new PhpSession();

        // Wrap in our extended session manager
        return new SessionManager($odanSession);
    }

    /**
     * Create session manager with custom Odan session.
     */
    public static function createWithOdanSession(SessionManagerInterface $odanSession): SessionInterface
    {
        return new SessionManager($odanSession);
    }

    /**
     * Create session manager for testing.
     */
    public static function createForTesting(): SessionInterface
    {
        $config = [
            'name' => 'test_session',
            'cookie_secure' => false,
            'use_cookies' => false,
        ];

        return self::create($config);
    }

    /**
     * Create session manager for production.
     * 
     * @param array<string, mixed> $config
     */
    public static function createForProduction(array $config = []): SessionInterface
    {
        $productionConfig = [
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'cache_expire' => 180,
        ];

        $config = array_merge($productionConfig, $config);

        return self::create($config);
    }

    /**
     * Create session manager for development.
     * 
     * @param array<string, mixed> $config
     */
    public static function createForDevelopment(array $config = []): SessionInterface
    {
        $developmentConfig = [
            'cookie_secure' => false,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => false,
        ];

        $config = array_merge($developmentConfig, $config);

        return self::create($config);
    }
}
