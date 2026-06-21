<?php

namespace Webkul\Iyzico\Providers;

use Illuminate\Support\ServiceProvider;

class IyzicoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'iyzico');

        $this->exemptCallbackFromCsrf();
    }

    /**
     * iyzico'nun Checkout Form sonrası gönderdiği callback isteği bizim Laravel
     * uygulamamızdan alınmış bir CSRF token taşımaz; bu route'u 'web' grubundaki
     * CSRF doğrulamasından muaf tutmamız gerekiyor.
     */
    protected function exemptCallbackFromCsrf(): void
    {
        $csrfMiddlewareClass = \App\Http\Middleware\VerifyCsrfToken::class;

        if (! class_exists($csrfMiddlewareClass)) {
            return;
        }

        $this->app->resolving($csrfMiddlewareClass, function ($middleware) {
            $middleware->except[] = 'iyzico/callback';
        });
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/payment-methods.php', 'payment_methods'
        );
    }
}
