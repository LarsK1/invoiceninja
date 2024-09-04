/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class a{constructor(){var e;this.clientSecret=(e=document.querySelector("meta[name=stripe-pi-client-secret]"))==null?void 0:e.content}init(){var t,n;let e={};return document.querySelector("meta[name=stripe-account-id]")&&(e.apiVersion="2020-08-27",e.stripeAccount=(t=document.querySelector("meta[name=stripe-account-id]"))==null?void 0:t.content),this.stripe=Stripe((n=document.querySelector("meta[name=stripe-publishable-key]"))==null?void 0:n.content,e),this.elements=this.stripe.elements(),this}createPaymentRequest(){return this.paymentRequest=this.stripe.paymentRequest(JSON.parse(document.querySelector("meta[name=payment-request-data").content)),this}createPaymentRequestButton(){this.paymentRequestButton=this.elements.create("paymentRequestButton",{paymentRequest:this.paymentRequest})}handlePaymentRequestEvents(e,t){document.querySelector("#errors").hidden=!0,this.paymentRequest.on("paymentmethod",function(n){e.confirmCardPayment(t,{payment_method:n.paymentMethod.id},{handleActions:!1}).then(function(r){r.error?(document.querySelector("#errors").innerText=r.error.message,document.querySelector("#errors").hidden=!1,n.complete("fail")):(n.complete("success"),r.paymentIntent.status==="requires_action"?e.confirmCardPayment(t).then(function(s){s.error?(n.complete("fail"),document.querySelector("#errors").innerText=s.error.message,document.querySelector("#errors").hidden=!1):(document.querySelector('input[name="gateway_response"]').value=JSON.stringify(s.paymentIntent),document.getElementById("server-response").submit())}):(document.querySelector('input[name="gateway_response"]').value=JSON.stringify(r.paymentIntent),document.getElementById("server-response").submit()))})})}handle(){this.init().createPaymentRequest().createPaymentRequestButton(),this.paymentRequest.canMakePayment().then(e=>{var t;if(e)return this.paymentRequestButton.mount("#payment-request-button");document.querySelector("#errors").innerHTML=JSON.parse((t=document.querySelector("meta[name=no-available-methods]"))==null?void 0:t.content),document.querySelector("#errors").hidden=!1}),this.handlePaymentRequestEvents(this.stripe,this.clientSecret)}}new a().handle();
