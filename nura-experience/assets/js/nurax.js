/* NURA Experience - front-end for AI Wig Finder + Virtual Try-On. */
(function () {
	'use strict';
	var d = document;
	function ready(fn){ if(d.readyState!=='loading'){fn();}else{d.addEventListener('DOMContentLoaded',fn);} }

	ready(function () {
		initFinder();
		initTryOn();
	});

	/* ---------- AI Wig Finder ---------- */
	function initFinder() {
			var root = d.querySelector('[data-nurax-finder]');
			if (!root) { return; }
			var quiz = root.querySelector('[data-nurax-quiz]');
			var steps = [].slice.call(root.querySelectorAll('.nurax-step'));
			var results = root.querySelector('[data-nurax-results]');
			var bar = root.querySelector('[data-nurax-progress]');
			var backBtn = root.querySelector('[data-nurax-back]');
			var nextBtn = root.querySelector('[data-nurax-next]');
			var submitBtn = root.querySelector('[data-nurax-submit]');
			var answers = {};
			var cur = 0;
			if (!steps.length || !quiz) { return; }

			[].slice.call(root.querySelectorAll('.nurax-opts')).forEach(function (grp) {
				var field = grp.getAttribute('data-field');
				[].slice.call(grp.querySelectorAll('button')).forEach(function (b) {
					b.addEventListener('click', function () {
						[].slice.call(grp.querySelectorAll('button')).forEach(function (o) { o.classList.remove('is-sel'); });
						b.classList.add('is-sel');
						answers[field] = b.getAttribute('data-value');
					});
				});
			});

			function show(i) {
				cur = Math.max(0, Math.min(i, steps.length - 1));
				steps.forEach(function (s2, idx) { s2.classList.toggle('is-active', idx === cur); });
				if (bar) { bar.style.width = Math.round(((cur + 1) / steps.length) * 100) + '%'; }
				var last = (cur === steps.length - 1);
				if (backBtn) { backBtn.hidden = (cur === 0); }
				if (nextBtn) { nextBtn.hidden = last; }
				if (submitBtn) { submitBtn.hidden = !last; }
			}
			if (nextBtn) { nextBtn.addEventListener('click', function () { show(cur + 1); }); }
			if (backBtn) { backBtn.addEventListener('click', function () { show(cur - 1); }); }

			quiz.addEventListener('submit', function (e) {
				e.preventDefault();
				function val(sel) { var el = quiz.querySelector(sel); return el ? el.value : ''; }
				var name = val('[name="name"]');
				var phone = val('[name="phone"]');
				if (!name || !phone) { window.alert('Please add your name and phone so we can send your recommendation.'); return; }
				var consentEl = quiz.querySelector('[name="consent"]');
				var payload = {
					face: answers.face || '', texture: answers.texture || '', length: answers.length || '',
					life: answers.life || '', budget: answers.budget || '',
					name: name, phone: phone, email: val('[name="email"]'), concern: val('[name="concern"]'),
					consent: (consentEl && consentEl.checked) ? 1 : 0
				};
				results.hidden = false;
				results.innerHTML = '<p class="nurax-results__note">Finding your perfect match...</p>';
				results.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				fetch((window.NURAX && NURAX.rest ? NURAX.rest : '/wp-json/nurax/v1/') + 'wig-finder', {
					method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
				}).then(function (r) { return r.json(); }).then(function (data) {
					var html = '<p class="nurax-results__note">' + (data && data.note ? data.note : 'Your matches') + '</p>';
					if (data && data.products && data.products.length) {
						html += '<div class="nurax-reco">';
						data.products.forEach(function (pr) {
							html += '<a href="' + pr.url + '"><img src="' + pr.img + '" alt="' + pr.name + '" loading="lazy"><span>' + pr.name + '<br><b>' + pr.price + '</b></span></a>';
						});
						html += '</div>';
					}
					html += '<div class="nurax-results__cta">';
					if (data && data.whatsapp) { html += '<a class="nura-btn nura-btn--gold" href="' + data.whatsapp + '" target="_blank" rel="noopener">Book a fitting on WhatsApp</a>'; }
					html += '<a class="nura-btn nura-btn--ghost" href="' + ((window.NURAX && NURAX.shopUrl) ? NURAX.shopUrl : '/shop/') + '">Browse all wigs</a></div>';
					results.innerHTML = html;
				}).catch(function () {
					results.innerHTML = '<p>Something went wrong. Please try again or <a href="/book-appointment/">book a consultation</a>.</p>';
				});
			});

			show(0);
		}

		/* ---------- Virtual Try-On (client-side canvas overlay MVP) ---------- */
	function initTryOn() {
		var root = d.querySelector('[data-nurax-tryon]');
		if (!root) { return; }
		var canvas = root.querySelector('[data-nurax-canvas]');
		var ctx = canvas.getContext('2d');
		var hint = root.querySelector('.nurax-tryon__hint');
		var photoInput = root.querySelector('[data-nurax-photo]');
		var scaleInput = root.querySelector('[data-nurax-scale]');
		var opacityInput = root.querySelector('[data-nurax-opacity]');
		var resetBtn = root.querySelector('[data-nurax-reset]');

		// Overlay image from query param product or data-overlay.
		var overlayUrl = root.getAttribute('data-overlay');
		var params = new URLSearchParams(window.location.search);
		if (params.get('overlay')) { overlayUrl = params.get('overlay'); }

		var photo = null, wig = null;
		var state = { x: canvas.width / 2, y: canvas.height * 0.32, scale: 1, opacity: 1, dragging: false, ox: 0, oy: 0 };

		if (overlayUrl) {
			wig = new Image();
			wig.onload = draw;
			wig.onerror = function () { if (hint) { hint.textContent = 'Could not load the wig image — open a product and tap Try it on.'; } };
			wig.src = overlayUrl;
		}

		function draw() {
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			if (photo) {
				var r = Math.max(canvas.width / photo.width, canvas.height / photo.height);
				var w = photo.width * r, h = photo.height * r;
				ctx.drawImage(photo, (canvas.width - w) / 2, (canvas.height - h) / 2, w, h);
				if (hint) { hint.style.display = 'none'; }
			}
			if (wig && photo) {
				ctx.globalAlpha = state.opacity;
				var ww = wig.width * state.scale * 0.5, wh = wig.height * state.scale * 0.5;
				ctx.drawImage(wig, state.x - ww / 2, state.y - wh / 2, ww, wh);
				ctx.globalAlpha = 1;
			}
		}

		if (photoInput) {
			photoInput.addEventListener('change', function (e) {
				var file = e.target.files[0]; if (!file) { return; }
				var reader = new FileReader();
				reader.onload = function (ev) { photo = new Image(); photo.onload = draw; photo.src = ev.target.result; };
				reader.readAsDataURL(file);
			});
		}
		if (scaleInput) { scaleInput.addEventListener('input', function () { state.scale = this.value / 100; draw(); }); }
		if (opacityInput) { opacityInput.addEventListener('input', function () { state.opacity = this.value / 100; draw(); }); }
		if (resetBtn) { resetBtn.addEventListener('click', function () { state.x = canvas.width / 2; state.y = canvas.height * 0.32; state.scale = 1; if (scaleInput) { scaleInput.value = 100; } draw(); }); }

		// Drag to position the wig.
		function pos(e) { var r = canvas.getBoundingClientRect(); var t = e.touches ? e.touches[0] : e; return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) }; }
		function down(e) { state.dragging = true; var p = pos(e); state.ox = p.x - state.x; state.oy = p.y - state.y; }
		function move(e) { if (!state.dragging) { return; } var p = pos(e); state.x = p.x - state.ox; state.y = p.y - state.oy; draw(); e.preventDefault(); }
		function up() { state.dragging = false; }
		canvas.addEventListener('mousedown', down); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', up);
		canvas.addEventListener('touchstart', down, { passive: true }); canvas.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', up);

		// Hook point for a real face-tracking provider (see plugin settings/readme).
		if (window.nuraxTryonProvider && typeof window.nuraxTryonProvider === 'function') {
			window.nuraxTryonProvider({ canvas: canvas, setState: function (s) { Object.assign(state, s); draw(); } });
		}
	}
})();


/* ===== NURA AI Stylist chat (v1.1.0) ===== */
(function(){var d=document;function r(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
r(function(){
	var root=d.querySelector("[data-nurax-stylist]");if(!root){return;}
	var panel=root.querySelector("[data-stylist-panel]");
	var log=root.querySelector("[data-stylist-log]");
	var form=root.querySelector("[data-stylist-form]");
	var input=form?form.querySelector('input[name="msg"]'):null;
	var wa=root.getAttribute("data-wa")||"";
	var history=[];var busy=false;
	function openPanel(){root.classList.add("is-open");if(panel){panel.removeAttribute("hidden");}if(input){input.focus();}}
	function closePanel(){root.classList.remove("is-open");if(panel){panel.setAttribute("hidden","");}}
	var toggle=root.querySelector("[data-stylist-toggle]");
	if(toggle){toggle.addEventListener("click",function(){if(root.classList.contains("is-open")){closePanel();}else{openPanel();}});}
	var x=root.querySelector("[data-stylist-close]");if(x){x.addEventListener("click",closePanel);}
	function esc(s){var e=d.createElement("div");e.textContent=(s==null)?"":String(s);return e.innerHTML;}
	function addMsg(role,text){var el=d.createElement("div");el.className="nurax-msg nurax-msg--"+(role==="user"?"user":"bot");el.innerHTML=esc(text);log.appendChild(el);log.scrollTop=log.scrollHeight;return el;}
	function addProducts(items){if(!items||!items.length){return;}var wrap=d.createElement("div");wrap.className="nurax-msg nurax-msg--bot nurax-msg--cards";items.forEach(function(pr){var a=d.createElement("a");a.className="nurax-chip";a.href=pr.url||"#";a.target="_blank";a.rel="noopener";a.innerHTML='<img src="'+esc(pr.img)+'" alt="" loading="lazy"><span>'+esc(pr.name)+'<b>'+esc(pr.price)+'</b></span>';wrap.appendChild(a);});log.appendChild(wrap);log.scrollTop=log.scrollHeight;}
	function typing(on){var t=log.querySelector(".nurax-typing");if(on){if(t){return;}var el=d.createElement("div");el.className="nurax-msg nurax-msg--bot nurax-typing";el.innerHTML="<span></span><span></span><span></span>";log.appendChild(el);log.scrollTop=log.scrollHeight;}else if(t){t.parentNode.removeChild(t);}}
	function send(text){
		if(busy||!text){return;}
		busy=true;
		addMsg("user",text);history.push({role:"user",content:text});
		if(input){input.value="";}
		typing(true);
		var rest=(window.NURAX&&NURAX.rest)?NURAX.rest:"/wp-json/nurax/v1/";
		fetch(rest+"stylist",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({messages:history.slice(-10)})})
			.then(function(res){return res.json();})
			.then(function(data){typing(false);var reply=(data&&data.reply)?data.reply:"Sorry, I could not respond just now. Please try WhatsApp.";addMsg("bot",reply);history.push({role:"assistant",content:reply});if(data&&data.products){addProducts(data.products);}busy=false;})
			.catch(function(){typing(false);var el=addMsg("bot","I am having trouble connecting right now. ");if(wa){var a=d.createElement("a");a.href=wa;a.target="_blank";a.rel="noopener";a.textContent="Chat on WhatsApp";a.className="nurax-msg-link";el.appendChild(a);}busy=false;});
	}
	if(form){form.addEventListener("submit",function(e){e.preventDefault();send(input?input.value.trim():"");});}
	[].slice.call(root.querySelectorAll("[data-q]")).forEach(function(qb){qb.addEventListener("click",function(){send(qb.getAttribute("data-q"));});});
});})();


/* ===== NURA Quick View (v1.2.0) ===== */
(function(){var d=document;function r(f){if(d.readyState!=="loading"){f();}else{d.addEventListener("DOMContentLoaded",f);}}
r(function(){
	var modal=d.querySelector("[data-qv-modal]");if(!modal){return;}
	var body=modal.querySelector("[data-qv-body]");
	function openM(){modal.hidden=false;modal.classList.add("is-open");d.body.style.overflow="hidden";}
	function closeM(){modal.hidden=true;modal.classList.remove("is-open");d.body.style.overflow="";}
	[].slice.call(modal.querySelectorAll("[data-qv-close]")).forEach(function(x){x.addEventListener("click",closeM);});
	d.addEventListener("keyup",function(e){if(e.key==="Escape"){closeM();}});
	// Close Quick View whenever an add-to-cart succeeds (the site cart drawer then opens).
	if(window.jQuery){window.jQuery(d.body).on("added_to_cart",function(){closeM();});}
	// Quick View add-to-cart: the simple ajax button is handled by WooCommerce core; this
	// handles the variation form (and any form-based add) -> AJAX add, then open the drawer.
	body.addEventListener("submit",function(ev){
		var form=ev.target.closest("form.cart");
		if(!form){return;}
		ev.preventDefault();
		var isVar=form.classList.contains("variations_form");
		var vidEl=form.querySelector("input[name=variation_id]");
		var variationId=vidEl?String(vidEl.value||""):"";
		var addBtn=form.querySelector("button.single_add_to_cart_button, button[name=add-to-cart]");
		function qvError(msg){var err=form.querySelector(".nura-qv-error");if(!err){err=d.createElement("p");err.className="nura-qv-error";form.appendChild(err);}err.textContent=msg;}
		if(isVar&&(!variationId||variationId==="0"||(addBtn&&addBtn.classList.contains("wc-variation-selection-needed")))){
			qvError("Please choose the available options first.");
			return;
		}
		if(addBtn&&addBtn.classList.contains("disabled")){
			qvError("Sorry, that option is currently unavailable.");
			return;
		}
		var fd=new FormData(form);
		// WooCommerce's ?wc-ajax=add_to_cart adds by product_id; for a variable product
		// that id MUST be the chosen VARIATION id (not the parent) or the add is rejected.
		if(isVar&&variationId){fd.set("product_id",variationId);fd.set("variation_id",variationId);}
		else{var pid=fd.get("add-to-cart")||fd.get("product_id")||(addBtn&&addBtn.value)||"";if(pid){fd.set("product_id",pid);}}
		fd.delete("add-to-cart"); // strip so WooCommerce classic form handler does not add a 2nd copy on this AJAX request
		var qtyEl=form.querySelector("input.qty, input[name=quantity]");
		if(qtyEl&&qtyEl.value){fd.set("quantity",qtyEl.value);}
		var ep=(window.wc_add_to_cart_params&&wc_add_to_cart_params.wc_ajax_url)?wc_add_to_cart_params.wc_ajax_url.replace("%%endpoint%%","add_to_cart"):"/?wc-ajax=add_to_cart";
		var label=addBtn?addBtn.textContent:"";
		if(addBtn){addBtn.classList.add("loading");addBtn.setAttribute("aria-busy","true");addBtn.disabled=true;}
		fetch(ep,{method:"POST",body:fd,credentials:"same-origin"}).then(function(res){return res.json();}).then(function(data){
			if(addBtn){addBtn.classList.remove("loading");addBtn.removeAttribute("aria-busy");addBtn.disabled=false;}
			if(!data||data.error){qvError("Sorry, we couldn't add that just now. Please try again.");return;}
			if(addBtn){addBtn.classList.add("added");addBtn.textContent="Added \u2713";setTimeout(function(){if(addBtn){addBtn.textContent=label||"Add to cart";addBtn.classList.remove("added");}},1600);}
			if(window.jQuery){window.jQuery(d.body).trigger("added_to_cart",[data.fragments,data.cart_hash,addBtn?window.jQuery(addBtn):null]);}
			else{if(data.fragments){Object.keys(data.fragments).forEach(function(sel){var t=d.querySelector(sel);if(t){t.outerHTML=data.fragments[sel];}});}closeM();d.dispatchEvent(new CustomEvent("nura:cartadded"));}
		}).catch(function(){if(addBtn){addBtn.classList.remove("loading");addBtn.removeAttribute("aria-busy");addBtn.disabled=false;}qvError("Network hiccup \u2014 please try again.");});
	});
	d.addEventListener("click",function(e){
		var el=e.target;while(el&&el.nodeType!==1){el=el.parentNode;}
		var btn=(el&&el.closest)?el.closest(".nura-qv"):null;
		if(!btn){return;}
		e.preventDefault();
		var id=btn.getAttribute("data-qv");
		body.innerHTML='<p class="nura-qv-loading">Loading...</p>';openM();
		var rest=(window.NURAX&&NURAX.rest)?NURAX.rest:"/wp-json/nurax/v1/";
		fetch(rest+"quickview?id="+encodeURIComponent(id)).then(function(res){return res.json();}).then(function(pr){
			if(!pr||pr.error){body.innerHTML='<p style="padding:2rem">Sorry, this product could not be loaded.</p>';return;}
			body.innerHTML='<div class="nura-qv-grid"><div class="nura-qv-media"><img src="'+pr.image+'" alt=""></div><div class="nura-qv-info"><h3>'+pr.title+'</h3><div class="nura-qv-price">'+(pr.price||"")+'</div><div class="nura-qv-desc">'+(pr.excerpt||"")+'</div><div class="nura-qv-actions">'+(pr.add||"")+'<a class="nura-btn nura-btn--ghost" href="'+pr.url+'">Full details</a></div></div></div>';
			if(window.jQuery){window.jQuery(d.body).trigger("wc_fragment_refresh");var $vf=window.jQuery(body).find("form.variations_form");if($vf.length&&$vf.wc_variation_form){try{$vf.wc_variation_form();$vf.trigger("check_variations");}catch(err){}}}
		}).catch(function(){body.innerHTML='<p style="padding:2rem">Sorry, something went wrong.</p>';});
	});
});})();
