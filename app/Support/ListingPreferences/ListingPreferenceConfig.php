<?php

namespace App\Support\ListingPreferences;

/**
 * The one reader of config/listing_preference_reasons.php.
 *
 * WHY NOT JUST `config()`. The reason catalog and the reason policy are pure
 * classes, unit tested without a booted application — exactly as
 * SmartTagConfig, LandlordScreeningPolicy and SmartTagSelectionPolicy are, and
 * for the same reason: `config()` raises there, and the symptom of that failure
 * arrives several frames away as an empty catalog rather than as a missing file.
 *
 * So: use the container when one is bound, read the file when it is not. This
 * is a deliberate copy of {@see \App\Support\SmartTags\SmartTagConfig::load()}
 * rather than a shared base class — the two configs are independent, and
 * coupling their readers would mean a change to one subsystem's loading rules
 * silently changed the other's.
 *
 * A test asserts this class is the only reader of that config file.
 */
final class ListingPreferenceConfig
{
    public const REASONS = 'listing_preference_reasons';

    /** @var array<string, array<string, mixed>> */
    private static array $fileConfig = [];

    /**
     * @return array<string, mixed>
     */
    public static function reasons(): array
    {
        return self::load(self::REASONS);
    }

    /** Test hook: forget any file-loaded copy. */
    public static function flush(): void
    {
        self::$fileConfig = [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(string $name): array
    {
        if (function_exists('app')) {
            try {
                $container = app();
                if (is_object($container) && method_exists($container, 'bound') && $container->bound('config')) {
                    $fromContainer = config($name);
                    if (is_array($fromContainer) && $fromContainer !== []) {
                        return $fromContainer;
                    }
                }
            } catch (\Throwable) {
                // Fall through to the file.
            }
        }

        if (! array_key_exists($name, self::$fileConfig)) {
            $path = __DIR__ . '/../../../config/' . $name . '.php';
            $loaded = is_file($path) ? require $path : [];
            self::$fileConfig[$name] = is_array($loaded) ? $loaded : [];
        }

        return self::$fileConfig[$name];
    }
}
