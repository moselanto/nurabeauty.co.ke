<?php
/**
 * R14 (theme 1.159.0)
 * 1. Shop (desktop) layout shift: the "Ask NURA Stylist" chip styles were printed in the footer, so the
 *    chips grew from 21 to 29 px after first paint and pushed the 12-wig grid down. They now ship in <head>.
 * 2. Product pages (owner-approved): only the first 6 colour swatches and the first 5 specification rows
 *    show at first; "+N more colours" / "Show all N specifications" reveal the rest. A chosen colour
 *    beyond the first six always stays visible.
 * Off: add_filter( 'nura_r14_on', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r14_on() {
	return (bool) apply_filters( 'nura_r14_on', true ) && ! is_admin();
}

/* 1. Chip styles in <head>. */
add_action( 'wp_head', function () {
	if ( ! nura_r14_on() ) {
		return;
	}
	echo '<style id="nura-r14-chips">.nura-ask-chip{border:1px solid currentColor;background:transparent;border-radius:999px;padding:6px 12px;font-size:13px;line-height:1.25;cursor:pointer;color:inherit}.nura-ask-row__t{font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.7;margin-right:4px}</style>' . "\n";
}, 4 );

/* 2. Product page: compact swatches and specs. */
function nura_r14_swatch_limit() {
	return (int) apply_filters( 'nura_r14_swatch_limit', 6 );
}
function nura_r14_spec_limit() {
	return (int) apply_filters( 'nura_r14_spec_limit', 5 );
}

add_action( 'template_redirect', function () {
	if ( ! nura_r14_on() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	ob_start( function ( $html ) {
		if ( ! is_string( $html ) ) {
			return $html;
		}
		$sl = nura_r14_swatch_limit();
		// Colour swatches: add a "+N more" button at the end of the first colour group with more than $sl swatches.
		$html = preg_replace_callback( '#(<div class="nura-swatches nura-swatches--colour"[^>]*>)(.*?)(</div>)(?=\s*(?:<|$))#s', function ( $m ) use ( $sl ) {
			$n = preg_match_all( '#<button type="button" class="nura-swatch[ "]#', $m[2] );
			if ( $n <= $sl ) {
				return $m[0];
			}
			$more = $n - $sl;
			/* translators: %d: number of hidden colours. */
			$label = sprintf( _n( '+%d more colour', '+%d more colours', $more, 'nura-beauty' ), $more );
			return str_replace( 'class="nura-swatches nura-swatches--colour"', 'class="nura-swatches nura-swatches--colour nura-swatches--compact"', $m[1] )
				. $m[2] . '<button type="button" class="nura-swatch-more" data-nura-reveal="swatches" aria-expanded="false">' . esc_html( $label ) . '</button>' . $m[3];
		}, $html, 1 );
		// Specifications: button after the list.
		$sp = nura_r14_spec_limit();
		$html = preg_replace_callback( '#<section class="nura-specsec"([^>]*)>(.*?)</dl>#s', function ( $m ) use ( $sp ) {
			$n = substr_count( $m[2], 'class="nura-specsec__row"' );
			if ( $n <= $sp ) {
				return $m[0];
			}
			/* translators: %d: number of specifications. */
			$label = sprintf( __( 'Show all %d specifications', 'nura-beauty' ), $n );
			return '<section class="nura-specsec nura-specsec--compact"' . $m[1] . '>' . $m[2] . '</dl><button type="button" class="nura-spec-more" data-nura-reveal="specs" aria-expanded="false">' . esc_html( $label ) . '</button>';
		}, $html, 1 );
		return $html;
	} );
}, 96 );

add_action( 'wp_head', function () {
	if ( ! nura_r14_on() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$sl = nura_r14_swatch_limit() + 1;
	$sp = nura_r14_spec_limit() + 1;
	echo '<style id="nura-r14-pdp">'
		. '.nura-swatches--compact .nura-swatch:nth-of-type(n+' . $sl . '):not(.is-active):not([aria-pressed="true"]){display:none}'
		. '.nura-swatch-more,.nura-spec-more{background:none;border:0;padding:.35rem .2rem;font:inherit;font-size:.85rem;font-weight:600;color:#7a5b2e;text-decoration:underline;text-underline-offset:2px;cursor:pointer}'
		. '.nura-swatches--compact .nura-swatch-more{align-self:center}'
		. '.nura-specsec--compact .nura-specsec__row:nth-child(n+' . $sp . '){display:none}'
		. '.nura-spec-more{margin:.4rem 0 0}</style>' . "\n";
	echo "<script id=\"nura-r14-pdp-js\">document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-nura-reveal]');if(!b){return;}e.preventDefault();var w=b.getAttribute('data-nura-reveal')==='specs'?b.closest('.nura-specsec'):b.closest('.nura-swatches');if(w){w.classList.remove('nura-swatches--compact','nura-specsec--compact');}b.setAttribute('aria-expanded','true');b.parentNode.removeChild(b);});</script>\n";
}, 5 );
