<?php

namespace Webkul\Iyzico\Http\Controllers;

use Iyzipay\Model\PaymentAuth;
use Iyzipay\Options as IyzicoOptions;
use Illuminate\Http\RedirectResponse;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Iyzico\Payment\Iyzico;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shop\Http\Controllers\Controller;

class IyzicoController extends Controller
{
    public function __construct(
        protected CartRepository $cartRepository,
        protected OrderRepository $orderRepository,
        protected OrderTransactionRepository $orderTransactionRepository,
        protected InvoiceRepository $invoiceRepository,
        protected Iyzico $iyzico,
    ) {}

    public function redirect()
    {
        if (! $this->iyzico->hasValidCredentials()) {
            session()->flash('error', trans('iyzico::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $cart = Cart::getCart();

        if (! $cart) {
            session()->flash('error', trans('iyzico::app.response.cart-not-found'));

            return redirect()->route('shop.checkout.cart.index');
        }

        try {
            $result = $this->iyzico->createPaymentRequest($cart);

            $status = $result->getStatus();
            $paymentPageUrl = $result->getPaymentPageUrl();
            $htmlContent = method_exists($result, 'getHtmlContent') ? $result->getHtmlContent() : null;

            if ($status === 'success') {
                if ($this->iyzico->isSecure3d() && $htmlContent) {
                    return response()->html($htmlContent);
                }

                if ($paymentPageUrl) {
                    return redirect($paymentPageUrl);
                }
            }

            $message = method_exists($result, 'getRawResult')
                ? ($result->getErrorMessage() ?? 'Payment initialization failed')
                : 'Payment initialization failed';

            session()->flash('error', trans('iyzico::app.response.payment-failed').': '.$message);

            return redirect()->route('shop.checkout.cart.index');
        } catch (\Exception $e) {
            session()->flash('error', trans('iyzico::app.response.payment-failed').': '.$e->getMessage());

            return redirect()->route('shop.checkout.cart.index');
        }
    }

    public function callback()
    {
        $token = request()->get('token');
        $conversationId = request()->get('conversationId');

        if (! $token) {
            session()->flash('error', trans('iyzico::app.response.invalid-callback'));

            return redirect()->route('shop.checkout.cart.index');
        }

        try {
            $options = $this->iyzico->createIyzicoOptions();

            $request = new \Iyzipay\Request\RetrievePaymentRequest();
            $request->setLocale($this->iyzico->getLocale() === 'EN'
                ? \Iyzipay\Model\Locale::EN
                : \Iyzipay\Model\Locale::TR);
            $request->setConversationId($conversationId);
            $request->setPaymentId($token);

            $result = \Iyzipay\Model\RetrievePayment::create($request, $options);

            if ($result->getStatus() !== 'success') {
                session()->flash('error', trans('iyzico::app.response.payment-verification-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $paymentId = $result->getPaymentId();
            $paymentStatus = $result->getPaymentStatus();

            if ($paymentStatus !== 'SUCCESS') {
                session()->flash('error', trans('iyzico::app.response.payment-failed').': '.$paymentStatus);

                return redirect()->route('shop.checkout.cart.index');
            }

            $cartId = $this->extractCartId($conversationId);

            if (! $cartId) {
                session()->flash('error', trans('iyzico::app.response.cart-not-found'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $cart = $this->cartRepository->find($cartId);

            if (! $cart || ! $cart->is_active) {
                session()->flash('error', trans('iyzico::app.response.cart-processed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            Cart::setCart($cart);
            Cart::collectTotals();

            $data = (new OrderResource($cart))->jsonSerialize();

            $data['payment']['additional'] = [
                'iyzico_payment_id' => $paymentId,
                'iyzico_conversation_id' => $conversationId,
                'iyzico_status' => $paymentStatus,
            ];

            $order = $this->orderRepository->create($data);

            $this->orderRepository->update(['status' => 'processing'], $order->id);

            if ($order->canInvoice()) {
                $invoiceData = [
                    'order_id' => $order->id,
                ];

                foreach ($order->items as $item) {
                    $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
                }

                $invoice = $this->invoiceRepository->create($invoiceData);

                $this->orderTransactionRepository->create([
                    'transaction_id' => $paymentId,
                    'status' => 'completed',
                    'type' => $order->payment->method,
                    'payment_method' => $order->payment->method,
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $order->base_grand_total,
                    'data' => json_encode([
                        'iyzico_payment_id' => $paymentId,
                        'iyzico_conversation_id' => $conversationId,
                        'iyzico_status' => $paymentStatus,
                    ]),
                ]);
            }

            Cart::deActivateCart();

            session()->flash('order_id', $order->id);
            session()->flash('success', trans('iyzico::app.response.payment-success'));

            return redirect()->route('shop.checkout.onepage.success');
        } catch (\Exception $e) {
            session()->flash('error', trans('iyzico::app.response.payment-failed').': '.$e->getMessage());

            return redirect()->route('shop.checkout.cart.index');
        }
    }

    public function cancel(): RedirectResponse
    {
        session()->flash('error', trans('iyzico::app.response.payment-cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    private function extractCartId(string $conversationId): ?int
    {
        if (preg_match('/^bagisto_(\d+)_/', $conversationId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
