<?php
/**
 * Virtual Try-On - client-side visual preview (standalone).
 *
 * Rebuilt to be genuinely usable:
 *   - Base image from an uploaded photo OR the device camera (still capture).
 *   - The product image's plain studio background is knocked out on the client
 *     with an edge flood-fill, so the overlay is the wig/head silhouette rather
 *     than a white rectangle. A soft bottom fade hides the mannequin's chest.
 *   - Drag to position, plus Size, Rotate and Blend controls, Reset, and Save.
 *   - Everything runs in the browser; the photo is never uploaded or stored.
 *
 * This is an honest visual preview, not automated AR face-tracking. A real
 * face-landmark provider can still be attached via window.nuraxTryonProvider
 * (the UI is unchanged). Markup uses data-nura-vto so it does not collide with
 * the legacy handler in nurax.js.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Virtual_TryOn {

	public function __construct() {
		add_shortcode( 'nura_virtual_tryon', array( $this, 'render' ) );
		// NOTE: the single-product "Try it on" button now renders in the
		// consolidated action row in NURAX_Product_Page, so it lines up with the
		// new "Order on WhatsApp" button. No product hook here.
	}

	public function render( $atts ) {
		$atts       = shortcode_atts( array( 'product' => 0 ), $atts, 'nura_virtual_tryon' );
		$product_id = absint( $atts['product'] );
		// Product arrives from the "Try it on" button as ?tryon={id}. We avoid
		// ?product= because "product" is a reserved WooCommerce query var and
		// would 404 the try-on page.
		if ( ! $product_id && isset( $_GET['tryon'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$product_id = absint( wp_unslash( $_GET['tryon'] ) );
		}
		$overlay = '';
		$title   = '';
		if ( $product_id ) {
			$overlay = wp_get_attachment_image_url( get_post_thumbnail_id( $product_id ), 'large' );
			$title   = get_the_title( $product_id );
		}

		ob_start(); ?>
		<div class="nura-vto" data-nura-vto data-overlay="<?php echo esc_url( $overlay ); ?>">
			<div class="nura-vto__stage">
				<canvas class="nura-vto__canvas" data-vto-canvas width="640" height="820"></canvas>
				<video class="nura-vto__video" data-vto-video playsinline muted hidden></video>
				<div class="nura-vto__empty" data-vto-empty>
					<p><?php esc_html_e( 'Upload a clear, front-facing photo (or use your camera) to preview the look.', 'nura-experience' ); ?></p>
				</div>
			</div>

			<div class="nura-vto__bar">
				<label class="nura-btn nura-btn--gold nura-vto__file">
					<span data-vto-uploadlabel><?php esc_html_e( 'Upload photo', 'nura-experience' ); ?></span>
					<input type="file" accept="image/*" data-vto-photo hidden>
				</label>
				<button type="button" class="nura-btn nura-btn--ghost" data-vto-camera><?php esc_html_e( 'Use camera', 'nura-experience' ); ?></button>
				<button type="button" class="nura-btn nura-btn--gold" data-vto-capture hidden><?php esc_html_e( 'Capture', 'nura-experience' ); ?></button>
				<button type="button" class="nura-btn nura-btn--ghost" data-vto-save hidden><?php esc_html_e( 'Save preview', 'nura-experience' ); ?></button>
				<button type="button" class="nura-btn nura-btn--ghost" data-vto-reset><?php esc_html_e( 'Reset', 'nura-experience' ); ?></button>
			</div>

			<div class="nura-vto__sliders">
				<label><span><?php esc_html_e( 'Size', 'nura-experience' ); ?></span><input type="range" min="30" max="220" value="100" data-vto-scale></label>
				<label><span><?php esc_html_e( 'Rotate', 'nura-experience' ); ?></span><input type="range" min="-45" max="45" value="0" data-vto-rotate></label>
				<label><span><?php esc_html_e( 'Blend', 'nura-experience' ); ?></span><input type="range" min="50" max="100" value="100" data-vto-opacity></label>
			</div>

			<p class="nura-vto__note"><small><?php esc_html_e( 'A visual preview to picture the look: upload or snap a photo, then drag, resize, rotate and blend the wig over it. Your photo stays in your browser and is never uploaded or shared. For an exact match, book a fitting at our Nairobi studio.', 'nura-experience' ); ?></small></p>
		</div>

		<style>
		.nura-vto{max-width:680px;margin:1.5rem auto;font-family:inherit}
		.nura-vto__stage{position:relative;width:100%;max-width:520px;margin:0 auto;aspect-ratio:640/820;background:#f3efe9;border:1px solid #e6ddcf;border-radius:16px;overflow:hidden}
		.nura-vto__canvas{position:absolute;inset:0;width:100%;height:100%;touch-action:none;cursor:grab}
		.nura-vto__canvas:active{cursor:grabbing}
		.nura-vto__video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
		.nura-vto__empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;text-align:center;padding:2rem;color:#8a7f6d}
		.nura-vto__empty p{margin:0;font-size:.95rem}
		.nura-vto__bar{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center;margin:1rem auto 0}
		.nura-vto__bar .nura-btn{cursor:pointer}
		.nura-vto__file{display:inline-flex;align-items:center;margin:0}
		.nura-vto__sliders{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.6rem 1.2rem;max-width:520px;margin:1rem auto 0}
		.nura-vto__sliders label{display:flex;align-items:center;gap:.6rem;font-size:.85rem;color:#5a5346}
		.nura-vto__sliders label span{min-width:44px}
		.nura-vto__sliders input[type=range]{flex:1}
		.nura-vto__note{text-align:center;color:#8a7f6d;margin:1rem auto 0;max-width:520px}
		</style>

		<script>
		(function(){
			var roots = document.querySelectorAll('[data-nura-vto]');
			Array.prototype.forEach.call(roots, function(root){ initVTO(root); });

			function initVTO(root){
				var canvas = root.querySelector('[data-vto-canvas]');
				if (!canvas) { return; }
				var ctx = canvas.getContext('2d');
				var video = root.querySelector('[data-vto-video]');
				var empty = root.querySelector('[data-vto-empty]');
				var fileInput = root.querySelector('[data-vto-photo]');
				var camBtn = root.querySelector('[data-vto-camera]');
				var capBtn = root.querySelector('[data-vto-capture]');
				var saveBtn = root.querySelector('[data-vto-save]');
				var resetBtn = root.querySelector('[data-vto-reset]');
				var scaleI = root.querySelector('[data-vto-scale]');
				var rotI = root.querySelector('[data-vto-rotate]');
				var opI = root.querySelector('[data-vto-opacity]');

				var W = canvas.width, H = canvas.height;
				var photo = null, wig = null, stream = null;
				var st = { x: W/2, y: H*0.30, scale: 1, rot: 0, opacity: 1, drag:false, ox:0, oy:0 };

				var overlayUrl = root.getAttribute('data-overlay') || '';
				var params = new URLSearchParams(window.location.search);
				if (params.get('overlay')) { overlayUrl = params.get('overlay'); }
				if (overlayUrl) {
					var img = new Image();
					img.crossOrigin = 'anonymous';
					img.onload = function(){ wig = knockoutBg(img); fitDefault(); draw(); };
					img.onerror = function(){ wig = null; };
					img.src = overlayUrl;
				}

				// Remove the plain studio background via an edge flood-fill, then
				// fade the bottom so the mannequin's shoulders/chest disappear.
				function knockoutBg(image){
					var max = 520;
					var s = Math.min(1, max / Math.max(image.width, image.height));
					var w = Math.max(1, Math.round(image.width * s));
					var h = Math.max(1, Math.round(image.height * s));
					var off = document.createElement('canvas');
					off.width = w; off.height = h;
					var octx = off.getContext('2d');
					octx.drawImage(image, 0, 0, w, h);
					var data;
					try { data = octx.getImageData(0, 0, w, h); } catch(e) { return off; }
					var px = data.data;
					function idx(x,y){ return (y*w + x)*4; }
					// Reference background = average of the four corners.
					var cs = [idx(0,0), idx(w-1,0), idx(0,h-1), idx(w-1,h-1)];
					var br=0,bg=0,bb=0;
					cs.forEach(function(c){ br+=px[c]; bg+=px[c+1]; bb+=px[c+2]; });
					br/=4; bg/=4; bb/=4;
					var thresh = 46; // colour distance
					var visited = new Uint8Array(w*h);
					var stack = [];
					for (var x=0; x<w; x++){ stack.push([x,0]); stack.push([x,h-1]); }
					for (var y=0; y<h; y++){ stack.push([0,y]); stack.push([w-1,y]); }
					while (stack.length){
						var p = stack.pop(); var xx=p[0], yy=p[1];
						if (xx<0||yy<0||xx>=w||yy>=h) { continue; }
						var vi = yy*w+xx;
						if (visited[vi]) { continue; }
						visited[vi]=1;
						var i = vi*4;
						var dr=px[i]-br, dg=px[i+1]-bg, db=px[i+2]-bb;
						if ((dr*dr+dg*dg+db*db) > thresh*thresh) { continue; }
						px[i+3]=0;
						stack.push([xx+1,yy]); stack.push([xx-1,yy]); stack.push([xx,yy+1]); stack.push([xx,yy-1]);
					}
					// Soft bottom fade over the last 22%.
					var fadeFrom = Math.floor(h*0.78);
					for (var fy=fadeFrom; fy<h; fy++){
						var a = 1 - (fy-fadeFrom)/(h-fadeFrom);
						for (var fx=0; fx<w; fx++){
							var fi = idx(fx,fy);
							px[fi+3] = Math.min(px[fi+3], Math.round(px[fi+3]*a));
						}
					}
					octx.putImageData(data, 0, 0);
					return off;
				}

				function fitDefault(){
					if (!wig) { return; }
					var targetH = H * 0.6;
					st.scale = targetH / wig.height;
					st.x = W/2; st.y = H*0.30; st.rot = 0;
					if (scaleI) { scaleI.value = 100; }
					if (rotI) { rotI.value = 0; }
					// Re-scale the slider baseline so 100 == fitted size.
					st.base = st.scale;
				}

				function draw(){
					ctx.clearRect(0,0,W,H);
					if (photo){
						var r = Math.max(W/photo.width, H/photo.height);
						var pw = photo.width*r, ph = photo.height*r;
						ctx.drawImage(photo, (W-pw)/2, (H-ph)/2, pw, ph);
					}
					if (wig && (photo || !overlayUrl)){
						var base = st.base || 1;
						var sc = base * (st.slider || 1);
						var ww = wig.width*sc, wh = wig.height*sc;
						ctx.save();
						ctx.globalAlpha = st.opacity;
						ctx.translate(st.x, st.y);
						ctx.rotate(st.rot*Math.PI/180);
						ctx.drawImage(wig, -ww/2, -wh/2, ww, wh);
						ctx.restore();
						ctx.globalAlpha = 1;
					}
				}
				st.slider = 1;

				function setPhotoFromSrc(src){
					photo = new Image();
					photo.onload = function(){ if (empty){ empty.style.display='none'; } if (saveBtn){ saveBtn.hidden=false; } draw(); };
					photo.src = src;
				}

				if (fileInput){
					fileInput.addEventListener('change', function(e){
						var f = e.target.files && e.target.files[0]; if (!f) { return; }
						stopCam();
						var rd = new FileReader();
						rd.onload = function(ev){ setPhotoFromSrc(ev.target.result); };
						rd.readAsDataURL(f);
					});
				}

				function stopCam(){
					if (stream){ stream.getTracks().forEach(function(t){ t.stop(); }); stream=null; }
					if (video){ video.hidden=true; }
					if (capBtn){ capBtn.hidden=true; }
				}

				if (camBtn){
					camBtn.addEventListener('click', function(){
						if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){ window.alert('Camera is not available on this device or browser.'); return; }
						navigator.mediaDevices.getUserMedia({ video: { facingMode:'user' }, audio:false }).then(function(s){
							stream = s; video.srcObject = s; video.hidden=false; video.play();
							if (empty){ empty.style.display='none'; }
							if (capBtn){ capBtn.hidden=false; }
						}).catch(function(){ window.alert('We could not access the camera. Please allow camera access or upload a photo instead.'); });
					});
				}
				if (capBtn){
					capBtn.addEventListener('click', function(){
						if (!stream) { return; }
						var tmp = document.createElement('canvas');
						var vw = video.videoWidth||W, vh = video.videoHeight||H;
						tmp.width=vw; tmp.height=vh;
						tmp.getContext('2d').drawImage(video,0,0,vw,vh);
						setPhotoFromSrc(tmp.toDataURL('image/png'));
						stopCam();
					});
				}

				if (scaleI){ scaleI.addEventListener('input', function(){ st.slider = this.value/100; draw(); }); }
				if (rotI){ rotI.addEventListener('input', function(){ st.rot = parseFloat(this.value)||0; draw(); }); }
				if (opI){ opI.addEventListener('input', function(){ st.opacity = this.value/100; draw(); }); }
				if (resetBtn){ resetBtn.addEventListener('click', function(){ st.slider=1; st.opacity=1; if(scaleI){scaleI.value=100;} if(rotI){rotI.value=0;} if(opI){opI.value=100;} fitDefault(); draw(); }); }
				if (saveBtn){ saveBtn.addEventListener('click', function(){ try { var a=document.createElement('a'); a.download='nura-tryon.png'; a.href=canvas.toDataURL('image/png'); a.click(); } catch(e){ window.alert('Saving is blocked for this image on this browser.'); } }); }

				function pos(e){ var r=canvas.getBoundingClientRect(); var t=e.touches?e.touches[0]:e; return { x:(t.clientX-r.left)*(W/r.width), y:(t.clientY-r.top)*(H/r.height) }; }
				function down(e){ st.drag=true; var p=pos(e); st.ox=p.x-st.x; st.oy=p.y-st.y; }
				function move(e){ if(!st.drag){return;} var p=pos(e); st.x=p.x-st.ox; st.y=p.y-st.oy; draw(); if(e.cancelable){e.preventDefault();} }
				function up(){ st.drag=false; }
				canvas.addEventListener('mousedown',down); window.addEventListener('mousemove',move); window.addEventListener('mouseup',up);
				canvas.addEventListener('touchstart',down,{passive:true}); canvas.addEventListener('touchmove',move,{passive:false}); window.addEventListener('touchend',up);

				if (window.nuraxTryonProvider && typeof window.nuraxTryonProvider === 'function'){
					window.nuraxTryonProvider({ canvas: canvas, redraw: draw, setState: function(s){ Object.assign(st,s); draw(); } });
				}
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
