var m=Object.defineProperty;var y=(n,e,t)=>e in n?m(n,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):n[e]=t;var o=(n,e,t)=>(y(n,typeof e!="symbol"?e+"":e,t),t);import{i as h,w as g}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */class p{constructor(e,t){o(this,"handleAuthorization",()=>{var c,l,s,i;if(this.cvvRequired=="1"&&document.getElementById("cvv").value.length<3){const r=document.getElementById("errors");r&&(r.innerText="CVV is required",r.style.display="block"),document.getElementById("pay-now").disabled=!1,document.querySelector("#pay-now > svg").classList.add("hidden"),document.querySelector("#pay-now > span").classList.remove("hidden");return}var e={};e.clientKey=this.publicKey,e.apiLoginID=this.loginId;var t={};t.cardNumber=(c=this.sc.value("number"))==null?void 0:c.replace(/[^\d]/g,""),t.month=(l=this.sc.value("month"))==null?void 0:l.replace(/[^\d]/g,""),t.year=`20${(s=this.sc.value("year"))==null?void 0:s.replace(/[^\d]/g,"")}`,t.cardCode=(i=this.sc.value("cvv"))==null?void 0:i.replace(/[^\d]/g,"");var a={};return a.authData=e,a.cardData=t,document.getElementById("pay-now")&&(document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden")),Accept.dispatchData(a,this.responseHandler),!1});o(this,"responseHandler",e=>{if(e.messages.resultCode==="Error"){var t=0;const a=document.getElementById("errors");a&&(a.innerText=`${e.messages.message[t].code}: ${e.messages.message[t].text}`,a.style.display="block"),document.getElementById("pay-now").disabled=!1,document.querySelector("#pay-now > svg").classList.add("hidden"),document.querySelector("#pay-now > span").classList.remove("hidden")}else if(e.messages.resultCode==="Ok"){document.getElementById("dataDescriptor").value=e.opaqueData.dataDescriptor,document.getElementById("dataValue").value=e.opaqueData.dataValue;let a=document.querySelector("input[name=token-billing-checkbox]:checked");a&&(document.getElementById("store_card").value=a.value),document.getElementById("server_response").submit()}return!1});o(this,"handle",()=>{Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach(a=>a.addEventListener("click",d=>{document.getElementById("save-card--container").style.display="none",document.getElementById("authorize--credit-card-container").style.display="none",document.getElementById("token").value=d.target.dataset.token}));let e=document.getElementById("toggle-payment-with-credit-card");e&&e.addEventListener("click",()=>{document.getElementById("save-card--container").style.display="grid",document.getElementById("authorize--credit-card-container").style.display="flex",document.getElementById("token").value=null});let t=document.getElementById("pay-now");return t&&t.addEventListener("click",a=>{let d=document.getElementById("token");d.value?this.handlePayNowAction(d.value):this.handleAuthorization()}),this});this.publicKey=e,this.loginId=t,this.cardHolderName=document.getElementById("cardholder_name"),this.sc=createSimpleCard({fields:{card:{number:"#number",date:"#date",cvv:"#cvv"}}}),this.sc.mount(),this.cvvRequired=document.querySelector('meta[name="authnet-require-cvv"]').content}handlePayNowAction(e){document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden"),document.getElementById("token").value=e,document.getElementById("server_response").submit()}}function u(){const n=document.querySelector('meta[name="authorize-public-key"]').content,e=document.querySelector('meta[name="authorize-login-id"]').content;new p(n,e).handle()}h()?u():g("#authorize-net-credit-card-payment").then(()=>u());
