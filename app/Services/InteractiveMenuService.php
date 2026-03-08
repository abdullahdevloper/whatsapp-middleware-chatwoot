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

    public function buildInteractiveListPayload(): array
    {
        return [
            'content' => 'اختر القسم الذي تريد تصفحه',
            'content_type' => 'text',
            'message_type' => 'outgoing',
            'private' => false,
            'content_attributes' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => 'متجر طيب الأتراك',
                ],
                'body' => [
                    'text' => 'اختر القسم الذي تريد تصفحه',
                ],
                'action' => [
                    'button' => 'عرض القائمة',
                    'sections' => [
                        [
                            'title' => 'الأقسام الرئيسية',
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
            'قائمة العطور',
            'اختر القسم الذي تريد تصفحه',
            'عرض القائمة',
            'الأقسام',
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
            'قائمة البخور واللمسات',
            'اختر القسم الذي تريد تصفحه',
            'عرض القائمة',
            'الأقسام',
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
            'اختر القسم الذي تريد تصفحه',
            'عرض القائمة',
            'الأقسام',
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
            'اختر القسم الذي تريد تصفحه',
            'عرض القائمة',
            'الأقسام',
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
            'اختر القسم الذي تريد تصفحه',
            'عرض القائمة',
            'الأقسام',
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
