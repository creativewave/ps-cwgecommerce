<?php

class DataLayer
{
    /**
     * @var Cookie
     */
    protected $cookie;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $namespace;

    public function __construct(Cookie $cookie, string $namespace)
    {
        $this->cookie = $cookie;
        $this->namespace = $namespace;

        if (isset($this->cookie->{$this->namespace})) {
            $this->data = Tools::jsonDecode($this->cookie->{$this->namespace}, true);
        }
    }

    /**
     * Add product (details) view.
     */
    public function addProductView(array $product): self
    {
        $this->data['ecommerce']['detail']['products'] = $product;

        return $this->write();
    }

    /**
     * Add Analytics product cart action.
     */
    public function addCartAction(string $action, array $product, string $currency): self
    {
        $this->data['ecommerce']['currencyCode'] = Tools::safeOutput($currency);
        $this->data['ecommerce'][$action]['products'][] = $product;

        return $this->addEvent('add' === $action ? 'addToCart' : 'removeFromCart');
    }

    /**
     * Add Adwords conversion.
     */
    public function addConversion(int $id_order, int $value, string $currency): self
    {
        $this->data['google_conversion_order_id'] = $id_order;
        $this->data['google_conversion_value']    = number_format($value, '2', '.', '');
        $this->data['google_conversion_currency'] = Tools::safeOutput($currency);

        return $this->addEvent('orderConfirmation');
    }

    /**
     * Add Analytics purchase.
     */
    public function addPurchase(array $purchase, array $products, string $currency): self
    {
        $this->data['ecommerce']['currencyCode'] = $currency;
        $this->data['ecommerce']['purchase']['actionField'] = $purchase;
        $this->data['ecommerce']['purchase']['products'] = $products;

        return $this->addEvent('orderUpdate', true);
    }

    /**
     * Add Analytics refund.
     */
    public function addRefund(int $id_order): self
    {
        $this->data['ecommerce']['refund']['actionField']['id'] = $id_order;

        return $this->addEvent('orderUpdate', false);
    }

    /**
     * Add Analytics partial refund.
     */
    public function addPartialRefund(int $id_order, array $products): self
    {
        $this->data['ecommerce']['refund']['actionField']['id'] = $id_order;
        $this->data['ecommerce']['refund']['actionField']['products'] = $products;

        return $this->addEvent('orderUpdate', true);
    }

    /**
     * Add event.
     */
    public function addEvent(string $name, bool $from_admin = false): self
    {
        $this->data['event'] = $name;
        $this->data['nonInteraction'] = $from_admin;

        return $this->write();
    }

    /**
     * Flush data.
     */
    public function flush(): string
    {
        if (isset($this->cookie->{$this->namespace})) {
            $dataLayer = $this->cookie->{$this->namespace};
            $this->cookie->__unset($this->namespace);
        }

        return $dataLayer ?? '';
    }

    /**
     * Wether or not there is data enqueued.
     */
    public function hasData(): bool
    {
        return !empty($this->data);
    }

    /**
     * Write data in Cookie.
     */
    protected function write(): self
    {
        $this->cookie->{$this->namespace} = Tools::jsonEncode($this->data);

        return $this;
    }
}
