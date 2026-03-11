<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InteractiveMenuService
{
    public function __construct(
        private readonly WhatsAppService $whatsApp,
        private readonly ImageProxyService $imageProxy
    )
    {
    }

    public function sendMainMenu(string $phoneNumberId, string $phoneNumber): void
    {
        $payload = $this->buildInteractiveListPayload();
        $this->whatsApp->sendInteractiveList(
            $phoneNumberId,
            $phoneNumber,
            $payload['content_attributes']['header']['text'],
            $payload['content_attributes']['body']['text'],
            $payload['content_attributes']['action']['button'],
            $payload['content_attributes']['action']['sections']
        );
        Log::info('interactive_menu_sent', [
            'phone_number' => $phoneNumber,
        ]);
    }

    public function sendTextMessage(string $phoneNumberId, string $phoneNumber, string $text): void
    {
        $this->whatsApp->sendText($phoneNumberId, $phoneNumber, $text);
    }

    public function sendProductListMenu(
        string $phoneNumberId,
        string $phoneNumber,
        string $title,
        string $body,
        string $sectionTitle,
        string $buttonText,
        array $products
    ): void {
        $rows = [];

        foreach ($products as $product) {
            $name = (string) ($product['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $price = $product['price'] ?? null;
            $currency = $product['currency'] ?? null;
            $description = $price !== null ? trim($price . ' ' . $currency) : '';
            $rows[] = [
                'id' => 'product:' . $product['id'],
                'title' => mb_substr($name, 0, 24),
                'description' => $description !== '' ? mb_substr($description, 0, 72) : null,
            ];
        }

        $rows = array_values(array_filter($rows, fn ($row) => !empty($row['title'])));
        $rows = array_slice($rows, 0, 9);
        $rows[] = [
            'id' => 'back_previous',
            'title' => 'الرجوع للقائمة السابقة',
        ];

        $payload = $this->buildMenuPayload(
            mb_substr($title, 0, 24),
            $body,
            mb_substr($buttonText, 0, 20),
            mb_substr($sectionTitle, 0, 24),
            $rows
        );

        $this->sendPayload($phoneNumberId, $phoneNumber, $payload);
    }

    public function sendProductDetailsWithBuyButton(
        string $phoneNumberId,
        string $phoneNumber,
        array $product,
        ?string $categoryKey = null,
        ?string $menuLabel = null
    ): void {
        $fallbackImageUrl = 'https://tybalatrak.com/public/haybat_al_salateen_bundle_offer.png';
        $name = $product['name'] ?? '';
        $description = $this->formatDescription($product['description'] ?? '');
        $price = $product['price'] ?? '';
        $currency = $product['currency'] ?? '';
        $rawImageUrl = $product['image_url'] ?? '';
        $imageResult = $this->imageProxy->resolveImage($rawImageUrl, $fallbackImageUrl);
        $imageUrl = $imageResult['url'];

        $priceLine = $price !== '' ? trim($price . ' ' . $currency) : null;
        if (!empty($imageUrl)) {
            Log::info('whatsapp_image_final', [
                'phone_number' => $phoneNumber,
                'raw_image_url' => $rawImageUrl,
                'image_url' => $imageUrl,
                'status' => $imageResult['status'] ?? null,
                'reason' => $imageResult['reason'] ?? null,
            ]);
            $captionLines = array_filter([
                $name,
                $priceLine,
            ]);
            $caption = implode("\n\n", $captionLines);
            $this->whatsApp->sendImage($phoneNumberId, $phoneNumber, $imageUrl, $caption);
            $this->sleepAfterImage();
        }

        $this->notifyImageConversionFailure($phoneNumberId, $rawImageUrl, $imageResult);

        if ($description !== '') {
            $formattedDescription = $this->formatProductDescription($description);
            $this->sendTextMessage($phoneNumberId, $phoneNumber, $formattedDescription);
        }

        $bodyText = $this->productActionBody($categoryKey);
        $buttonTitle = $this->productBuyButtonTitle($name);
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'buy:' . ($product['id'] ?? ''),
                    'title' => $buttonTitle,
                ],
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'support_request',
                    'title' => 'تواصل مع الدعم',
                ],
            ],
        ];

        $this->whatsApp->sendInteractiveButtons($phoneNumberId, $phoneNumber, $bodyText, $buttons);
    }

    private function formatDescription(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $withBreaks = preg_replace('/<\s*br\s*\/?>/i', "\n", $decoded);
        $withBreaks = preg_replace('/<\s*\/p\s*>\s*<\s*p\s*>/i', "\n\n", $withBreaks ?? $decoded);
        $withBreaks = preg_replace('/<\s*p\s*>/i', '', $withBreaks ?? $decoded);
        $withBreaks = preg_replace('/<\s*\/p\s*>/i', "\n", $withBreaks ?? $decoded);

        $stripped = strip_tags($withBreaks ?? $decoded);
        $stripped = preg_replace("/\\r\\n|\\r/u", "\n", $stripped ?? '');
        $stripped = preg_replace("/[ \\t]+/u", ' ', $stripped ?? '');
        $stripped = preg_replace("/\\n{3,}/u", "\n\n", $stripped ?? '');

        if ($stripped === null) {
            return '';
        }

        $labels = [
            'التصنيف:',
            'الحجم:',
            'الافتتاحية:',
            'القلب:',
            'القاعدة:',
            'الفئة المستهدفة:',
            'الاستخدام:',
            'أفضل موسم:',
            '🧭 الاستخدام والفئة:',
            '🌿 التركيبة الهرمية:',
        ];
        $labelPattern = implode('|', array_map(fn ($label) => preg_quote($label, '/'), $labels));
        $stripped = preg_replace("/(\\S)($labelPattern)/u", "$1\n$2", $stripped);
        $stripped = preg_replace("/،(\\S)/u", "، $1", $stripped);

        $result = trim($stripped);
        return $result;
    }

    private function formatProductDescription(string $description): string
    {
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/u', $description) ?: []));
        $lines = array_values($lines);

        $out = [];
        $out[] = '📝 تفاصيل المنتج';

        foreach ($lines as $line) {
            if ($line === '') {
                $out[] = '';
                continue;
            }
            $wrapped = $this->wrapLinePreserveWords($line, 55);
            if (empty($wrapped)) {
                continue;
            }

            foreach ($wrapped as $index => $wrappedLine) {
                $out[] = ($index === 0 ? '• ' : '  ') . $wrappedLine;
            }
        }

        return implode("\n", $out);
    }

    private function wrapLinePreserveWords(string $text, int $maxChars): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function productActionBody(?string $categoryKey): string
    {
        return match ($categoryKey) {
            'perfume_new', 'perfume_best', 'perfume_all', 'perfume_men', 'perfume_women', 'perfume_youth' => 'اطلب عطرك بثقة',
            'bakhoor_bakhoor' => 'اطلب بخورك بثقة',
            'bakhoor_touch' => 'اطلب لمستك بثقة',
            'bakhoor_makhmaria' => 'اطلب مخمريتك بثقة',
            default => 'اطلب المنتج بثقة',
        };
    }

    private function productBuyButtonTitle(string $productName): string
    {
        $base = 'شراء ' . trim($productName);
        if (mb_strlen($base) <= 20) {
            return $base;
        }
        return mb_substr($base, 0, 19) . '…';
    }

    private function notifyImageConversionFailure(string $phoneNumberId, string $rawImageUrl, array $imageResult): void
    {
        if (empty($rawImageUrl)) {
            return;
        }

        $isWebp = (bool) ($imageResult['is_webp'] ?? false);
        $status = (string) ($imageResult['status'] ?? '');
        if (!$isWebp || $status === 'ok') {
            return;
        }

        $supportPhone = (string) env('SUPPORT_TEAM_PHONE', '');
        if ($supportPhone === '') {
            return;
        }

        $hash = sha1($rawImageUrl);
        $cacheKey = 'image_proxy_alert:' . $hash;
        if (!Cache::add($cacheKey, true, 60 * 60 * 24)) {
            return;
        }

        $reason = (string) ($imageResult['reason'] ?? 'unknown');
        $message = "تنبيه: فشل تحويل صورة منتج من webp\n"
            . "السبب: {$reason}\n"
            . "الرابط: {$rawImageUrl}";

        $this->whatsApp->sendText($phoneNumberId, $supportPhone, $message);
    }

    private function sleepAfterImage(): void
    {
        $delayMs = (int) env('IMAGE_SEND_DELAY_MS', 500);
        if ($delayMs <= 0) {
            return;
        }

        usleep($delayMs * 1000);
    }

    public function buildInteractiveListPayload(): array
    {
        return [
            'content' => 'أهلاً بك، خلّنا نختار لك اللي يناسب ذوقك',
            'content_type' => 'text',
            'message_type' => 'outgoing',
            'private' => false,
            'content_attributes' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => 'طيّب الأتراك - مرحباً بك',
                ],
                'body' => [
                    'text' => 'اختر القسم، ونساعدك توصل لأفضل الخيارات بسرعة',
                ],
                'action' => [
                    'button' => 'عرض الأقسام',
                    'sections' => [
                        [
                            'title' => 'الأقسام',
                            'rows' => [
                                [
                                    'id' => 'menu_perfumes',
                                    'title' => 'العطور',
                                ],
                                [
                                    'id' => 'menu_bakhoor',
                                    'title' => 'البخور واللمسات',
                                ],
                                [
                                    'id' => 'menu_shipping',
                                    'title' => 'الشحن والسياسات',
                                ],
                                [
                                    'id' => 'help_faq',
                                    'title' => 'الأسئلة الشائعة',
                                ],
                                [
                                    'id' => 'help_payment',
                                    'title' => 'طرق الدفع والتحويل',
                                ],
                                [
                                    'id' => 'help_hours',
                                    'title' => 'أوقات الدوام',
                                ],
                                [
                                    'id' => 'help_contact',
                                    'title' => 'التواصل مع خدمة العملاء',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function sendPerfumeMenu(string $phoneNumberId, string $phoneNumber): void
    {
        $payload = $this->buildMenuPayload(
            'عطور طيب الأتراك',
            'اختر عطرك بثقة واترك الباقي علينا بفضل الله',
            'عرض العطور',
            'أنواع العطور',
            [
                ['id' => 'perfume_new', 'title' => 'أحدث الإصدارات'],
                ['id' => 'perfume_best', 'title' => 'الأكثر مبيعاً'],
                ['id' => 'perfume_all', 'title' => 'جميع العطور'],
                ['id' => 'perfume_men', 'title' => 'عطور رجالية'],
                ['id' => 'perfume_women', 'title' => 'عطور نسائية'],
                ['id' => 'perfume_youth', 'title' => 'عطور شبابية'],
                ['id' => 'back_main', 'title' => 'الرجوع للقائمة الرئيسية'],
            ]
        );

        $this->sendPayload($phoneNumberId, $phoneNumber, $payload);
    }

    public function sendBakhoorMenu(string $phoneNumberId, string $phoneNumber): void
    {
        $payload = $this->buildMenuPayload(
            'البخور واللمسات',
            'اختر ما يناسب ذوقك اليوم',
            'عرض البخور',
            'الخيارات',
            [
                ['id' => 'bakhoor_bakhoor', 'title' => 'البخور'],
                ['id' => 'bakhoor_makhmaria', 'title' => 'المخمريات'],
                ['id' => 'bakhoor_touch', 'title' => 'اللمسات العطرية'],
                ['id' => 'back_main', 'title' => 'الرجوع للقائمة الرئيسية'],
            ]
        );

        $this->sendPayload($phoneNumberId, $phoneNumber, $payload);
    }

    public function sendShippingMenu(string $phoneNumberId, string $phoneNumber): void
    {
        $payload = $this->buildMenuPayload(
            'الشحن والسياسات',
            'كل التفاصيل المهمة قبل الطلب',
            'عرض التفاصيل',
            'المعلومات',
            [
                ['id' => 'shipping_yemen', 'title' => 'الشحن داخل اليمن'],
                ['id' => 'shipping_gulf', 'title' => 'الشحن إلى دول الخليج'],
                ['id' => 'shipping_policy', 'title' => 'سياسة الشحن'],
                ['id' => 'return_policy', 'title' => 'سياسة الاسترجاع'],
                ['id' => 'back_main', 'title' => 'الرجوع للقائمة الرئيسية'],
            ]
        );

        $this->sendPayload($phoneNumberId, $phoneNumber, $payload);
    }

    public function sendHelpMenu(string $phoneNumberId, string $phoneNumber): void
    {
        $payload = $this->buildMenuPayload(
            'المساعدة',
            'نرد على كل استفساراتك بسرعة',
            'عرض المساعدة',
            'المساعدة',
            [
                ['id' => 'help_faq', 'title' => 'الأسئلة الشائعة'],
                ['id' => 'help_payment', 'title' => 'طرق الدفع والتحويل'],
                ['id' => 'help_hours', 'title' => 'أوقات الدوام'],
                ['id' => 'help_contact', 'title' => 'التواصل مع خدمة العملاء'],
                ['id' => 'back_main', 'title' => 'الرجوع للقائمة الرئيسية'],
            ]
        );

        $this->sendPayload($phoneNumberId, $phoneNumber, $payload);
    }

    public function sendOrderMenu(string $phoneNumberId, string $phoneNumber): void
    {
        $payload = $this->buildMenuPayload(
            'الطلب',
            'ابدأ طلبك بسهولة من هنا',
            'خيارات الطلب',
            'خيارات الطلب',
            [
                ['id' => 'order_form', 'title' => 'نموذج الطلب'],
                ['id' => 'order_browse', 'title' => 'تصفح المنتجات والتسوق'],
                ['id' => 'back_main', 'title' => 'الرجوع للقائمة الرئيسية'],
            ]
        );

        $this->sendPayload($phoneNumberId, $phoneNumber, $payload);
    }

    private function buildMenuPayload(
        string $header,
        string $body,
        string $buttonText,
        string $sectionTitle,
        array $rows
    ): array {
        return [
            'content' => $body,
            'content_type' => 'text',
            'message_type' => 'outgoing',
            'private' => false,
            'content_attributes' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => $header,
                ],
                'body' => [
                    'text' => $body,
                ],
                'action' => [
                    'button' => $buttonText,
                    'sections' => [
                        [
                            'title' => $sectionTitle,
                            'rows' => $rows,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function sendPayload(string $phoneNumberId, string $phoneNumber, array $payload): void
    {
        $this->whatsApp->sendInteractiveList(
            $phoneNumberId,
            $phoneNumber,
            $payload['content_attributes']['header']['text'],
            $payload['content_attributes']['body']['text'],
            $payload['content_attributes']['action']['button'],
            $payload['content_attributes']['action']['sections']
        );
    }
}
