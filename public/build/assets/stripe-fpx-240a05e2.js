<<<<<<<< HEAD:public/build/assets/stripe-fpx-240a05e2.js
var i=Object.defineProperty;var c=(n,t,e)=>t in n?i(n,t,{enumerable:!0,configurable:!0,writable:!0,value:e}):n[t]=e;var s=(n,t,e)=>(c(n,typeof t!="symbol"?t+"":t,e),e);/**
========
var i=Object.defineProperty;var a=(n,t,e)=>t in n?i(n,t,{enumerable:!0,configurable:!0,writable:!0,value:e}):n[t]=e;var s=(n,t,e)=>(a(n,typeof t!="symbol"?t+"":t,e),e);import{i as c,w as d}from"./wait-8f4ae121.js";/**
>>>>>>>> new_payment_flow:public/build/assets/stripe-fpx-c82fd7dc.js
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class d{constructor(t,e){s(this,"setupStripe",()=>{this.stripeConnect?this.stripe=Stripe(this.key,{stripeAccount:this.stripeConnect}):this.stripe=Stripe(this.key);let t=this.stripe.elements(),e={base:{padding:"10px 12px",color:"#32325d",fontSize:"16px"}};return this.fpx=t.create("fpxBank",{style:e,accountHolderType:"individual"}),this.fpx.mount("#fpx-bank-element"),this});s(this,"handle",()=>{document.getElementById("pay-now").addEventListener("click",t=>{document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden"),this.stripe.confirmFpxPayment(document.querySelector("meta[name=pi-client-secret").content,{payment_method:{fpx:this.fpx},return_url:document.querySelector('meta[name="return-url"]').content}).then(e=>{e.error&&this.handleFailure(e.error.message)})})});this.key=t,this.errors=document.getElementById("errors"),this.stripeConnect=e}handleFailure(t){let e=document.getElementById("errors");e.textContent="",e.textContent=t,e.hidden=!1,document.getElementById("pay-now").disabled=!1,document.querySelector("#pay-now > svg").classList.add("hidden"),document.querySelector("#pay-now > span").classList.remove("hidden")}}var r;const a=((r=document.querySelector('meta[name="stripe-publishable-key"]'))==null?void 0:r.content)??"";var o;const l=((o=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:o.content)??"";new d(a,l).setupStripe().handle();
