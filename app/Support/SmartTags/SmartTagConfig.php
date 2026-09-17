<?php

namespace App\Support\SmartTags;

/**
 * The one reader of config/smart_tags.php and config/smart_tag_sources.php.
 *
 * WHY NOT JUST `config()`. The Smart Tag taxonomy is consulted by pure classes —
 * the selection policy, the derivers, the description parser — that are unit
 * tested without a booted application, exactly as LandlordScreeningPolicy is.
 * `config()` raises there. So: use the container when one is bound, read the
 * file when it is not. The same pattern, for the same reason, as
 * {@see \App\Support\OfferListing\LandlordScreeningPolicy::conf()}.
 *
 * A test asserts this class is the only reader of both files, so a Blade view or
 * a controller cannot grow its own idea of what a tag means.
 */
final class SmartTagConfig
{
    public const TAXONOMY = 'smart_tags';

    public const SOURCES = 'smart_tag_sources';

    /** @var array<string, array<string, mixed>> */
    private static array $fileConfig = [];

    /**
     * @return array<string, mixed>
     */
    public static function taxonomy(): array
    {
        return self::load(self::TAXONOMY);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sources(): array
    {
        return self::load(self::SOURCES);
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
