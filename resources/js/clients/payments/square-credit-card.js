/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

class SquareCreditCard {
    constructor() {
        this.appId = document.querySelector('meta[name=square-appId]').content;
        this.locationId = document.querySelector(
            'meta[name=square-locationId]'
        ).content;
        this.isLoaded = false;
    }

    async init() {
        this.payments = Square.payments(this.appId, this.locationId);

        this.card = await this.payments.card();

        await this.card.attach('#card-container');

        this.isLoaded = true;

        let iframeContainer = document.querySelector(
            '.sq-card-iframe-container'
        );

        if (iframeContainer) {
            iframeContainer.setAttribute('style', '150px !important');
        }

        let toggleWithToken = document.querySelector(
            '.toggle-payment-with-token'
        );

        if (toggleWithToken) {
            document.getElementById('card-container').classList.add('hidden');
        }
    }

    async completePaymentWithoutToken(e) {
        document.getElementById('errors').hidden = true;
        e.target.parentElement.disabled = true;

        let result = await this.card.tokenize();

        console.log("square token = " + result.token);
        
        /* SCA */
       let verificationToken;
         verificationToken = await verifyBuyer(
           this.payments,
           result.token
         );
       
       console.debug('Verification Token:', verificationToken);

        document.querySelector('input[name="verificationToken"]').value =
            verificationToken;

        if (result.status === 'OK') {
            document.getElementById('sourceId').value = result.token;

            let tokenBillingCheckbox = document.querySelector(
                'input[name="token-billing-checkbox"]:checked'
            );

            if (tokenBillingCheckbox) {
                document.querySelector('input[name="store_card"]').value =
                    tokenBillingCheckbox.value;
            }

            return document.getElementById('server_response').submit();
        }

        document.getElementById('errors').textContent =
            result.errors[0].message;
        document.getElementById('errors').hidden = false;

        e.target.parentElement.disabled = false;
    }

    async completePaymentUsingToken(e) {
        e.target.parentElement.disabled = true;

        return document.getElementById('server_response').submit();
    }

    /* SCA */
    async verifyBuyer(payments, token) {
        const verificationDetails = {
          amount: document.querySelector('meta[name=amount]').content,
          billingContact: document.querySelector('meta[name=contact]').content,
          currencyCode: document.querySelector('meta[name=currencyCode]').content,
          intent: 'CHARGE'
        };

        const verificationResults = await payments.verifyBuyer(
          token,
          verificationDetails
        );

        console.log(" verification toke = " + verificationResults.token);

        return verificationResults.token;
    }

    async handle() {
        await this.init();

        document
            .getElementById('authorize-card')
            ?.addEventListener('click', (e) =>
                this.completePaymentWithoutToken(e)
            );

        document.getElementById('pay-now')?.addEventListener('click', (e) => {
            let tokenInput = document.querySelector('input[name=token]');

            if (tokenInput.value) {
                return this.completePaymentUsingToken(e);
            }

            return this.completePaymentWithoutToken(e);
        });

        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document
                    .getElementById('card-container')
                    .classList.add('hidden');
                document.getElementById('save-card--container').style.display =
                    'none';
                document.querySelector('input[name=token]').value =
                    element.target.dataset.token;
            })
        );

        document
            .getElementById('toggle-payment-with-credit-card')
            ?.addEventListener('click', async (element) => {
                document
                    .getElementById('card-container')
                    .classList.remove('hidden');
                document.getElementById('save-card--container').style.display =
                    'grid';
                document.querySelector('input[name=token]').value = '';
            });

        let toggleWithToken = document.querySelector(
            '.toggle-payment-with-token'
        );

        if (!toggleWithToken) {
            document.getElementById('toggle-payment-with-credit-card')?.click();
        }
    }
}

new SquareCreditCard().handle();
