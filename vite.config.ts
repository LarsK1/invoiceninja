import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
    plugins: [
        laravel([
            'resources/js/app.js',
            'resources/sass/app.scss',
            'resources/js/clients/payment_methods/authorize-authorize-card.js',
            'resources/js/clients/payments/authorize-credit-card-payment.js',
            'resources/js/clients/payments/forte-credit-card-payment.js',
            'resources/js/clients/payments/forte-ach-payment.js',
            'resources/js/clients/payments/stripe-ach.js',
            'resources/js/clients/payments/stripe-klarna.js',
            'resources/js/clients/payments/stripe-bacs.js',
            'resources/js/clients/invoices/action-selectors.js',
            'resources/js/clients/purchase_orders/action-selectors.js',
            'resources/js/clients/purchase_orders/accept.js',
            'resources/js/clients/invoices/payment.js',
            'resources/js/clients/payments/stripe-sofort.js',
            'resources/js/clients/payments/stripe-alipay.js',
            'resources/js/clients/payments/checkout-credit-card.js',
            'resources/js/clients/quotes/action-selectors.js',
            'resources/js/clients/quotes/approve.js',
            'resources/js/clients/payments/stripe-credit-card.js',
            'resources/js/setup/setup.js',
            'resources/js/clients/shared/pdf.js',
            'resources/js/clients/shared/multiple-downloads.js',
            'resources/js/clients/linkify-urls.js',
            'resources/js/clients/payments/braintree-credit-card.js',
            'resources/js/clients/payments/braintree-paypal.js',
            'resources/js/clients/payments/wepay-credit-card.js',
            'resources/js/clients/payment_methods/wepay-bank-account.js',
            'resources/js/clients/payments/paytrace-credit-card.js',
            'resources/js/clients/payments/mollie-credit-card.js',
            'resources/js/clients/payments/eway-credit-card.js',
            'resources/js/clients/payment_methods/braintree-ach.js',
            'resources/js/clients/payments/square-credit-card.js',
            'resources/js/clients/statements/view.js',
            'resources/js/clients/payments/razorpay-aio.js',
            'resources/js/clients/payments/stripe-sepa.js',
            'resources/js/clients/payment_methods/authorize-checkout-card.js',
            'resources/js/clients/payments/stripe-giropay.js',
            'resources/js/clients/payments/stripe-acss.js',
            'resources/js/clients/payments/stripe-bancontact.js',
            'resources/js/clients/payments/stripe-becs.js',
            'resources/js/clients/payments/stripe-eps.js',
            'resources/js/clients/payments/stripe-ideal.js',
            'resources/js/clients/payments/stripe-przelewy24.js',
            'resources/js/clients/payments/stripe-browserpay.js',
            'resources/js/clients/payments/stripe-fpx.js',
            'resources/js/clients/payments/stripe-ach-pay.js',
            'resources/js/clients/payments/stripe-bank-transfer.js',
            'resources/js/clients/payment_methods/authorize-stripe-acss.js',
        ]),
        viteStaticCopy({
            targets: [
                {
                    src: 'node_modules/card-js/card-js.min.js',
                    dest: 'public/js/card-js.min.js',
                },
                {
                    src: 'node_modules/card-js/card-js.min.css',
                    dest: 'public/css/card-js.min.css',
                },
                {
                    src: 'node_modules/clipboard/dist/clipboard.min.js',
                    dest: 'public/vendor/clipboard.min.js',
                },
            ],
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: (id) => {
                    if (
                        id.includes('forte-credit-card-payment.js') ||
                        id.includes('authorize-credit-card-payment')
                    ) {
                        return id;
                    }
                },
            },
        },
    },
});
