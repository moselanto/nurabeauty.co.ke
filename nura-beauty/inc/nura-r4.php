<?php
/**
 * Release R4 of the SEO / AEO / GEO roadmap (theme 1.98.0): answer-engine content.
 *
 * 1. New question group "Where to buy wigs in Nairobi and Kenya" added to the shared
 *    customer-question engine (NURA Stylist chat, WhatsApp bot, /wig-questions/ hub,
 *    FAQ schema and /llms-full.txt) - "where to buy human hair wigs in Nairobi",
 *    "best wig shop in Nairobi CBD", "wig shop near me", "original human hair" etc.
 *    Answers are factual (no "number one" claims) and filled from live prices and facts.
 * 2. Kenyan conversational searches table (English / Kiswahili / Sheng) on /wig-questions/.
 * 3. Eight live answer pages, created once (never overwritten) and rendered from code
 *    with [nura_r4 page="slug"], so prices, address and hours stay current:
 *    where-to-buy-human-hair-wigs-nairobi, wig-shop-nairobi-cbd (directions),
 *    same-day-wig-delivery-nairobi, wigs-for-hair-loss-kenya, wigs-for-occasions-kenya,
 *    and Kiswahili pages bei-ya-wigs-kenya, wig-za-kila-siku, wig-bila-gundi (lang sw-KE).
 *    Each page: short answer first, sections, live product picks, FAQ + FAQPage schema.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------- helpers */

function nura_r4_fill( $t ) {
	if ( class_exists( 'NURAX_Knowledge' ) ) {
		return NURAX_Knowledge::fill( $t );
	}
	$f = function ( $k, $d ) {
		return function_exists( 'nura_fact' ) ? nura_fact( $k, $d ) : $d;
	};
	return strtr( $t, array(
		'{address}'   => $f( 'address_short', 'Stanbank House, 2nd Floor, Room 20, Nairobi CBD' ) . ', Moi Avenue',
		'{hours}'     => $f( 'hours_long', 'Monday to Saturday' ),
		'{cutoff}'    => $f( 'cutoff', '5 PM' ),
		'{till}'      => $f( 'till', '1679269' ),
		'{rider}'     => 'KSh 300',
		'{human_min}' => 'our lowest human hair price',
		'{human_max}' => '',
		'{fibre_min}' => 'our lowest price',
		'{fibre_max}' => '',
		'{blend_min}' => 'a mid-range price',
		'{returns}'   => 'Unworn, unaltered wigs can be returned within 48 hours of delivery.',
	) );
}

/* --------------------------------- 1. "where to buy" question group */

function nura_r4_where_questions() {
	return array(
		array( 'Where can I buy human hair wigs in Nairobi?', 'At NURA Beauty, {address}. Our Pure Human wigs cost {human_min} to {human_max}, every unit is labelled with its exact hair type, and you can see and try it in the studio ({hours}) or order online with same-day Nairobi delivery before {cutoff}.', array( 'hair' => 'human' ) ),
		array( 'Where is the best place to buy wigs in Nairobi CBD?', 'Choose a shop where you can see the wig before paying, where hair type is labelled honestly and where installation is available. NURA Beauty is a wig studio at {address}: you can try units on, compare human hair, Semi Human and fibre side by side, and book installation the same day if a slot is free.', array() ),
		array( 'Is there a wig shop near me in Nairobi?', 'NURA Beauty is in Nairobi CBD at {address}, open {hours}. If you cannot come in, we deliver anywhere in Nairobi the same day for orders confirmed before {cutoff} (rider from {rider}), and countrywide by bus or courier.', array() ),
		array( 'Where can I buy original human hair wigs in Kenya?', 'Buy from a seller that labels every unit with its real hair type and lets you inspect it. At NURA, only units labelled Pure Human are human hair; blends and fibre are named as such. Human hair can be washed, heat-styled and dyed; ask us to show you the hair in the studio or on a WhatsApp video before you pay.', array( 'hair' => 'human' ) ),
		array( 'How do I avoid fake human hair wigs in Nairobi?', 'Ask for the exact hair type in writing, check that it takes heat (human hair does not melt or turn wiry), ask for a live video, and buy from a shop with a physical address and a returns policy. NURA lists the hair type on every product and accepts returns of unworn, unaltered units: {returns}', array( 'hair' => 'human' ) ),
		array( 'Which shop sells human hair wigs with installation in Nairobi?', 'NURA Beauty sells human hair wigs and installs them at the studio, {address}. Installation starts from KSh 1,500 and we also install wigs bought elsewhere. Book a morning slot online or on WhatsApp.', array( 'hair' => 'human' ) ),
		array( 'Can I buy a human hair wig online in Kenya and pay with M-Pesa?', 'Yes. Order on nurabeauty.co.ke and pay by M-Pesa or card at checkout, or order on WhatsApp and pay to Buy Goods Till {till}. Nairobi delivery is same day before {cutoff}; other towns: dispatched the same day you pay by shuttle or courier, which sets the arrival time.', array( 'hair' => 'human' ) ),
		array( 'Where can I buy affordable wigs in Nairobi?', 'NURA Semi Human wigs start at {blend_min} and Pure Human wigs at {human_min}. Shop by budget on the website or tell NURA Stylist your budget and we show you the three best options in stock.', array( 'sort' => 'cheap' ) ),
		array( 'Do you have a physical wig shop in Nairobi?', 'Yes - NURA Beauty studio, {address}. Opening hours: {hours}. You can see units, try them on, book a fitting or installation, and collect online orders.', array() ),
		array( 'Can I buy a wig in Nairobi and have it delivered the same day?', 'Yes. Orders confirmed and paid before {cutoff}, Monday to Saturday, are delivered in Nairobi the same day by our rider (from {rider}); later orders go out the next working day.', array() ),
	);
}

add_filter( 'nurax_knowledge_base', function ( $kb ) {
	if ( ! is_array( $kb ) || empty( $kb['cats'] ) ) {
		return $kb;
	}
	foreach ( $kb['cats'] as $c ) {
		if ( isset( $c['id'] ) && 'where-to-buy' === $c['id'] ) {
			return $kb;
		}
	}
	array_splice( $kb['cats'], 1, 0, array( array(
		'id'     => 'where-to-buy',
		'title'  => 'Where to buy wigs in Nairobi and Kenya',
		'kw'     => 'where buy shop store near me nairobi cbd kenya best place original genuine fake human hair physical shop online m-pesa same day',
		'h'      => array( 'hair' => 'human' ),
		'guides' => array( 'how-to-buy-wigs-online-in-kenya-safely', 'best-human-hair-wigs-kenya', 'best-wigs-in-kenya-buying-guide' ),
		'q'      => nura_r4_where_questions(),
	) ) );
	if ( isset( $kb['swahili'] ) && is_array( $kb['swahili'] ) ) {
		$kb['swahili'] += array(
			'nitanunua wapi' => 'where can i buy',
			'naweza nunua wapi' => 'where can i buy',
			'wapi naweza pata' => 'where can i buy',
			'duka la wigi' => 'wig shop',
			'duka la wig' => 'wig shop',
			'karibu na mimi' => 'near me',
			'original' => 'original genuine',
			'feki' => 'fake',
		);
	}
	return $kb;
}, 5 );

/* ------------------------------ 2. conversational searches table */

function nura_r4_convo_table() {
	$rows = array(
		array( 'Ni wig gani iko poa?', 'Which wig is best for me?' ),
		array( 'Nataka wig ya daily use', 'An everyday wig' ),
		array( 'Bei ya human hair ni ngapi?', 'Human hair wig prices' ),
		array( 'Nataka wig isihitaji glue', 'Glueless wigs' ),
		array( 'Mnaweka wig? / Mnaweza kuniinstall leo?', 'Installation, same-day appointments' ),
		array( 'Nataka wig ya budget ya 3k', 'Wigs around KSh 3,000' ),
		array( 'Iko na lace? / Nataka ionekane natural', 'Lace front, natural hairline' ),
		array( 'Mko CBD? / Duka liko wapi?', 'Shop location in Nairobi CBD' ),
		array( 'Mnafanya delivery? / Mnatuma Mombasa?', 'Delivery in Nairobi and countrywide' ),
		array( 'Hii wig inaweza kudumu?', 'How long a wig lasts' ),
		array( 'Nataka bob fupi / Mko na curls?', 'Short bobs, curly wigs' ),
		array( 'Nitanunua wapi human hair wig Nairobi?', 'Where to buy human hair wigs in Nairobi' ),
	);
	$h  = '<section class="nura-wq-sec" id="kiswahili-sheng"><h2>' . esc_html__( 'Ask in English, Kiswahili or Sheng', 'nura-beauty' ) . '</h2>';
	$h .= '<p>' . esc_html__( 'NURA Stylist (on the website and on WhatsApp) understands mixed-language questions. Some of the ways customers ask:', 'nura-beauty' ) . '</p>';
	$h .= '<table><thead><tr><th>' . esc_html__( 'You can ask', 'nura-beauty' ) . '</th><th>' . esc_html__( 'We understand', 'nura-beauty' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$h .= '<tr><td lang="sw">' . esc_html( $r[0] ) . '</td><td>' . esc_html( $r[1] ) . '</td></tr>';
	}
	return $h . '</tbody></table><p><a href="' . esc_url( home_url( '/maswali-ya-wigi/' ) ) . '">' . esc_html__( 'Maswali ya wigi kwa Kiswahili', 'nura-beauty' ) . '</a></p></section>';
}

add_filter( 'the_content', function ( $content ) {
	if ( is_page( 'wig-questions' ) && in_the_loop() && is_main_query() && false === strpos( $content, 'kiswahili-sheng' ) ) {
		$content .= nura_r4_convo_table();
	}
	return $content;
}, 40 );

/* --------------------------------------------- 3. answer pages */

/**
 * slug => [ title, seo title, meta description, focus keyword, lang, short answer,
 *           sections [ h2 => html ], faq [ [q, a] ], picks query, related links ]
 */
function nura_r4_pages() {
	$wa   = 'https://wa.me/254714994898';
	$book = home_url( '/book-appointment/' );
	$dir  = function_exists( 'nura_fact' ) ? nura_fact( 'directions_url', nura_fact( 'map_url', '' ) ) : '';
	$a    = function ( $path, $label ) {
		return '<a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
	};
	return apply_filters( 'nura_r4_pages', array(
		'where-to-buy-human-hair-wigs-nairobi' => array(
			'Where to Buy Human Hair Wigs in Nairobi',
			'Where to Buy Human Hair Wigs in Nairobi (Prices & Shop) | NURA Beauty',
			'Buy Pure Human wigs in Nairobi CBD at NURA Beauty: real prices, honest hair-type labels, try-on and installation at Stanbank House, same-day delivery.',
			'where to buy human hair wigs in nairobi',
			'en',
			'NURA Beauty sells Pure Human wigs at its studio at {address} and online with same-day Nairobi delivery before {cutoff}. Prices run from {human_min} to {human_max}, every unit is labelled with its exact hair type, and you can try it on and have it installed the same visit.',
			array(
				'What a good human hair wig shop gives you' => '<ul><li><strong>Honest labels:</strong> the exact hair type on every unit - Pure Human, Semi Human or luxury fibre.</li><li><strong>See it first:</strong> try units in the studio, or ask for a live WhatsApp video before you pay.</li><li><strong>Real prices:</strong> shown on every product, with no "DM for price".</li><li><strong>Installation and aftercare:</strong> fitting, styling and revamp in the same place.</li><li><strong>A returns policy:</strong> {returns}</li></ul>',
				'Human hair wig prices at NURA' => '<p>Pure Human wigs: {human_min} to {human_max}, rising with length and lace. Semi Human wigs start at {blend_min}. Lipa Mdogo Mdogo (pay in instalments) is available on wigs from KSh 7,000. See the full ' . $a( '/wig-prices-kenya/', 'wig price list' ) . '.</p>',
				'How to check it is real human hair' => '<ul><li>It takes heat: human hair can be flat-ironed and curled without melting.</li><li>It moves and feels like hair, not plastic, and is not unnaturally shiny.</li><li>It can be washed, conditioned and dyed.</li><li>The seller states the hair type in writing on the product or receipt.</li></ul><p>More in our guide ' . $a( '/how-to-buy-wigs-online-in-kenya-safely/', 'how to buy wigs online in Kenya safely' ) . '.</p>',
				'Buy in the studio or online' => '<p><strong>Studio:</strong> {address}, {hours}. ' . ( $dir ? '<a href="' . esc_url( $dir ) . '" target="_blank" rel="noopener">Get directions</a>. ' : '' ) . 'See ' . $a( '/wig-shop-nairobi-cbd/', 'how to find us' ) . '.</p><p><strong>Online:</strong> pay by M-Pesa or card at checkout, or order on WhatsApp and pay to Till {till}. Nairobi same day before {cutoff} (rider from {rider}); countrywide by bus/shuttle parcel or courier.</p>',
			),
			array(
				array( 'Where can I buy human hair wigs in Nairobi?', 'At NURA Beauty, {address}, or online at nurabeauty.co.ke with same-day Nairobi delivery before {cutoff}.' ),
				array( 'How much is a human hair wig in Nairobi?', 'At NURA, Pure Human wigs cost {human_min} to {human_max}, depending on length, density and lace.' ),
				array( 'Can I try a human hair wig before buying?', 'Yes. Visit the studio ({hours}) or ask for a live video on WhatsApp before you pay.' ),
				array( 'Do you install the human hair wig after I buy it?', 'Yes. Installation starts from KSh 1,500 at the studio; book a morning slot.' ),
			),
			'human hair wig',
		),
		'wig-shop-nairobi-cbd' => array(
			'Wig Shop in Nairobi CBD: How to Find NURA Beauty',
			'Wig Shop in Nairobi CBD: Directions to NURA Beauty, Stanbank House',
			'Find the NURA Beauty wig studio in Nairobi CBD: Stanbank House, 2nd Floor, Room 20, Moi Avenue. Opening hours, directions, parking, what to bring and booking.',
			'wig shop nairobi cbd',
			'en',
			'The NURA Beauty wig studio is at {address}, Nairobi CBD. We are open {hours}. Take the stairs or lift to the 2nd floor and look for Room 20; if you cannot find us, call or WhatsApp +254 714 994 898 and we will guide you in.',
			array(
				'Address and opening hours' => '<p><strong>NURA Beauty</strong><br>{address}<br>Nairobi CBD, Kenya</p><p>{hours}. Fittings and installations are by appointment in the mornings; walk-ins are welcome to browse and buy.</p>' . ( $dir ? '<p><a class="nura-btn nura-btn--gold" href="' . esc_url( $dir ) . '" target="_blank" rel="noopener">Open directions in Google Maps</a></p>' : '' ),
				'Getting here' => '<ol><li>Make your way to <strong>Moi Avenue</strong> in the CBD - most matatu stages and the main bus termini are a short walk away.</li><li>Look for <strong>Stanbank House</strong> on Moi Avenue.</li><li>Go up to the <strong>2nd floor</strong> and find <strong>Room 20</strong>.</li><li>Lost? Call or WhatsApp <a href="tel:+254714994898">+254 714 994 898</a> and share your location.</li></ol>',
				'Before you visit' => '<ul><li>For an installation, book first: ' . '<a href="' . esc_url( $book ) . '">book an appointment</a>' . '.</li><li>Want a specific wig? Send us the link on WhatsApp and we will set it aside.</li><li>Pay by M-Pesa (Till {till}), card or bank transfer. We do not take cash on delivery for delivered orders.</li></ul>',
				'Cannot come to town?' => '<p>We deliver in Nairobi the same day for orders before {cutoff} and countrywide by bus/shuttle parcel or courier. See ' . $a( '/same-day-wig-delivery-nairobi/', 'same-day delivery' ) . ' and ' . $a( '/shipping-delivery/', 'shipping and delivery' ) . '.</p>',
			),
			array(
				array( 'Where is NURA Beauty located?', '{address}, Nairobi CBD.' ),
				array( 'What are your opening hours?', '{hours}.' ),
				array( 'Do I need an appointment?', 'Not to browse or buy. Installations and fittings are by appointment, mostly in the mornings.' ),
				array( 'Can I collect an online order at the shop?', 'Yes - choose pick-up at checkout and collect at the studio.' ),
			),
			'best seller wig',
		),
		'same-day-wig-delivery-nairobi' => array(
			'Same-Day Wig Delivery in Nairobi',
			'Same-Day Wig Delivery in Nairobi from KSh 300 | NURA Beauty',
			'Order before 5 PM and get your wig delivered in Nairobi the same day by NURA rider, from KSh 300. Pay by M-Pesa or card. Same-day dispatch countrywide by shuttle or courier.',
			'same day wig delivery nairobi',
			'en',
			'Yes - order and pay before {cutoff}, Monday to Saturday, and our rider delivers your wig in Nairobi the same day, from {rider} depending on your area. Orders after {cutoff} go out the next working day.',
			array(
				'How same-day delivery works' => '<ol><li>Choose your wig and checkout, or order on WhatsApp.</li><li>Pay by M-Pesa or card (or to Till {till} on WhatsApp).</li><li>We confirm and pack your wig, discreetly and quality-checked.</li><li>Our rider calls you on the way and delivers to your door or office.</li></ol>',
				'Delivery fees' => '<ul><li><strong>Nairobi rider:</strong> from {rider}, same day before {cutoff}.</li><li><strong>Countrywide bus / shuttle parcel:</strong> KSh 350, usually dispatched the same day to your town\'s stage.</li><li><strong>Courier (Fargo / G4S):</strong> KSh 550, dispatched the same day you pay; the courier sets the arrival time.</li><li><strong>Studio pick-up:</strong> free at {address}.</li></ul><p>The exact fee for your location shows at checkout.</p>',
				'Outside Nairobi' => '<p>We deliver to all 47 counties. See ' . $a( '/wigs-mombasa/', 'Mombasa' ) . ', ' . $a( '/wigs-kisumu/', 'Kisumu' ) . ', ' . $a( '/wigs-nakuru/', 'Nakuru' ) . ' and ' . $a( '/wigs-eldoret/', 'Eldoret' ) . ', or the full ' . $a( '/shipping-delivery/', 'shipping and delivery' ) . ' page.</p>',
			),
			array(
				array( 'Do you deliver wigs the same day in Nairobi?', 'Yes, for orders confirmed and paid before {cutoff}, Monday to Saturday.' ),
				array( 'How much is wig delivery in Nairobi?', 'From {rider} by rider, depending on your area; the exact fee shows at checkout.' ),
				array( 'Do you offer cash on delivery?', 'No. Delivered orders are paid before dispatch by M-Pesa, card or bank transfer; you can pay at the shop when collecting.' ),
			),
			'ready to wear wig',
		),
		'wigs-for-hair-loss-kenya' => array(
			'Wigs for Hair Loss and Thinning Hair in Kenya',
			'Wigs for Hair Loss & Thinning Hair in Kenya | Private Fittings | NURA',
			'Comfortable, natural-looking wigs for hair loss, thinning hair and treatment in Kenya. Private fittings in Nairobi CBD, glueless options, honest advice.',
			'wigs for hair loss kenya',
			'en',
			'For hair loss or thinning hair, most women are most comfortable in a soft, breathable, glueless wig - a headband or glueless closure unit - in human hair or a good Semi Human. At NURA we offer private, unhurried fittings at {address} so you can try styles and get the cap size right.',
			array(
				'What to look for' => '<ul><li><strong>No glue:</strong> headband and glueless units avoid adhesives on sensitive skin.</li><li><strong>A soft, breathable cap</strong> with adjustable straps and combs.</li><li><strong>Natural density:</strong> lighter units look more natural and feel cooler.</li><li><strong>The right size:</strong> measure your head - see ' . $a( '/how-to-measure-your-head-for-a-wig/', 'how to measure your head' ) . '.</li><li><strong>A wig cap or liner</strong> for comfort and grip.</li></ul>',
				'Private fittings in Nairobi' => '<p>Book a private morning fitting and tell us anything you would like us to know in advance. We help you choose, adjust the fit and show you how to put the wig on and take it off at home. <a href="' . esc_url( $book ) . '">Book a fitting</a> or message us on <a href="' . esc_url( $wa ) . '">WhatsApp</a>.</p>',
				'A note on health' => '<p>We are wig specialists, not doctors. If you are losing hair suddenly or do not know why, please see a doctor or dermatologist; we are happy to work alongside your treatment.</p>',
			),
			array(
				array( 'Which wig is best for hair loss?', 'A glueless or headband wig with a soft, breathable cap and natural density is usually the most comfortable; human hair or a Semi Human looks most natural.' ),
				array( 'Can I get a private wig fitting in Nairobi?', 'Yes. Book a morning fitting at {address} and we will give you an unhurried, private appointment.' ),
				array( 'Do I need glue if my scalp is sensitive?', 'No. Headband and glueless units stay secure with straps, combs and a wig grip, with no adhesive on your skin.' ),
			),
			'glueless headband wig comfortable',
		),
		'wigs-for-occasions-kenya' => array(
			'Wigs for Weddings, Graduation, Church and the Office',
			'Wigs for Weddings, Graduation, Church & Office in Kenya | NURA Beauty',
			'Which wig to wear to a wedding, graduation, church, a corporate event or the office in Kenya - styles, colours, lengths and install-before-your-event bookings.',
			'wig for wedding kenya',
			'en',
			'For weddings and evening events choose a lace front in body wave, deep wave or sleek straight; for graduation and church a polished bob, closure or soft curls; for the office a neat bob, straight or Yaki headband or closure wig in 1B or natural brown. Book installation a day or two before your event.',
			array(
				'Weddings and evening events' => '<p>Lace fronts give the most natural hairline in photos. Body wave, deep wave or a glossy straight style in 18 to 24 inches suit gowns; brides usually book a trial fitting. See ' . $a( '/bridal-wigs-kenya/', 'bridal wigs in Kenya' ) . '.</p>',
				'Graduation, birthdays and church' => '<p>Choose a style that stays neat under a cap or hat and in photos: bobs, closure wigs and defined curls work well. Colour 1B or soft brown reads elegant; burgundy or honey blonde for a birthday statement.</p>',
				'The office and corporate events' => '<p>Low-maintenance, polished styles: a straight or Yaki bob, a closure wig or a headband wig you can put on in minutes. More in ' . $a( '/best-everyday-wigs-for-working-women/', 'the best everyday wigs for working women' ) . '.</p>',
				'Install before your event' => '<p>Book a morning slot one to two days before so the lace settles and the style is set. <a href="' . esc_url( $book ) . '">Book installation</a> or message us on <a href="' . esc_url( $wa ) . '">WhatsApp</a>.</p>',
			),
			array(
				array( 'Which wig should I wear to a wedding?', 'A lace front in body wave, deep wave or sleek straight, 18 to 24 inches, for a natural hairline in photos.' ),
				array( 'Which wig is good for the office?', 'A neat straight or Yaki bob, closure or headband wig in 1B or natural brown - quick to wear and low-maintenance.' ),
				array( 'Can you install my wig before my event?', 'Yes. Book a morning slot one to two days before your event.' ),
			),
			'lace front body wave wig',
		),
		'bei-ya-wigs-kenya' => array(
			'Bei ya Wigi Kenya: Human Hair, Semi Human na Fibre',
			'Bei ya Wigi Kenya 2026: Human Hair, Semi Human na Fibre | NURA Beauty',
			'Bei halisi za wigi Kenya kutoka NURA Beauty: fibre, Semi Human na human hair. Delivery Nairobi siku hiyo hiyo, lipa kwa M-Pesa, Lipa Mdogo Mdogo.',
			'bei ya wigi kenya',
			'sw',
			'Kwa NURA Beauty, wigi za Semi Human zinaanzia {blend_min}, na wigi za Pure Human ni {human_min} hadi {human_max}, kulingana na urefu na lace. Bei iko wazi kwenye kila bidhaa.',
			array(
				'Bei kwa aina ya nywele' => '<ul><li><strong>Semi Human:</strong> kuanzia {blend_min} - inaonekana natural kuliko fibre.</li><li><strong>Pure Human:</strong> {human_min} hadi {human_max} - unaweza kunyoosha, kukunja na kupaka rangi, na inadumu zaidi.</li></ul><p>Angalia ' . $a( '/wig-prices-kenya/', 'orodha kamili ya bei' ) . '.</p>',
				'Lipa Mdogo Mdogo' => '<p>Kwa wigi za kuanzia KSh 7,000, lipa deposit ya 50% kushikilia wigi yako, kisha maliza salio ndani ya wiki 2 - bila riba.</p>',
				'Jinsi ya kununua' => '<p>Nunua kwenye tovuti na ulipe kwa M-Pesa au kadi, au agiza WhatsApp na ulipe Till {till}. Delivery Nairobi siku hiyo hiyo ukiagiza kabla ya {cutoff}. Studio: {address}.</p>',
			),
			array(
				array( 'Bei ya human hair wig ni ngapi Kenya?', 'Kwa NURA, wigi za Pure Human ni {human_min} hadi {human_max}.' ),
				array( 'Wigi ya bei nafuu ni ipi?', 'Wigi za Semi Human, kuanzia {blend_min}, hasa headband na closure.' ),
				array( 'Naweza kulipa kidogo kidogo?', 'Ndiyo - Lipa Mdogo Mdogo kwa wigi za kuanzia KSh 7,000: deposit 50%, salio ndani ya wiki 2.' ),
			),
			'cheap wig',
		),
		'wig-za-kila-siku' => array(
			'Wigi za Kuvaa Kila Siku',
			'Wigi za Kuvaa Kila Siku (Daily Use) Kenya | NURA Beauty',
			'Wigi bora za kuvaa kila siku: rahisi kuvaa, nyepesi na zisizohitaji gundi. Headband, closure na bob - bei halisi, delivery Nairobi siku hiyo hiyo.',
			'wig ya daily use',
			'sw',
			'Kwa matumizi ya kila siku, chagua wigi nyepesi na rahisi kuvaa: headband wig (unavaa kwa dakika tano bila gundi), 4x4 closure wig au bob fupi. Kinky straight (Yaki) na body wave zinaonekana natural na ni rahisi kutunza.',
			array(
				'Wigi tunazopendekeza' => '<ul><li><strong>Headband wig:</strong> haina lace wala gundi - vaa na uvue kila jioni.</li><li><strong>4x4 closure:</strong> mwonekano natural, rahisi kwa wanaoanza.</li><li><strong>Bob fupi:</strong> nyepesi, baridi na haichukui muda kutengeneza.</li></ul>',
				'Utunzaji wa haraka' => '<p>Chana kwa upole kila siku, lala ukiwa umeivua au ukiwa na kitambaa cha satin, na osha baada ya kuvaa mara 10 hadi 15. Soma ' . $a( '/how-to-care-for-a-human-hair-wig/', 'utunzaji wa human hair wig' ) . '.</p>',
			),
			array(
				array( 'Ni wig gani poa ya kutumia daily?', 'Headband wig, 4x4 closure au bob fupi - nyepesi na rahisi kuvaa.' ),
				array( 'Naweza kuvua wigi kila jioni?', 'Ndiyo, hasa headband na glueless wig - hazitumii gundi.' ),
			),
			'everyday headband wig',
		),
		'wig-bila-gundi' => array(
			'Wigi Bila Gundi (Glueless Wigs)',
			'Wigi Bila Gundi (Glueless) Kenya: Headband na Closure | NURA Beauty',
			'Wigi zisizohitaji gundi: headband na glueless closure wigs. Rahisi kwa wanaoanza, vaa kwa dakika tano. Bei halisi, delivery Nairobi siku hiyo hiyo.',
			'wig bila gundi',
			'sw',
			'Wigi bila gundi (glueless) zinashikiliwa na mikanda, combs na wig grip badala ya gundi. Headband wigs na glueless closure wigs ni rahisi zaidi kwa wanaoanza - unaweza kuivaa mwenyewe kwa dakika tano na kuivua kila jioni.',
			array(
				'Aina za wigi bila gundi' => '<ul><li><strong>Headband wig:</strong> mkanda wa kitambaa mbele, hakuna lace - rahisi kuliko zote.</li><li><strong>Glueless closure:</strong> lace ndogo juu, inashikiliwa na mikanda na combs.</li></ul><p>Soma zaidi: ' . $a( '/glueless-wigs-kenya/', 'glueless wigs Kenya' ) . ' na ' . $a( '/headband-wig-vs-lace-front-wig/', 'headband vs lace front' ) . '.</p>',
				'Tunaweza kukusaidia' => '<p>Tembelea studio yetu {address} tukusaidie kuchagua na kurekebisha mikanda, au tuandikie <a href="' . esc_url( $wa ) . '">WhatsApp</a>.</p>',
			),
			array(
				array( 'Nataka wig isihitaji glue - ipi?', 'Headband wig au glueless closure wig - zinashikiliwa na mikanda na combs.' ),
				array( 'Wigi bila gundi inafaa wanaoanza?', 'Ndiyo - ni rahisi kuvaa mwenyewe bila salon.' ),
			),
			'glueless headband wig',
		),
	) );
}

add_shortcode( 'nura_r4', function ( $atts ) {
	$atts  = shortcode_atts( array( 'page' => '' ), $atts, 'nura_r4' );
	$pages = nura_r4_pages();
	if ( empty( $pages[ $atts['page'] ] ) ) {
		return '';
	}
	$p   = $pages[ $atts['page'] ];
	$sw  = 'sw' === $p[4];
	$h   = '<div class="nura-r4"' . ( $sw ? ' lang="sw"' : '' ) . '>';
	$h  .= '<p class="nura-answer"><strong>' . esc_html( $sw ? 'Jibu fupi:' : 'Short answer:' ) . '</strong> ' . esc_html( nura_r4_fill( $p[5] ) ) . '</p>';
	foreach ( $p[6] as $title => $body ) {
		$h .= '<h2>' . esc_html( $title ) . '</h2>' . wp_kses_post( nura_r4_fill( $body ) );
	}
	if ( $p[8] && shortcode_exists( 'nura_stylist_picks' ) ) {
		$h .= do_shortcode( '[nura_stylist_picks q="' . esc_attr( $p[8] ) . '" title="' . esc_attr( $sw ? 'Wigi tunazopendekeza' : 'NURA Stylist picks' ) . '"]' );
	}
	$h .= '<h2>' . esc_html( $sw ? 'Maswali ya kawaida' : 'Questions' ) . '</h2>';
	foreach ( $p[7] as $qa ) {
		$h .= '<details class="nura-faq-item"><summary><h3>' . esc_html( $qa[0] ) . '</h3></summary><div class="nura-faq-item__a"><p>' . esc_html( nura_r4_fill( $qa[1] ) ) . '</p></div></details>';
	}
	$h .= '<p class="nura-cta-row"><a class="nura-btn nura-btn--gold" href="https://wa.me/254714994898" target="_blank" rel="noopener">' . esc_html( $sw ? 'Tuandikie WhatsApp' : 'Chat on WhatsApp' ) . '</a> <a class="nura-btn nura-btn--ghost" href="' . esc_url( home_url( '/shop/' ) ) . '">' . esc_html( $sw ? 'Nunua wigi' : 'Shop wigs' ) . '</a></p>';
	return $h . '</div>';
} );

function nura_r4_current() {
	if ( ! is_page() ) {
		return '';
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	$all  = nura_r4_pages();
	return isset( $all[ $slug ] ) ? $slug : '';
}

/* FAQPage schema for the answer pages (answers match the visible text). */
add_action( 'wp_head', function () {
	$slug = nura_r4_current();
	if ( ! $slug ) {
		return;
	}
	$p     = nura_r4_pages()[ $slug ];
	$items = array();
	foreach ( $p[7] as $qa ) {
		$items[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => nura_r4_fill( $qa[1] ) ) );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => 'sw' === $p[4] ? 'sw-KE' : 'en-KE', 'mainEntity' => $items ) ) . '</script>' . "\n";
}, 30 );

/* Kiswahili pages: html lang="sw-KE". */
add_filter( 'language_attributes', function ( $out ) {
	$slug = nura_r4_current();
	if ( $slug && 'sw' === nura_r4_pages()[ $slug ][4] ) {
		$out = preg_replace( '/lang="[^"]*"/', 'lang="sw-KE"', $out );
	}
	return $out;
}, 20 );

/* One-time creation (never overwrites an existing slug). */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || '1.98.0' === get_option( 'nura_r4_pages' ) ) {
		return;
	}
	foreach ( nura_r4_pages() as $slug => $p ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $p[0], 'post_status' => 'publish', 'post_content' => '[nura_r4 page="' . $slug . '"]' ), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', $p[1] );
			update_post_meta( $id, 'rank_math_description', $p[2] );
			update_post_meta( $id, 'rank_math_focus_keyword', $p[3] );
			if ( function_exists( 'nura_indexnow_queue_post' ) ) {
				nura_indexnow_queue_post( $id );
			}
		}
	}
	update_option( 'nura_r4_pages', '1.98.0', false );
} );

/* AI engines: list the answer pages in /llms.txt and /llms-full.txt. */
add_filter( 'nura_llms_extra', function ( $lines ) {
	$lines[] = '## Answer pages';
	foreach ( nura_r4_pages() as $slug => $p ) {
		$lines[] = '- [' . $p[0] . '](' . home_url( '/' . $slug . '/' ) . '): ' . $p[2];
	}
	$lines[] = '';
	return $lines;
}, 20, 1 );


/* 1.99.0: link the two key local pages from the footer Help menu, the footer fallback and the header Help dropdown. */
function nura_r4_nav_links() {
	return (array) apply_filters( 'nura_r4_nav_links', array(
		array( __( 'Where to buy human hair wigs', 'nura-beauty' ), home_url( '/where-to-buy-human-hair-wigs-nairobi/' ) ),
		array( __( 'Find our Nairobi CBD shop', 'nura-beauty' ), home_url( '/wig-shop-nairobi-cbd/' ) ),
	) );
}
add_filter( 'wp_nav_menu_items', function ( $items, $args ) {
	if ( empty( $args->theme_location ) || 'footer_help' !== $args->theme_location ) {
		return $items;
	}
	foreach ( nura_r4_nav_links() as $l ) {
		if ( false === strpos( $items, $l[1] ) ) {
			$items .= '<li class="menu-item"><a href="' . esc_url( $l[1] ) . '">' . esc_html( $l[0] ) . '</a></li>';
		}
	}
	return $items;
}, 10, 2 );
