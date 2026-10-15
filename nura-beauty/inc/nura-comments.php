<?php
/**
 * Branded comment form for Journal posts + readable article link hovers.
 *
 * - NURA copy, placeholders, two-column Name/Email, no Website field (spam magnet).
 * - WhatsApp shortcut for shoppers who want a fast answer.
 * - Honeypot + time-trap spam filter on comment submissions.
 * - Article links keep strong contrast on hover (deep gold + underline).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Fields: drop Website, add placeholders, autocomplete, two-column wrap. */
add_filter( 'comment_form_default_fields', function ( $fields ) {
	$commenter = wp_get_current_commenter();
	unset( $fields['url'] );
	$fields['author'] = '<p class="comment-form-author nura-cf__half"><label for="author">' . esc_html__( 'Your name', 'nura-beauty' ) . ' <span class="required">*</span></label>'
		. '<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" maxlength="245" autocomplete="name" placeholder="' . esc_attr__( 'e.g. Wanjiku', 'nura-beauty' ) . '" required></p>';
	$fields['email'] = '<p class="comment-form-email nura-cf__half"><label for="email">' . esc_html__( 'Email', 'nura-beauty' ) . ' <span class="required">*</span></label>'
		. '<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" maxlength="100" autocomplete="email" placeholder="' . esc_attr__( 'you@example.com', 'nura-beauty' ) . '" required></p>';
	if ( isset( $fields['cookies'] ) ) {
		$checked = empty( $commenter['comment_author_email'] ) ? '' : ' checked';
		$fields['cookies'] = '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $checked . '>'
			. '<label for="wp-comment-cookies-consent">' . esc_html__( 'Remember my name and email on this device', 'nura-beauty' ) . '</label></p>';
	}
	return $fields;
} );

/** Title, notes, textarea and button. */
add_filter( 'comment_form_defaults', function ( $d ) {
	$wa = function_exists( 'get_theme_mod' ) ? get_theme_mod( 'nura_whatsapp', 'https://wa.me/254714994898' ) : 'https://wa.me/254714994898';
	if ( ! $wa || 'https://wa.me/254700000000' === $wa ) {
		$wa = 'https://wa.me/254714994898';
	}
	$d['title_reply']          = esc_html__( 'Ask a question or share your tips', 'nura-beauty' );
	$d['title_reply_to']       = esc_html__( 'Reply to %s', 'nura-beauty' );
	$d['title_reply_before']   = '<h3 id="reply-title" class="comment-reply-title">';
	$d['title_reply_after']    = '</h3>';
	$d['comment_notes_before'] = '<p class="comment-notes nura-cf__note">' . esc_html__( 'Your email stays private and is never shown. Comments appear once approved.', 'nura-beauty' )
		. ' <a class="nura-cf__wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Need a quick answer? Chat on WhatsApp', 'nura-beauty' ) . '</a></p>';
	$d['comment_field']        = '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Your comment', 'nura-beauty' ) . ' <span class="required">*</span></label>'
		. '<textarea id="comment" name="comment" rows="5" maxlength="65525" required placeholder="' . esc_attr__( 'Which texture are you considering? Any tips from wearing a headband wig?', 'nura-beauty' ) . '"></textarea></p>';
	$d['label_submit']         = esc_html__( 'Post comment', 'nura-beauty' );
	$d['class_submit']         = 'submit nura-cf__submit';
	$d['class_form']           = 'comment-form nura-cf';
	$d['submit_field']         = '<p class="form-submit">%1$s %2$s</p>';
	return $d;
} );

/** Spam traps rendered inside the form. */
add_action( 'comment_form', function () {
	echo '<p class="nura-cf__hp" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'nura-beauty' ) . '<input type="text" name="nura_cf_hp" value="" tabindex="-1" autocomplete="off"></label></p>';
	echo '<input type="hidden" name="nura_cf_ts" value="' . esc_attr( (string) time() ) . '">';
} );

/** Reject obvious bots (only when our fields are present, so apps/API are unaffected). */
add_filter( 'preprocess_comment', function ( $data ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- anti-spam fields only.
	if ( is_user_logged_in() || ! isset( $_POST['nura_cf_ts'] ) ) {
		return $data;
	}
	$hp = isset( $_POST['nura_cf_hp'] ) ? trim( (string) wp_unslash( $_POST['nura_cf_hp'] ) ) : '';
	$ts = absint( $_POST['nura_cf_ts'] );
	// phpcs:enable
	if ( '' !== $hp || ( $ts && ( time() - $ts ) < 3 ) ) {
		wp_die( esc_html__( 'Sorry, your comment could not be posted. Please go back and try again.', 'nura-beauty' ), esc_html__( 'Comment not posted', 'nura-beauty' ), array( 'response' => 403, 'back_link' => true ) );
	}
	return $data;
} );

/** Styles: single posts only. */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	?>
	<style id="nura-comments-css">
	/* Article links: readable on hover (was light gold on white). */
	.single-post .entry-content a:not(.nura-btn):not(.nura-jcard__media){color:var(--nura-ink,#0e0e0e);text-decoration:underline;text-decoration-color:rgba(201,162,75,.6);text-underline-offset:3px;text-decoration-thickness:1px;transition:color .2s,text-decoration-color .2s}
	.single-post .entry-content a:not(.nura-btn):not(.nura-jcard__media):hover,.single-post .entry-content a:not(.nura-btn):not(.nura-jcard__media):focus-visible{color:#7a5a12;text-decoration-color:#7a5a12;text-decoration-thickness:2px}
	.single-post .entry-content .nura-jcard__title a{text-decoration:none}
	.single-post .entry-content .nura-jcard__title a:hover{color:#7a5a12}
	.single-post .entry-content .nura-jcard__more,.single-post .entry-content .nura-related__pillar{color:#7a5a12;text-decoration:none}
	.single-post .entry-content .nura-jcard__more:hover,.single-post .entry-content .nura-related__pillar:hover{color:var(--nura-ink,#0e0e0e);text-decoration:underline}
	/* Comment form card. */
	.nura-comments{max-width:760px;margin:3rem auto 0}
	.nura-comments .comment-respond{background:#0e0e0e;color:#f3e9dc;border:1px solid rgba(201,162,75,.35);border-radius:16px;padding:2rem 2rem 1.6rem}
	.nura-comments .comment-reply-title{font-family:var(--font-display,'Playfair Display',serif);color:#f3e9dc;font-size:1.6rem;margin:0 0 .4rem}
	.nura-comments .comment-reply-title small a{color:#c9a24b;font-size:.8rem;margin-left:.6rem}
	.nura-cf__note{font-size:.9rem;color:rgba(243,233,220,.75);margin:0 0 1.4rem}
	.nura-cf__wa{display:inline-block;margin-top:.35rem;color:#c9a24b!important;font-weight:600;text-decoration:none!important}
	.nura-cf__wa:hover{color:#f3e9dc!important;text-decoration:underline!important}
	.nura-cf{display:flex;flex-wrap:wrap;gap:0 1rem}
	.nura-cf>p{flex:1 1 100%;margin:0 0 1rem}
	.nura-cf>p.nura-cf__half{flex:1 1 calc(50% - .5rem);min-width:220px}
	.nura-cf label{display:block;font-family:var(--font-ui,'Jost',sans-serif);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#c9a24b;margin-bottom:.4rem}
	.nura-cf input[type=text],.nura-cf input[type=email],.nura-cf textarea{width:100%;box-sizing:border-box;background:#1a1a1a;border:1px solid rgba(243,233,220,.18);border-radius:10px;color:#f3e9dc;font-family:var(--font-body,inherit);font-size:1rem;padding:.8rem .95rem;transition:border-color .2s,box-shadow .2s}
	.nura-cf textarea{min-height:130px;resize:vertical}
	.nura-cf input::placeholder,.nura-cf textarea::placeholder{color:rgba(243,233,220,.4)}
	.nura-cf input:focus,.nura-cf textarea:focus{outline:0;border-color:#c9a24b;box-shadow:0 0 0 3px rgba(201,162,75,.2)}
	.nura-cf .comment-form-cookies-consent{display:flex;align-items:center;gap:.6rem}
	.nura-cf .comment-form-cookies-consent label{margin:0;text-transform:none;letter-spacing:0;font-size:.88rem;color:rgba(243,233,220,.8)}
	.nura-cf .comment-form-cookies-consent input{accent-color:#c9a24b;width:18px;height:18px}
	.nura-cf .required{color:#c9a24b}
	.nura-cf .form-submit{margin:.4rem 0 0}
	.nura-cf__submit{background:#c9a24b!important;color:#0e0e0e!important;border:0!important;border-radius:999px!important;padding:.85rem 2rem!important;font-family:var(--font-ui,'Jost',sans-serif);font-weight:600;letter-spacing:.1em;text-transform:uppercase;font-size:.8rem;cursor:pointer;transition:transform .2s,box-shadow .2s,background .2s}
	.nura-cf__submit:hover,.nura-cf__submit:focus-visible{background:#e0bb63!important;transform:translateY(-1px);box-shadow:0 10px 24px rgba(201,162,75,.3)}
	.nura-cf .logged-in-as{font-size:.88rem;color:rgba(243,233,220,.75)}
	.nura-cf .logged-in-as a{color:#c9a24b}
	.nura-cf__hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}
	@media(max-width:600px){.nura-comments .comment-respond{padding:1.4rem 1.1rem;border-radius:12px}.nura-cf>p.nura-cf__half{flex-basis:100%}}
	</style>
	<?php
}, 30 );
