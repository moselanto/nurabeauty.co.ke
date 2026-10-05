<?php
/**
 * NURA policy & trust pages - one-click importer + content refresher.
 *
 * Creates the standard e-commerce trust/policy pages (About, Contact, Delivery,
 * Refund & Returns, Privacy, Terms, Wig Care Guide, Payment Info, Wig
 * Installation, FAQ) with real, NURA-specific copy, and can refresh the DRAFT
 * pages to the latest copy in one click.
 *
 * Safety model:
 *  - "Create" only inserts pages whose slug does not yet exist; it never
 *    overwrites an existing page. New pages are created as drafts.
 *  - "Update drafts" only rewrites pages that are still in DRAFT status. It
 *    never touches a PUBLISHED page - so an already-live page you have edited
 *    (for example Contact Us or FAQ) is left exactly as it is.
 *  - New/updated pages stay drafts, so nothing goes live until you review and
 *    publish.
 *
 * The copy is grounded in NURA's own published details (Nairobi studio,
 * +254 714 994 898, care@nurabeauty.co.ke, M-Pesa/card/PayPal, same-day Nairobi
 * delivery, 48-hour returns window). A few store-specific values that only the
 * owner can confirm (exact Till number, precise fees) are shown at checkout or
 * left as a clearly marked note rather than invented.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Pages {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_nurax_make_pages', array( $this, 'handle_create' ) );
		add_action( 'admin_post_nurax_update_pages', array( $this, 'handle_update' ) );
	}

	/** slug => array( title, content ) */
	public static function defs() {
		$biz   = get_bloginfo( 'name' );
		$phone = '+254 714 994 898';
		$email = 'care@nurabeauty.co.ke';

		$about = <<<HTML
<h2>The House of Radiant Confidence</h2>
<p>$biz is a Nairobi-based house of premium human hair wigs in Kenya, built on a simple belief: every woman deserves to wear her crown with confidence. We hand-finish and personally curate each unit, then back it with the kind of concierge service you would expect from a luxury house.</p>
<h3>What we stand for</h3>
<ul>
<li><strong>Honest hair.</strong> Our premium units are verified human hair, supplied with a provenance certificate and a written guarantee. We never sell synthetic hair as human hair.</li>
<li><strong>Personal service.</strong> From your first consultation to installation and aftercare, our team helps you choose, fit and maintain the right wig for your face shape, lifestyle and budget.</li>
<li><strong>Made for Kenya.</strong> Same-day delivery in Nairobi, fast countrywide shipping and M-Pesa checkout, designed around how our clients actually shop for wigs in Kenya.</li>
</ul>
<h3>The NURA Experience</h3>
<p>We are more than a wig shop. Our team handles installation, revamp, styling, colouring and consultation end to end, and members of <em>The NURA Circle</em> enjoy care reminders, scheduled revamps and loyalty rewards. Visit us at Imenti House, Moi Avenue, Nairobi CBD - hand-finished in Nairobi, made for you.</p>
<p>Have a question or want a recommendation? Message us on WhatsApp at <strong>$phone</strong> or email <strong>$email</strong>.</p>
HTML;

		$contact = <<<HTML
<h2>Contact Us</h2>
<p>We would love to hear from you. Reach us by WhatsApp, phone or email, or visit our Nairobi studio by appointment.</p>
<h3>Talk to us</h3>
<ul>
<li><strong>WhatsApp &amp; phone:</strong> $phone - fastest for orders, consultations and bookings.</li>
<li><strong>Email:</strong> $email</li>
<li><strong>Studio:</strong> Nairobi, Kenya (by appointment)</li>
<li><strong>Hours:</strong> Monday to Saturday, 9:00am to 6:00pm</li>
</ul>
<h3>Visit the studio</h3>
<p>Come in for a consultation, fitting, installation or styling. So we can give you our full concierge attention, please book an appointment on WhatsApp before visiting.</p>
<h3>Follow the house</h3>
<p>See transformations, tutorials and new drops on Instagram and TikTok at <strong>@nurabeauty</strong>.</p>
HTML;

		$delivery = <<<HTML
<h2>Delivery Information</h2>
<p>We deliver premium wigs, hair and beauty across Kenya and worldwide, carefully packaged and tracked.</p>
<h3>Delivery times</h3>
<ul>
<li><strong>Nairobi:</strong> same-day delivery on orders placed before 2:00pm; otherwise next day.</li>
<li><strong>Countrywide (Kenya):</strong> 1 to 3 business days via trusted couriers.</li>
<li><strong>International / diaspora:</strong> 3 to 10 business days via DHL or FedEx with full tracking, priced in USD.</li>
</ul>
<h3>Fees &amp; processing</h3>
<p>Delivery fees are calculated at checkout based on your location; free delivery is available on qualifying orders. Orders are processed within 1 business day. You will receive tracking details once your order is dispatched.</p>
<h3>Pay on delivery</h3>
<p>Pay-on-delivery is available within Nairobi. For all other locations we confirm payment (or a deposit) before dispatch. See our <a href="/payment-information/">Payment Information</a> page for options.</p>
<p>Questions about a delivery? Message us on WhatsApp at <strong>$phone</strong>.</p>
HTML;

		$returns = <<<HTML
<h2>Returns &amp; Refunds</h2>
<p>Your confidence matters. We want you to love your NURA unit - here is exactly how returns, exchanges and refunds work.</p>
<h3>Our promise</h3>
<p>For hygiene and safety reasons, wigs and hair products are intimate items. We follow Kenyan consumer-protection guidance and offer returns and exchanges under the clear conditions below. Nothing here removes any right you have under the Consumer Protection Act.</p>
<h3>What can be returned</h3>
<ul>
<li><strong>Unworn, unaltered units</strong> in original condition with tags on, lace uncut and all packaging intact - within <strong>14 days</strong> of delivery.</li>
<li><strong>Wrong item received</strong> - we cover return shipping and send the correct unit or a full refund.</li>
<li><strong>Faulty or damaged on arrival</strong> - reported with photos within 48 hours of delivery (see Warranty for full coverage terms).</li>
</ul>
<h3>What cannot be returned</h3>
<ul>
<li>Units that have been <strong>worn, washed, cut, coloured, installed or restyled</strong>.</li>
<li>Custom or bespoke units built to your specification (unless faulty).</li>
<li>Clearance items and gift cards.</li>
<li>Accessories that have been opened or used, for hygiene reasons (caps, edge control).</li>
</ul>
<h3>Who pays for return shipping</h3>
<ul>
<li><strong>Wrong item received or faulty on arrival:</strong> NURA covers return shipping in full.</li>
<li><strong>Change-of-mind returns</strong> (unworn unit, within 14 days): return shipping is the customer's responsibility. We recommend a tracked courier so we can confirm arrival before processing your refund.</li>
</ul>
<h3>International &amp; diaspora orders</h3>
<p>Because of the cost of return freight on international shipments, change-of-mind returns are not available for orders shipped outside Kenya via DHL or FedEx. Diaspora orders remain fully covered by our <a href="/warranty/">Warranty</a> for faulty or damaged units. If you are unsure about sizing, colour or texture before ordering internationally, we strongly recommend a free virtual consultation first, or our Virtual Try-On tool.</p>
<h3>How to start a return</h3>
<ol>
<li>Contact us on WhatsApp or email within 14 days of delivery, quoting your order number.</li>
<li>Share clear photos of the item and packaging.</li>
<li>We confirm approval and share return instructions, including the return address.</li>
<li>Send the item back (unworn, in original packaging) via a tracked courier.</li>
</ol>
<h3>Refunds &amp; exchanges</h3>
<ul>
<li>Approved refunds are processed to your original payment method (M-Pesa or card) within <strong>3 to 7 business days</strong> of us receiving and inspecting the returned item.</li>
<li>Original delivery fees are non-refundable unless the return is due to our error (wrong item or fault).</li>
<li>Prefer an exchange or store credit? We can swap length, texture or colour, or issue NURA credit - often faster than a refund.</li>
</ul>
<h3>Questions</h3>
<p>Our team will guide you through every step. Reach us on WhatsApp for the fastest response at <strong>$phone</strong> or email <strong>$email</strong>.</p>
HTML;

		$privacy = <<<HTML
<h2>Privacy Policy</h2>
<p><em>Last updated: September 2026. Please have this reviewed by a Kenyan lawyer, or against the ODPC model templates, before publishing.</em></p>
<p>$biz (part of NURA CROWN &amp; BEAUTY - "NURA", "we", "us", "our") operates nurabeauty.co.ke (the "Site"). This policy explains what personal information we collect, how we use it, and the choices you have. We handle your data in line with Kenya's Data Protection Act, 2019.</p>
<h3>Who we are</h3>
<ul>
<li>NURA CROWN &amp; BEAUTY (trading as NURA Beauty)</li>
<li>Business Registration No. BN-MJS7ALRV</li>
<li>Imenti House, Moi Avenue, Nairobi CBD, Kenya</li>
<li>Email: $email</li>
<li>Phone / WhatsApp: $phone</li>
</ul>
<h3>Information we collect</h3>
<ul>
<li><strong>Information you give us:</strong> name, phone number, email, delivery address and order details when you place an order, book an appointment, message us on WhatsApp, or subscribe to Join The House.</li>
<li><strong>Payment information:</strong> we do not store your full M-Pesa or card details. Payments are processed securely through Safaricom (M-Pesa) and our card and PayPal payment providers, who handle this data under their own security and privacy standards.</li>
<li><strong>Automatically collected information:</strong> IP address, browser type, device information and browsing behaviour on our Site, collected via cookies and similar technologies.</li>
<li><strong>Photos you share:</strong> if you send photos via WhatsApp (for a return, warranty claim or styling consultation), we keep these only as long as needed to resolve your request.</li>
</ul>
<h3>How we use your information</h3>
<ul>
<li>Process and deliver your orders</li>
<li>Communicate with you about orders, appointments and support</li>
<li>Send marketing updates, if you have opted in (you can unsubscribe anytime)</li>
<li>Improve our Site, products and services</li>
<li>Meet legal and tax obligations</li>
<li>Prevent fraud and protect our business and customers</li>
</ul>
<h3>How we share your information</h3>
<p>We share information only where necessary to run our business: delivery partners (such as courier companies, DHL and FedEx) to fulfil your order; payment processors to take payment securely; and service providers who support our Site (hosting, email, WhatsApp business tools). We do not sell your personal information to third parties.</p>
<h3>Cookies</h3>
<p>Our Site uses cookies to keep your cart working, remember your preferences and understand how visitors use the Site. You can control cookies through your browser settings; disabling them may affect how the Site functions.</p>
<h3>Your rights</h3>
<p>Under Kenya's Data Protection Act you have the right to know what data we hold, request a copy, request correction, request deletion (subject to legal retention), and withdraw consent to marketing at any time. To exercise any of these, email $email.</p>
<h3>Data security &amp; retention</h3>
<p>We take reasonable technical and organisational measures to protect your information, though no method of transmission over the internet is completely secure. We keep your information only as long as needed for the purposes above, or as required by law.</p>
<h3>Changes &amp; contact</h3>
<p>We may update this policy from time to time; the date above reflects the latest revision. Questions? Email $email or message us on WhatsApp at $phone.</p>
HTML;

		$terms = <<<HTML
<h2>Terms &amp; Conditions</h2>
<p><em>Last updated: September 2026.</em></p>
<p>Welcome to $biz. By accessing or using nurabeauty.co.ke, placing an order, or booking an appointment, you agree to the terms below.</p>
<h3>About NURA</h3>
<p>$biz (registered as NURA CROWN &amp; BEAUTY, Business Registration No. BN-MJS7ALRV) is East Africa's house of radiant confidence, offering premium human-hair and fashion wigs, hair care and beauty products, hand-finished and serviced from our studio at Imenti House, Moi Avenue, Nairobi CBD.</p>
<h3>Orders &amp; payment</h3>
<ul>
<li>All prices are listed in Kenyan Shillings (KES) unless otherwise stated; international orders may be quoted in USD.</li>
<li>We accept M-Pesa, card payment, PayPal and pay-on-delivery within Nairobi (where available).</li>
<li>Orders are confirmed once payment is received or, for pay-on-delivery orders, once we have confirmed the order details with you.</li>
<li>We may cancel or refuse an order - for example in cases of suspected fraud, pricing errors or stock unavailability - and will notify you promptly.</li>
</ul>
<h3>Product information</h3>
<p>We do our best to accurately describe and photograph every product, including hair type (human hair versus synthetic fibre), texture, length and construction. Minor variations in colour or texture are possible due to the natural nature of human hair and to photography and screen differences.</p>
<h3>Shipping &amp; delivery</h3>
<p>See our <a href="/shipping-delivery/">Shipping &amp; Delivery</a> page for timelines and fees. Delivery estimates are not guaranteed and may be affected by factors outside our control (courier delays, customs processing for international orders).</p>
<h3>Returns, refunds &amp; warranty</h3>
<p>See our <a href="/returns-refunds/">Returns &amp; Refunds</a> and <a href="/warranty/">Warranty</a> pages for full terms. Nothing in these Terms affects your statutory rights under Kenya's Consumer Protection Act.</p>
<h3>Custom &amp; bespoke orders</h3>
<p>Custom and bespoke units are made to your specification and are non-returnable unless faulty. Production timelines are confirmed at the time of order.</p>
<h3>Appointments &amp; installation</h3>
<p>Appointments booked through our Site or WhatsApp are subject to availability. Please give at least 24 hours notice if you need to reschedule or cancel.</p>
<h3>Intellectual property</h3>
<p>All content on this Site - photos, text, logos and design - is owned by NURA or used under licence, and may not be copied or reused without our written permission.</p>
<h3>Limitation of liability</h3>
<p>To the extent permitted by law, NURA is not liable for indirect or consequential losses arising from use of our Site or products. This does not limit any liability that cannot be excluded under Kenyan law.</p>
<h3>Governing law</h3>
<p>These Terms are governed by the laws of Kenya, and any disputes will be subject to the jurisdiction of Kenyan courts.</p>
<h3>Changes &amp; contact</h3>
<p>We may update these Terms from time to time; continued use of the Site after changes are posted means you accept the updated Terms. Questions? Email $email or message us on WhatsApp at $phone.</p>
HTML;

		$care = <<<HTML
<h2>Wig Care Guide</h2>
<p>Treat your NURA unit well and it will stay soft, full and radiant for far longer. Every unit ships with a care card and a QR code linking to a care video.</p>
<h3>Washing</h3>
<ul>
<li>Gently detangle from the ends up before washing.</li>
<li>Wash every 7 to 10 wears (or sooner with heavy product use) in cool water with a sulphate-free shampoo.</li>
<li>Condition the hair - avoid the lace and knots - then rinse thoroughly.</li>
</ul>
<h3>Drying &amp; styling</h3>
<ul>
<li>Blot with a towel and air-dry on a wig stand; never brush while soaking wet.</li>
<li>Use heat tools only on human-hair units, on a low-to-medium setting, with a heat protectant.</li>
<li>Keep the lace clean and free of heavy oils to preserve a natural hairline.</li>
</ul>
<h3>Storage</h3>
<p>Store your unit on a stand or in the satin bag it came in, away from direct sunlight and dust. This keeps the style and prevents tangling.</p>
<h3>Keep it effortless</h3>
<p>Stock up on the essentials from our <a href="/product-category/wig-care/">Wig Care</a> range, and join <em>The NURA Circle</em> for automatic care reminders and scheduled professional revamps. Need a refresh? Ask us about wash &amp; revamp at <strong>$phone</strong>.</p>
HTML;

		$payment = <<<HTML
<h2>Payment Information</h2>
<p>Shopping with NURA is secure and flexible. Choose whatever works best for you at checkout.</p>
<h3>Ways to pay</h3>
<ul>
<li><strong>M-Pesa:</strong> STK push, Buy Goods/Till, or Pochi la Biashara. Your M-Pesa prompt and Till details are shown at checkout.</li>
<li><strong>Card:</strong> Visa and Mastercard, processed securely.</li>
<li><strong>PayPal:</strong> ideal for diaspora and international orders.</li>
<li><strong>Pay on delivery:</strong> available within Nairobi.</li>
</ul>
<h3>NURA Flex (flexible payment)</h3>
<p>For units above KES 15,000, begin with a <strong>50% M-Pesa deposit</strong> and pay the balance before delivery, or split the cost over instalments. Lipa Later / Buy-Now-Pay-Later is available on eligible orders.</p>
<h3>Security</h3>
<p>Payments are handled by trusted, encrypted providers. We never see or store your full card details. If a payment fails or you need help, message us on WhatsApp at <strong>$phone</strong>.</p>
HTML;

		$install = <<<HTML
<h2>Wig Installation &amp; Services</h2>
<p>NURA is a full-service house. Beyond selling your unit, our stylists help you wear it flawlessly.</p>
<h3>Installation</h3>
<p>Book a professional installation at our Nairobi studio, or - for VIP clients - at home. We melt the lace, style and finish so your unit looks natural and photograph-ready.</p>
<h3>Our services</h3>
<ul>
<li>Installation &amp; hairline styling</li>
<li>Styling, curling and restyling</li>
<li>Colouring and customisation</li>
<li>Wash &amp; revamp (bring your unit back to life)</li>
<li>Repairs and re-ventilation</li>
<li>Custom wig making and bridal hair</li>
</ul>
<h3>Book your appointment</h3>
<p>Message us on WhatsApp at <strong>$phone</strong> or email <strong>$email</strong> to book. Studio hours are Monday to Saturday, 9:00am to 6:00pm. We recommend booking ahead so we can reserve dedicated time for you.</p>
HTML;

		$faq = <<<HTML
<h2>Frequently Asked Questions</h2>
<h3>Are NURA wigs real human hair?</h3>
<p>Yes. Our premium units are verified human hair with a provenance certificate and written guarantee. We never sell synthetic hair as human hair.</p>
<h3>How do I choose the right wig?</h3>
<p>Take our Find Your Wig quiz, chat with NURA Stylist, or book a consultation. You can also visit our Nairobi studio for a fitting.</p>
<h3>What lengths, textures and colours are available?</h3>
<p>Most units come in 10&Prime; to 30&Prime; lengths, densities of 130% to 200%, textures from straight to afro curly, and colours from natural black to blonde, ombr&eacute;, burgundy and 613. Each variation shows its own price and availability on the product page.</p>
<h3>How do I pay?</h3>
<p>M-Pesa, Visa/Mastercard, PayPal, and pay-on-delivery in Nairobi. NURA Flex is available on units above KES 15,000. See <a href="/payment-information/">Payment Information</a>.</p>
<h3>How fast is delivery?</h3>
<p>Same-day within Nairobi (order before 2:00pm), 1 to 3 business days countrywide, and 3 to 10 business days internationally with tracking. See <a href="/shipping-delivery/">Delivery Information</a>.</p>
<h3>Can I return or exchange a wig?</h3>
<p>Unworn, unaltered units can be returned within 48 hours of delivery. Worn, cut or installed units cannot be returned for hygiene reasons. See <a href="/returns-refunds/">Refund &amp; Returns</a>.</p>
<h3>Do you install wigs?</h3>
<p>Yes - installation, styling, colouring, revamp and repairs. See <a href="/wig-installation/">Wig Installation</a>.</p>
<p>Still have a question? Chat with us on WhatsApp at <strong>$phone</strong>.</p>
HTML;

		$sizing = <<<HTML
<h2>Wig Size &amp; Fit Guide</h2>
<p>The right fit is what makes a wig look natural and feel comfortable all day. This quick guide shows you how to measure your head, understand cap sizes and choose the right density - so you order with confidence and rarely need to return.</p>
<h3>How to measure your head</h3>
<p>Use a soft tape measure (or a piece of string you then hold against a ruler), with your own hair flat. Take these four measurements:</p>
<ul>
<li><strong>Circumference:</strong> around your hairline - across the forehead, above the ears and around the nape. This is the most important number. Most women measure 21 to 23 inches (53 to 58 cm).</li>
<li><strong>Front to nape:</strong> from your front hairline straight back to the nape of your neck.</li>
<li><strong>Ear to ear (over the top):</strong> across the crown from the top of one ear to the other.</li>
<li><strong>Temple to temple (round the back):</strong> across the back of the head between the temples.</li>
</ul>
<h3>Cap sizes</h3>
<ul>
<li><strong>Petite:</strong> about 20 to 21 inches (51 to 53.5 cm).</li>
<li><strong>Average:</strong> about 21.5 to 22.5 inches (54.5 to 57 cm) - this fits most women and is our standard cap.</li>
<li><strong>Large:</strong> about 23 to 24 inches (58.5 to 61 cm).</li>
</ul>
<p>Between sizes? Choose the larger cap and use the adjustable straps to tighten the fit.</p>
<h3>What an adjustable cap means</h3>
<p>Most NURA units are built on an <strong>average, adjustable cap</strong>. Inside the back you will find:</p>
<ul>
<li><strong>Adjustable straps:</strong> two hooked straps that tighten or loosen the cap by up to about an inch.</li>
<li><strong>Combs:</strong> small clips that anchor the unit to your own hair or a wig cap for a secure hold.</li>
<li><strong>Elastic band:</strong> a stretch band along the nape that keeps the wig snug and helps hold a lace front flat.</li>
</ul>
<h3>Density - how full the wig looks</h3>
<ul>
<li><strong>130% to 150%:</strong> natural, everyday fullness that suits most face shapes.</li>
<li><strong>180%:</strong> fuller and more glamorous, popular for curly and longer styles.</li>
<li><strong>200% and above:</strong> very full, dramatic red-carpet volume.</li>
</ul>
<h3>Still not sure?</h3>
<p>Try our <a href="/ai-wig-finder/">Find Your Wig</a> quiz and <a href="/virtual-try-on/">Virtual Try-On</a> for the look, then message us on WhatsApp at <strong>$phone</strong> or email <strong>$email</strong> and we will help you confirm size, cap and density before you order. You can also visit our Nairobi studio for a fitting.</p>
HTML;

		return array(
			'about-nura'           => array( 'About NURA', $about ),
			'contact-us'           => array( 'Contact Us', $contact ),
			'delivery-information' => array( 'Delivery Information', $delivery ),
			'returns-refunds'       => array( 'Returns & Refunds', $returns ),
			'privacy-policy'       => array( 'Privacy Policy', $privacy ),
			'terms-conditions'     => array( 'Terms & Conditions', $terms ),
			'wig-care-guide'       => array( 'Wig Care Guide', $care ),
			'wig-size-fit-guide'   => array( 'Wig Size & Fit Guide', $sizing ),
			'payment-information'  => array( 'Payment Information', $payment ),
			'wig-installation'     => array( 'Wig Installation', $install ),
			'faq'                  => array( 'FAQ', $faq ),
		);
	}

	public function menu() {
		add_submenu_page( 'woocommerce', 'NURA Pages', 'NURA Pages', 'manage_options', 'nurax-pages', array( $this, 'page' ) );
	}

	private function status() {
		$rows = array();
		foreach ( self::defs() as $slug => $d ) {
			$existing      = get_page_by_path( $slug );
			$rows[ $slug ] = array( $d[0], $existing ? $existing->post_status : '' );
		}
		return $rows;
	}

	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rows = $this->status();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA Policy & Trust Pages', 'nura-experience' ); ?></h1>
			<p style="max-width:760px"><?php esc_html_e( 'These are your standard trust and policy pages, written with real NURA details. "Create" adds any missing page as a draft. "Update drafts" refreshes pages that are still drafts to the latest copy - it never changes a page you have already published (such as Contact Us or FAQ). Nothing goes live until you review and publish it.', 'nura-experience' ); ?></p>

			<?php if ( isset( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success"><p><?php printf( esc_html__( 'Created %d new draft page(s).', 'nura-experience' ), (int) $_GET['created'] ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success"><p><?php printf( esc_html__( 'Refreshed %d draft page(s) to the latest content. Review under Pages, then publish.', 'nura-experience' ), (int) $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
			<?php endif; ?>

			<table class="widefat" style="max-width:640px;margin:1em 0">
				<thead><tr><th><?php esc_html_e( 'Page', 'nura-experience' ); ?></th><th><?php esc_html_e( 'Status', 'nura-experience' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $slug => $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $r[0] ); ?></strong> <code>/<?php echo esc_html( $slug ); ?>/</code></td>
						<td><?php
						if ( 'publish' === $r[1] ) {
							echo '<span style="color:#2e7d32;font-weight:600">' . esc_html__( 'Published (left untouched)', 'nura-experience' ) . '</span>';
						} elseif ( 'draft' === $r[1] ) {
							echo '<span style="color:#b8860b;font-weight:600">' . esc_html__( 'Draft', 'nura-experience' ) . '</span>';
						} else {
							echo '<span style="color:#a00">' . esc_html__( 'Not created', 'nura-experience' ) . '</span>';
						}
						?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p style="display:flex;gap:1rem;flex-wrap:wrap">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nurax_make_pages">
					<?php wp_nonce_field( 'nurax_make_pages' ); ?>
					<?php submit_button( __( 'Create missing pages', 'nura-experience' ), 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nurax_update_pages">
					<?php wp_nonce_field( 'nurax_update_pages' ); ?>
					<?php submit_button( __( 'Update draft pages to latest content', 'nura-experience' ), 'primary', 'submit', false ); ?>
				</form>
			</p>
			<p class="description" style="max-width:760px"><?php esc_html_e( 'Tip: after updating, open each page under Pages, adjust anything specific to you (Till number, exact fees), and click Publish.', 'nura-experience' ); ?></p>
		</div>
		<?php
	}

	public function handle_create() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'nurax_make_pages' ) ) {
			wp_die( esc_html__( 'Not allowed', 'nura-experience' ) );
		}

		$created  = 0;
		$page_ids = array();
		foreach ( self::defs() as $slug => $d ) {
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				$page_ids[] = (int) $existing->ID;
				continue;
			}
			$id = wp_insert_post( array(
				'post_title'   => $d[0],
				'post_name'    => $slug,
				'post_content' => $d[1],
				'post_status'  => 'draft',
				'post_type'    => 'page',
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				$created++;
				$page_ids[] = (int) $id;
			}
		}

		$this->footer_menu( $page_ids );

		wp_safe_redirect( admin_url( 'admin.php?page=nurax-pages&created=' . (int) $created ) );
		exit;
	}

	public function handle_update() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'nurax_update_pages' ) ) {
			wp_die( esc_html__( 'Not allowed', 'nura-experience' ) );
		}

		$updated = 0;
		foreach ( self::defs() as $slug => $d ) {
			$existing = get_page_by_path( $slug );
			if ( ! $existing || 'draft' !== $existing->post_status ) {
				continue; // Never touch published pages.
			}
			$res = wp_update_post( array(
				'ID'           => (int) $existing->ID,
				'post_title'   => $d[0],
				'post_content' => $d[1],
			), true );
			if ( $res && ! is_wp_error( $res ) ) {
				$updated++;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nurax-pages&updated=' . (int) $updated ) );
		exit;
	}

	/** Build/refresh a "NURA Footer" menu with these pages and assign it to a free footer location. */
	private function footer_menu( $page_ids ) {
		if ( empty( $page_ids ) ) {
			return;
		}
		$menu    = wp_get_nav_menu_object( 'NURA Footer' );
		$menu_id = $menu ? (int) $menu->term_id : wp_create_nav_menu( 'NURA Footer' );
		if ( is_wp_error( $menu_id ) || ! $menu_id ) {
			return;
		}
		$menu_id = (int) $menu_id;

		$linked = array();
		$items  = wp_get_nav_menu_items( $menu_id );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( 'page' === $item->object ) {
					$linked[] = (int) $item->object_id;
				}
			}
		}
		foreach ( $page_ids as $pid ) {
			if ( in_array( (int) $pid, $linked, true ) ) {
				continue;
			}
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-object'    => 'page',
				'menu-item-object-id' => (int) $pid,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			) );
		}

		// Assign to a footer location only if the theme registers one and it is empty.
		$locations = get_registered_nav_menus();
		$assigned  = get_theme_mod( 'nav_menu_locations', array() );
		if ( ! is_array( $assigned ) ) {
			$assigned = array();
		}
		$changed = false;
		foreach ( array_keys( (array) $locations ) as $loc ) {
			if ( false !== stripos( $loc, 'footer' ) && empty( $assigned[ $loc ] ) ) {
				$assigned[ $loc ] = $menu_id;
				$changed          = true;
			}
		}
		if ( $changed ) {
			set_theme_mod( 'nav_menu_locations', $assigned );
		}
	}
}
