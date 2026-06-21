<?php

namespace Webkul\Iyzico\Payment;

use Iyzipay\Model\Payment;
use Iyzipay\Model\ThreedsInitialize;
use Iyzipay\Options as IyzicoOptions;
use Webkul\Checkout\Facades\Cart;
use Webkul\Payment\Payment\Payment as BasePayment;

class Iyzico extends BasePayment
{
    protected $code = 'iyzico';

    protected array $endpoints = [
        'test' => 'https://sandbox-api.iyzipay.com',
        'live' => 'https://api.iyzipay.com',
    ];

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
        $isSandbox = $this->getConfigData('sandbox');

        return $isSandbox
            ? $this->getConfigData('api_test_key')
            : $this->getConfigData('api_live_key');
    }

    public function getSecretKey(): string
    {
        $isSandbox = $this->getConfigData('sandbox');

        return $isSandbox
            ? $this->getConfigData('api_test_secret_key')
            : $this->getConfigData('api_live_secret_key');
    }

    public function getBaseUrl(): string
    {
        $isSandbox = $this->getConfigData('sandbox');

        return $isSandbox
            ? $this->endpoints['test']
            : $this->endpoints['live'];
    }

    public function getIdentityNumber(): string
    {
        return $this->getConfigData('identity_number') ?? '';
    }

    public function getLocale(): string
    {
        return $this->getConfigData('locale') ?? 'TR';
    }

    public function isSecure3d(): bool
    {
        return (bool) ($this->getConfigData('secure3d') ?? true);
    }

    public function hasValidCredentials(): bool
    {
        $isSandbox = $this->getConfigData('sandbox');

        if ($isSandbox) {
            return ! empty($this->getConfigData('api_test_key'))
                && ! empty($this->getConfigData('api_test_secret_key'));
        }

        return ! empty($this->getConfigData('api_live_key'))
            && ! empty($this->getConfigData('api_live_secret_key'));
    }

    public function createIyzicoOptions(): IyzicoOptions
    {
        $options = new IyzicoOptions();
        $options->setApiKey($this->getApiKey());
        $options->setSecretKey($this->getSecretKey());
        $options->setBaseUrl($this->getBaseUrl());

        return $options;
    }

    public function createPaymentRequest($cart, $card = null): \Iyzipay\Model\ThreedsInitialize|\Iyzipay\Model\Payment
    {
        $options = $this->createIyzicoOptions();

        $request = new \Iyzipay\Request\CreatePaymentRequest();
        $request->setLocale($this->mapLocale($this->getLocale()));
        $request->setConversationId('bagisto_' . $cart->id . '_' . uniqid());
        $request->setPrice((string) $cart->base_grand_total);
        $request->setPaidPrice((string) $cart->base_grand_total);
        $request->setCurrency($this->mapCurrency($cart->currency_code));
        $request->setInstallment(0);
        $request->setPaymentChannel(\Iyzipay\Model\PaymentChannel::WEB);
        $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);

        if ($this->isSecure3d()) {
            $request->setCallbackUrl(route('iyzico.standard.callback'));
        }

        if ($card) {
            $request->setPaymentCard($this->buildPaymentCard($card));
        }

        $request->setBuyer($this->buildBuyer($cart));
        $request->setShippingAddress($this->buildShippingAddress($cart));
        $request->setBillingAddress($this->buildBillingAddress($cart));
        $request->setBasketItems($this->buildBasketItems($cart));

        if ($this->isSecure3d()) {
            return ThreedsInitialize::create($request, $options);
        }

        return Payment::create($request, $options);
    }

    private function mapLocale(string $locale): \Iyzipay\Model\Locale
    {
        return match (strtoupper($locale)) {
            'EN' => \Iyzipay\Model\Locale::EN,
            default => \Iyzipay\Model\Locale::TR,
        };
    }

    private function mapCurrency(string $currency): \Iyzipay\Model\Currency
    {
        return match (strtoupper($currency)) {
            'USD' => \Iyzipay\Model\Currency::USD,
            'EUR' => \Iyzipay\Model\Currency::EUR,
            'GBP' => \Iyzipay\Model\Currency::GBP,
            default => \Iyzipay\Model\Currency::TRY,
        };
    }

    private function buildPaymentCard($card): \Iyzipay\Model\PaymentCard
    {
        $paymentCard = new \Iyzipay\Model\PaymentCard();
        $paymentCard->setCardHolderName($card['holder_name'] ?? '');
        $paymentCard->setCardNumber($card['number'] ?? '');
        $paymentCard->setExpireMonth($card['expiry_month'] ?? '');
        $paymentCard->setExpireYear($card['expiry_year'] ?? '');
        $paymentCard->setCvc($card['cvv'] ?? '');
        $paymentCard->setRegisterCard(0);

        return $paymentCard;
    }

    private function buildBuyer($cart): \Iyzipay\Model\Buyer
    {
        $billing = $cart->billing_address ?? $cart->shipping_address;

        $buyer = new \Iyzipay\Model\Buyer();
        $buyer->setId((string) $cart->customer_id);
        $buyer->setName($billing->first_name ?? '');
        $buyer->setSurname($billing->last_name ?? '');
        $buyer->setGsmNumber($billing->phone ?? '');
        $buyer->setEmail($cart->customer_email ?? '');
        $buyer->setIdentityNumber($this->getIdentityNumber());
        $buyer->setLastLoginDate(now()->format('Y-m-d H:i:s'));
        $buyer->setRegistrationDate(now()->format('Y-m-d H:i:s'));
        $buyer->setRegistrationAddress($billing->address ?? '');
        $buyer->setIp(request()->ip());
        $buyer->setCity($billing->city ?? '');
        $buyer->setCountry($billing->country ?? '');
        $buyer->setZipCode($billing->postcode ?? '');

        return $buyer;
    }

    private function buildShippingAddress($cart): \Iyzipay\Model\Address
    {
        $shipping = $cart->shipping_address ?? $cart->billing_address;

        $address = new \Iyzipay\Model\Address();
        $address->setContactName(($shipping->first_name ?? '') . ' ' . ($shipping->last_name ?? ''));
        $address->setCity($shipping->city ?? '');
        $address->setCountry($shipping->country ?? '');
        $address->setAddress($shipping->address ?? '');
        $address->setZipCode($shipping->postcode ?? '');

        return $address;
    }

    private function buildBillingAddress($cart): \Iyzipay\Model\Address
    {
        $billing = $cart->billing_address ?? $cart->shipping_address;

        $address = new \Iyzipay\Model\Address();
        $address->setContactName(($billing->first_name ?? '') . ' ' . ($billing->last_name ?? ''));
        $address->setCity($billing->city ?? '');
        $address->setCountry($billing->country ?? '');
        $address->setAddress($billing->address ?? '');
        $address->setZipCode($billing->postcode ?? '');

        return $address;
    }

    private function buildBasketItems($cart): array
    {
        $basketItems = [];

        foreach ($cart->items as $item) {
            $basketItem = new \Iyzipay\Model\BasketItem();
            $basketItem->setId((string) $item->id);
            $basketItem->setName($item->product->name ?? '');
            $basketItem->setCategory1($item->product->category->name ?? 'Genel');
            $basketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
            $basketItem->setPrice((string) $item->base_price);
            $basketItems[] = $basketItem;
        }

        if ($cart->base_shipping_amount > 0) {
            $basketItem = new \Iyzipay\Model\BasketItem();
            $basketItem->setId('shipping');
            $basketItem->setName('Shipping');
            $basketItem->setCategory1('Shipping');
            $basketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
            $basketItem->setPrice((string) $cart->base_shipping_amount);
            $basketItems[] = $basketItem;
        }

        return $basketItems;
    }
}
