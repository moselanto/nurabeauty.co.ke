<?php
/**
 * Theme 1.161.26: Pixie Water Curl (#396) refresh (owner, 18 Nov 2026).
 *
 *  - Name: "Human Hair Water Curl Pixie Wig (13x4 Lace Front)" (link unchanged: /product/pixie-water-curl-wig/).
 *  - Price KSh 3,499 on every colour (sale prices cleared).
 *  - New description, short description and product questions (_nura_faq, shown on the page
 *    and in the FAQPage schema). Described accurately as a 13x4 lace front, 100% human hair.
 *  - The owner's 4 new photos are added in wp-admin (featured + gallery front).
 *
 * One-time data update, run the first time an admin opens wp-admin after this theme is
 * installed (option nura_px396_done). To re-run, delete the option.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_px396_desc() {
	return <<<'HTML'
<p><strong>In short:</strong> the Human Hair Water Curl Pixie Wig is a short, 100% human hair pixie cut with a soft water curl pattern and a 13x4 HD-look lace front, at KSh 3,499 from NURA Beauty in Nairobi. It comes in Black, Burgundy, Piano and C4, at 150% density, on an adjustable cap you can wear glueless. Same-day delivery in Nairobi, countrywide dispatch, pay by M-Pesa or card.</p>

<h2>A short water curl pixie that looks like your own hair</h2>
<p>This pixie is cut short and full, with springy water curls that sit higher on the crown and fall softly around the ears and nape. The 13x4 lace front runs from ear to ear across the hairline, so you can part it on the side, push the curls back or wear a soft fringe, and the front still looks like hair growing from your scalp. It is a confident, low-effort short cut for work, weekends and holidays.</p>

<h2>Why you will love it</h2>
<ul>
<li><strong>100% human hair:</strong> soft, natural movement and shine; you can wash, condition and style it like your own hair.</li>
<li><strong>13x4 lace front:</strong> a natural hairline and free side parting across the front 13 inches of the wig.</li>
<li><strong>Water curl texture:</strong> defined, bouncy curls that come back to shape with water and a little leave-in.</li>
<li><strong>150% density:</strong> full and voluminous, but still light and natural on a short cut.</li>
<li><strong>Glueless option:</strong> adjustable straps and combs hold it securely; use glue only if you want a melted, installed finish.</li>
<li><strong>Checked in Nairobi:</strong> every unit is inspected and photographed as it is in our Moi Avenue studio before it leaves.</li>
</ul>

<h2>Details</h2>
<table>
<tbody>
<tr><th>Hair</th><td>100% human hair (Pure Human)</td></tr>
<tr><th>Construction</th><td>13x4 lace front (lace across the front hairline, ear to ear)</td></tr>
<tr><th>Texture</th><td>Water curl</td></tr>
<tr><th>Style</th><td>Short pixie cut</td></tr>
<tr><th>Density</th><td>150% (full)</td></tr>
<tr><th>Colours</th><td>Black, Burgundy, Piano, C4</td></tr>
<tr><th>Cap</th><td>Average, adjustable straps and combs (wear glueless or installed)</td></tr>
<tr><th>Price</th><td>KSh 3,499</td></tr>
</tbody>
</table>

<h2>How to wear it</h2>
<ol>
<li>Braid or flatten your hair and put on a wig cap.</li>
<li>Place the lace at your natural hairline, then tighten the straps until it feels secure.</li>
<li>Trim the extra lace close to the hairline, press the lace flat and set the edges.</li>
<li>Spray the curls with water and a little leave-in, then scrunch to shape.</li>
</ol>
<p>Prefer a professional finish? Our stylists install, cut the lace and style the hairline at the NURA studio (installation from KSh 1,000).</p>

<h2>Care for your water curls</h2>
<ul>
<li>Wash every 7 to 10 wears with a sulfate-free shampoo and conditioner; Nairobi dust builds up faster than you think.</li>
<li>Do not brush dry curls. Finger-detangle or use a wide-tooth comb on wet, conditioned hair.</li>
<li>Air dry on a wig stand to keep the curl pattern and cap shape.</li>
<li>Refresh daily with a water and leave-in spray; avoid heavy oils, which weigh the curls down.</li>
<li>Store on a stand or in a satin bag, and sleep without it or with a satin scarf.</li>
</ul>

<h2>Who it suits</h2>
<p>Ideal if you want a short, curly look that is quick to put on, gives your own hairline and edges a rest from braids and glue, and still looks natural up close. If you want a long, sleek style, see our straight and body wave wigs instead.</p>

<h2>Price, payment and delivery</h2>
<ul>
<li><strong>Price:</strong> KSh 3,499.</li>
<li><strong>Pay:</strong> M-Pesa or card at checkout, or order on WhatsApp and pay to our M-Pesa Buy Goods Till 1679269. Lipa Mdogo Mdogo (pay in instalments) is available.</li>
<li><strong>Nairobi:</strong> same-day rider delivery for orders before 5 PM (KSh 300 within 5 km of the CBD, plus KSh 50 per km beyond).</li>
<li><strong>Countrywide:</strong> same-day dispatch by shuttle or courier.</li>
<li><strong>See it first:</strong> NURA Beauty studio, Stanbank House, 2nd Floor, Room 20, Moi Avenue, Nairobi CBD.</li>
</ul>
<p>Not sure about the colour? Chat with a NURA stylist on WhatsApp (+254 714 994 898) for a live photo or video before you order.</p>
HTML;
}

function nura_px396_short() {
	return '<p>Short 100% human hair pixie wig with soft water curls and a 13x4 lace front for a natural hairline and side part. 150% density, adjustable glueless cap, in Black, Burgundy, Piano and C4. KSh 3,499 with same-day delivery in Nairobi.</p>';
}

function nura_px396_faq() {
	return array(
		array( 'What is a water curl pixie wig?', 'A water curl pixie is a short pixie-cut wig with a soft, defined curl pattern that looks like wet, freshly washed curls. It is fuller and bouncier than a straight pixie and needs very little styling.' ),
		array( 'Is this pixie wig a lace front?', 'Yes. It has a 13x4 lace front: lace runs ear to ear across the front hairline and 4 inches back, so you get a natural-looking hairline and can part it to the side.' ),
		array( 'Can I wear this pixie wig without glue?', 'Yes. The cap has adjustable straps and combs, so you can wear it glueless. Glue or a lace tint is optional if you want a melted, installed look.' ),
		array( 'What colours does the water curl pixie come in?', 'Black, Burgundy, Piano (a brown and blonde blend) and C4. Ask us on WhatsApp (+254 714 994 898) for a live photo or video of any colour.' ),
		array( 'How do I keep the water curls defined?', 'Wash every 7 to 10 wears with sulfate-free shampoo, finger-detangle while conditioned, air dry on a stand and refresh with water and a light leave-in. Avoid brushing dry curls and heavy oils.' ),
		array( 'How much is the Human Hair Water Curl Pixie Wig in Kenya?', 'KSh 3,499 at NURA Beauty, in every colour. Installation at our Nairobi studio is from KSh 1,000.' ),
	);
}

add_action( 'admin_init', function () {
	if ( get_option( 'nura_px396_done' ) || ! function_exists( 'wc_get_product' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$p = wc_get_product( 396 );
	if ( ! $p ) {
		update_option( 'nura_px396_done', 'missing', false );
		return;
	}
	$log = array();
	if ( $p->is_type( 'variable' ) ) {
		foreach ( $p->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v ) {
				continue;
			}
			$v->set_regular_price( '3499' );
			$v->set_sale_price( '' );
			$v->set_date_on_sale_from( '' );
			$v->set_date_on_sale_to( '' );
			$v->save();
			$log[] = $vid;
		}
	} else {
		$p->set_regular_price( '3499' );
		$p->set_sale_price( '' );
	}
	$p->set_name( 'Human Hair Water Curl Pixie Wig (13x4 Lace Front)' );
	$p->set_description( nura_px396_desc() );
	$p->set_short_description( nura_px396_short() );
	$p->update_meta_data( '_nura_faq', nura_px396_faq() );
	$p->save();
	if ( $p->is_type( 'variable' ) && class_exists( 'WC_Product_Variable' ) ) {
		WC_Product_Variable::sync( 396 );
	}
	wc_delete_product_transients( 396 );
	update_option( 'nura_px396_done', current_time( 'mysql' ) . ' variations ' . implode( ',', $log ), false );
} );
