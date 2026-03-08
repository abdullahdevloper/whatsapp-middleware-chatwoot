<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
}
