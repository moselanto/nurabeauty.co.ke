<?php
/**
 * NURA customer-question knowledge base (plugin 1.81.0).
 *
 * 12 intent groups of real customer questions (English, Kiswahili, Sheng) with
 * short, honest answers. Tokens such as {human_min}, {delivery_ke} and
 * {address} are filled at runtime from the live WooCommerce catalogue and the
 * theme business facts, so prices and policies are never hard-coded here.
 * 'h' = product hints used to pick three live products for each answer.
 * Edit freely; filter: nurax_knowledge_base.
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

return array(
	'swahili' => array(
		'ni wig gani poa' => 'which wig is best',
		'wig gani poa' => 'which wig is best',
		'iko poa' => 'best',
		'poa' => 'best',
		'nataka' => 'i want',
		'ya daily' => 'everyday daily wear',
		'daily use' => 'everyday daily wear',
		'kila siku' => 'everyday daily wear',
		'bei ya' => 'price of',
		'bei' => 'price cost',
		'ni ngapi' => 'how much cost',
		'pesa ngapi' => 'how much cost',
		'isihitaji glue' => 'glueless without glue',
		'bila glue' => 'glueless without glue',
		'haina glue' => 'glueless without glue',
		'mnaweka wig' => 'do you offer wig installation',
		'mnaweka' => 'do you offer wig installation',
		'kuniinstall' => 'install installation',
		'kuinstall' => 'install installation',
		'kufix' => 'install installation fixing',
		'wig fixing' => 'wig installation',
		'wig fitting' => 'wig installation fitting',
		'fix wig' => 'wig installation',
		'lace melting' => 'lace front installation',
		'frontal install' => 'lace front installation',
		'closure install' => 'closure installation',
		'wig install' => 'wig installation',
		'leo' => 'today',
		'kesho' => 'tomorrow',
		'budget ya' => 'budget under',
		'iko na lace' => 'lace front',
		'ionekane natural' => 'natural looking',
		'natural hair ya kiafrika' => 'natural african look afro kinky',
		'mko cbd' => 'physical shop location cbd',
		'mko wapi' => 'where is your shop location',
		'duka' => 'shop location',
		'mnafanya delivery' => 'delivery',
		'mnaleta' => 'delivery',
		'mnatuma' => 'delivery countrywide',
		'inaweza kudumu' => 'how long does it last',
		'kudumu' => 'last longer',
		'fupi' => 'short bob',
		'bob fupi' => 'short bob',
		'ndefu' => 'long',
		'mko na curls' => 'which wig has more volume curly',
		'curls' => 'curly',
		'nywele' => 'hair',
		'nywele asili' => 'human hair',
		'rangi' => 'colour',
		'kazi' => 'work office',
		'kanisa' => 'church',
		'harusi' => 'wedding',
		'sherehe' => 'event party',
		'kulipa pole pole' => 'instalments lipa mdogo mdogo',
		'pole pole' => 'instalments',
		'mdogo mdogo' => 'instalments lipa mdogo mdogo',
		'mpesa' => 'm-pesa',
		'kichwa kubwa' => 'big head cap size',
		'kichwa ndogo' => 'small head cap size',
		'kuosha' => 'wash',
		'kulala na wig' => 'sleep with wig',
		'beginner' => 'beginner easy',
		'rahisi' => 'easy beginner',
		'african hair' => 'natural african look afro kinky',
	),
	'cats' => array(
		array(
			'id' => 'prices',
			'title' => 'Wig prices and affordability',
			'kw' => 'price cost how much budget cheap affordable value instalment deposit ksh kes 2000 3000 5000 10000',
			'h' => array(
				'sort' => 'cheap',
			),
			'guides' => array(
				'human-hair-vs-semi-human-vs-fibre-wigs-kenya',
				'best-wigs-in-kenya-buying-guide',
				'affordable-wigs-under-3000-kenya',
			),
			'q' => array(
				array(
					'How much is a good wig in Kenya?',
					'At NURA, heat-resistant fibre wigs cost {fibre_min} to {fibre_max}, human-hair blend (semi-human) wigs start at {blend_min}, and 100% human hair wigs cost {human_min} to {human_max}, rising with length. A good wig is one whose hair type, length and lace suit how often you will wear it, so tell us your budget and we will match you.',
					array(),
				),
				array(
					'Where can I buy affordable human hair wigs in Nairobi?',
					'At the NURA Beauty studio, {address}, open {hours}, or online with same-day Nairobi delivery for orders before {cutoff}. Our 100% human hair wigs start at {human_min}, and the shorter lengths and bobs are the most affordable.',
					array(
						'hair' => 'human',
						'sort' => 'cheap',
					),
				),
				array(
					'Which wigs can I get for KES 2,000?',
					'Around KES 2,000 you are looking at heat-resistant fibre wigs. Our most affordable unit starts at {fibre_min}; headband and 4x4 closure fibre wigs sit just above that.',
					array(
						'max' => 2500,
						'sort' => 'cheap',
					),
				),
				array(
					'Do you have good wigs under KES 3,000?',
					'Yes. Several heat-resistant fibre wigs are under KES 3,000, including headband wigs (no glue needed) and 4x4 closure wigs in kinky straight (Yaki), loose wave and short water-curl bob styles.',
					array(
						'max' => 3000,
						'sort' => 'cheap',
					),
				),
				array(
					'What is the difference between a KES 3,000 wig and a KES 10,000 wig?',
					'Mostly the hair. Around KES 3,000 you get heat-resistant fibre: styled out of the box, low maintenance, about 3 to 6 months of regular wear. Around KES 10,000 you get 100% human hair: the most natural movement, can be heat-styled and coloured, and lasts about a year or more with good care.',
					array(),
				),
				array(
					'How much does a 100% human hair wig cost?',
					'Our 100% human hair wigs cost {human_min} to {human_max}. Bobs and 12 to 14 inch curls are at the lower end; 18 to 22 inch lengths cost the most.',
					array(
						'hair' => 'human',
						'sort' => 'cheap',
					),
				),
				array(
					'Can I get a good-looking wig for KES 5,000?',
					'Yes. Up to KES 5,000 you can choose from our heat-resistant fibre wigs, including lace front and 4x4 closure styles, and most of them leave money for delivery.',
					array(
						'max' => 5000,
					),
				),
				array(
					'Which wig gives the best value for money?',
					'If you will wear it every day for a year or more, a 100% human hair wig usually gives the best value over time. If you like changing styles often, two fibre wigs for the price of one human hair wig can be better value.',
					array(),
				),
				array(
					'Do you sell wigs on instalments?',
					'Yes, through Lipa Mdogo Mdogo: pay a 50% deposit and clear the balance within 1 or 2 weeks, then we release your wig.',
					array(),
				),
				array(
					'Can I pay deposit and clear the balance later?',
					'Yes. With Lipa Mdogo Mdogo you pay a 50% deposit and the balance within 1 or 2 weeks. Pay by M-Pesa or card.',
					array(),
				),
			),
		),
		array(
			'id' => 'hair-types',
			'title' => 'Human hair vs synthetic vs semi-human',
			'kw' => 'human hair synthetic fibre semi-human semi human blend real genuine flat iron straighten curl heat tangle natural difference better',
			'h' => array(),
			'guides' => array(
				'human-hair-vs-semi-human-vs-fibre-wigs-kenya',
				'how-long-does-a-wig-last',
			),
			'q' => array(
				array(
					'Which is better, human hair or synthetic wigs?',
					'Neither is better for everyone. 100% human hair looks the most natural, takes heat and colour and lasts longest, but costs the most. Heat-resistant fibre (synthetic) costs much less and keeps its style with little effort. Choose by budget and how often you will wear it.',
					array(),
				),
				array(
					'What is semi-human hair?',
					'Semi-human hair, also called a human-hair blend, mixes human hair with heat-resistant fibre. It feels softer and moves more naturally than pure fibre at a lower price than 100% human hair.',
					array(
						'hair' => 'blend',
					),
				),
				array(
					'Is semi-human hair real human hair?',
					'Only partly. A semi-human (blend) wig contains some human hair mixed with fibre, so it is not 100% human hair. Every NURA product name states its exact hair type.',
					array(
						'hair' => 'blend',
					),
				),
				array(
					'Which wigs can I straighten using a flat iron?',
					'100% human hair wigs can be straightened with a flat iron using a heat protectant. Blend wigs take low to medium heat only, and heat-resistant fibre wigs low heat only.',
					array(
						'hair' => 'human',
					),
				),
				array(
					'Which wigs can I curl?',
					'100% human hair wigs can be curled with heat. Blend wigs take gentle, low to medium heat. For fibre, choose a wig that already has the curl you want, such as water curl or bubble curl.',
					array(
						'hair' => 'human',
					),
				),
				array(
					'Do synthetic wigs look natural?',
					'A good heat-resistant fibre wig can look neat and natural, especially a kinky straight (Yaki) or closure style, but it has more shine and less movement than human hair.',
					array(
						'hair' => 'fibre',
						'texture' => 'yaki',
					),
				),
				array(
					'Which wig lasts longer?',
					'With regular wear and good care, 100% human hair lasts about a year or more, a semi-human (blend) wig about 6 to 9 months, and a heat-resistant fibre wig about 3 to 6 months.',
					array(
						'hair' => 'human',
					),
				),
				array(
					'Which wig doesn\'t tangle easily?',
					'Straight and kinky straight (Yaki) styles tangle the least. Tight curls need more gentle detangling. Whatever you choose, detangle from the ends up and never sleep in the wig.',
					array(
						'texture' => 'straight',
					),
				),
				array(
					'Is a human hair wig worth the price?',
					'It is worth it if you wear a wig most days or want to heat-style and colour it, because it lasts much longer. If you wear wigs occasionally, a fibre wig may be the smarter buy.',
					array(
						'hair' => 'human',
					),
				),
				array(
					'How can I identify genuine human hair?',
					'Human hair moves and feels like natural hair, takes heat without melting and has a soft, low shine. At NURA you do not have to guess: every product name states 100% human hair, human-hair blend or heat-resistant fibre, and you can feel the units at our studio.',
					array(
						'hair' => 'human',
					),
				),
			),
		),
		array(
			'id' => 'glueless',
			'title' => 'Glueless and beginner-friendly wigs',
			'kw' => 'glue glueless beginner easy first time without salon five minutes headband everyday daily comfortable remove evening',
			'h' => array(
				'construction' => 'headband',
			),
			'guides' => array(
				'glueless-wigs-kenya',
				'headband-wig-vs-lace-front-wig',
				'best-wigs-for-beginners-kenya',
			),
			'q' => array(
				array(
					'Which wig can I wear without glue?',
					'Headband wigs need no glue at all, and 4x4 closure wigs are usually worn glueless with their adjustable straps and combs.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'What is a glueless wig?',
					'A glueless wig stays on with an adjustable band, straps, combs or clips instead of adhesive, so you can put it on and take it off in minutes without damaging your edges.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Which wig is easiest for a beginner?',
					'A headband wig: slip it on, adjust the band and go. No lace to cut and no glue. A 4x4 closure wig is the next easiest.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Can I wear a wig without visiting a salon?',
					'Yes. Headband and 4x4 closure wigs are designed to be put on at home. Lace front wigs look best when the lace is cut and fitted, which our studio can do for you.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Which wig can I put on in five minutes?',
					'A headband wig. Pull it on, adjust the strap and position the band, and you are ready.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Are headband wigs good for everyday use?',
					'Yes. They are comfortable, quick, need no glue and leave your hairline free, which makes them a favourite for daily wear.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Which wig is best for someone who doesn\'t know how to install wigs?',
					'Start with a headband wig or a 4x4 closure wig. If you want a lace front, book a fitting and our stylist will install it for you.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Can I remove my wig every evening?',
					'Yes, if it is glueless. Headband and closure wigs are made to come off at night, which also lets your scalp rest.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Which wig is comfortable for daily wear?',
					'A lightweight headband or 4x4 closure wig in a medium length is the most comfortable for long days.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'What\'s the difference between a headband wig and a lace-front wig?',
					'A headband wig has no lace: a fabric band covers the hairline, so it is the fastest to wear. A lace-front wig has sheer lace along the front for a natural-looking hairline and flexible parting, but needs the lace cut and fitted.',
					array(
						'construction' => 'headband',
					),
				),
			),
		),
		array(
			'id' => 'installation',
			'title' => 'Wig installation and salon services',
			'kw' => 'install installation installed fix fitting salon studio appointment book booking today revamp style wash prepare lace frontal closure braid cornrow cap',
			'h' => array(
				'construction' => 'lace',
			),
			'guides' => array(
				'wig-installation-nairobi-what-to-expect',
				'glueless-wigs-kenya',
			),
			'q' => array(
				array(
					'Where can I get a wig installed in Nairobi?',
					'At the NURA Beauty studio, {address}. Book a morning appointment online; a deposit secures your slot and is deducted from your bill.',
					array(),
				),
				array(
					'Do you offer wig installation?',
					'Yes. Our stylists fit, install and style wigs at the studio, including lace front and closure units. Book online for a morning slot.',
					array(),
				),
				array(
					'How much does wig installation cost?',
					'Installation prices depend on the unit and the look, and are listed on our installation and booking pages. Wig revamps start from KSh 1,500. Video and WhatsApp consultations are free.',
					array(),
				),
				array(
					'Can you install a wig I bought somewhere else?',
					'Yes. We install, restyle and revamp NURA units and your own units. Bring the wig with you to your appointment.',
					array(),
				),
				array(
					'Do you install lace-front wigs?',
					'Yes. Our stylist fits the wig, cuts the lace and styles the hairline.',
					array(
						'construction' => 'lace',
					),
				),
				array(
					'Can you install a 4x4 closure wig?',
					'Yes. Closure wigs are quick to fit and are usually worn glueless.',
					array(
						'construction' => 'closure',
					),
				),
				array(
					'Do I need to bring my own wig cap?',
					'Tell us when you book and we will confirm what to bring. Come with clean, dry hair, braided flat if possible.',
					array(),
				),
				array(
					'How long does wig installation take?',
					'It depends on the wig and the styling you want. We confirm the time for your service when you book.',
					array(),
				),
				array(
					'Can I book an appointment for today?',
					'Studio appointments run in the mornings. Choose the earliest available time on the booking page, or message us on WhatsApp to check same-day space. A booking is only confirmed once we confirm it.',
					array(),
				),
				array(
					'Do you offer wig revamping?',
					'Yes. We wash, treat and restyle tired wigs at the studio. Revamps start from KSh 1,500.',
					array(),
				),
				array(
					'Can you style my wig after installation?',
					'Yes. Styling is part of our installation and styling service.',
					array(),
				),
				array(
					'Do you wash and prepare wigs before installation?',
					'Yes, washing and preparing a unit can be added to your service. Tell us when you book.',
					array(),
				),
				array(
					'Can you install a wig without glue?',
					'Yes. Many units, especially closure and headband wigs, are fitted glueless with straps, combs or a band.',
					array(
						'construction' => 'closure',
					),
				),
				array(
					'How long does a wig installation last?',
					'A glueless install comes off whenever you like. How long a glued install holds depends on your skin, sweat and care; ask your stylist for the right touch-up schedule.',
					array(),
				),
				array(
					'Can I come with my own natural hair braided?',
					'Yes, that is ideal. Flat braids or cornrows give the smoothest fit.',
					array(),
				),
			),
		),
		array(
			'id' => 'face',
			'title' => 'Which wig suits my face?',
			'kw' => 'face round oval square heart small slimmer younger part middle side bob suit flatter professional',
			'h' => array(),
			'guides' => array(
				'how-to-choose-a-wig-for-your-face-shape',
			),
			'q' => array(
				array(
					'Which wig suits a round face?',
					'Longer lengths and side parts that add height work well, such as long straight or loose wave styles. Very short, rounded bobs can make a round face look fuller.',
					array(
						'texture' => 'straight',
					),
				),
				array(
					'Which wig makes my face look slimmer?',
					'Length past the chin, a side part and volume that falls below the jawline draw the eye down and lengthen the face.',
					array(
						'texture' => 'straight',
					),
				),
				array(
					'Which hairstyle suits an oval face?',
					'Almost anything, from short bobs to long curls. Avoid heavy fringes that hide your features.',
					array(),
				),
				array(
					'Which wig looks good on a small face?',
					'Short to medium lengths and bobs keep a small face in proportion; very long, voluminous styles can overwhelm it.',
					array(
						'style' => 'bob',
					),
				),
				array(
					'Which bob wig would suit me?',
					'Bobs suit heart-shaped and oval faces especially well. A sleek straight bob looks polished; a water-curl bob is softer and more playful.',
					array(
						'style' => 'bob',
					),
				),
				array(
					'Should I choose a middle part or side part?',
					'A middle part suits oval faces and gives a balanced, modern look. A side part adds height and softens round or square faces. Lace front and closure wigs let you choose.',
					array(),
				),
				array(
					'Which wig gives a natural everyday look?',
					'A kinky straight (Yaki) or straight style in natural black, on a closure or lace front, looks the most like natural hair day to day.',
					array(
						'texture' => 'yaki',
					),
				),
				array(
					'Which wig makes me look younger?',
					'Soft movement around the face, such as curls, waves or a chin-length bob, and a warm natural shade tend to look fresh and youthful.',
					array(),
				),
				array(
					'Which wig is good for a professional working woman?',
					'A neat straight or kinky straight style, or a sleek bob, in natural black or dark brown.',
					array(
						'occasion' => 'work',
					),
				),
				array(
					'Which wig would you recommend for my face shape?',
					'Tell us your face shape, or send a selfie on WhatsApp, and we will suggest a few units. Round faces suit length and side parts, oval faces suit almost anything, square faces suit soft waves, and heart-shaped faces suit chin-length bobs. These are suggestions, not rules: wear what makes you feel good.',
					array(),
				),
			),
		),
		array(
			'id' => 'textures',
			'title' => 'Wig lengths, textures and styles',
			'kw' => 'body wave water wave water curl jerry curl kinky straight yaki afro deep curl bubble length inch inches 8 10 12 14 16 18 20 22 short long volume maintain',
			'h' => array(),
			'guides' => array(
				'wig-lengths-and-textures-explained',
				'human-hair-vs-semi-human-vs-fibre-wigs-kenya',
			),
			'q' => array(
				array(
					'What is the difference between body wave and water wave?',
					'Body wave has large, loose S-shaped waves that look soft and glamorous. Water wave (water curl) has smaller, tighter, wet-look curls with more volume.',
					array(
						'texture' => 'water',
					),
				),
				array(
					'What is Jerry curl?',
					'Jerry curl is a defined, springy curl pattern that looks full and bouncy. Ours is available in 100% human hair from 14 to 22 inches.',
					array(
						'texture' => 'jerry',
					),
				),
				array(
					'What is kinky straight or Yaki?',
					'Kinky straight (Yaki) is straight hair with a slightly coarse texture, like relaxed or blow-dried African hair, so it blends naturally.',
					array(
						'texture' => 'yaki',
					),
				),
				array(
					'Which wig is best for a natural African look?',
					'Kinky straight (Yaki), afro kinky curl and coily kinky curl textures look closest to natural African hair.',
					array(
						'texture' => 'kinky',
					),
				),
				array(
					'Is a 12-inch wig too short?',
					'No. 12 inches usually falls around the shoulders, and with curls it sits shorter. It is a practical length for everyday wear.',
					array(
						'length' => '12',
					),
				),
				array(
					'What is the difference between 14, 16, 18 and 22 inches?',
					'Roughly: 14 inches falls around the collarbone, 16 inches just below it, 18 inches to mid-chest and 22 inches towards the bust or below. Curls shrink the visible length.',
					array(),
				),
				array(
					'Which wig has more volume?',
					'Curly textures such as jerry curl, deep curl, water curl and bubble curl give the most volume.',
					array(
						'texture' => 'curl',
					),
				),
				array(
					'Which wig is best for a short hairstyle?',
					'A bob: our human hair bob lace front or our 8 inch water curl bob.',
					array(
						'style' => 'bob',
					),
				),
				array(
					'Which wig is good for a long, glamorous look?',
					'An 18 to 22 inch human hair water curl or jerry curl, or a long straight lace front.',
					array(
						'hair' => 'human',
						'length' => '22',
					),
				),
				array(
					'Which wig is easiest to maintain?',
					'Heat-resistant fibre wigs in straight or kinky straight styles: they hold their style and need little more than gentle detangling.',
					array(
						'hair' => 'fibre',
						'texture' => 'yaki',
					),
				),
			),
		),
		array(
			'id' => 'colours',
			'title' => 'Wig colours and highlights',
			'kw' => 'colour color 1b black natural brown burgundy honey blonde highlight highlighted ombre ginger dye dark skin',
			'h' => array(),
			'guides' => array(
				'wig-colours-guide-kenya',
			),
			'q' => array(
				array(
					'Which wig colour looks natural?',
					'Natural black (often called 1B) and dark brown look the most natural on most Kenyan skin tones.',
					array(
						'colour' => 'natural black',
					),
				),
				array(
					'Do you have colour 1B wigs?',
					'Yes. 1B is natural black, which many of our wigs come in. Pick Natural Black on the product page where it is offered.',
					array(
						'colour' => 'natural black',
					),
				),
				array(
					'Do you have burgundy wigs?',
					'Yes, several units come in burgundy, in human hair and fibre.',
					array(
						'colour' => 'burgundy',
					),
				),
				array(
					'Which is better, black or natural brown?',
					'Black looks classic and natural; brown softens your features and looks warm in sunlight. If unsure, an ombre with a dark root gives you both.',
					array(
						'colour' => 'brown',
					),
				),
				array(
					'Do you have honey-blonde wigs?',
					'Yes, honey blonde and honey highlighted shades are available on selected units.',
					array(
						'colour' => 'honey',
					),
				),
				array(
					'Can I get a highlighted wig?',
					'Yes, selected units come in highlighted shades.',
					array(
						'colour' => 'highlight',
					),
				),
				array(
					'Which wig colour suits dark skin?',
					'Natural black, dark brown, burgundy, copper and honey shades all flatter dark skin. Warm tones add glow.',
					array(
						'colour' => 'burgundy',
					),
				),
				array(
					'Which colour is good for a wedding?',
					'Natural black or dark brown look timeless in photos; a soft brown or honey highlight adds warmth.',
					array(
						'colour' => 'natural black',
					),
				),
				array(
					'Do you have ombre wigs?',
					'Yes, several units come in ombre shades with a dark root.',
					array(
						'colour' => 'ombre',
					),
				),
				array(
					'Can human hair wigs be dyed?',
					'Yes, 100% human hair wigs can be coloured, best done by a professional at a studio. Blend wigs are not recommended for colouring and fibre wigs cannot be dyed.',
					array(
						'hair' => 'human',
					),
				),
			),
		),
		array(
			'id' => 'care',
			'title' => 'Wig maintenance and longevity',
			'kw' => 'wash washing tangle tangling sleep curls store storage shampoo shedding last long revive old revamp care maintain',
			'h' => array(),
			'guides' => array(
				'how-to-care-for-a-human-hair-wig',
				'how-to-care-for-a-fibre-wig',
				'how-long-does-a-wig-last',
			),
			'q' => array(
				array(
					'How do I wash my wig?',
					'Detangle from the ends up, wash gently in cool water with sulphate-free shampoo, condition from the middle to the ends, then air-dry on a stand. Never wring or rub it.',
					array(),
				),
				array(
					'How often should I wash my wig?',
					'For a human hair wig worn regularly, about every 6 to 8 weeks, or sooner if styling products build up. Wash fibre wigs when they look dull or heavy with product.',
					array(),
				),
				array(
					'How do I stop my wig from tangling?',
					'Detangle from the ends up with a wide-tooth comb, avoid sleeping in the wig, and store it on a stand or in a satin bag.',
					array(),
				),
				array(
					'Can I sleep with my wig?',
					'It is better not to. Sleeping in a wig causes friction and tangles. If you must, wrap it in a silk or satin scarf.',
					array(),
				),
				array(
					'How do I maintain curls?',
					'Finger-detangle, use a light water-based leave-in or curl spray, avoid brushing dry curls, and let them air-dry.',
					array(
						'texture' => 'curl',
					),
				),
				array(
					'How do I store my wig?',
					'On a wig stand or in a satin bag, away from heat and dust, so it keeps its shape.',
					array(),
				),
				array(
					'Can I use normal shampoo on my wig?',
					'Use a gentle, sulphate-free shampoo. Harsh shampoos dry out human hair and dull fibre.',
					array(),
				),
				array(
					'How do I prevent shedding?',
					'Detangle gently from the ends, avoid pulling at the lace or roots, limit heat and do not over-wash.',
					array(),
				),
				array(
					'How long does a synthetic wig last?',
					'A heat-resistant fibre wig lasts about 3 to 6 months of regular wear with good care, longer if you rotate it.',
					array(
						'hair' => 'fibre',
					),
				),
				array(
					'How long can human hair wigs last?',
					'About a year or more with regular wear and good care.',
					array(
						'hair' => 'human',
					),
				),
				array(
					'Can I revive an old wig?',
					'Often, yes. A wig revamp at our studio washes, treats and restyles it. Revamps start from KSh 1,500.',
					array(),
				),
				array(
					'Do you offer wig revamping?',
					'Yes, at our studio, {address}. Revamps start from KSh 1,500.',
					array(),
				),
			),
		),
		array(
			'id' => 'occasions',
			'title' => 'Weddings, events and special occasions',
			'kw' => 'wedding bride bridal birthday corporate event graduation evening dress church occasion party office before my event',
			'h' => array(),
			'guides' => array(
				'bridal-wigs-kenya',
				'best-everyday-wigs-for-working-women',
			),
			'q' => array(
				array(
					'Which wig should I wear to a wedding?',
					'For a guest look, a glossy straight lace front or soft curls. For a bride, plan a lace front unit with a trial; our bridal and occasion service plans the unit, trial and install together.',
					array(
						'construction' => 'lace',
						'occasion' => 'wedding',
					),
				),
				array(
					'Which wig is good for a birthday?',
					'Go for something fun: a water curl, bubble curl or a colour like burgundy or honey blonde.',
					array(
						'texture' => 'curl',
					),
				),
				array(
					'Which wig looks elegant for a corporate event?',
					'A sleek straight lace front or a polished bob in natural black or dark brown.',
					array(
						'occasion' => 'work',
					),
				),
				array(
					'What hairstyle should I wear for graduation?',
					'A style that sits well under a cap: a bob, a medium straight style or defined curls.',
					array(
						'style' => 'bob',
					),
				),
				array(
					'Which wig looks good with an evening dress?',
					'Long, glossy straight hair or big soft curls on a lace front.',
					array(
						'construction' => 'lace',
					),
				),
				array(
					'Which wig should I wear for church?',
					'A neat kinky straight (Yaki), straight or bob style, in natural black or brown.',
					array(
						'occasion' => 'church',
					),
				),
				array(
					'Which wig is suitable for everyday office wear?',
					'A straight or kinky straight closure wig, or a sleek bob: neat, easy and low maintenance.',
					array(
						'occasion' => 'work',
					),
				),
				array(
					'Can you install my wig before my event?',
					'Yes. Book a morning studio appointment ahead of your event. For weddings, ask about our bridal and occasion service.',
					array(),
				),
			),
		),
		array(
			'id' => 'shopping',
			'title' => 'Shopping and delivery in Kenya',
			'kw' => 'buy online kenya deliver delivery mombasa kisumu nakuru eldoret same-day whatsapp m-pesa mpesa pay payment visit shop physical see pictures video different exchange return countrywide',
			'h' => array(),
			'guides' => array(
				'how-to-buy-wigs-online-in-kenya-safely',
			),
			'q' => array(
				array(
					'Where can I buy wigs online in Kenya?',
					'On nurabeauty.co.ke, or on WhatsApp. Every product shows its exact hair type, live price and stock, and we deliver countrywide.',
					array(),
				),
				array(
					'Do you deliver to Mombasa?',
					'Yes, by courier in 1 to 3 business days. Countrywide delivery is {delivery_ke}, and the exact fee shows at checkout.',
					array(),
				),
				array(
					'Do you deliver to Kisumu?',
					'Yes, by courier in 1 to 3 business days. Countrywide delivery is {delivery_ke}, and the exact fee shows at checkout.',
					array(),
				),
				array(
					'Can I get same-day delivery in Nairobi?',
					'Yes, for orders placed before {cutoff}. Rider delivery starts from {rider}, and Nairobi delivery is free on orders over {free_over}.',
					array(),
				),
				array(
					'Can I order through WhatsApp?',
					'Yes. Tap Order on WhatsApp on any product, and pay to M-Pesa Buy Goods Till {till} before dispatch.',
					array(),
				),
				array(
					'Do you accept M-Pesa?',
					'Yes: M-Pesa and card at checkout, M-Pesa Buy Goods Till {till} for WhatsApp orders, or bank transfer. We do not offer cash on delivery; if you collect from the studio you can pay in person.',
					array(),
				),
				array(
					'Can I visit your physical shop?',
					'Yes. We are at {address}, open {hours}. Walk-ins are welcome.',
					array(),
				),
				array(
					'Can I see the actual wig before buying?',
					'Yes, visit the studio to see and try units on. If you are outside Nairobi, ask on WhatsApp for extra photos or a video of the unit.',
					array(),
				),
				array(
					'Can you send pictures or videos of the wig?',
					'Yes, ask us on WhatsApp for extra photos or a short video of the unit you like.',
					array(),
				),
				array(
					'What happens if the wig I receive is different from the picture?',
					'Contact us within 48 hours of delivery and we will put it right. {returns}',
					array(),
				),
				array(
					'Can I exchange a wig?',
					'{returns} Returns and exchanges are accepted at the studio or by courier.',
					array(),
				),
				array(
					'Do you offer countrywide delivery?',
					'Yes, to all 47 counties: same day in Nairobi for orders before {cutoff}, and 1 to 3 business days elsewhere by courier.',
					array(),
				),
			),
		),
		array(
			'id' => 'fit',
			'title' => 'Wig cap size and fitting',
			'kw' => 'cap size small head big head measure adjustable strap fit comfortable short natural hair',
			'h' => array(),
			'guides' => array(
				'how-to-measure-your-head-for-a-wig',
			),
			'q' => array(
				array(
					'How do I know my wig cap size?',
					'Measure around your head along the hairline, above the ears and around the nape. Most women measure about 21.5 to 22.5 inches (55 to 57 cm), a standard medium cap.',
					array(),
				),
				array(
					'What if my head is small?',
					'Most of our wigs have adjustable straps, so you can tighten the cap for a secure fit. Visit the studio for a fitting if you are unsure.',
					array(),
				),
				array(
					'Do you have adjustable wigs?',
					'Yes, most of our wigs have adjustable straps, and headband wigs have an adjustable band.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Can I wear a wig if I have short natural hair?',
					'Yes. Short hair lies flat easily under a wig cap, which gives a very natural fit.',
					array(),
				),
				array(
					'Which wig is suitable for a big head?',
					'Measure first. A standard cap with adjustable straps fits most heads; for a larger measurement, ask us on WhatsApp before ordering.',
					array(),
				),
				array(
					'Can I adjust the straps?',
					'Yes. The straps at the back adjust to tighten or loosen the fit.',
					array(),
				),
				array(
					'Will the wig fit comfortably?',
					'With the right cap size and straps adjusted, yes. Braid your hair flat underneath for the most comfortable fit.',
					array(),
				),
				array(
					'How do I measure my head for a wig?',
					'Take three measurements: around your head (hairline, above the ears, round the nape), front hairline to nape, and ear to ear across the top. Measure the way you will wear your hair underneath.',
					array(),
				),
			),
		),
		array(
			'id' => 'lifestyle',
			'title' => 'Wig recommendations for specific lifestyles',
			'kw' => 'busy working woman every day student university travel hot weather least maintenance gym styling time',
			'h' => array(
				'construction' => 'headband',
			),
			'guides' => array(
				'best-everyday-wigs-for-working-women',
				'glueless-wigs-kenya',
			),
			'q' => array(
				array(
					'Which wig is best for a busy working woman?',
					'A glueless 4x4 closure or headband wig in a straight or kinky straight style: neat, quick and low maintenance.',
					array(
						'occasion' => 'work',
					),
				),
				array(
					'Which wig can I wear every day?',
					'A headband or 4x4 closure wig. For daily wear over a year or more, consider 100% human hair.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Which wig is good for a university student?',
					'An affordable fibre headband or closure wig, under KES 3,000, that is quick to put on.',
					array(
						'max' => 3000,
						'sort' => 'cheap',
					),
				),
				array(
					'Which wig is best for someone who travels frequently?',
					'A glueless headband or closure wig that packs easily and needs little styling.',
					array(
						'construction' => 'headband',
					),
				),
				array(
					'Which wig is good for hot weather?',
					'A lighter, shorter or medium-length wig such as a bob, or a breathable headband wig.',
					array(
						'style' => 'bob',
					),
				),
				array(
					'Which wig requires the least maintenance?',
					'A heat-resistant fibre wig in a straight or kinky straight style.',
					array(
						'hair' => 'fibre',
						'texture' => 'yaki',
					),
				),
				array(
					'Can I wear a wig to the gym?',
					'A secure, glueless short style is best, and remove it after if you sweat a lot. Many women prefer a protective style for heavy workouts.',
					array(
						'style' => 'bob',
					),
				),
				array(
					'Which wig is best for someone who doesn\'t like spending time styling hair?',
					'A headband wig or a fibre wig that holds its style out of the box.',
					array(
						'construction' => 'headband',
					),
				),
			),
		),
	),
);
