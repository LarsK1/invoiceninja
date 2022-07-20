/*! For license information please see action-selectors.js.LICENSE.txt */
(()=>{function e(e,n){var r="undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(!r){if(Array.isArray(e)||(r=function(e,n){if(!e)return;if("string"==typeof e)return t(e,n);var r=Object.prototype.toString.call(e).slice(8,-1);"Object"===r&&e.constructor&&(r=e.constructor.name);if("Map"===r||"Set"===r)return Array.from(e);if("Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r))return t(e,n)}(e))||n&&e&&"number"==typeof e.length){r&&(e=r);var o=0,c=function(){};return{s:c,n:function(){return o>=e.length?{done:!0}:{done:!1,value:e[o++]}},e:function(e){throw e},f:c}}throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}var i,a=!0,u=!1;return{s:function(){r=r.call(e)},n:function(){var e=r.next();return a=e.done,e},e:function(e){u=!0,i=e},f:function(){try{a||null==r.return||r.return()}finally{if(u)throw i}}}}function t(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r}function n(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}(new(function(){function t(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,t),this.parentElement=document.querySelector(".form-check-parent"),this.parentForm=document.getElementById("bulkActions")}var r,o,c;return r=t,o=[{key:"watchCheckboxes",value:function(e){var t=this;document.querySelectorAll(".child-hidden-input").forEach((function(e){return e.remove()})),document.querySelectorAll(".form-check-child").forEach((function(n){e.checked?(n.checked=e.checked,t.processChildItem(n,document.getElementById("bulkActions"))):(n.checked=!1,document.querySelectorAll(".child-hidden-input").forEach((function(e){return e.remove()})))}))}},{key:"processChildItem",value:function(t,n){var r=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{};if(r.hasOwnProperty("single")&&document.querySelectorAll(".child-hidden-input").forEach((function(e){return e.remove()})),!1!==t.checked){var o=document.createElement("INPUT");o.setAttribute("name","purchase_orders[]"),o.setAttribute("value",t.dataset.value),o.setAttribute("class","child-hidden-input"),o.hidden=!0,n.append(o)}else{var c,i=document.querySelectorAll("input.child-hidden-input"),a=e(i);try{for(a.s();!(c=a.n()).done;){var u=c.value;u.value==t.dataset.value&&u.remove()}}catch(e){a.e(e)}finally{a.f()}}}},{key:"handle",value:function(){var t=this;this.parentElement.addEventListener("click",(function(){t.watchCheckboxes(t.parentElement)}));var n,r=e(document.querySelectorAll(".form-check-child"));try{var o=function(){var e=n.value;e.addEventListener("click",(function(){t.processChildItem(e,t.parentForm)}))};for(r.s();!(n=r.n()).done;)o()}catch(e){r.e(e)}finally{r.f()}}}],o&&n(r.prototype,o),c&&n(r,c),t}())).handle()})();