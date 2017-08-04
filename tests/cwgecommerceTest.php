<?php

use PHPUnit\Framework\TestCase;

class CWGEcommerceTest extends TestCase
{
    const REQUIRED_HOOKS = [
        'actionAdminModulesOptionsModifier',
        'actionCartSave',
        'actionObjectOrderPaymentAddAfter',
        'actionOrderSlipAdd',
        'displayBackOfficeHeader',
        'displayHeader',
        'displayOrderConfirmation',
    ];
    const REQUIRED_OPTIONS = ['GTM_ID', 'SANDBOX'];
    const REQUIRED_PROPERTIES = [
        'author',
        'confirmUninstall',
        'description',
        'displayName',
        'name',
        'ps_versions_compliancy',
        'tab',
        'version',
    ];

    /**
     * This setup fixes an obscure problem when using stdClass as a base mock,
     * where its methods wouldn't be configurable.
     */
    public function setUp()
    {
        $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('Cart')
            ->setMethods([
                'getCartRules',
                'getLastProduct',
                'getProducts',
            ])
            ->getMock();
    }

    /**
     * New instance should have required properties.
     */
    public function testInstanceHasRequiredProperties()
    {
        $module = new CWGEcommerce();
        foreach (self::REQUIRED_PROPERTIES as $prop) {
            $this->assertNotNull($module->$prop);
        }
    }

    /**
     * CWGEcommerce::install() should add required hooks.
     */
    public function testInstall()
    {
        $mock = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods(['addHooks'])
            ->getMock();

        $mock
            ->expects($this->once())
            ->method('addHooks')
            ->with($this->equalTo(self::REQUIRED_HOOKS))
            ->willReturn(true);

        $mock->install();
    }

    /**
     * CWBundle::uninstall() should clear cache and remove required options
     * values.
     */
    public function testUninstall()
    {
        $mock_config = $this->createMock('CW\Module\Configuration');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                '_clearCache',
                'getConfiguration',
            ])
            ->getMock();

        $mock_module->method('getConfiguration')->willReturn($mock_config);

        $mock_module
            ->expects($this->once())
            ->method('_clearCache')
            ->with($this->equalTo('*'));
        $mock_config
            ->expects($this->once())
            ->method('removeOptionsValues')
            ->with($this->equalTo(static::REQUIRED_OPTIONS));

        $mock_module->uninstall();
    }

    /**
     * CWGEcommerce::hookDisplayHeader() should display nothing if Google Tag
     * Manager container ID is not set.
     */
    public function testDisplayContainerWithoutContainerID()
    {
        $mock_config = $this->createMock('CW\Module\Configuration');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods(['getConfiguration'])
            ->getMock();

        $mock_module->method('getConfiguration')->willReturn($mock_config);
        $mock_config->method('getOptionValue')->willReturn('');

        $this->assertSame('', $mock_module->hookDisplayHeader([]));
    }

    /**
     * CWGEcommerce::hookDisplayHeader() should display nothing if Prestashop is
     * in development mode without sandbox mode.
     */
    public function testDisplayContainerWithDevModeWithoutSandbox()
    {
        $mock_config = $this->createMock('CW\Module\Configuration');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getConfiguration',
                'isModeDev',
            ])
            ->getMock();

        $mock_module->method('getConfiguration')->willReturn($mock_config);
        $mock_config->method('getOptionValue')->will($this->returnCallback(function ($config) {
            return 'GTM_ID' === $config ? 'GTM_ID' : '';
        }));
        $mock_module->method('isModeDev')->willReturn(true);

        $this->assertSame('', $mock_module->hookDisplayHeader([]));
    }

    /**
     * CWGEcommerce::hookDisplayHeader() should not set required template
     * variables if template is already cached.
     */
    public function testDisplayContainerWithCache()
    {
        $mock_config = $this->createMock('CW\Module\Configuration');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getConfiguration',
                'isCached',
                'isModeDev',
                'setTemplateVars',
            ])
            ->getMock();

        $mock_module->method('getConfiguration')->willReturn($mock_config);
        $mock_config->method('getOptionValue')->willReturn('GTM_ID');
        $mock_module->method('isCached')->willReturn(true);
        $mock_module->method('isModeDev')->willReturn(false);

        $mock_module->expects($this->never())->method('setTemplateVars');

        $mock_module->hookDisplayHeader([]);
    }

    /**
     * CWGEcommerce::hookDisplayBackOfficeHeader() should display nothing if
     * page is not admin orders.
     */
    public function testDisplayContainerAdminPagesNotOrderPage()
    {
        $module = new CWGEcommerce();
        $module->context = new stdClass();
        $module->context->controller = new stdClass();
        $module->context->controller->controller_name = 'NotAdminOrdersController';

        $this->assertSame('', $module->hookDisplayBackOfficeHeader([]));
    }

    /**
     * CWGEcommerce::hookDisplayBackOfficeHeader() should display nothing if no
     * data is set in cookie.
     */
    public function testDisplayContainerAdminPagesEmptyData()
    {
        $module = new CWGEcommerce();
        $module->context = new stdClass();
        $module->context->controller = new stdClass();
        $module->context->controller->controller_name = 'AdminOrdersController';
        $module->context->cookie = new stdClass();
        $module->context->cookie->{$module->name} = null;

        $this->assertSame('', $module->hookDisplayBackOfficeHeader([]));
    }

    /**
     * CWGEcommerce::hookDisplayHeader() should set required template variables.
     */
    public function testPublicDisplayContainer()
    {
        $mock_config = $this->createMock('CW\Module\Configuration');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getConfiguration',
                'isCached',
                'isModeDev',
                'isPagePublicProduct',
                'setTemplateVars',
            ])
            ->getMock();
        $mock_module->context = new stdClass();
        $mock_module->context->cookie = $this->createMock('Cookie');
        $mock_smarty = $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('Smarty_Internal_Data')
            ->getMock();

        $mock_module->method('getConfiguration')->willReturn($mock_config);
        $mock_config->method('getOptionValue')->willReturn($containerId = 'GTM_ID');
        $mock_module->method('isCached')->willReturn(false);
        $mock_module->method('isModeDev')->willReturn(false);
        $mock_module->method('isPagePublicProduct')->willReturn(false);

        $mock_module
            ->expects($this->once())
            ->method('setTemplateVars')
            ->with($this->equalTo([
                'containerId'    => $containerId,
                'dataLayer'      => '',
                'dataLayerQuery' => '',
            ]))
            ->willReturn($mock_smarty);

        $mock_module->hookDisplayHeader([]);
    }

    /**
     * CWGEcommerce::hookDisplayHeader() should add a product view to data layer
     * on public product page, and set template variables with expected values.
     */
    public function testProductDetailsView()
    {
        $mock_config = $this->createMock('CW\Module\Configuration');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getConfiguration',
                'isCached',
                'isModeDev',
                'isPagePublicProduct',
                'setTemplateVars',
            ])
            ->getMock();
        $mock_module->context = new stdClass();
        $mock_module->context->cookie = $this->createMock('Cookie');
        $mock_module->context->controller = $this
            ->getMockBuilder('ProductController')
            ->setMethods(['getProduct'])
            ->getMock();
        $mock_product = new stdClass();
        $mock_smarty = $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('Smarty_Internal_Data')
            ->getMock();

        $mock_module->method('getConfiguration')->willReturn($mock_config);
        $mock_config->method('getOptionValue')->willReturn($containerId = 'GTM_ID');
        $mock_module->method('isModeDev')->willReturn(false);
        $mock_module->method('isCached')->willReturn(false);
        $mock_module->method('isPagePublicProduct')->willReturn(true);
        $mock_module->context->controller->method('getProduct')->willReturn($mock_product);
        $mock_product->category = 'category';
        $mock_product->reference = 'reference';
        $mock_product->manufacturer_name = 'brand';
        $mock_product->name = 'name';
        $mock_product->price = 100;
        $mock_product->tax_rate = 20;

        $dataLayer = '{"ecommerce":{'.
            '"detail":{"products":{'.
                '"brand":"brand",'.
                '"category":"category",'.
                '"id":"reference",'.
                '"name":"name",'.
                '"price":"120.00",'.
                '"variant":""'.
            '}}'.
        '}}';
        $query = '&'.http_build_query(json_decode($dataLayer, true));

        $mock_module
            ->expects($this->once())
            ->method('setTemplateVars')
            ->with($this->equalTo([
                'containerId'    => $containerId,
                'dataLayer'      => $dataLayer,
                'dataLayerQuery' => $query,
            ]))
            ->willReturn($mock_smarty);

        $mock_module->hookDisplayHeader([]);
    }

    /**
     * CWGEcommerce::hookActionCartSave() should add nothing to data layer if
     * cart action is initiated by admin.
     */
    public function testCartActionNotFromUser()
    {
        $mock = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getDataLayer',
                'getValue',
            ])
            ->getMock();
        $mock->context = new stdClass();
        $mock->context->cart = new stdClass();

        $mock->method('getValue')->willReturn('');

        $mock->expects($this->never())->method('getDataLayer');

        $mock->hookActionCartSave([]);
    }

    /**
     * CWGEcommerce::hookActionCartSave() should add nothing to data layer if
     * it's not an add or remove product action.
     */
    public function testCartActionNotProduct()
    {
        $mock = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getDataLayer',
                'getValue',
            ])
            ->getMock();

        $mock->method('getValue')->willReturn('');

        $mock->expects($this->never())->method('getDataLayer');

        $mock->hookActionCartSave([]);
    }

    /**
     * CWGEcommerce::hookActionCartSave() should add expected action to data
     * layer when adding a product to cart or increasing its quantity.
     */
    public function testCartActionAddProduct()
    {
        $mock_data_layer = $this->createMock('DataLayer');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getContextCurrencyCode',
                'getDataLayer',
                'getValue',
                'isActionPublicCartProduct',
            ])
            ->getMock();
        $mock_module->context = new stdClass();
        $mock_module->context->cart = $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('Cart')
            ->setMethods(['getLastProduct'])
            ->getMock();

        $mock_module->method('getContextCurrencyCode')->willReturn($currency = 'EUR');
        $mock_module->method('getDataLayer')->willReturn($mock_data_layer);
        $mock_module->method('getValue')->will($this->onConsecutiveCalls(
            $id_product = '1',
            $add        = '1',
            $op         = '',
            $qty        = '3'
        ));
        $mock_module->method('isActionPublicCartProduct')->willReturn(true);
        $mock_module->context->cart->method('getLastProduct')->willReturn(
            $product = [
                'attributes_small'     => 'attributes_small',
                'category'             => 'category',
                'id_manufacturer'      => 0,
                'name'                 => 'name',
                'price_with_reduction' => 100.00,
                'reference'            => 'reference',
            ]
        );

        $mock_data_layer
            ->expects($this->once())
            ->method('addCartAction')
            ->with(
                $this->equalTo('add'),
                $this->equalTo([
                    'brand'    => '',
                    'category' => $product['category'],
                    'id'       => $product['reference'],
                    'name'     => $product['name'],
                    'price'    => $product['price_with_reduction'],
                    'quantity' => $qty,
                    'variant'  => $product['attributes_small'],
                ]),
                $this->equalTo($currency)
            );

        $mock_module->hookActionCartSave([]);
    }

    /**
     * CWGEcommerce::hookActionCartSave() should add expected action to data layer
     * when decreasing a product quantity.
     */
    public function testCartActionRemoveProduct()
    {
        $mock_data_layer = $this->createMock('DataLayer');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getContextCurrencyCode',
                'getDataLayer',
                'getValue',
                'isActionPublicCartProduct',
            ])
            ->getMock();
        $mock_module->context = new stdClass();
        $mock_module->context->cart = $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('Cart')
            ->setMethods(['getLastProduct'])
            ->getMock();

        $mock_module->method('getContextCurrencyCode')->willReturn($currency = 'EUR');
        $mock_module->method('getDataLayer')->willReturn($mock_data_layer);
        $mock_module->method('getValue')->will($this->onConsecutiveCalls(
            $id_product = '1',
            $add        = '1',
            $op         = 'down',
            $qty        = '5'
        ));
        $mock_module->method('isActionPublicCartProduct')->willReturn(true);
        $mock_module->context->cart->method('getLastProduct')->willReturn(
            $product = [
                'attributes_small'     => 'attributes_small',
                'category'             => 'category',
                'id_manufacturer'      => 0,
                'name'                 => 'name',
                'price_with_reduction' => '100.00',
                'reference'            => 'reference',
            ]
        );

        $mock_data_layer
            ->expects($this->once())
            ->method('addCartAction')
            ->with(
                $this->equalTo('remove'),
                $this->equalTo([
                    'brand'    => '',
                    'category' => $product['category'],
                    'id'       => $product['reference'],
                    'name'     => $product['name'],
                    'price'    => $product['price_with_reduction'],
                    'quantity' => $qty,
                    'variant'  => $product['attributes_small'],
                ]),
                $this->equalTo($currency)
            );

        $mock_module->hookActionCartSave([]);
    }

    /**
     * CWGEcommerce::hookActionCartSave() should add expected action to data
     * layer when removing product from cart.
     */
    public function testCartActionDeleteProduct()
    {
        $mock_data_layer = $this->createMock('DataLayer');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getContextCurrencyCode',
                'getContextShopDefaultLanguageId',
                'getDataLayer',
                'getProduct',
                'getProductPrice',
                'getValue',
                'isActionPublicCartProduct',
            ])
            ->getMock();
        $mock_product = $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('Product')
            ->setMethods(['getAttributeCombinationsById'])
            ->getMock();

        $mock_module->method('getContextCurrencyCode')->willReturn($currency = 'EUR');
        $mock_module->method('getContextShopDefaultLanguageId')->willReturn($id_lang = 1);
        $mock_module->method('getDataLayer')->willReturn($mock_data_layer);
        $mock_module->method('getProduct')->willReturn($mock_product);
        $mock_module->method('getProductPrice')->willReturn($price = 100.00);
        $mock_module->method('getValue')->will($this->onConsecutiveCalls(
            $id_product = '1',
            $add = '',
            $delete = '1',
            $ipa = '1'
        ));
        $mock_module->method('isActionPublicCartProduct')->willReturn(true);
        $mock_product->method('getAttributeCombinationsById')->willReturn([
            $combination = ['attribute_name' => 'variant'],
        ]);
        $mock_product->manufacturer_name = 'manufacturer';
        $mock_product->category          = 'category';
        $mock_product->reference         = 'reference';
        $mock_product->name              = 'name';

        $mock_data_layer
            ->expects($this->once())
            ->method('addCartAction')
            ->with(
                $this->equalTo('remove'),
                $this->equalTo([
                    'brand'    => $mock_product->manufacturer_name,
                    'category' => $mock_product->category,
                    'id'       => $mock_product->reference,
                    'name'     => $mock_product->name,
                    'price'    => $price,
                    'quantity' => $delete,
                    'variant'  => $combination['attribute_name'],
                ]),
                $this->equalTo($currency)
            );

        $mock_module->hookActionCartSave([]);
    }

    /**
     * CWGEcommerce::hookDisplayOrderConfirmation() should add expected action
     * to data layer when an order is confirmed.
     */
    public function testConversion()
    {
        $mock_data_layer = $this->createMock('DataLayer');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getContextCurrencyCode',
                'getDataLayer',
            ])
            ->getMock();
        $mock_order = new stdClass();

        $mock_module->method('getContextCurrencyCode')->willReturn($currency = 'EUR');
        $mock_module->method('getDataLayer')->willReturn($mock_data_layer);
        $mock_order->id = 1;

        $mock_data_layer
            ->expects($this->once())
            ->method('addConversion')
            ->with(
                $this->equalTo($mock_order->id),
                $this->equalTo('100.00'),
                $this->equalTo($currency)
            );

        $mock_module->hookDisplayOrderConfirmation([
            'objOrder'     => $mock_order,
            'total_to_pay' => 100.00000,
        ]);
    }

    /**
     * CWGEcommerce::ctionObjectOrderPaymentAddAfter() should add expected
     * action to data layer when an order payment is confirmed.
     */
    public function testPurchase()
    {
        $mock_cart = $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('Cart')
            ->setMethods([
                'getCartRules',
                'getProducts',
            ])
            ->getMock();
        $mock_data_layer = $this->createMock('DataLayer');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getCart',
                'getContextCurrencyCode',
                'getContextShopName',
                'getDataLayer',
                'getOrderFromReference',
            ])
            ->getMock();
        $mock_order = $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('Order')
            ->getMock();
        $mock_order_payment = new stdClass();

        $mock_cart->method('getCartRules')->willReturn([
            ['code' => 'PROMO-CODE-1'],
            ['code' => 'PROMO-CODE-2'],
        ]);
        $mock_cart->method('getProducts')->willReturn([
            $product = [
                'attributes_small'     => 'variant',
                'category'             => 'category',
                'cart_quantity'        => 2,
                'id_manufacturer'      => 0,
                'name'                 => 'name',
                'price_with_reduction' => 100.00,
                'reference'            => 'id',
            ],
        ]);
        $mock_module->method('getCart')->willReturn($mock_cart);
        $mock_module->method('getContextCurrencyCode')->willReturn($currency = 'EUR');
        $mock_module->method('getContextShopName')->willReturn($shop_name = 'My shop name');
        $mock_module->method('getDataLayer')->willReturn($mock_data_layer);
        $mock_module->method('getOrderFromReference')->willReturn($mock_order);
        $mock_order->id = 1;
        $mock_order->id_cart = 123;
        $mock_order->total_paid_tax_incl = 120.00;
        $mock_order->total_paid_tax_excl = 100.00;
        $mock_order->total_shipping_tax_incl = 20;
        $mock_order_payment->order_reference = 'REFERENCE';

        $mock_data_layer
            ->expects($this->once())
            ->method('addPurchase')
            ->with(
                $this->equalTo([
                    'id'          => $mock_order->id,
                    'affiliation' => $shop_name,
                    'revenue'     => $mock_order->total_paid_tax_incl,
                    'tax'         => $mock_order->total_paid_tax_incl - $mock_order->total_paid_tax_excl,
                    'shipping'    => $mock_order->total_shipping_tax_incl,
                    'coupon'      => 'PROMO-CODE-1, PROMO-CODE-2',
                ]),
                $this->equalTo([[
                    'brand'    => '',
                    'category' => $product['category'],
                    'id'       => $product['reference'],
                    'name'     => $product['name'],
                    'price'    => $product['price_with_reduction'],
                    'quantity' => $product['cart_quantity'],
                    'variant'  => $product['attributes_small'],
                ]]),
                $this->equalTo($currency)
            );

        $mock_module->hookActionObjectOrderPaymentAddAfter(['object' => $mock_order_payment]);
    }

    /**
     * CWGEcommerce::hookActionOrderSlipAdd() should add expected action to data
     * layer when an order slip is added.
     */
    public function testPartialRefund()
    {
        $mock_data_layer = $this->createMock('DataLayer');
        $mock_module = $this
            ->getMockBuilder('CWGEcommerce')
            ->setMethods([
                'getDataLayer',
                'getOrderDetail',
            ])
            ->getMock();
        $mock_order = new stdClass();
        $mock_order_detail = $this
            ->getMockBuilder('stdClass')
            ->setMockClassName('OrderDetail')
            ->getMock();

        $mock_module->method('getDataLayer')->willReturn($mock_data_layer);
        $mock_module->method('getOrderDetail')->willReturn($mock_order_detail);
        $mock_order->id = 1;
        $mock_order_detail->product_reference = 'PRODUCT.REFERENCE';

        $mock_data_layer
            ->expects($this->once())
            ->method('addPartialRefund')
            ->with(
                $this->equalTo($mock_order->id),
                $this->equalTo(array_fill(0, $count = 5, ['id' => 'PRODUCT.REFERENCE', 'quantity' => $qty = 2]))
            );

        $mock_module->hookActionOrderSlipAdd([
            'order'       => $mock_order,
            'productList' => array_fill(0, $count, []),
            'qtyList'     => array_fill(0, $count, $qty),
        ]);
    }
}
