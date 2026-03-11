<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageProxyService
{
    public function resolveImageUrl(string $url, string $fallbackUrl): string
    {
        $result = $this->resolveImage($url, $fallbackUrl);
        return $result['url'];
    }

    public function resolveImage(string $url, string $fallbackUrl): array
    {
        $url = trim($url);
        if ($url === '') {
            return [
                'url' => $fallbackUrl,
                'status' => 'fallback',
                'reason' => 'empty_url',
                'is_webp' => false,
            ];
        }

        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if ($extension !== 'webp') {
            return [
                'url' => $url,
                'status' => 'ok',
                'reason' => 'original',
                'is_webp' => false,
            ];
        }

        $allowedHosts = $this->allowedHosts();
        if (!empty($allowedHosts)) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || !in_array($host, $allowedHosts, true)) {
                Log::warning('image_proxy_host_blocked', [
                    'host' => $host,
                    'url' => $url,
                ]);
                return [
                    'url' => $fallbackUrl,
                    'status' => 'fallback',
                    'reason' => 'host_blocked',
                    'is_webp' => true,
                ];
            }
        }

        $hash = sha1($url);
        $relativePath = 'product-images/' . $hash . '.jpg';

        if (Storage::disk('public')->exists($relativePath)) {
            Log::info('image_proxy_cache_hit', [
                'url' => $url,
                'path' => $relativePath,
            ]);
            $this->maybeCleanupCache();
            return [
                'url' => $this->publicUrl($relativePath),
                'status' => 'ok',
                'reason' => 'cache',
                'is_webp' => true,
            ];
        }

        if (!function_exists('imagecreatefromwebp')) {
            Log::warning('image_proxy_webp_unsupported', [
                'url' => $url,
            ]);
            return [
                'url' => $fallbackUrl,
                'status' => 'fallback',
                'reason' => 'webp_unsupported',
                'is_webp' => true,
            ];
        }

        try {
            $response = Http::timeout(15)->get($url);
            if (!$response->ok()) {
                Log::warning('image_proxy_download_failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return [
                    'url' => $fallbackUrl,
                    'status' => 'fallback',
                    'reason' => 'download_failed',
                    'is_webp' => true,
                ];
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'webp_');
            if ($tmpFile === false) {
                Log::warning('image_proxy_temp_failed', [
                    'url' => $url,
                ]);
                return [
                    'url' => $fallbackUrl,
                    'status' => 'fallback',
                    'reason' => 'temp_failed',
                    'is_webp' => true,
                ];
            }

            file_put_contents($tmpFile, $response->body());

            $image = @imagecreatefromwebp($tmpFile);
            if ($image === false) {
                @unlink($tmpFile);
                Log::warning('image_proxy_decode_failed', [
                    'url' => $url,
                ]);
                return [
                    'url' => $fallbackUrl,
                    'status' => 'fallback',
                    'reason' => 'decode_failed',
                    'is_webp' => true,
                ];
            }

            Storage::disk('public')->makeDirectory('product-images');
            $destination = Storage::disk('public')->path($relativePath);
            imagejpeg($image, $destination, 85);
            imagedestroy($image);
            @unlink($tmpFile);

            Log::info('image_proxy_converted', [
                'url' => $url,
                'path' => $relativePath,
            ]);

            $this->maybeCleanupCache();
            return [
                'url' => $this->publicUrl($relativePath),
                'status' => 'ok',
                'reason' => 'converted',
                'is_webp' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning('image_proxy_exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return [
                'url' => $fallbackUrl,
                'status' => 'fallback',
                'reason' => 'exception',
                'is_webp' => true,
            ];
        }
    }

    private function publicUrl(string $relativePath): string
    {
        return asset('storage/' . ltrim($relativePath, '/'));
    }

    private function allowedHosts(): array
    {
        $raw = (string) env('STORE_IMAGE_HOSTS', '');
        $hosts = array_filter(array_map('trim', explode(',', $raw)));
        return array_map('strtolower', $hosts);
    }

    private function maybeCleanupCache(): void
    {
        $chance = (int) env('IMAGE_PROXY_CLEANUP_CHANCE', 100);
        if ($chance <= 0) {
            return;
        }

        if (random_int(1, $chance) !== 1) {
            return;
        }

        $ttlDays = (int) env('IMAGE_PROXY_TTL_DAYS', 7);
        $cutoff = now()->subDays(max($ttlDays, 1))->getTimestamp();

        $files = Storage::disk('public')->files('product-images');
        $deleted = 0;

        foreach ($files as $file) {
            $modified = Storage::disk('public')->lastModified($file);
            if ($modified < $cutoff) {
                Storage::disk('public')->delete($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            Log::info('image_proxy_cache_cleanup', [
                'deleted' => $deleted,
                'ttl_days' => $ttlDays,
            ]);
        }
    }
}
