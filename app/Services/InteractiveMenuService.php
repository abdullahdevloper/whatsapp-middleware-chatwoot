<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class InteractiveMenuService
{
    public function __construct(private readonly WhatsAppService $whatsApp)
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

        $payload = $this->buildMenuPayload(
            mb_substr($title, 0, 24),
            $body,
            'عرض المنتجات',
            $sectionTitle,
            $rows
        );

        $this->sendPayload($phoneNumberId, $phoneNumber, $payload);
    }

    public function sendProductDetailsWithBuyButton(
        string $phoneNumberId,
        string $phoneNumber,
        array $product
    ): void {
        $fallbackImageUrl = 'https://tybalatrak.com/public/haybat_al_salateen_bundle_offer.png';
        $name = $product['name'] ?? '';
        $description = $this->formatDescription($product['description'] ?? '');
        $price = $product['price'] ?? '';
        $currency = $product['currency'] ?? '';
        $imageUrl = $fallbackImageUrl;

        $priceLine = $price !== '' ? trim($price . ' ' . $currency) : null;
        if (!empty($imageUrl)) {
            $captionLines = array_filter([
                $name,
                $priceLine,
            ]);
            $caption = implode("\n\n", $captionLines);
            $this->whatsApp->sendImage($phoneNumberId, $phoneNumber, $imageUrl, $caption);
        }

        if ($description !== '') {
            $formattedDescription = "📝 وصف المنتج\n\n" . $description;
            $this->sendTextMessage($phoneNumberId, $phoneNumber, $formattedDescription);
        }

        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'buy:' . ($product['id'] ?? ''),
                    'title' => 'شراء المنتج',
                ],
            ],
        ];

        $this->whatsApp->sendInteractiveButtons($phoneNumberId, $phoneNumber, 'إذا ناسبك المنتج تقدر تطلبه مباشرة بالزر التالي', $buttons);
    }

    private function formatDescription(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags($decoded);
        $collapsed = preg_replace('/\s+/', ' ', $stripped);
        if ($collapsed === null) {
            return '';
        }

        $withBreaks = preg_replace('/\s*([،؛:])\s*/u', "$1 ", $collapsed);
        $withBreaks = preg_replace('/\s*(\.)\s*/u', "$1\n", $withBreaks ?? $collapsed);
        $withBreaks = preg_replace('/\s*-\s*/u', "\n- ", $withBreaks ?? $collapsed);

        $result = trim($withBreaks ?? $collapsed);
        return $result;
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
            'اختر النوع المناسب لك، ونقترح لك الأفضل',
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
