<p align="center">
    <a href="https://sylius.com" target="_blank">
        <picture>
          <source media="(prefers-color-scheme: dark)" srcset="https://media.sylius.com/sylius-logo-800-dark.png">
          <source media="(prefers-color-scheme: light)" srcset="https://media.sylius.com/sylius-logo-800.png">
          <img alt="Sylius Logo." src="https://media.sylius.com/sylius-logo-800.png">
        </picture>
    </a>
</p>

<h1 align="center">Mudrak One Page Checkout Plugin</h1>

<p align="center">A Sylius plugin that replaces the default multi-step checkout with a single-page checkout experience using Symfony UX Live Components.</p>

## Features

- **Single-page checkout** — Address, shipping, payment, and order completion on one page
- **Live Components** — Real-time updates without full page reloads (powered by Symfony UX Live Components)
- **Automatic order processing** — Shipping and payment selections update the order in real-time
- **Simplified checkout flow** — Modified state machine allows completing the order directly from the cart state
- **Sylius Twig Hooks** — Modular components rendered via Sylius Twig Hooks for easy customization
- **Bootstrap 5** — Styled with Bootstrap 5 form themes

## Requirements

- PHP ^8.2
- Sylius ^2.0
- Symfony UX Live Component (included with Sylius 2.0)

---

## Installation

### Step 1: Require the plugin via Composer

```bash
composer require mudrak/one-page-checkout-plugin
```

### Step 2: Register the bundle

Add the plugin to your `config/bundles.php`:

```php
return [
    // ... other bundles
    Mudrak\OnePageCheckoutPlugin\MudrakOnePageCheckoutPlugin::class => ['all' => true],
];
```

### Step 3: Import plugin configuration

Create `config/packages/mudrak_one_page_checkout.yaml`:

```yaml
imports:
    - { resource: "@MudrakOnePageCheckoutPlugin/config/config.yaml" }
```

### Step 4: Import routes

Create `config/routes/mudrak_one_page_checkout.yaml`:

```yaml
mudrak_one_page_checkout_shop:
    resource: "@MudrakOnePageCheckoutPlugin/config/routes/shop.yaml"
```

### Step 5: Ensure Live Component routes are loaded

Sylius 2.0 should already include these, but verify that your project has the Live Component route loaded (typically in `config/routes/ux_live_component.yaml` or similar):

```yaml
live_component:
    resource: '@LiveComponentBundle/config/routes.php'
```

If it doesn't exist, create it. Live Components require this route for AJAX re-renders.

### Step 6: Clear cache and rebuild assets

```bash
bin/console cache:clear
```

If you're using Webpack Encore, rebuild your frontend assets:

```bash
yarn install
yarn build
```

### Step 7: Verify the installation

1. Make sure you have at least one product, shipping method, and payment method configured (or load fixtures with `bin/console sylius:fixtures:load -n`)
2. Add a product to the cart
3. Navigate to: `http://your-app/{locale}/one-page-checkout` (e.g., `http://localhost/en_US/one-page-checkout`)

You should see the one-page checkout with address form, payment, shipping, summary, and order completion — all on a single page.

---

## How It Works

### Architecture

The plugin provides a single checkout page built with **Symfony UX Live Components**:

| Component | Purpose |
|-----------|---------|
| `OnePageCheckoutComponent` | Main checkout form — handles billing & shipping address |
| `Payment` | Payment method selection |
| `Shipment` | Shipping method selection |
| `Summary` | Order summary (updates live when shipping/payment changes) |
| `Complete` | Order completion button — applies the checkout state machine transition |

### State Machine Modification

The plugin modifies the `sylius_order_checkout` workflow to allow the `complete` transition directly from the `cart`, `payment_selected`, and `payment_skipped` states. This is required because the one-page checkout doesn't follow the standard multi-step flow.

### Route

The plugin registers a single shop route:

```
GET|PUT|POST /{_locale}/one-page-checkout
```

### Twig Hooks

The sidebar components (Payment, Shipment, Summary, Complete) are rendered via Sylius Twig Hooks:

- `one_page_checkout_payment`
- `one_page_checkout_shipment`
- `one_page_checkout_summary`
- `one_page_checkout_complete`

You can override any of these hooks in your application to customize the checkout experience.

---

## Customization

### Overriding templates

To override any plugin template, create the corresponding file in your application's `templates/bundles/MudrakOnePageCheckoutPlugin/` directory:

```
templates/
└── bundles/
    └── MudrakOnePageCheckoutPlugin/
        ├── shop/
        │   └── one_page_checkout.html.twig
        └── components/
            └── OnePageCheckout/
                ├── OnePageCheckoutComponent.html.twig
                ├── Payment.html.twig
                ├── Shipment.html.twig
                ├── Summary.html.twig
                └── Complete.html.twig
```

### Overriding Twig Hooks

You can replace or extend individual checkout sections by overriding the twig hooks in your application's configuration:

```yaml
# config/packages/mudrak_one_page_checkout.yaml
sylius_twig_hooks:
    hooks:
        one_page_checkout_summary:
            my_custom_summary:
                template: 'shop/checkout/my_custom_summary.html.twig'
                priority: 10
```

---

## Development

### Running the plugin in isolation (for contributors)

#### Docker

```bash
make init
make database-init
make load-fixtures  # optional
```

App available at `http://localhost`.

#### Traditional

```bash
(cd vendor/sylius/test-application && yarn install)
(cd vendor/sylius/test-application && yarn build)
vendor/bin/console assets:install

vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load -n

symfony server:start -d
```

### Tests

```bash
# PHPUnit
vendor/bin/phpunit

# Behat (non-JS)
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver"

# PHPStan
vendor/bin/phpstan analyse -c phpstan.neon -l max src/

# Coding Standards
vendor/bin/ecs check
```

---

## License

This plugin is released under the [MIT License](LICENSE).
