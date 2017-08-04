<?php

require_once _PS_ROOT_DIR_.'/vendor/autoload.php';

class CWGEcommerce extends Module
{
    /**
     * Registered hooks.
     *
     * @var array
     */
    const HOOKS = [
        'actionAdminModulesOptionsModifier',
        'actionCartSave',
        'actionObjectOrderPaymentAddAfter',
        'actionOrderSlipAdd',
        'displayBackOfficeHeader',
        'displayHeader',
        'displayOrderConfirmation',
    ];

    /**
     * Options fields.
     *
     * @var array
     */
    const OPTIONS = [
        'GTM_ID' => [
            'type'       => 'text',
            'title'      => 'Google Tag Manager container ID', /* ->l('Google Tag Manager container ID') */
            'desc'       => 'This information is available in your Google Tag Manager account.', /* ->l('This information is available in your Google Tag Manager account.') */
            'size'       => 10,
            'required'   => true,
            'validation' => 'isGenericName',
            'cast'       => 'strval',
        ],
        'SANDBOX' => [
            'type'       => 'bool',
            'title'      => 'Enable sandbox mode.', /* ->l('Enable sandbox mode.') */
            'desc'       => 'This module will not send any data if _PS_MODE_DEV_ is on. Enable sandbox mode to bypass this behavior.', /* ->l('This module will not send any data if _PS_MODE_DEV_ is on. Enable sandbox mode to bypass this behavior.') */
            'default'    => true,
            'required'   => true,
            'validation' => 'isBool',
            'cast'       => 'intval',
        ],
    ];

    /**
     * @see ModuleCore
     */
    public $name    = 'cwgecommerce';
    public $tab     = 'analytics_stats';
    public $version = '1.0.0';
    public $author  = 'Creative Wave';
    public $bootstrap = true;
    public $ps_versions_compliancy = [
        'min' => '1.6',
        'max' => '1.6.99.99',
    ];

    /**
     * @see CWGEcommerce::hookActionCartSave()
     *
     * @var array
     */
    protected $cart_action_products = [];

    /**
     * Initialize module.
     */
    public function __construct()
    {
        parent::__construct();

        $this->displayName      = $this->l('Google E-commerce');
        $this->description      = $this->l('Send data to Google Analytics and Google Adwords via Google Tag Manager.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Install module.
     */
    public function install(): bool
    {
        return parent::install() and $this->addHooks(static::HOOKS);
    }

    /**
     * Uninstall module.
     */
    public function uninstall(): bool
    {
        $this->_clearCache('*');
        $this->getConfiguration()->removeOptionsValues(array_keys(static::OPTIONS));

        return parent::uninstall();
    }

    /**
     * @see \CW\Module\Configuration::hookActionAdminModulesOptionsModifier()
     */
    public function hookActionAdminModulesOptionsModifier(array $params)
    {
        $this->getConfiguration()->hookActionAdminModulesOptionsModifier($params);
    }

    /**
     * @see \CW\Module\Configuration::getContent()
     */
    public function getContent(): string
    {
        return $this->getConfiguration()->getContent();
    }

    /**
     * Display Google Tag Manager script.
     * Add product (details) view on public product page.
     */
    public function hookDisplayHeader(array $params): string
    {
        $id_container = $this->getConfiguration()->getOptionValue('GTM_ID');
        if (!$id_container) {
            return '';
        }
        if ($this->isModeDev() and !$this->isModeSandbox()) {
            return '';
        }

        $template_name = 'header.tpl';
        $id_cache = $this->getCacheId();

        if (!$this->isCached($template_name, $id_cache)) {
            $data_layer = $this->getDataLayer();
            if ($this->isPagePublicProduct()) {
                $product = $this->getProductFromController($this->context->controller);
                $data_layer->addProductView($product);
            }
            $data = $data_layer->flush();
            $this->setTemplateVars([
                'containerId'    => $id_container,
                'dataLayer'      => $data,
                'dataLayerQuery' => $this->getQueryFromJson($data),
            ]);
        }

        return $this->display(__FILE__, $template_name, $id_cache);
    }

    /**
     * Add cart action.
     */
    public function hookActionCartSave(array $params)
    {
        if (!$this->isActionPublicCartProduct()) {
            return;
        }

        $id_product = $this->getValue('id_product');

        /*
         * This hook may fire more than once happen when creating cart, removing
         * product from cart, or when a module updates cart through the current
         * request processing.
         */
        if (in_array($id_product, $this->cart_action_products, true)) {
            return;
        }

        $currency = $this->getContextCurrencyCode();
        if ($this->getValue('add')) {
            $action   = 'down' === $this->getValue('op') ? 'remove' : 'add';
            $quantity = $this->getValue('qty');
            $product  = $this->getLastProductFromCart($this->context->cart);
            $product['quantity'] = $quantity;
            if (!$product['id']) { // Cart is empty when created.
                return;
            }
        } elseif ($this->getValue('delete')) {
            $action  = 'remove';
            $id_product_attribute = (int) $this->getValue('ipa'); // TODO: remove type casting with php >= 7.1.
            $id_lang = $this->getContextShopDefaultLanguageId();
            $product = $this->getProductFromModel($id_product, $id_product_attribute, $id_lang);
        }

        $this->getDataLayer()->addCartAction($action, $product, $currency);
        $this->cart_action_products[] = $id_product;
    }

    /**
     * Add conversion.
     */
    public function hookDisplayOrderConfirmation(array $params): string
    {
        $id_order = $params['objOrder']->id;
        $value = number_format($params['total_to_pay'], '2', '.', '');
        $currency = $this->getContextCurrencyCode();
        $this->getDataLayer()->addConversion($id_order, $value, $currency);

        return '';
    }

    /**
     * Display Google Tag Manager script on admin order page.
     */
    public function hookDisplayBackOfficeHeader(array $params): string
    {
        if (!($this->isPageAdminOrder() and $this->getDataLayer()->hasData())) {
            return '';
        }

        return $this->hookDisplayHeader($params);
    }

    /**
     * Add purchase.
     * This is currently the best event for catching purchases. All other hooks
     * (actionPaymentConfirmation, actionObjectOrderInvoiceAddAfter...) have
     * corner cases.
     */
    public function hookActionObjectOrderPaymentAddAfter(array $params)
    {
        $order_reference = $params['object']->order_reference;
        $order = $this->getOrderFromReference($order_reference);
        $cart  = $this->getCart($order->id_cart);

        $purchase = [
            'affiliation' => $this->getContextShopName(),
            'coupon'      => $this->getCouponsFromCart($cart),
            'id'          => $order->id,
            'revenue'     => number_format($order->total_paid_tax_incl, '2', '.', ''),
            'tax'         => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
            'shipping'    => number_format($order->total_shipping_tax_incl, '2', '.', ''),
        ];
        $products = $this->getProductsFromCart($cart);
        $currency = $this->getContextCurrencyCode();

        $this->getDataLayer()->addPurchase($purchase, $products, $currency);
    }

    /**
     * Add partial refund.
     */
    public function hookActionOrderSlipAdd(array $params)
    {
        $ids_orders_details = array_keys($params['productList']);
        $products = array_map(function ($id_order_detail, $quantity) {
            return [
                'id'       => $this->getOrderDetail($id_order_detail)->product_reference,
                'quantity' => $quantity,
            ];
        }, $ids_orders_details, $params['qtyList']);

        $this->getDataLayer()->addPartialRefund($params['order']->id, $products);
    }

    /**
     * Add hooks.
     */
    protected function addHooks(array $hooks): bool
    {
        return array_product(array_map([$this, 'registerHook'], $hooks));
    }

    /**
     * Get Cart.
     */
    protected function getCart(int $id_cart): Cart
    {
        return new Cart($id_cart);
    }

    /**
     * Get \CW\Module\Configuration.
     */
    protected function getConfiguration(): CW\Module\Configuration
    {
        static $instance;

        return $instance ?? $instance = new CW\Module\Configuration($this);
    }

    /**
     * Get context currency code.
     */
    protected function getContextCurrencyCode(): string
    {
        return $this->context->currency->iso_code;
    }

    /**
     * Get context shop default language ID.
     */
    protected function getContextShopDefaultLanguageId(): int
    {
        return Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * Get shop name by ID.
     */
    protected function getContextShopName(): string
    {
        return $this->context->shop->name;
    }

    /**
     * Get coupons from Cart.
     */
    protected function getCouponsFromCart(Cart $cart): string
    {
        $rules = $cart->getCartRules();
        $coupons = array_map([$this, 'getCouponsFromCartRule'], $rules);

        return implode(', ', $coupons);
    }

    /**
     * Get coupons from cart rule.
     */
    protected function getCouponsFromCartRule(array $cart_rule): string
    {
        return '' === $cart_rule['code'] ? $cart_rule['name'] : $cart_rule['code'];
    }

    /**
     * Get DataLayer.
     */
    protected function getDataLayer(): DataLayer
    {
        static $instance;

        return $instance ?? $instance = new DataLayer($this->context->cookie, $this->name);
    }

    /**
     * Get last product from Cart.
     */
    protected function getLastProductFromCart(Cart $cart): array
    {
        $product = $cart->getLastProduct();

        return [
            'brand'    => $product['id_manufacturer'] ? $this->getManufacturerName($product['id_manufacturer']) : '',
            'category' => $product['category'],
            'id'       => $product['reference'],
            'name'     => $product['name'],
            'price'    => number_format($product['price_with_reduction'], '2', '.', ''),
            'variant'  => $product['attributes_small'] ?? '',
        ];
    }

    /**
     * Get manufacturer name.
     */
    protected function getManufacturerName(int $id_manufacturer): string
    {
        return Manufacturer::getNameById($id_manufacturer);
    }

    /**
     * Get OrderDetail.
     */
    protected function getOrderDetail(int $id_order_detail): OrderDetail
    {
        return new OrderDetail($id_order_detail);
    }

    /**
     * Get Order from reference.
     */
    protected function getOrderFromReference(string $reference): Order
    {
        return Order::getByReference($reference)->getFirst();
    }

    /**
     * Get product.
     */
    protected function getProduct(int $id_product, int $id_lang): Product
    {
        return new Product($id_product, true, $id_lang);
    }

    /**
     * Get product from ProductController.
     */
    protected function getProductFromController(ProductController $controller): array
    {
        $product = $controller->getProduct();

        return [
            'brand'    => $product->manufacturer_name ?: '',
            'category' => $product->category,
            'id'       => $product->reference,
            'name'     => $product->name,
            'price'    => number_format($product->price * (1 + $product->tax_rate / 100), '2', '.', ''),
            'variant'  => '', // There is no easy way to know the real value.
        ];
    }

    /**
     * Get product from model.
     *
     * @todo Use ?int $id_product_attribute with php >= 7.1.
     */
    protected function getProductFromModel(int $id_product, int $id_product_attribute, int $id_lang): array
    {
        $product = $this->getProduct($id_product, $id_lang);
        $price = $this->getProductPrice($id_product, $id_product_attribute);
        if ($id_product_attribute) {
            $combinations = $product->getAttributeCombinationsById($id_product_attribute, $id_lang);
        }

        return [
            'brand'    => $product->manufacturer_name ?: '',
            'category' => $product->category,
            'id'       => $product->reference,
            'name'     => $product->name,
            'price'    => number_format($price, '2', '.', ''),
            'quantity' => 1, // There is no easy way to retrieve real value.
            'variant'  => $combinations[0]['attribute_name'] ?? '',
        ];
    }

    /**
     * Get product price.
     *
     * @todo Use ?int $id_product_attribute with php >= 7.1.
     */
    protected function getProductPrice(int $id_product, int $id_product_attribute): float
    {
        return Product::getPriceStatic($id_product, true, $id_product_attribute, 6);
    }

    /**
     * Get products from Cart.
     */
    protected function getProductsFromCart(Cart $cart): array
    {
        return array_map(function ($product) {
            $brand = $product['id_manufacturer'] ? $this->getManufacturerName($product['id_manufacturer']) : '';

            return [
                'brand'    => $brand,
                'category' => $product['category'],
                'id'       => $product['reference'],
                'name'     => $product['name'],
                'price'    => number_format($product['price_with_reduction'], '2', '.', ''),
                'quantity' => $product['cart_quantity'],
                'variant'  => $product['attributes_small'] ?? '',
            ];
        }, $cart->getProducts());
    }

    /**
     * Get query from JSON.
     */
    protected function getQueryFromJson(string $json): string
    {
        return $json ? '&'.http_build_query(Tools::jsonDecode($json, true) ?? []) : '';
    }

    /**
     * Get value from $_GET/$_POST.
     */
    protected function getValue(string $key, string $default = ''): string
    {
        return Tools::getValue($key, $default);
    }

    /**
     * Wether or not a public product cart action is currently processing.
     */
    protected function isActionPublicCartProduct(): bool
    {
        return isset($this->context->cart)
               and $this->getValue('add', $this->getValue('delete'));
    }

    /**
     * Wether or dev mode is on.
     */
    protected function isModeDev(): bool
    {
        return _PS_MODE_DEV_;
    }

    /**
     * Wether or sandbox mode is on.
     */
    protected function isModeSandbox(): bool
    {
        return Tools::getIsset('SANDBOX') or $this->getConfiguration()->getOptionValue('SANDBOX');
    }

    /**
     * Wether or not admin order page is currently loading.
     */
    protected function isPageAdminOrder(): bool
    {
        return 'AdminOrders' === $this->context->controller->controller_name
               and $this->getValue('id_order');
    }

    /**
     * Wether or not public product page is currently loading.
     */
    protected function isPagePublicProduct(): bool
    {
        return 'product' === Dispatcher::getInstance()->getController();
    }

    /**
     * Set template variables.
     */
    protected function setTemplateVars(array $vars): Smarty_Internal_Data
    {
        return $this->smarty->assign($vars);
    }
}
