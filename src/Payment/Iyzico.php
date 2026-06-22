<?php

namespace Webkul\Iyzico\Payment;

use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Iyzico\Gateway;
use Webkul\Payment\Payment\Payment as BasePayment;

class Iyzico extends BasePayment
{
    protected $code = 'iyzico';

    public function getRedirectUrl(): string
    {
        return route('iyzico.standard.redirect');
    }

    public function isAvailable(): bool
    {
        return parent::isAvailable() && $this->hasValidCredentials();
    }

    public function getTitle(): string
    {
        return $this->getConfigData('title') ?? trans('iyzico::app.title');
    }

    public function getDescription(): string
    {
        return $this->getConfigData('description') ?? trans('iyzico::app.description');
    }

    public function getApiKey(): string
    {
        return $this->isSandbox()
            ? $this->getConfigData('api_test_key')
            : $this->getConfigData('api_live_key');
    }

    public function getSecretKey(): string
    {
        return $this->isSandbox()
            ? $this->getConfigData('api_test_secret_key')
            : $this->getConfigData('api_live_secret_key');
    }

    public function isSandbox(): bool
    {
        return (bool) $this->getConfigData('sandbox');
    }

    public function getIdentityNumber(): string
    {
        return $this->getConfigData('identity_number') ?? '';
    }

    public function getLocale(): string
    {
        return $this->getConfigData('locale') ?? 'TR';
    }

    /**
     * @todo Bu config alanı şu an kullanılmıyor. Şu anki entegrasyon iyzico'nun barındırdığı
     * Checkout Form sayfasına yönlendirir (3DS dahil, iyzico tarafında şeffaf şekilde
     * yönetilir). Kart bilgisini doğrudan kendi checkout sayfamızda toplayıp
     * Omnipay\Iyzico\Gateway::purchase() / completePurchase() (direkt/embedded 3DS) ile
     * çalışan bir mod eklenirse bu ayar devreye girecek.
     */
    public function isSecure3d(): bool
    {
        return (bool) ($this->getConfigData('secure3d') ?? true);
    }

    public function hasValidCredentials(): bool
    {
        if ($this->isSandbox()) {
            return ! empty($this->getConfigData('api_test_key'))
                && ! empty($this->getConfigData('api_test_secret_key'));
        }

        return ! empty($this->getConfigData('api_live_key'))
            && ! empty($this->getConfigData('api_live_secret_key'));
    }

    public function createGateway(): Gateway
    {
        $gateway = new Gateway();
        $gateway->setApiKey($this->getApiKey());
        $gateway->setSecretKey($this->getSecretKey());
        $gateway->setTestMode($this->isSandbox());
        $gateway->setIdentityNumber($this->getIdentityNumber());
        $gateway->setLocale($this->getLocale());

        return $gateway;
    }

    /**
     * Alıcıyı iyzico'nun barındırdığı ödeme sayfasına (Checkout Form) yönlendirmek için
     * ödemeyi başlatır. Kart bilgisi hiçbir zaman bizim sunucumuza dokunmaz.
     */
    public function checkout($cart): ResponseInterface
    {
        return $this->createGateway()->checkout([
            'amount' => (string) $cart->base_grand_total,
            'currency' => $cart->currency_code,
            'conversationId' => $this->buildConversationId($cart),
            'basketId' => 'bagisto_'.$cart->id,
            'returnUrl' => route('iyzico.standard.callback'),
            'card' => $this->buildCard($cart),
            'items' => $this->buildItems($cart),
        ])->send();
    }

    /**
     * Callback'ten dönen token ile ödeme sonucunu iyzico'dan doğrudan iyzico'dan sorgular
     * (sonucu asla yalnızca callback'teki query/post verisine güvenerek kabul etme).
     */
    public function checkoutStatus(string $token): ResponseInterface
    {
        return $this->createGateway()->checkoutStatus([
            'token' => $token,
        ])->send();
    }

    public function buildConversationId($cart): string
    {
        return 'bagisto_'.$cart->id.'_'.uniqid();
    }

    public function extractCartId(string $conversationId): ?int
    {
        if (preg_match('/^bagisto_(\d+)_/', $conversationId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Bagisto cart adreslerini iyzico Checkout Form Initialize için
     * buyer/shippingAddress/billingAddress alanlarına çevirir.
     */
    private function buildCard($cart): array
    {
        $billing = $cart->billing_address;

        if (! $billing) {
            throw new \RuntimeException('Billing address is required for iyzico checkout.');
        }

        $card = [
            'firstName' => $billing->first_name,
            'lastName' => $billing->last_name,
            'email' => $billing->email ?? $cart->customer_email,
            'phone' => $billing->phone,
            'billingAddress1' => $billing->address,
            'billingCity' => $billing->city,
            'billingPostcode' => $billing->postcode,
            'billingCountry' => $billing->country,
        ];

        $shipping = $cart->shipping_address;

        if ($shipping) {
            $card['shippingFirstName'] = $shipping->first_name;
            $card['shippingLastName'] = $shipping->last_name;
            $card['shippingAddress1'] = $shipping->address;
            $card['shippingCity'] = $shipping->city;
            $card['shippingPostcode'] = $shipping->postcode;
            $card['shippingCountry'] = $shipping->country;
        }

        return $card;
    }

    /**
     * Bagisto cart kalemlerini Omnipay'in standart 'items' parametresine çevirir.
     * iyzico'nun BasketItem'ında miktar alanı yok; bu yüzden satır toplamını
     * (base_total) tek bir kalem fiyatı olarak gönderiyoruz, birim fiyatı değil —
     * yoksa quantity > 1 olan kalemlerde basket toplamı sepet toplamından sapar.
     */
    private function buildItems($cart): array
    {
        $items = [];

        foreach ($cart->items as $item) {
            $items[] = [
                'name' => $item->product->name ?? ('Item #'.$item->id),
                'description' => $item->product->category->name ?? 'Genel',
                'price' => (string) $item->base_total,
            ];
        }

        if ($cart->base_shipping_amount > 0) {
            $items[] = [
                'name' => 'Shipping',
                'description' => 'Shipping',
                'price' => (string) $cart->base_shipping_amount,
            ];
        }

        return $items;
    }
}
