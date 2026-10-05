=== NURA Experience ===
Contributors: NURA
Requires at least: 6.2
Requires PHP: 7.4
WC requires at least: 8.0
License: GPLv2 or later

NURA's three exclusive features for a luxury wig store.

== What it adds ==
1. AI Wig Finder  -> shortcode [nura_ai_wig_finder]
   A quiz (face shape, skin tone, lifestyle, budget) + optional selfie upload that
   recommends WooCommerce products. Works out of the box with a transparent
   rule-based recommender. To upgrade to REAL face-shape vision AI, set an API
   endpoint + key under Settings > NURA Experience; the value is passed through the
   'nurax_face_analysis' PHP filter so you can call your provider and return a
   detected face shape before matching.

2. Virtual Try-On -> shortcode [nura_virtual_tryon]  (also adds a "Try it on" button on products)
   Upload a photo and preview a wig image over it (drag to position, size + blend
   sliders). This is a client-side MVP. For auto-aligned, face-tracked try-on,
   attach a provider via a global JS function `window.nuraxTryonProvider` (e.g.
   MediaPipe FaceMesh, Banuba, or a custom model). The UI stays identical.

3. The NURA Circle -> shortcode [nura_circle_portal] + WooCommerce My Account tabs
   Adds "The NURA Circle", "Care Schedule" and "Warranty Certificates" tabs to the
   customer account. Awards Radiance Points on completed orders (1 pt / KES 100),
   auto-computes wash & revamp reminders, and issues a warranty/provenance
   certificate number per purchased item. VIP membership flag + price is configurable.

== Honest scope note ==
The recommender, try-on overlay and portal are fully functional MVPs with clean
extension points. True computer-vision face analysis and auto-aligned AR try-on
require a third-party vision service or model and an API key/subscription — this
plugin is built to plug those in, not to ship a trained model.
