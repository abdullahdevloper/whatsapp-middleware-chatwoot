<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class StoreApiService
{
    public function getProductsByCategoryId(int $categoryId): array
    {
        $baseUrl = rtrim((string) env('STORE_API_BASE_URL', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('STORE_API_BASE_URL is not configured.');
        }

        $url = $baseUrl . '/api/v1/categories/product/' . $categoryId;
        $response = Http::get($url)->throw();

        $data = $response->json();
        return is_array($data) ? $data : [];
    }

    public function formatProductsMessage(string $category, array $products): string
    {
        if (empty($products)) {
            return 'لا توجد منتجات حالياً.';
        }

        $lines = ['🛍 المنتجات المتوفرة:'];

        foreach ($products as $product) {
            $name = $product['name'] ?? '';
            $price = $product['price'] ?? '';
            $currency = $product['currency'] ?? '';

            if ($name === '') {
                continue;
            }

            $lines[] = '';
            $lines[] = $name;

            if ($price !== '') {
                $lines[] = trim($price . ' ' . $currency);
            }
        }

        
        return implode("\n", $lines);
    }

    public function normalizeProducts(array $products, int $limit = 10): array
    {
        $normalized = [];

        
        foreach ($products as $product) {
            if (count($normalized) >= $limit) {
                break;
            }

            $name = $product['name'] ?? null;
            if ($name === null) {
                continue;
            }
            $id = $product['id'] ?? null;
            if ($id === null) {
                $id = substr(sha1((string) $name), 0, 12);
            }

            $normalized[] = [
                'id' => (string) $id,
                'name' => (string) $name,
                'price' => $product['price'] ?? null,
                'currency' => $product['currency'] ?? null,
                'description' => $this->cleanDescription($product['description'] ?? null),
                'image_url' => $product['image_url'] ?? null,
            ];
        }

        return $normalized;
    }

    private function cleanDescription(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }

        $decoded = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags($decoded);
        $collapsed = preg_replace('/\s+/', ' ', $stripped);

        return $collapsed !== null ? Str::of($collapsed)->trim()->limit(900, '...')->toString() : null;
    }
}
