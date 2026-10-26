<?php

declare(strict_types=1);

namespace WTSBill\Core;

/**
 * Zero-dependency Secure Logger for WTSBill ERP.
 * Automatically sanitizes and redacts secrets, credentials, and sensitive tokens.
 */
class Logger
{
    private static ?string $logDirectory = null;

    private static array $redactKeys = [
        'password',
        'passwd',
        'db_password',
        'db_pass',
        'secret',
        'app_key',
        'jwt_secret',
        'authorization',
        'bearer',
        'token',
        'credit_card',
        'card_number',
        'cvv',
    ];

    public static function setLogDirectory(string $directory): void
    {
        self::$logDirectory = rtrim(str_replace('\\', '/', $directory), '/');
    }

    public static function getLogDirectory(): string
    {
        if (self::$logDirectory === null) {
            self::$logDirectory = dirname(__DIR__, 2) . '/storage/logs';
        }
        return self::$logDirectory;
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $logDir = self::getLogDirectory();
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $date = date('Y-m-d');
        $filePath = "{$logDir}/wtsbill-{$date}.log";

        $sanitizedMessage = self::redactString($message);
        $sanitizedContext = self::redactArray($context);

        $contextStr = !empty($sanitizedContext) ? ' ' . json_encode($sanitizedContext, JSON_UNESCAPED_SLASHES) : '';
        $timestamp = date('Y-m-d H:i:s');
        $logLine = sprintf("[%s] [%s] %s%s\n", $timestamp, strtoupper($level), $sanitizedMessage, $contextStr);

        file_put_contents($filePath, $logLine, FILE_APPEND | LOCK_EX);
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::log('EMERGENCY', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::log('ALERT', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('CRITICAL', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::log('NOTICE', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    public static function redactString(string $input): string
    {
        foreach (self::$redactKeys as $key) {
            $input = preg_replace("/({$key}\s*[:=]\s*)(['\"]?[^'\"\\s,;]+['\"]?)/i", '$1[REDACTED]', $input);
        }
        return $input;
    }

    public static function redactArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);
            $shouldRedact = false;
            foreach (self::$redactKeys as $redactKey) {
                if (str_contains($keyLower, $redactKey)) {
                    $shouldRedact = true;
                    break;
                }
            }

            if ($shouldRedact) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::redactArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Logger', false)) {
    class_alias(Logger::class, 'App\Core\Logger');
}
