<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\Helper;

/**
 * Resolves hashed asset filenames from the Vite manifest.
 */
class CacheBust
{
    private static ?array $manifest = null;

    /**
     * @param string $name Original path (e.g. 'css/modularity-simpleview-events.css')
     * @return string|false Hashed filename relative to assets/dist, or false
     */
    public static function name(string $name): string|false
    {
        $manifest = self::getManifest();

        if ($manifest && isset($manifest[$name])) {
            return $manifest[$name];
        }

        return false;
    }

    private static function getManifest(): ?array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $manifestPath = MODULARITYSIMPLEVIEWEVENTS_PATH . 'assets/dist/manifest.json';

        if (file_exists($manifestPath)) {
            $manifestContent = file_get_contents($manifestPath);
            self::$manifest = json_decode($manifestContent, true);
            return self::$manifest;
        }

        return null;
    }
}
