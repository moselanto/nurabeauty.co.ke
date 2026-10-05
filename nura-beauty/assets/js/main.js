/* NURA Beauty - lightweight, dependency-free interactions.
   Kept small and deferred to protect Core Web Vitals (INP/TBT). */
(function () {
	'use strict';

	var d = document;

	function ready(fn){ if(d.readyState!=='loading'){fn();}else{d.addEventListener('DOMContentLoaded',fn);} }

	ready(function () {
		// Header shadow on scroll.
		var header = d.getElementById('site-header');
		if (header) {
			var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 10); };
			window.addEventListener('scroll', onScroll, { passive: true });
			onScroll();
		}

		// Mobile drawer. Bind EVERY trigger ([data-nura-drawer]) - both the header
		// hamburger AND the mobile bottom-nav "Menu" button open it. (Previously
		// only the first match was wired, so the bottom-nav Menu did nothing.)
		var burgers = d.querySelectorAll('[data-nura-drawer]');
		var panel   = d.querySelector('[data-nura-drawer-panel]');
		var overlay = d.querySelector('[data-nura-overlay]');
		function setExpanded(v){ [].forEach.call(burgers, function (b) { b.setAttribute('aria-expanded', v ? 'true' : 'false'); }); }
		function closeDrawer(){ if(panel){panel.classList.remove('is-open');panel.setAttribute('aria-hidden','true');} if(overlay){overlay.classList.remove('is-open');} d.body.classList.remove('nura-drawer-open'); setExpanded(false); }
		function openDrawer(){ if(panel){panel.classList.add('is-open');panel.setAttribute('aria-hidden','false');} if(overlay){overlay.classList.add('is-open');} d.body.classList.add('nura-drawer-open'); setExpanded(true); }
		[].forEach.call(burgers, function (btn) {
			btn.setAttribute('aria-expanded', 'false');
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				if (panel && panel.classList.contains('is-open')) { closeDrawer(); } else { openDrawer(); }
			});
		});
		if (overlay) { overlay.addEventListener('click', closeDrawer); }
		d.addEventListener('keyup', function (e) { if (e.key === 'Escape') { closeDrawer(); } });

		// Scroll reveal.
		var reveal = d.querySelectorAll('.nura-reveal');
		if ('IntersectionObserver' in window && reveal.length) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
			}, { threshold: 0.12 });
			reveal.forEach(function (el) { io.observe(el); });
		} else {
			reveal.forEach(function (el) { el.classList.add('is-in'); });
		}

		// Sticky add-to-cart on single product. It lives on the same bottom edge as
		// the footer, the 'Complete Your NURA Look' rail AND the cookie-consent bar,
		// so only show it once the main add-to-cart form has scrolled away and none
		// of those bottom-anchored regions are on screen - otherwise the fixed bar
		// stacks on top of them.
		var sticky = d.querySelector('.nura-sticky-atc');
		var atc = d.querySelector('form.cart');
		if (sticky && atc && 'IntersectionObserver' in window) {
			var atcAway = false, tailNear = false;
			var consentOpen = function () {
				var root = d.getElementById('wpconsent-root');
				if (root === null) { return false; }
				var holder = root.querySelector('.wpconsent-banner-holder');
				if (holder === null) { holder = root; }
				var cs = window.getComputedStyle(holder);
				if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) { return false; }
				return holder.getBoundingClientRect().height > 0;
			};
			var syncSticky = function () {
				sticky.classList.toggle('is-visible', atcAway && tailNear === false && consentOpen() === false);
			};
			var so = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) { atcAway = en.isIntersecting === false; });
				syncSticky();
			}, { threshold: 0 });
			so.observe(atc);
			// Observe BOTH the related rail and the footer so the bar stays hidden
			// across the whole bottom of the page (not just while one of them shows).
			var tails = [ d.querySelector('.nura-complete-look'), d.querySelector('#site-footer') ].filter(function (el) { return el; });
			var nearState = [];
			if (tails.length) {
				var fo = new IntersectionObserver(function (entries) {
					entries.forEach(function (en) {
						var idx = tails.indexOf(en.target);
						if (idx > -1) { nearState[idx] = en.isIntersecting; }
					});
					tailNear = nearState.indexOf(true) > -1;
					syncSticky();
				}, { threshold: 0 });
				tails.forEach(function (el) { fo.observe(el); });
			}
			// Re-evaluate right after the cookie bar is dismissed so the sticky bar
			// can appear without needing another scroll.
			d.addEventListener('click', function (e) {
				if (e.target && e.target.closest && e.target.closest('#wpconsent-root')) { setTimeout(syncSticky, 400); }
			});
			sticky.addEventListener('click', function () { atc.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
		}
	});
})();


/* Category carousel arrows */
(function(){var d=document;function r(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
r(function(){var row=d.querySelector("[data-nura-cats]");if(!row){return;}
var p=d.querySelector("[data-nura-cats-prev]"),n=d.querySelector("[data-nura-cats-next]");
function step(){return Math.max(row.clientWidth*0.85,240);}
if(p){p.addEventListener("click",function(){row.scrollBy({left:-step(),behavior:"smooth"});});}
if(n){n.addEventListener("click",function(){row.scrollBy({left:step(),behavior:"smooth"});});}
});})();


/* ===== NURA v1.1.0 - Hero slider ===== */
(function(){var d=document;function r(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
r(function(){
	var root=d.querySelector("[data-nura-slider]");if(!root){return;}
	var slides=[].slice.call(root.querySelectorAll(".nura-slide"));if(slides.length<2){return;}
	var dotsWrap=root.querySelector("[data-slider-dots]");var cur=0,timer=null;
	var reduceMotion=window.matchMedia?window.matchMedia("(prefers-reduced-motion: reduce)"):{matches:false};
	var dots=slides.map(function(s,i){var btn=d.createElement("button");btn.type="button";btn.setAttribute("aria-label","Go to slide "+(i+1));if(i===0){btn.className="is-active";btn.setAttribute("aria-current","true");}btn.addEventListener("click",function(){go(i);reset();});if(dotsWrap){dotsWrap.appendChild(btn);}return btn;});
	function go(i){slides[cur].classList.remove("is-active");if(dots[cur]){dots[cur].classList.remove("is-active");dots[cur].removeAttribute("aria-current");}cur=(i+slides.length)%slides.length;slides[cur].classList.add("is-active");if(dots[cur]){dots[cur].classList.add("is-active");dots[cur].setAttribute("aria-current","true");}}
	function next(){go(cur+1);}function prev(){go(cur-1);}
	var bn=root.querySelector("[data-slider-next]"),bp=root.querySelector("[data-slider-prev]");
	if(bn){bn.addEventListener("click",function(){next();reset();});}
	if(bp){bp.addEventListener("click",function(){prev();reset();});}
	function start(){if(reduceMotion.matches){return;}window.clearInterval(timer);timer=window.setInterval(next,6500);}
	function reset(){window.clearInterval(timer);start();}
	root.addEventListener("mouseenter",function(){window.clearInterval(timer);});
	root.addEventListener("mouseleave",start);
	start();
});})();

/* ===== NURA v1.1.0 - Product rails ===== */
(function(){var d=document;function r(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
r(function(){[].slice.call(d.querySelectorAll("[data-nura-rail]")).forEach(function(rail){
	var track=rail.querySelector("ul.products");if(!track){return;}
	var p=rail.querySelector("[data-rail-prev]"),n=rail.querySelector("[data-rail-next]");
	function step(){var card=track.querySelector("li.product");var w=card?card.getBoundingClientRect().width+24:280;return w*2;}
	if(p){p.addEventListener("click",function(){track.scrollBy({left:-step(),behavior:"smooth"});});}
	if(n){n.addEventListener("click",function(){track.scrollBy({left:step(),behavior:"smooth"});});}
});});})();

/* ===== NURA v1.1.0 - Book a fitting -> WhatsApp ===== */
(function(){var d=document;function r(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
r(function(){var form=d.querySelector("[data-nura-book]");if(!form){return;}
	form.addEventListener("submit",function(e){e.preventDefault();
		var wa=form.getAttribute("data-wa")||"";
		var g=function(k){var el=form.querySelector('[name="'+k+'"]');return el?el.value:"";};
		var parts=["Hello NURA, I would like to book: "+g("service"),"Name: "+g("name"),"Phone: "+g("phone"),"Preferred date: "+g("date"),"Notes: "+g("note")];
		var text=encodeURIComponent(parts.join("\n"));
		var base=wa;
		if(base.indexOf("wa.me")===-1&&base.indexOf("whatsapp")===-1){var digits=(wa||"").replace(/[^0-9]/g,"");base=digits?("https://wa.me/"+digits):"https://wa.me/";}
		var url=base+(base.indexOf("?")===-1?"?":"&")+"text="+text;
		window.open(url,"_blank");
	});
});})();

/* ===== NURA v1.9.0 - Shop faceted filters (progressive enhancement + AJAX) ===== */
(function(){
	var d=document;
	function ready(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
	ready(function(){
		if(!d.querySelector("[data-nura-filterbar]")){return;}
		var overlay=d.querySelector("[data-nura-overlay]");

		function panel(){return d.querySelector("[data-nura-filters]");}
		function openDrawer(){var p=panel();if(p){p.classList.add("is-open");p.setAttribute("aria-hidden","false");}var t=d.querySelector("[data-nura-filter-toggle]");if(t){t.setAttribute("aria-expanded","true");}if(overlay){overlay.classList.add("is-open");}d.body.classList.add("nura-filters-open");}
		function closeDrawer(){var p=panel();if(p){p.classList.remove("is-open");p.setAttribute("aria-hidden","true");}var t=d.querySelector("[data-nura-filter-toggle]");if(t){t.setAttribute("aria-expanded","false");}if(overlay){overlay.classList.remove("is-open");}d.body.classList.remove("nura-filters-open");}

		var canAjax=("fetch" in window)&&("DOMParser" in window)&&!!d.querySelector("[data-nura-results]");

		function swap(url,push){
			var cur=d.querySelector("[data-nura-results]");
			if(cur){cur.classList.add("is-loading");}
			fetch(url,{headers:{"X-Requested-With":"XMLHttpRequest"},credentials:"same-origin"})
				.then(function(res){return res.text();})
				.then(function(html){
					var doc=new DOMParser().parseFromString(html,"text/html");
					var nRes=doc.querySelector("[data-nura-results]"),nBar=doc.querySelector("[data-nura-filterbar]");
					var cRes=d.querySelector("[data-nura-results]"),cBar=d.querySelector("[data-nura-filterbar]");
					if(nRes&&cRes){cRes.parentNode.replaceChild(nRes,cRes);}
					if(nBar&&cBar){cBar.parentNode.replaceChild(nBar,cBar);}
					if(d.body.classList.contains("nura-filters-open")){var p=panel();if(p){p.classList.add("is-open");p.setAttribute("aria-hidden","false");}}
					if(push&&window.history&&window.history.pushState){window.history.pushState({nura:1},"",url);}
					var anchor=d.querySelector(".nura-filterbar");
					if(anchor){var y=anchor.getBoundingClientRect().top+window.scrollY-90;if(y<window.scrollY){window.scrollTo({top:y,behavior:"smooth"});}}
				})
				.catch(function(){window.location.href=url;});
		}

		d.addEventListener("click",function(e){
			var toggle=e.target.closest("[data-nura-filter-toggle]");
			if(toggle){e.preventDefault();openDrawer();return;}
			var closeBtn=e.target.closest("[data-nura-filter-close]");
			if(closeBtn){e.preventDefault();closeDrawer();return;}
			if(overlay&&e.target===overlay){closeDrawer();}
			if(!canAjax){return;}
			var link=e.target.closest("a[data-nura-filter]");
			if(!link){return;}
			if(e.metaKey||e.ctrlKey||e.shiftKey||e.button){return;}
			e.preventDefault();
			swap(link.href,true);
		});

		d.addEventListener("submit",function(e){
			if(!canAjax){return;}
			var form=e.target.closest("form[data-nura-price]");
			if(!form){return;}
			e.preventDefault();
			var fd=new FormData(form),pairs=[];
			fd.forEach(function(v,k){if(String(v).length){pairs.push(encodeURIComponent(k)+"="+encodeURIComponent(v));}});
			var action=form.getAttribute("action")||window.location.pathname;
			var url=action+(action.indexOf("?")===-1?"?":"&")+pairs.join("&");
			swap(url,true);
		});

		d.addEventListener("keyup",function(e){if(e.key==="Escape"){closeDrawer();}});
		window.addEventListener("popstate",function(){if(canAjax){swap(window.location.href,false);}});
	});
})();

/* ===== NURA v1.10.0 - Variation swatch selector (enhances native <select>) ===== */
(function(){
	var d=document;
	function ready(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
	ready(function(){
		var form=d.querySelector("form.variations_form");
		if(!form||!form.querySelector("[data-nura-swatches]")){return;}
		form.classList.add("nura-var-enhanced");

		function sync(sel){
			var wrap=sel.parentNode.querySelector("[data-nura-swatches]");
			if(!wrap){return;}
			var val=sel.value;
			[].forEach.call(wrap.querySelectorAll(".nura-swatch,.nura-pill"),function(b){
				var on=(b.getAttribute("data-value")===val&&val!=="");
				b.classList.toggle("is-active",on);
				b.setAttribute("aria-pressed",on?"true":"false");
			});
		}
		function syncAll(){[].forEach.call(form.querySelectorAll(".variations select"),sync);}

		form.addEventListener("click",function(e){
			var btn=e.target.closest(".nura-swatch,.nura-pill");
			if(!btn||!form.contains(btn)){return;}
			e.preventDefault();
			var wrap=btn.closest("[data-nura-swatches]");
			var sel=wrap?wrap.parentNode.querySelector("select"):null;
			if(!sel){return;}
			sel.value=btn.getAttribute("data-value");
			sel.dispatchEvent(new Event("change",{bubbles:true}));
			sync(sel);
		});
		form.addEventListener("change",function(e){if(e.target.tagName==="SELECT"){sync(e.target);}});
		form.addEventListener("reset",function(){setTimeout(syncAll,60);});
		form.addEventListener("click",function(e){if(e.target.closest(".reset_variations")){setTimeout(syncAll,60);}});
		syncAll();
	});
})();

/* NURA smart search overlay (v1.12.0) */
(function(){
	var modal=document.querySelector("[data-nura-search-modal]");
	if(!modal){return;}
	var input=modal.querySelector("[data-nura-search-input]");
	var results=modal.querySelector("[data-nura-search-results]");
	var openers=document.querySelectorAll("[data-nura-search-open]");
	var timer=null,lastQ="",ctrl=null;

	function openModal(e){if(e){e.preventDefault();}modal.classList.add("is-open");modal.setAttribute("aria-hidden","false");document.documentElement.classList.add("nura-noscroll");setTimeout(function(){input.focus();},40);}
	function closeModal(){modal.classList.remove("is-open");modal.setAttribute("aria-hidden","true");document.documentElement.classList.remove("nura-noscroll");}

	[].forEach.call(openers,function(a){a.addEventListener("click",openModal);});
	[].forEach.call(modal.querySelectorAll("[data-nura-search-close]"),function(b){b.addEventListener("click",closeModal);});
	document.addEventListener("keydown",function(e){if(e.key==="Escape"&&modal.classList.contains("is-open")){closeModal();}});

	function esc(s){var d=document.createElement("div");d.textContent=(s==null)?"":String(s);return d.innerHTML;}

	function render(data){
		var q=data.query||"",html="";
		if(data.suggestions&&data.suggestions.length){
			html+='<div class="nura-sresult__group"><p class="nura-sresult__label">Shop by</p><div class="nura-sresult__chips">';
			data.suggestions.forEach(function(s){
				html+='<a class="nura-sresult__chip" href="'+encodeURI(s.url)+'"><span>'+esc(s.label)+'</span><em>'+esc(s.context)+'</em></a>';
			});
			html+='</div></div>';
		}
		if(data.products&&data.products.length){
			html+='<div class="nura-sresult__group"><p class="nura-sresult__label">Products</p><div class="nura-sresult__list">';
			data.products.forEach(function(p){
				var oos=p.inStock?"":' <em class="nura-sresult__oos">Sold out</em>';
				var thumb=p.img?'<img src="'+encodeURI(p.img)+'" alt="" loading="lazy">':"";
				html+='<a class="nura-sresult__item" href="'+encodeURI(p.url)+'"><span class="nura-sresult__thumb">'+thumb+'</span><span class="nura-sresult__meta"><span class="nura-sresult__name">'+esc(p.title)+oos+'</span><span class="nura-sresult__price">'+(p.price||"")+'</span></span></a>';
			});
			html+='</div></div>';
			html+='<a class="nura-sresult__all" href="'+encodeURI(data.viewAllUrl)+'">See all results for \u201C'+esc(q)+'\u201D</a>';
		}else if(q.length>=2){
			html+='<p class="nura-sresult__empty">No products match \u201C'+esc(q)+'\u201D. Try a texture, colour or length.</p>';
		}
		results.innerHTML=html;
	}

	function run(q){
		if(typeof NURA==="undefined"||!NURA.ajaxUrl){return;}
		if(ctrl&&ctrl.abort){ctrl.abort();}
		var opts={};
		if(window.AbortController){ctrl=new AbortController();opts.signal=ctrl.signal;}
		results.classList.add("is-loading");
		var url=NURA.ajaxUrl+"?action=nura_search&nonce="+encodeURIComponent(NURA.nonce||"")+"&q="+encodeURIComponent(q);
		fetch(url,opts).then(function(r){return r.json();}).then(function(j){results.classList.remove("is-loading");if(j&&j.success){render(j.data);}}).catch(function(){results.classList.remove("is-loading");});
	}

	input.addEventListener("input",function(){
		var q=input.value.trim();
		if(q===lastQ){return;}
		lastQ=q;
		if(timer){clearTimeout(timer);}
		if(q.length<2){results.innerHTML="";return;}
		timer=setTimeout(function(){run(q);},220);
	});
})();

/* ===== NURA v1.17.0 - AJAX cart drawer + variable quick-add routing ===== */
(function(){
	var d=document;
	function ready(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
	function wcAjax(ep){
		if(window.wc_add_to_cart_params&&wc_add_to_cart_params.wc_ajax_url){
			return wc_add_to_cart_params.wc_ajax_url.replace("%%endpoint%%",ep);
		}
		return "/?wc-ajax="+ep;
	}
	ready(function(){
		var drawer=d.querySelector("[data-nura-cartdrawer]");
		var overlay=d.querySelector("[data-nura-cart-overlay]");
		function openCart(){if(!drawer){return;}drawer.classList.add("is-open");drawer.setAttribute("aria-hidden","false");if(overlay){overlay.classList.add("is-open");}d.body.classList.add("nura-cart-open");}
		function closeCart(){if(!drawer){return;}drawer.classList.remove("is-open");drawer.setAttribute("aria-hidden","true");if(overlay){overlay.classList.remove("is-open");}d.body.classList.remove("nura-cart-open");}

		if(overlay){overlay.addEventListener("click",closeCart);}
		d.addEventListener("click",function(e){
			if(e.target.closest("[data-nura-cart-close]")){e.preventDefault();closeCart();return;}
			var toggle=e.target.closest("[data-nura-cart-toggle]");
			if(toggle){e.preventDefault();openCart();return;}
		});
		d.addEventListener("keyup",function(e){if(e.key==="Escape"){closeCart();}});

		// Open the drawer whenever WooCommerce finishes an AJAX add (loop simple + Quick View simple).
		if(window.jQuery){window.jQuery(d.body).on("added_to_cart",function(){openCart();});}
		// Custom event fired by our own single-product / Quick View variable add flows.
		d.addEventListener("nura:cartadded",function(){openCart();});

		function refreshFragments(){
			if(window.jQuery){window.jQuery(d.body).trigger("wc_fragment_refresh");return;}
			fetch(wcAjax("get_refreshed_fragments"),{method:"POST",credentials:"same-origin"}).then(function(r){return r.json();}).then(function(j){
				if(j&&j.fragments){Object.keys(j.fragments).forEach(function(sel){var el=d.querySelector(sel);if(el){el.outerHTML=j.fragments[sel];}});}
			}).catch(function(){});
		}

		// Single-product add-to-cart -> AJAX so the drawer opens instead of a full reload.
		d.addEventListener("submit",function(e){
			var form=e.target.closest("form.cart");
			if(!form){return;}
			if(form.closest("[data-qv-body]")){return;} // Quick View forms handled in nurax.js
			if(form.classList.contains("grouped_form")){return;} // grouped products -> let WC handle natively
			var addBtn=form.querySelector("button.single_add_to_cart_button, button[name=add-to-cart]");
			if(!addBtn||addBtn.classList.contains("disabled")){return;}
			if(form.classList.contains("variations_form")){
				var vid=form.querySelector("input[name=variation_id]");
				if(!vid||!vid.value||vid.value==="0"){return;} // no variation chosen -> let WC show "choose options"
			}
			e.preventDefault();
			var fd=new FormData(form);
			// For a variable product the AJAX endpoint adds by the VARIATION id, not the parent.
			var vsel=form.querySelector("input[name=variation_id]");
			var pid=(form.classList.contains("variations_form")&&vsel&&vsel.value&&vsel.value!=="0")?vsel.value:(fd.get("add-to-cart")||addBtn.value||"");
			if(pid){fd.set("product_id",pid);}
			fd.delete("add-to-cart"); // avoid double-add: WC form handler would otherwise add this item again
			addBtn.classList.add("loading");
			fetch(wcAjax("add_to_cart"),{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(data){
				addBtn.classList.remove("loading");
				if(!data||data.error){var a=form.getAttribute("action");if(a){window.location.assign(a);}else{form.submit();}return;}
				if(window.jQuery){window.jQuery(d.body).trigger("added_to_cart",[data.fragments,data.cart_hash,window.jQuery(addBtn)]);}
				else{refreshFragments();openCart();}
			}).catch(function(){addBtn.classList.remove("loading");form.submit();});
		});

		// Loop simple products when WooCommerce AJAX-add is OFF: AJAX it ourselves so the drawer still opens.
		d.addEventListener("click",function(e){
			var btn=e.target.closest("a.add_to_cart_button");
			if(!btn){return;}
			if(btn.classList.contains("product_type_variable")){return;} // handled by Quick View routing below
			if(btn.classList.contains("ajax_add_to_cart")){return;} // WC core will AJAX + fire added_to_cart
			var pid=btn.getAttribute("data-product_id")||"";
			if(!pid){var m=(btn.getAttribute("href")||"").match(/add-to-cart=(\d+)/);if(m){pid=m[1];}}
			if(!pid){return;}
			e.preventDefault();
			var body=new FormData();
			body.append("product_id",pid);
			body.append("quantity",btn.getAttribute("data-quantity")||1);
			btn.classList.add("loading");
			fetch(wcAjax("add_to_cart"),{method:"POST",body:body,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(data){
				btn.classList.remove("loading");
				if(!data||data.error){window.location.assign(btn.getAttribute("href")||window.location.href);return;}
				if(window.jQuery){window.jQuery(d.body).trigger("added_to_cart",[data.fragments,data.cart_hash,window.jQuery(btn)]);}
				else{refreshFragments();openCart();}
			}).catch(function(){btn.classList.remove("loading");window.location.assign(btn.getAttribute("href")||window.location.href);});
		});

		// #7 Variable products in loops: "Select options" reveals options in Quick View
		// instead of bouncing to the product page.
		d.addEventListener("click",function(e){
			var link=e.target.closest("a.product_type_variable, a.add_to_cart_button.product_type_variable");
			if(!link){return;}
			var li=link.closest("li.product");
			var qv=li?li.querySelector(".nura-qv"):null;
			if(!qv){return;} // no Quick View available -> let it navigate
			e.preventDefault();
			qv.click();
		});
	});
})();

/* ===== NURA v1.20.0 - Mini-cart drawer quantity steppers (Store API) ===== */
(function(){
	var d=document;
	function ready(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}

	var API=(function(){
		var base="/wp-json/wc/store/v1/";
		var nonce="";
		function readNonce(res){try{var n=res.headers.get("Nonce");if(n){nonce=n;}}catch(e){}return nonce;}
		function ensureNonce(){
			if(nonce){return Promise.resolve(nonce);}
			return fetch(base+"cart",{credentials:"same-origin",headers:{"Accept":"application/json"}}).then(function(res){return readNonce(res);});
		}
		function send(path,payload){
			return fetch(base+path,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Nonce":nonce},body:JSON.stringify(payload)}).then(function(res){readNonce(res);return res;});
		}
		function post(path,payload){
			return ensureNonce().then(function(){return send(path,payload);}).then(function(res){
				if(res.status===403){nonce="";return ensureNonce().then(function(){return send(path,payload);});}
				return res;
			});
		}
		return {
			setQty:function(key,qty){return post("cart/update-item",{key:key,quantity:qty});},
			remove:function(key){return post("cart/remove-item",{key:key});}
		};
	})();

	function enhance(){
		var items=d.querySelectorAll(".woocommerce-mini-cart-item");
		[].forEach.call(items,function(item){
			if(item.querySelector(".nura-qtystep")){return;}
			var rm=item.querySelector("a.remove_from_cart_button, a.remove");
			var key=rm?rm.getAttribute("data-cart_item_key"):null;
			if(!key){return;}
			var q=item.querySelector(".quantity");
			var m=q?(q.textContent||"").replace(/,/g,"").match(/\d+/):null;
			var qty=m?parseInt(m[0],10):1;
			var wrap=d.createElement("div");
			wrap.className="nura-qtystep";
			wrap.setAttribute("data-key",key);
			wrap.innerHTML='<button type="button" class="nura-qtystep__btn" data-step="-1" aria-label="Decrease quantity">&minus;</button><span class="nura-qtystep__n" aria-live="polite">'+qty+'</span><button type="button" class="nura-qtystep__btn" data-step="1" aria-label="Increase quantity">+</button>';
			item.appendChild(wrap);
		});
	}

	function refreshCart(){
		if(window.jQuery){window.jQuery(d.body).trigger("wc_fragment_refresh");return;}
		fetch("/?wc-ajax=get_refreshed_fragments",{method:"POST",credentials:"same-origin"}).then(function(r){return r.json();}).then(function(j){
			if(j&&j.fragments){Object.keys(j.fragments).forEach(function(sel){var el=d.querySelector(sel);if(el){el.outerHTML=j.fragments[sel];}});enhance();}
		}).catch(function(){});
	}

	function busy(wrap,on){
		wrap.classList.toggle("is-busy",on);
		[].forEach.call(wrap.querySelectorAll("button"),function(b){b.disabled=on;});
	}

	ready(function(){
		var style=d.createElement("style");
		style.textContent=".woocommerce-mini-cart-item{position:relative}.nura-qtystep{display:inline-flex;align-items:center;gap:.4rem;margin-top:.5rem}.nura-qtystep__btn{width:28px;height:28px;line-height:1;border:1px solid #d8cfc4;background:#fff;border-radius:6px;font-size:1rem;cursor:pointer;color:#1a1a1a;display:inline-flex;align-items:center;justify-content:center;padding:0}.nura-qtystep__btn:hover{background:#f4efe8}.nura-qtystep__btn:disabled{opacity:.5;cursor:default}.nura-qtystep__n{min-width:1.7rem;text-align:center;font-weight:600}.nura-qtystep.is-busy{opacity:.55}";
		d.head.appendChild(style);
		enhance();
		if(window.jQuery){window.jQuery(d.body).on("wc_fragments_refreshed wc_fragments_loaded added_to_cart removed_from_cart",function(){enhance();});}
		var drawer=d.querySelector("[data-nura-cartdrawer]");
		if(drawer){try{new MutationObserver(function(){enhance();}).observe(drawer,{childList:true,subtree:true});}catch(e){}}

		d.addEventListener("click",function(e){
			var btn=(e.target&&e.target.closest)?e.target.closest(".nura-qtystep__btn"):null;
			if(!btn){return;}
			e.preventDefault();
			var wrap=btn.closest(".nura-qtystep");
			if(!wrap||wrap.classList.contains("is-busy")){return;}
			var key=wrap.getAttribute("data-key");
			var nEl=wrap.querySelector(".nura-qtystep__n");
			var cur=parseInt(nEl.textContent,10)||1;
			var step=parseInt(btn.getAttribute("data-step"),10)||0;
			var next=cur+step;
			busy(wrap,true);
			var op=(next<1)?API.remove(key):API.setQty(key,next);
			op.then(function(res){
				if(res&&res.ok&&next>=1){nEl.textContent=String(next);}
				refreshCart();
			}).catch(function(){refreshCart();}).then(function(){busy(wrap,false);});
		});
	});
})();

/* ===== NURA v1.20.0 - "Edit cart" link on block checkout ===== */
(function(){
	var d=document;
	function ready(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
	ready(function(){
		if(!d.querySelector(".wp-block-woocommerce-checkout")){return;}
		var cartUrl=(window.wc_cart_fragments_params&&wc_cart_fragments_params.cart_url)?wc_cart_fragments_params.cart_url:"/cart/";
		var style=d.createElement("style");
		style.textContent=".nura-editcart{display:inline-flex;align-items:center;gap:.3rem;margin:0 0 .85rem;font-size:.9rem;font-weight:600;color:#7a5b2e;text-decoration:underline;text-underline-offset:2px}.nura-editcart:hover{color:#5c451f}";
		d.head.appendChild(style);
		function ensure(){
			var anchor=d.querySelector(".wc-block-components-order-summary")||d.querySelector(".wp-block-woocommerce-checkout-order-summary-block");
			if(!anchor){return;}
			var host=anchor.parentNode;
			if(!host){return;}
			if(host.querySelector(":scope > .nura-editcart")){return;}
			var a=d.createElement("a");
			a.className="nura-editcart";
			a.href=cartUrl;
			a.textContent="\u2190 Edit cart";
			host.insertBefore(a,anchor);
		}
		ensure();
		try{new MutationObserver(function(){ensure();}).observe(d.body,{childList:true,subtree:true});}catch(e){}
	});
})();

/* ===== NURA v1.20.1 - Safety net for the Quick View double-add =====
   The Quick View variation form carries a hidden <input name="add-to-cart">.
   If it rides along in the plugin's AJAX add, WooCommerce's classic form
   handler adds the item a SECOND time (double add). The plugin's own fix
   (nurax.js >= 1.35.3) strips it, but this theme-side guard removes it too
   in a capture-phase submit listener that runs BEFORE the plugin's handler,
   so the double-add is prevented even when the plugin build is not yet
   deployed. Scoped to Quick View forms only; harmless once the plugin is
   current. */
(function(){
	var d=document;
	d.addEventListener("submit",function(e){
		var t=e.target;
		var form=(t&&t.closest)?t.closest("form.cart"):null;
		if(!form){return;}
		if(!form.closest("[data-qv-body]")){return;}
		var atc=form.querySelector('input[name="add-to-cart"]');
		if(atc&&atc.parentNode){atc.parentNode.removeChild(atc);}
	},true);
})();
