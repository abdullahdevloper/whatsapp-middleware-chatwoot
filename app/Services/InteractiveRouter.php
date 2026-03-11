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
    private const PERFUME_MENU_TEXT_MAP = [
        'أحدث الإصدارات' => 'perfume_new',
        'الأكثر مبيعاً' => 'perfume_best',
        'جميع العطور' => 'perfume_all',
        'عطور رجالية' => 'perfume_men',
        'عطور نسائية' => 'perfume_women',
        'عطور شبابية' => 'perfume_youth',
        'الرجوع للقائمة الرئيسية' => 'back_main',
    ];

    private const PERFUME_PRODUCTS_TEXT = [
        'perfume_new' => "🆕 آخر الإصدارات\n\nعاصفة طيب الأتراك\nهيبة طيب الأتراك\nإحساس طيب الأتراك\nسفير عود طيب الأتراك\nملك طيب الأتراك\nسلطان طيب الأتراك\nملكة طيب الأتراك",
        'perfume_best' => "⭐ الأكثر مبيعاً\n\nعاصفة طيب الأتراك\nهيبة طيب الأتراك\nإحساس طيب الأتراك\nسفير عود طيب الأتراك\nملك طيب الأتراك\nسلطان طيب الأتراك\nملكة طيب الأتراك",
        'perfume_all' => "🧴 جميع العطور\n\nعاصفة طيب الأتراك\nهيبة طيب الأتراك\nإحساس طيب الأتراك\nسفير عود طيب الأتراك\nملك طيب الأتراك\nسلطان طيب الأتراك\nملكة طيب الأتراك",
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
        'perfume_all',
        'perfume_men',
        'perfume_women',
        'perfume_youth',
        'bakhoor_bakhoor',
        'bakhoor_touch',
        'bakhoor_makhmaria',
    ];
    private const DEFAULT_CATEGORY_ID = 27;
    private const CATEGORY_ID_MAP = [
        'perfume_new' => 31,
        'perfume_men' => 32,
        'perfume_women' => 33,
        'perfume_youth' => 34,
        'bakhoor_bakhoor' => 25,
        'bakhoor_makhmaria' => 26,
        'bakhoor_touch' => 28,
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

    public function __construct(
        private readonly InteractiveMenuService $menuService,
        private readonly StoreApiService $storeApi
    )
    {
    }

    public function handleInteractiveReply(string $phoneNumberId, string $phoneNumber, string $routingId): void
    {
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
            Log::info('store_products_normalized', [
                'category' => $routingId,
                'category_id' => $categoryId,
                'products' => $normalizedProducts,
            ]);
            Log::info('store_products_loaded', [
                'category' => $routingId,
                'category_id' => $categoryId,
                'products_count' => count($products),
            ]);
            $this->cacheProductsForPhone($phoneNumber, $normalizedProducts);
            $menuCopy = $this->productMenuCopy($routingId);
            $this->menuService->sendProductListMenu(
                $phoneNumberId,
                $phoneNumber,
                $menuCopy['title'],
                $menuCopy['body'],
                $menuCopy['section'],
                $normalizedProducts
            );
            Log::info('products_message_sent', [
                'phone' => $phoneNumber,
                'category' => $routingId,
            ]);
            return;
        }

        if (array_key_exists($routingId, self::STATIC_MESSAGES)) {
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
                $this->menuService->sendProductDetailsWithBuyButton($phoneNumberId, $phoneNumber, $product);
            }
            return;
        }

        if (str_starts_with($routingId, 'buy:')) {
            $productId = substr($routingId, strlen('buy:'));
            $product = $this->findCachedProduct($phoneNumber, $productId);
            $productName = $product['name'] ?? '';
            $this->menuService->sendTextMessage(
                $phoneNumberId,
                $phoneNumber,
                "تم استلام رغبتك بشراء المنتج: {$productName}\n\nلإكمال الطلب، يرجى إرسال البيانات التالية:\n\n1) الاسم الكامل\n2) رقم الهاتف\n3) المحافظة\n4) العنوان التفصيلي\n5) طريقة الدفع\n\nيمكنك إرسالها في رسالة واحدة بالترتيب."
            );
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

    public function handleTextMessage(string $phoneNumberId, string $phoneNumber, string $text): void
    {
        Log::info('router_text_message', [
            'phone' => $phoneNumber,
            'text' => $text,
        ]);

        $trimmedText = trim($text);
        if (array_key_exists($trimmedText, self::MAIN_MENU_TEXT_MAP)) {
            $routingId = self::MAIN_MENU_TEXT_MAP[$trimmedText];
            Log::info('text_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::PERFUME_MENU_TEXT_MAP)) {
            $routingId = self::PERFUME_MENU_TEXT_MAP[$trimmedText];
            Log::info('perfume_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::BAKHOOR_MENU_TEXT_MAP)) {
            $routingId = self::BAKHOOR_MENU_TEXT_MAP[$trimmedText];
            Log::info('bakhoor_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::SHIPPING_MENU_TEXT_MAP)) {
            $routingId = self::SHIPPING_MENU_TEXT_MAP[$trimmedText];
            Log::info('shipping_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::HELP_MENU_TEXT_MAP)) {
            $routingId = self::HELP_MENU_TEXT_MAP[$trimmedText];
            Log::info('help_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $routingId);
            return;
        }

        if (array_key_exists($trimmedText, self::ORDER_MENU_TEXT_MAP)) {
            $routingId = self::ORDER_MENU_TEXT_MAP[$trimmedText];
            Log::info('order_menu_mapping', [
                'phone' => $phoneNumber,
                'text' => $trimmedText,
                'routing_id' => $routingId,
            ]);
            $this->handleInteractiveReply($phoneNumberId, $phoneNumber, $routingId);
            return;
        }

        if ($trimmedText === 'شراء المنتج') {
            $productId = $this->getLastProductId($phoneNumber);
            if ($productId !== null) {
                $this->handleInteractiveReply($phoneNumberId, $phoneNumber, 'buy:' . $productId);
                return;
            }
        }

        $product = $this->findCachedProductByName($phoneNumber, $trimmedText);
        if ($product !== null) {
            $productId = (string) ($product['id'] ?? '');
            if ($productId !== '') {
                $this->setLastProductId($phoneNumber, $productId);
            }
            $this->menuService->sendProductDetailsWithBuyButton($phoneNumberId, $phoneNumber, $product);
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
            if (($product['name'] ?? '') === $name) {
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
            'perfume_new' => [
                'title' => 'أحدث الإصدارات',
                'body' => 'إصدارات جديدة بروائح مميزة، اختر المنتج الذي أعجبك',
                'section' => 'المنتجات',
            ],
            'perfume_best' => [
                'title' => 'الأكثر مبيعاً',
                'body' => 'الأكثر طلباً من عملائنا، اختر المنتج المناسب لك',
                'section' => 'المنتجات',
            ],
            'perfume_all' => [
                'title' => 'جميع العطور',
                'body' => 'تصفّح كل العطور المتوفرة واختر ما يناسبك',
                'section' => 'المنتجات',
            ],
            'perfume_men' => [
                'title' => 'عطور رجالية',
                'body' => 'اختيارات رجالية بروائح قوية وفاخرة',
                'section' => 'المنتجات',
            ],
            'perfume_women' => [
                'title' => 'عطور نسائية',
                'body' => 'عطور نسائية بطابع أنيق وجذاب',
                'section' => 'المنتجات',
            ],
            'perfume_youth' => [
                'title' => 'عطور شبابية',
                'body' => 'روائح شبابية خفيفة ومنعشة',
                'section' => 'المنتجات',
            ],
            'bakhoor_bakhoor' => [
                'title' => 'البخور',
                'body' => 'أفضل أنواع البخور المختارة',
                'section' => 'المنتجات',
            ],
            'bakhoor_touch' => [
                'title' => 'اللمسات العطرية',
                'body' => 'لمسات تضيف جمالاً لكل مناسبة',
                'section' => 'المنتجات',
            ],
            'bakhoor_makhmaria' => [
                'title' => 'المخمريات',
                'body' => 'مخمريات بروائح ثابتة ومميزة',
                'section' => 'المنتجات',
            ],
            default => [
                'title' => 'المنتجات المتوفرة',
                'body' => 'اختر المنتج المطلوب من القائمة',
                'section' => 'المنتجات',
            ],
        };
    }

    private function lastProductCacheKey(string $phoneNumber): string
    {
        return 'last_product:phone:' . $phoneNumber;
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
