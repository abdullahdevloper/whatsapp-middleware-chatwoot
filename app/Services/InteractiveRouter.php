<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class InteractiveRouter
{
    private const MAIN_MENU_TEXT_MAP = [
        'العطور' => 'menu_perfumes',
        'البخور واللمسات' => 'menu_bakhoor',
        'الشحن والسياسات' => 'menu_shipping',
        'الأسئلة الشائعة' => 'help_faq',
        'طرق الدفع والتحويل' => 'help_payment',
        'أوقات الدوام' => 'help_hours',
        'التواصل مع خدمة العملاء' => 'help_contact',
    ];
    private const SUPPORT_TEXT_TRIGGERS = [
        'تواصل مع الدعم',
        'التواصل مع الدعم',
        'التواصل مع خدمة العملاء',
        'خدمة العملاء',
    ];
    private const PERFUME_MENU_TEXT_MAP = [
        'أحدث الإصدارات' => 'perfume_new',
        'الأكثر مبيعاً' => 'perfume_best',
        'باقات العيد' => 'bundle_eid',
        'الباقات والعروض' => 'bundle_offers',
        'عطور رجالية' => 'perfume_men',
        'عطور نسائية' => 'perfume_women',
        'عطور شبابية' => 'perfume_youth',
        'الرجوع للقائمة الرئيسية' => 'back_main',
    ];

    private const PERFUME_PRODUCTS_TEXT = [
        'perfume_new' => "🆕 آخر الإصدارات\n\nعاصفة طيب الأتراك\nهيبة طيب الأتراك\nإحساس طيب الأتراك\nسفير عود طيب الأتراك\nملك طيب الأتراك\nسلطان طيب الأتراك\nملكة طيب الأتراك",
        'perfume_best' => "⭐ الأكثر مبيعاً\n\nعاصفة طيب الأتراك\nهيبة طيب الأتراك\nإحساس طيب الأتراك\nسفير عود طيب الأتراك\nملك طيب الأتراك\nسلطان طيب الأتراك\nملكة طيب الأتراك",
        'perfume_men' => "👔 عطور رجالية\n\nعاصفة طيب الأتراك\nهيبة طيب الأتراك\nإحساس طيب الأتراك\nسفير عود طيب الأتراك\nملك طيب الأتراك\nسلطان طيب الأتراك\nملكة طيب الأتراك",
        'perfume_women' => "👗 عطور نسائية\n\nعاصفة طيب الأتراك\nهيبة طيب الأتراك\nإحساس طيب الأتراك\nسفير عود طيب الأتراك\nملك طيب الأتراك\nسلطان طيب الأتراك\nملكة طيب الأتراك",
        'perfume_youth' => "🧑 عطور شبابية\n\nعاصفة طيب الأتراك\nهيبة طيب الأتراك\nإحساس طيب الأتراك\nسفير عود طيب الأتراك\nملك طيب الأتراك\nسلطان طيب الأتراك\nملكة طيب الأتراك",
    ];

    private const BAKHOOR_MENU_TEXT_MAP = [
        'البخور' => 'bakhoor_bakhoor',
        'المخمريات' => 'bakhoor_makhmaria',
        'اللمسات العطرية' => 'bakhoor_touch',
        'الرجوع للقائمة الرئيسية' => 'back_main',
    ];

    private const SHIPPING_MENU_TEXT_MAP = [
        'الشحن داخل اليمن' => 'shipping_yemen',
        'الشحن إلى دول الخليج' => 'shipping_gulf',
        'سياسة الشحن' => 'shipping_policy',
        'سياسة الاسترجاع' => 'return_policy',
        'الرجوع للقائمة الرئيسية' => 'back_main',
    ];

    private const HELP_MENU_TEXT_MAP = [
        'الأسئلة الشائعة' => 'help_faq',
        'طرق الدفع والتحويل' => 'help_payment',
        'أوقات الدوام' => 'help_hours',
        'التواصل مع خدمة العملاء' => 'help_contact',
        'الرجوع للقائمة الرئيسية' => 'back_main',
    ];

    private const ORDER_MENU_TEXT_MAP = [
        'نموذج الطلب' => 'order_form',
        'تصفح المنتجات والتسوق' => 'order_browse',
        'الرجوع للقائمة الرئيسية' => 'back_main',
    ];

    private const PRODUCT_CATEGORIES = [
        'perfume_best',
        'perfume_new',
        'perfume_men',
        'perfume_women',
        'perfume_youth',
        'bakhoor_bakhoor',
        'bakhoor_touch',
        'bakhoor_makhmaria',
        'bundle_eid',
        'bundle_offers',
    ];
    private const DEFAULT_CATEGORY_ID = 27;
    private const CATEGORY_ID_MAP = [
        'perfume_best' => 290,
        'perfume_new' => 31,
        'perfume_men' => 32,
        'perfume_women' => 33,
        'perfume_youth' => 34,
        'bakhoor_bakhoor' => 25,
        'bakhoor_makhmaria' => 26,
        'bakhoor_touch' => 28,
        'bundle_eid' => 210,
        'bundle_offers' => 27,
    ];

    private const STATIC_MESSAGES = [
        'shipping_yemen' => "🚚 أولاً: مناطق وتكلفة الشحن (دفع مسبق)\n\n1- الشحن داخل اليمن:\nصنعاء: (حدة، جولة عمران، شميلة، حزيز، قاع القيضي، مذبح، دار سلم) | 7 ريال سعودي.\nعدن: | 10 ريال سعودي.\nحضرموت: (المكلا، سيئون) | 15 ريال سعودي.\nتعز: (التربة، الحوبان، المدينه، المخاء) | 10 ريال سعودي.\nالحديدة: (باجل، بيت الفقيه، زبيد، الجراحي، الحسينية) | 10ريال سعودي.\nإب: (اب، القاعدة، يريم) | 7 ريال سعودي.\nالبيضاء: (رداع، البيضاء، السوادية، ذي ناعم، عفار، الطفة) | 10 ريال سعودي.\nلحج: (يافع الحد، يافع لبعوس) | 10 ريال سعودي.\nمأرب: | 10 ريال سعودي.\nشبوة: (عتق) | 10 ريال سعودي.\nالجوف: (سوق الاثنين، الحزم) | 10 ريال سعودي.\nالمهرة: (الغيظة) | 14 ريال سعودي.\nالمحويت: (الرجم، الطويلة، المدينة، شبام، ابلاس) | 10 ريال سعودي.\nصعدة: | 10 ريال سعودي.\nحجة: (عبس،حجة، شفر) | 10 ريال سعودي.\nالضالع: (الجبارة، الضالع، قعطبة، الرضمة، دمت، جبن) | 10 ريال سعودي.\nعمران: (حوث، عمران، خمر، ريدة) | 10 ريال سعودي.\nذمار: (معبر، ضاف، ذمار، ضوران) | 3.5 ريال سعودي.",
        'shipping_gulf' => "2- الشحن الدولي (دول الخليج والأردن):\nالدول: (السعودية، عمان، قطر، الكويت، البحرين، الإمارات، الأردن).\nالتكلفة: 64 ريال سعودي لأول كيلو، و 22 ريال سعودي لكل كيلو إضافي.",
        'shipping_policy' => "📜 ثانياً: سياسة وآلية الشحن\n\nاعتماد الطلب: يتم حساب تكلفة الشحن وإضافتها إلى قيمة المنتج مقدماً عند تحويل المبلغ، ويتم اعتماد خروج الشحنة فور تأكيد السداد الكامل بفضل الله.\n\nمدة التوصيل:\nداخل اليمن: من 3 إلى 7 أيام عمل.\nخارج اليمن: من 15 إلى 24 يوم عمل بفضل الله.\n\nالضرائب والجمارك (للشحن الدولي): يقتصر دور \"طيب الأتراك\" على دفع رسوم الشحن المتفق عليها مقدماً، وفي حال وجود أي (ضرائب، جمارك، أو رسوم استيراد) في الدولة المستلمة، فإن المستلم يتحملها بالكامل حسب قوانين بلده.",
        'return_policy' => "🔄 ثالثاً: سياسة الضمان والاسترجاع (داخل اليمن فقط)\n\nنطاق الخدمة: خدمة الاستبدال والاسترجاع خاصة وحصرية للطلبات داخل الجمهورية اليمنية فقط، وبفضل الله نضمن لكم جودة منتجاتنا.\n\nالمدة والشروط: يحق للعميل الاسترجاع خلال 7 أيام من الاستلام، بشرط أن يظل المنتج بحالته الأصلية.\n\nتنبيه الجودة: ضماناً للسلامة العامة، لا تشمل سياسة الاستبدال الزيوت العطرية.",
        'help_faq' => "❓ الأسئلة الشائعة\n\nهل العطور أصلية؟\nنعم جميع منتجاتنا أصلية ومضمونة.\n\nهل يوجد توصيل؟\nنعم التوصيل متوفر لجميع المحافظات.",
        'help_payment' => "لضمان اعتماد طلبكم وتوصيله في الوقت المناسب بفضل الله، يرجى التحويل عبر أحد الخيارات التالية:\n\n🏦 أولاً: حسابات بنك الكريمي\n\nالاسم: محمد أحمد حسين التركي\n\nالريال اليمني 🇾🇪: 3158453109\n\nالريال السعودي 🇸🇦: 3158530577\n\nالدولار الأمريكي 🇺🇸: 3158459395\n\n📱 ثانياً: المحافظ الإلكترونية (كل محفظة على حدة)\n\nمحفظة جيب (Jib):\n\nالاسم: محمد أحمد حسين التركي\n\nالرقم: 773603698\n\nمحفظة ون كاش\n\nالاسم: محمد أحمد حسين التركي\n\nالرقم: 773603698\n\nمحفظة جوالي:\n\nالاسم: محمد أحمد حسين التركي\n\nالرقم: 773603698\n\nمحفظة فلوسك:\n\nالاسم: محمد أحمد حسين التركي\n\nالرقم: 773603698\n\n💸 ثالثاً: الحوالات الداخلية (عبر الصرافين)\n\nالاسم: محمد أحمد حسين التركي\n\nالرقم: 773603698\n\nعبر: (النجم، دادية، الامتياز).\n\n🌍 رابعاً: الحوالات الدولية (خارج اليمن)\n\nعبر: موني جرام (MoneyGram) أو ويسترن يونيون (Western Union).\n\nالاسم بالإنجليزية: Hussein Mohammed Ahmed Al-Turki\n\nالهاتف: +967773603698\n\n⚠️ ملاحظات هامة:\n\nالتأكد: يرجى مراجعة صحة الاسم والرقم بدقة قبل التحويل\n\nالإشعار: بعد التحويل، يرجى تزويدنا بصورة (السند) لاعتماد الطلب.\n\nالالتزام: يتم دفع الحساب مقدماً لضمان حجز وتوصيل المنتج في الوقت المناسب بفضل الله.",
        'help_hours' => "🕘 أوقات الدوام\n\nيومياً من الساعة:\n10 صباحاً\nحتى\n10 مساءً",
        'help_contact' => "📞 خدمة العملاء\n\nيمكنك التواصل معنا مباشرة عبر هذه المحادثة.",
        'order_form' => "📝 نموذج الطلب\n\nيرجى إرسال البيانات التالية:\nالاسم الكامل\nرقم الهاتف\nالمحافظة\nالعنوان التفصيلي",
        'order_browse' => "🛍 تصفح المنتجات\n\nيمكنك تصفح المنتجات عبر القوائم التفاعلية في هذه المحادثة.",
    ];
    private const PRODUCT_CACHE_TTL_SECONDS = 1800;
    private const LAST_PRODUCT_TTL_SECONDS = 1800;
    private const LAST_CATEGORY_MENU_TTL_SECONDS = 1800;
    private const SUPPORT_HANDOFF_TTL_SECONDS = 86400;
    private const SUPPORT_RESUME_KEYWORD = 'تشغيل';

    public function __construct(
        private readonly InteractiveMenuService $menuService,
        private readonly StoreApiService $storeApi
    )
    {
    }

    public function handleInteractiveReply(string $phoneNumberId, string $phoneNumber, ?int $inboxId, string $routingId): void
    {
        if ($this->isSupportHandoffActive($phoneNumber)) {
            Log::info('support_handoff_blocked', [
                'phone' => $phoneNumber,
                'routing_id' => $routingId,
            ]);
            return;
        }
        Log::info('interactive_reply_received', [
            'phone' => $phoneNumber,
            'routing_id' => $routingId,
        ]);

        if (in_array($routingId, self::PRODUCT_CATEGORIES, true)) {
            $categoryId = self::CATEGORY_ID_MAP[$routingId] ?? self::DEFAULT_CATEGORY_ID;
            $products = $this->storeApi->getProductsByCategoryId($categoryId);
            Log::info('store_products_raw', [
                'category' => $routingId,
                'category_id' => $categoryId,
                'products' => $products,
            ]);
            $normalizedProducts = $this->storeApi->normalizeProducts($products, 10);
            // Log::info('store_products_normalized', [
            //     'category' => $routingId,
            //     'category_id' => $categoryId,
            //     'products' => $normalizedProducts,
            // ]);
            Log::info('store_products_loaded', [
                'category' => $routingId,
                'category_id' => $categoryId,
                'products_count' => count($products),
            ]);
            $this->cacheProductsForPhone($phoneNumber, $normalizedProducts);
            $this->setLastCategoryKey($phoneNumber, $routingId);
            $menuCopy = $this->productMenuCopy($routingId);
            $this->menuService->sendProductListMenu(
                $phoneNumberId,
                $phoneNumber,
                $menuCopy['title'],
                $menuCopy['body'],
                $menuCopy['section'],
                $menuCopy['button'],
                $normalizedProducts
            );
            Log::info('products_message_sent', [
                'phone' => $phoneNumber,
                'category' => $routingId,
            ]);
            return;
        }

        if (array_key_exists($routingId, self::STATIC_MESSAGES)) {
            if ($routingId === 'help_contact') {
                $this->setSupportHandoff($phoneNumber);
                $this->menuService->sendTextMessage(
                    $phoneNumberId,
                    $phoneNumber,
                    'تم تحويلك لخدمة العملاء. تفضل أرسل استفسارك وسيتم الرد عليك قريباً.' . "\n\n" .
                    'لإعادة تفعيل الرد التلقائي لاحقاً أرسل كلمة: ' . self::SUPPORT_RESUME_KEYWORD
                );
                $this->notifySupportTeam($phoneNumberId, $phoneNumber, $inboxId);
                Log::info('support_handoff_started', [
                    'phone' => $phoneNumber,
                ]);
                return;
            }
            $this->menuService->sendTextMessage($phoneNumberId, $phoneNumber, self::STATIC_MESSAGES[$routingId]);
            Log::info('menu_section_sent', [
                'section' => $routingId,
                'phone' => $phoneNumber,
            ]);
            $this->menuService->sendTextMessage($phoneNumberId, $phoneNumber, 'يمكنك اختيار قسم آخر من القائمة 👇');
            $this->menuService->sendMainMenu($phoneNumberId, $phoneNumber);
            return;
        }

        if (str_starts_with($routingId, 'product:')) {
            $productId = substr($routingId, strlen('product:'));
            $product = $this->findCachedProduct($phoneNumber, $productId);
            if ($product !== null) {
                $this->setLastProductId($phoneNumber, $productId);
                $this->menuService->sendProductDetailsWithBuyButton(
                    $phoneNumberId,
                    $phoneNumber,
                    $product,
                    $this->getLastCategoryKey($phoneNumber),
                    $this->getLastMenuLabel($phoneNumber)
                );
            }
            return;
        }

        if (str_starts_with($routingId, 'buy:')) {
            $productId = substr($routingId, strlen('buy:'));
            $product = $this->findCachedProduct($phoneNumber, $productId);
            $productName = $product['name'] ?? '';
            Log::info('buy_button_mapped', [
                'phone' => $phoneNumber,
                'product_id' => $productId,
            ]);
            $this->menuService->sendTextMessage(
                $phoneNumberId,
                $phoneNumber,
                "تم استلام رغبتك بشراء المنتج: {$productName}\n\nلإكمال الطلب، يرجى إرسال البيانات التالية:\n\n1) الاسم الكامل\n2) رقم الهاتف\n3) المحافظة\n4) العنوان التفصيلي\n5) طريقة الدفع\n\nيمكنك إرسالها في رسالة واحدة بالترتيب."
            );
            return;
        }

        if ($routingId === 'support_request') {
            $this->setSupportHandoff($phoneNumber);
            $this->menuService->sendTextMessage(
                $phoneNumberId,
                $phoneNumber,
                'تم تحويلك لخدمة العملاء. تفضل أرسل استفسارك وسيتم الرد عليك قريباً.' . "\n\n" .
                'لإعادة تفعيل الرد التلقائي لاحقاً أرسل كلمة: ' . self::SUPPORT_RESUME_KEYWORD
            );
            $this->notifySupportTeam($phoneNumberId, $phoneNumber, $inboxId);
            Log::info('support_handoff_started', [
                'phone' => $phoneNumber,
            ]);
            return;
        }

        if ($routingId === 'back_previous') {
            $categoryKey = $this->getLastCategoryKey($phoneNumber);
            if (is_string($categoryKey) && str_starts_with($categoryKey, 'perfume_')) {
                $this->menuService->sendPerfumeMenu($phoneNumberId, $phoneNumber);
                return;
            }
            if (is_string($categoryKey) && str_starts_with($categoryKey, 'bakhoor_')) {
                $this->menuService->sendBakhoorMenu($phoneNumberId, $phoneNumber);
                return;
            }
            $this->menuService->sendMainMenu($phoneNumberId, $phoneNumber);
            return;
        }

        switch ($routingId) {
            case 'menu_perfumes':
                $this->menuService->sendPerfumeMenu($phoneNumberId, $phoneNumber);
                break;
            case 'menu_bakhoor':
                $this->menuService->sendBakhoorMenu($phoneNumberId, $phoneNumber);
                break;
            case 'menu_shipping':
                $this->menuService->sendShippingMenu($phoneNumberId, $phoneNumber);
                break;
            case 'menu_help':
                $this->menuService->sendHelpMenu($phoneNumberId, $phoneNumber);
                break;
            case 'menu_order':
                $this->menuService->sendOrderMenu($phoneNumberId, $phoneNumber);
                break;
            case 'back_main':
                $this->menuService->sendMainMenu($phoneNumberId, $phoneNumber);
                break;
            default:
                Log::info('interactive_reply_unknown', [
                    'phone' => $phoneNumber,
                    'routing_id' => $routingId,
                ]);
                break;
        }
    }

    public function handleTextMessage(string $phoneNumberId, string $phoneNumber, ?int $inboxId, string $text): void
    {
        $trimmedText = trim($text);
        if ($trimmedText === self::SUPPORT_RESUME_KEYWORD) {
            $this->clearSupportHandoff($phoneNumber);
            $this->menuService->sendTextMessage(
                $phoneNumberId,
                $phoneNumber,
                'تم إعادة تفعيل الرد التلقائي. تفضل اختر ما يناسبك.'
            );
            $this->menuService->sendMainMenu($phoneNumberId, $phoneNumber);
            return;
        }

        if ($this->isSupportHandoffActive($phoneNumber)) {
            Log::info('support_handoff_blocked', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
            ]);
            return;
        }
        Log::info('router_text_message', [
            'phone' => $phoneNumber,
            'text' => $trimmedText,
        ]);

        if (array_key_exists($trimmedText, self::MAIN_MENU_TEXT_MAP)) {
            $routingId = self::MAIN_MENU_TEXT_MAP[$trimmedText];
            Log::info('text_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::PERFUME_MENU_TEXT_MAP)) {
            $routingId = self::PERFUME_MENU_TEXT_MAP[$trimmedText];
            Log::info('perfume_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::BAKHOOR_MENU_TEXT_MAP)) {
            $routingId = self::BAKHOOR_MENU_TEXT_MAP[$trimmedText];
            Log::info('bakhoor_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::SHIPPING_MENU_TEXT_MAP)) {
            $routingId = self::SHIPPING_MENU_TEXT_MAP[$trimmedText];
            Log::info('shipping_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::HELP_MENU_TEXT_MAP)) {
            $routingId = self::HELP_MENU_TEXT_MAP[$trimmedText];
            Log::info('help_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::ORDER_MENU_TEXT_MAP)) {
            $routingId = self::ORDER_MENU_TEXT_MAP[$trimmedText];
            Log::info('order_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, $routingId);
            return;
        }

        if (in_array($trimmedText, self::SUPPORT_TEXT_TRIGGERS, true)) {
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, 'support_request');
            return;
        }

        $buyPrefixes = ['شراء ', 'طلب '];
        foreach ($buyPrefixes as $prefix) {
            if (str_starts_with($trimmedText, $prefix)) {
                $candidate = trim(mb_substr($trimmedText, mb_strlen($prefix)));
                $candidate = rtrim($candidate, "….");
                if ($candidate !== '') {
                    $product = $this->findCachedProductByNameOrPrefix($phoneNumber, $candidate);
                    if ($product !== null) {
                        $productId = (string) ($product['id'] ?? '');
                        if ($productId !== '') {
                            $this->setLastProductId($phoneNumber, $productId);
                            Log::info('buy_fallback_used', [
                                'phone' => $phoneNumber,
                                'text' => $trimmedText,
                                'product_id' => $productId,
                            ]);
                            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, 'buy:' . $productId);
                            return;
                        }
                    }
                }
            }
        }

        if ($trimmedText === 'شراء المنتج') {
            $productId = $this->getLastProductId($phoneNumber);
            if ($productId !== null) {
                $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, 'buy:' . $productId);
                return;
            }
        }

        $product = $this->findCachedProductByName($phoneNumber, $trimmedText);
        if ($product !== null) {
            $productId = (string) ($product['id'] ?? '');
            if ($productId !== '') {
                $this->setLastProductId($phoneNumber, $productId);
            }
            $this->menuService->sendProductDetailsWithBuyButton(
                $phoneNumberId,
                $phoneNumber,
                $product,
                $this->getLastCategoryKey($phoneNumber),
                $this->getLastMenuLabel($phoneNumber)
            );
            return;
        }

        $normalized = mb_strtolower(trim($text));
        $triggers = ['مرحبا', 'السلام', 'menu', 'start'];

        if (in_array($normalized, $triggers, true)) {
            $this->menuService->sendMainMenu($phoneNumberId, $phoneNumber);
            return;
        }

        $this->menuService->sendMainMenu($phoneNumberId, $phoneNumber);
    }

    private function cacheProductsForPhone(string $phoneNumber, array $products): void
    {
        Cache::put($this->productCacheKey($phoneNumber), $products, self::PRODUCT_CACHE_TTL_SECONDS);
    }

    private function findCachedProduct(string $phoneNumber, string $productId): ?array
    {
        $products = Cache::get($this->productCacheKey($phoneNumber), []);
        foreach ($products as $product) {
            if ((string) ($product['id'] ?? '') === (string) $productId) {
                return $product;
            }
        }
        return null;
    }

    private function findCachedProductByName(string $phoneNumber, string $name): ?array
    {
        $products = Cache::get($this->productCacheKey($phoneNumber), []);
        foreach ($products as $product) {
            $fullName = (string) ($product['name'] ?? '');
            $displayTitle = (string) ($product['display_title'] ?? '');
            if ($fullName === $name || $displayTitle === $name) {
                return $product;
            }
        }
        return null;
    }

    private function findCachedProductByNameOrPrefix(string $phoneNumber, string $name): ?array
    {
        $exact = $this->findCachedProductByName($phoneNumber, $name);
        if ($exact !== null) {
            return $exact;
        }

        $products = Cache::get($this->productCacheKey($phoneNumber), []);
        foreach ($products as $product) {
            $fullName = (string) ($product['name'] ?? '');
            $displayTitle = (string) ($product['display_title'] ?? '');
            if ($fullName !== '' && str_starts_with($fullName, $name)) {
                return $product;
            }
            if ($displayTitle !== '' && str_starts_with($displayTitle, $name)) {
                return $product;
            }
        }
        return null;
    }

    private function productCacheKey(string $phoneNumber): string
    {
        return 'products:phone:' . $phoneNumber;
    }

    private function productMenuCopy(string $routingId): array
    {
        return match ($routingId) {
            'bundle_eid' => [
                'title' => 'باقات العيد',
                'body' => 'اختيارات مميزة للعيد، اختر الباقة المناسبة لك',
                'section' => 'الباقات',
                'button' => 'قائمة باقات العيد',
            ],
            'bundle_offers' => [
                'title' => 'الباقات والعروض',
                'body' => 'عروض وباقات مختارة بأسعار مميزة',
                'section' => 'العروض',
                'button' => 'قائمة الباقات والعروض',
            ],
            'perfume_new' => [
                'title' => 'أحدث الإصدارات',
                'body' => 'إصدارات جديدة بروائح مميزة، اختر المنتج الذي أعجبك',
                'section' => 'المنتجات',
                'button' => 'قائمة أحدث الإصدارات',
            ],
            'perfume_best' => [
                'title' => 'الأكثر مبيعاً',
                'body' => 'الأكثر طلباً من عملائنا، اختر المنتج المناسب لك',
                'section' => 'المنتجات',
                'button' => 'قائمة الأكثر مبيعاً',
            ],
            'perfume_men' => [
                'title' => 'عطور رجالية',
                'body' => 'اختيارات رجالية بروائح قوية وفاخرة',
                'section' => 'المنتجات',
                'button' => 'قائمة العطور الرجالية',
            ],
            'perfume_women' => [
                'title' => 'عطور نسائية',
                'body' => 'عطور نسائية بطابع أنيق وجذاب',
                'section' => 'المنتجات',
                'button' => 'قائمة العطور النسائية',
            ],
            'perfume_youth' => [
                'title' => 'عطور شبابية',
                'body' => 'روائح شبابية خفيفة ومنعشة',
                'section' => 'المنتجات',
                'button' => 'قائمة العطور الشبابية',
            ],
            'bakhoor_bakhoor' => [
                'title' => 'البخور',
                'body' => 'أفضل أنواع البخور المختارة',
                'section' => 'المنتجات',
                'button' => 'قائمة البخور',
            ],
            'bakhoor_touch' => [
                'title' => 'اللمسات العطرية',
                'body' => 'لمسات تضيف جمالاً لكل مناسبة',
                'section' => 'المنتجات',
                'button' => 'قائمة اللمسات العطرية',
            ],
            'bakhoor_makhmaria' => [
                'title' => 'المخمريات',
                'body' => 'مخمريات بروائح ثابتة ومميزة',
                'section' => 'المنتجات',
                'button' => 'قائمة المخمريات',
            ],
            default => [
                'title' => 'المنتجات المتوفرة',
                'body' => 'اختر المنتج المطلوب من القائمة',
                'section' => 'المنتجات',
                'button' => 'قائمة المنتجات',
            ],
        };
    }

    private function lastProductCacheKey(string $phoneNumber): string
    {
        return 'last_product:phone:' . $phoneNumber;
    }

    private function lastCategoryCacheKey(string $phoneNumber): string
    {
        return 'last_category:phone:' . $phoneNumber;
    }

    private function lastMenuLabelKey(string $phoneNumber): string
    {
        return 'last_menu_label:phone:' . $phoneNumber;
    }

    private function supportHandoffKey(string $phoneNumber): string
    {
        return 'support_handoff:phone:' . $phoneNumber;
    }

    private function setLastCategoryKey(string $phoneNumber, string $categoryKey): void
    {
        Cache::put($this->lastCategoryCacheKey($phoneNumber), $categoryKey, self::LAST_CATEGORY_MENU_TTL_SECONDS);
        $menu = $this->productMenuCopy($categoryKey);
        Cache::put($this->lastMenuLabelKey($phoneNumber), $menu['button'] ?? null, self::PRODUCT_CACHE_TTL_SECONDS);
    }

    private function setSupportHandoff(string $phoneNumber): void
    {
        Cache::put($this->supportHandoffKey($phoneNumber), true, self::SUPPORT_HANDOFF_TTL_SECONDS);
    }

    private function clearSupportHandoff(string $phoneNumber): void
    {
        Cache::forget($this->supportHandoffKey($phoneNumber));
    }

    private function isSupportHandoffActive(string $phoneNumber): bool
    {
        return (bool) Cache::get($this->supportHandoffKey($phoneNumber), false);
    }

    private function notifySupportTeam(string $phoneNumberId, string $customerPhone, ?int $inboxId): void
    {
        $supportPhone = (string) env('SUPPORT_TEAM_PHONE', '');
        if ($supportPhone === '') {
            return;
        }

        $inboxPhone = null;
        if ($inboxId !== null) {
            $inbox = \App\Models\ChatwootInbox::where('chatwoot_inbox_id', $inboxId)->first();
            $inboxPhone = $inbox?->phone_number;
        }
        $sourcePhone = $inboxPhone ?? (string) $inboxId;
        $message = "العميل {$customerPhone} طلب حدمة العملاء من الرقم {$sourcePhone} يرجى الرد عليه في اقرب وقت";
        $this->menuService->sendTextMessage($phoneNumberId, $supportPhone, $message);
    }

    private function getLastCategoryKey(string $phoneNumber): ?string
    {
        $value = Cache::get($this->lastCategoryCacheKey($phoneNumber));
        return $value !== null ? (string) $value : null;
    }

    private function getLastMenuLabel(string $phoneNumber): ?string
    {
        $value = Cache::get($this->lastMenuLabelKey($phoneNumber));
        return $value !== null ? (string) $value : null;
    }

    private function setLastProductId(string $phoneNumber, string $productId): void
    {
        Cache::put($this->lastProductCacheKey($phoneNumber), $productId, self::LAST_PRODUCT_TTL_SECONDS);
    }

    private function getLastProductId(string $phoneNumber): ?string
    {
        $value = Cache::get($this->lastProductCacheKey($phoneNumber));
        return $value !== null ? (string) $value : null;
    }
}
