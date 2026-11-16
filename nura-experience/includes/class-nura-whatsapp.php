<?php
/**
 * Meta WhatsApp Cloud API sender for NURA.
 *
 * Sends plain-text messages when configured and records the last API error for
 * the settings screen. All methods no-op gracefully when not configured, so
 * callers never need to guard.
 *
 * Secrets (server-side only - env var or wp-config.php constant, never the DB):
 *   WHATSAPP_TOKEN            (or NURAX_WA_TOKEN)  - Cloud API access token
 *   WHATSAPP_PHONE_NUMBER_ID  (or NURAX_WA_PHONE_ID) - optional; may also be set
 *                              as a non-secret value on the WhatsApp settings page
 *
 * The phone number ID is not secret, so it can live either in a constant or in
 * the nurax_wa_bot option. 1.114.2: when wp-config/env give nothing, the token and
 * phone number ID may be saved on NURA Desk > Order alerts (option nurax_wa_fallback,
 * not autoloaded, never printed back).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NURAX_WhatsApp {

	const ERR_OPTION = 'nurax_wa_last_error';

	/** Resolve a secret from env first, then a wp-config constant. Never the DB. */
	private static function secret( string $name ): string {
		$env = getenv( $name );
		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return trim( $env );
		}
		if ( defined( $name ) ) {
			$val = constant( $name );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				return trim( $val );
			}
		}
		return '';
	}

	/** Cloud API access token (secret; constant/env only). */
	public static function token(): string {
		$t = self::secret( 'WHATSAPP_TOKEN' );
		if ( '' === $t ) {
			$t = self::secret( 'NURAX_WA_TOKEN' );
		}
		if ( '' === $t ) {
			$t = self::fallback( 'token' ); // 1.114.2: last resort, saved on NURA Desk > Order alerts.
		}
		return $t;
	}

	/** Business phone number ID: constant/env first, then the settings option. */
	public static function phone_id(): string {
		$c = self::secret( 'WHATSAPP_PHONE_NUMBER_ID' );
		if ( '' === $c ) {
			$c = self::secret( 'NURAX_WA_PHONE_ID' );
		}
		if ( '' !== $c ) {
			return $c;
		}
		$o = get_option( 'nurax_wa_bot', array() );
		if ( is_array( $o ) && ! empty( $o['phone_id'] ) ) {
			return (string) $o['phone_id'];
		}
		return self::fallback( 'phone_id' ); // 1.114.2: saved on NURA Desk > Order alerts.
	}

	/** 1.114.2: settings fallback (option nurax_wa_fallback) used only when wp-config/env give nothing. */
	public static function fallback( string $key ): string {
		$f = get_option( 'nurax_wa_fallback', array() );
		return ( is_array( $f ) && isset( $f[ $key ] ) ) ? trim( (string) $f[ $key ] ) : '';
	}

	/** Where a value comes from: 'wp-config', 'settings' or '' (missing). Never the value. */
	public static function source( string $what ): string {
		if ( 'token' === $what ) {
			if ( '' !== self::secret( 'WHATSAPP_TOKEN' ) || '' !== self::secret( 'NURAX_WA_TOKEN' ) ) {
				return 'wp-config';
			}
			return '' !== self::fallback( 'token' ) ? 'settings' : '';
		}
		if ( '' !== self::secret( 'WHATSAPP_PHONE_NUMBER_ID' ) || '' !== self::secret( 'NURAX_WA_PHONE_ID' ) ) {
			return 'wp-config';
		}
		$o = get_option( 'nurax_wa_bot', array() );
		if ( is_array( $o ) && ! empty( $o['phone_id'] ) ) {
			return 'WhatsApp bot settings';
		}
		return '' !== self::fallback( 'phone_id' ) ? 'settings' : '';
	}

	public static function api_version(): string {
		$o = get_option( 'nurax_wa_bot', array() );
		$v = ( is_array( $o ) && ! empty( $o['api_version'] ) ) ? (string) $o['api_version'] : 'v21.0';
		return $v;
	}

	public static function is_configured(): bool {
		return '' !== self::token() && '' !== self::phone_id();
	}

	public static function last_error(): string {
		return (string) get_option( self::ERR_OPTION, '' );
	}

	/**
	 * Send a plain-text WhatsApp message to a single recipient (in-window /
	 * session messages only). For business-initiated sends OUTSIDE the 24-hour
	 * customer-service window, use send_template() instead.
	 */
	public static function send_text( string $to, string $message ): bool {
		$to = (string) preg_replace( '/[^0-9]/', '', $to );
		if ( '' === $to || '' === trim( $message ) ) {
			return false;
		}
		if ( ! self::is_configured() ) {
			return false;
		}
		$payload = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'text',
			'text'              => array(
				'preview_url' => false,
				'body'        => $message,
			),
		);
		return self::request( $payload );
	}

	/**
	 * Send an approved WhatsApp message template. Unlike send_text(), a template
	 * CAN be delivered business-initiated - outside the 24-hour customer-service
	 * window - which is what proactive reminders (abandoned cart, follow-ups)
	 * need. The template name, language and variable count must already be
	 * approved in Meta; $body_params fills the body placeholders {{1}}, {{2}}...
	 * in order.
	 *
	 * @param string            $to          Recipient phone (digits, incl. country code).
	 * @param string            $template    Approved template name.
	 * @param string            $lang        Template language code, e.g. "en_US".
	 * @param array<int,string> $body_params Ordered body variable values.
	 */
	public static function send_template( string $to, string $template, string $lang = 'en_US', array $body_params = array() ): bool {
		$to       = (string) preg_replace( '/[^0-9]/', '', $to );
		$template = trim( $template );
		if ( '' === $to || '' === $template ) {
			return false;
		}
		if ( ! self::is_configured() ) {
			return false;
		}
		$params = array();
		foreach ( $body_params as $p ) {
			$params[] = array(
				'type' => 'text',
				'text' => (string) $p,
			);
		}
		$template_payload = array(
			'name'     => $template,
			'language' => array( 'code' => '' !== trim( $lang ) ? trim( $lang ) : 'en_US' ),
		);
		if ( ! empty( $params ) ) {
			$template_payload['components'] = array(
				array(
					'type'       => 'body',
					'parameters' => $params,
				),
			);
		}
		$payload = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'template',
			'template'          => $template_payload,
		);
		return self::request( $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private static function request( array $payload ): bool {
		$version = self::api_version();
		$phone   = self::phone_id();
		$token   = self::token();
		$url     = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $phone ) . '/messages';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			update_option( self::ERR_OPTION, $response->get_error_message() );
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			update_option( self::ERR_OPTION, '' );
			return true;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		update_option( self::ERR_OPTION, 'HTTP ' . $code . ': ' . wp_strip_all_tags( $body ) );
		return false;
	}
}
