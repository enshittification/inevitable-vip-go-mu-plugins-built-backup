!function(){"use strict";var n={n:function(o){var t=o&&o.__esModule?function(){return o.default}:function(){return o};return n.d(t,{a:t}),t},d:function(o,t){for(var e in t)n.o(t,e)&&!n.o(o,e)&&Object.defineProperty(o,e,{enumerable:!0,get:t[e]})}};n.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(n){if("object"==typeof window)return window}}(),n.o=function(n,o){return Object.prototype.hasOwnProperty.call(n,o)},function(){function n(o){return n="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(n){return typeof n}:function(n){return n&&"function"==typeof Symbol&&n.constructor===Symbol&&n!==Symbol.prototype?"symbol":typeof n},n(o)}var o=window.wp.hooks;window.wpParselyHooks=(0,o.createHooks)(),function(){var o=function(){return window.wpParselyHooks.doAction("wpParselyOnLoad")},t=function(){return window.wpParselyHooks.doAction("wpParselyOnReady")};if("object"===n(window.PARSELY)){if("function"!=typeof window.PARSELY.onload)window.PARSELY.onload=o;else{var e=window.PARSELY.onload;window.PARSELY.onload=function(){e&&e(),o()}}if("function"!=typeof window.PARSELY.onReady)window.PARSELY.onReady=t;else{var r=window.PARSELY.onReady;window.PARSELY.onReady=function(){r&&r(),t()}}}else window.PARSELY={onload:o,onReady:t};!0===window.wpParselyDisableAutotrack&&(window.PARSELY.autotrack=!1)}()}(),function(){function o(n,o,t,e,r,i,a){try{var u=n[i](a),c=u.value}catch(n){return void t(n)}u.done?o(c):Promise.resolve(c).then(e,r)}function t(n){return function(){var t=this,e=arguments;return new Promise((function(r,i){var a=n.apply(t,e);function u(n){o(a,r,i,u,c,"next",n)}function c(n){o(a,r,i,u,c,"throw",n)}u(void 0)}))}}var e=window.regeneratorRuntime,r=n.n(e);function i(){return(i=t(r().mark((function o(){var t,e,i,a;return r().wrap((function(o){for(;;)switch(o.prev=o.next){case 0:if(i=null===(t=n.g.PARSELY)||void 0===t||null===(e=t.config)||void 0===e?void 0:e.parsely_site_uuid,window.wpParselyApiKey&&i){o.next=3;break}return o.abrupt("return");case 3:return a="https://api.parsely.com/v2/profile?apikey=".concat(encodeURIComponent(window.wpParselyApiKey),"&uuid=").concat(encodeURIComponent(i),"&url=").concat(encodeURIComponent(window.location.href)),o.abrupt("return",fetch(a));case 5:case"end":return o.stop()}}),o)})))).apply(this,arguments)}void 0!==window.wpParselyApiKey&&window.wpParselyHooks.addAction("wpParselyOnLoad","wpParsely",(function(){return i.apply(this,arguments)}))}()}();