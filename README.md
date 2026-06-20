# Bagisto Iyzico Payment Gateway

Iyzico payment gateway integration for [Bagisto](https://www.bagisto.com/) e-commerce platform.

## Requirements

- PHP >= 8.1
- Bagisto >= 2.0
- iyzico/iyzipay-php ^2.0

## Installation

### Via Composer (Recommended)

```bash
composer require yellow-three/bagisto-iyzico
```

Then register the service provider in `config/app.php`:

```php
'providers' => [
    // ...
    Webkul\Iyzico\Providers\IyzicoServiceProvider::class,
    Webkul\Iyzico\Providers\ModuleServiceProvider::class,
],
```

### Via Path Repository

Add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/bagisto-iyzico"
        }
    ],
    "require": {
        "yellow-three/bagisto-iyzico": "*"
    }
}
```

Then run:

```bash
composer require yellow-three/bagisto-iyzico
```

## Configuration

1. Go to **Admin > Configuration > Sales > Payment Methods**
2. Find **Iyzico** in the payment methods list
3. Enable the payment method
4. Fill in the required credentials:

| Field | Description |
|---|---|
| Sandbox | Enable/disable test mode |
| API Test Key | Sandbox API key |
| API Test Secret Key | Sandbox secret key |
| API Live Key | Production API key |
| API Live Secret Key | Production secret key |
| Identity Number | Buyer TCKN (for test: `11111111111`) |
| Locale | TR or EN |
| Secure 3D | Enable/disable 3D Secure |

## Sandbox Testing

1. Register at [sandbox-merchant.iyzipay.com](https://sandbox-merchant.iyzipay.com/auth)
2. Login with SMS code `123456`
3. Get API keys from Settings > API Keys
4. Use test cards from [dev.iyzipay.com/tr/test-kartlari](https://dev.iyzipay.com/tr/test-kartlari)
5. 3DS password: `283126`

### Test Cards

| Card Type | Number |
|---|---|
| Visa (Success) | `4111111111111111` |
| Visa (Fail) | `4000000000000002` |
| Mastercard (Success) | `5528790000000008` |
| Mastercard (Fail) | `5100000000000015` |

## Payment Flow

1. Customer selects Iyzico at checkout
2. Clicks "Place Order"
3. Redirected to iyzico 3DS page (if secure3d enabled)
4. Completes 3DS verification
5. iyzico calls back to `/iyzico/callback`
6. Order is created and invoice is generated

## Supported Features

- 3D Secure payments
- Non-3D Secure payments
- Sandbox/Live mode switching
- Multi-currency support (TRY, USD, EUR, GBP)
- Multi-language support (TR, EN)

## License

MIT License. See [LICENSE](LICENSE) for details.
