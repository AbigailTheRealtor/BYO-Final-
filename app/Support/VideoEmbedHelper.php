<?php

namespace App\Support;

class VideoEmbedHelper
{
    public static function getEmbedUrl(string $url): ?string
    {
        $url = trim($url);
        if (empty($url)) {
            return null;
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return null;
        }

        $host = strtolower($parsed['host']);

        // YouTube
        if ($host === 'www.youtube.com' || $host === 'youtube.com') {
            if (preg_match('/\/(?:watch|embed|shorts)/', $parsed['path'] ?? '')) {
                if (preg_match('/(?:watch\?(?:[^&]*&)*v=|embed\/|shorts\/)([A-Za-z0-9_\-]{11})/', $url, $matches)) {
                    return 'https://www.youtube.com/embed/' . $matches[1];
                }
            }
            return null;
        }

        if ($host === 'youtu.be') {
            $path = ltrim($parsed['path'] ?? '', '/');
            if (preg_match('/^([A-Za-z0-9_\-]{11})/', $path, $matches)) {
                return 'https://www.youtube.com/embed/' . $matches[1];
            }
            return null;
        }

        // Vimeo
        if ($host === 'vimeo.com' || $host === 'www.vimeo.com') {
            if (preg_match('/^\/(\d+)(?:\/|$)/', $parsed['path'] ?? '', $matches)) {
                return 'https://player.vimeo.com/video/' . $matches[1];
            }
            return null;
        }

        return null;
    }
}
