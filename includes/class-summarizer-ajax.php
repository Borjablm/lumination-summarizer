<?php
/**
 * Lumination Summarizer — AJAX Handlers
 *
 * Handles the summarization AJAX request: extracts content from the
 * input source, calls the appropriate Lumination Summarization API
 * endpoint, and returns the result.
 *
 * @package    LuminationSummarizer
 * @since      1.0.0
 * @license    GPL-3.0-or-later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lumination_Summarizer_Ajax {

	/**
	 * Map output types to API endpoint paths.
	 *
	 * @var array
	 */
	private static $endpoints = array(
		'sections' => '/lumination-ai/api/v1/features/summarization/sections:generate',
		'raw'      => '/lumination-ai/api/v1/features/summarization/raw:generate',
		'mindmap'  => '/lumination-ai/api/v1/features/summarization/mindmap:generate',
	);

	/**
	 * Maximum context length in characters.
	 */
	const MAX_CONTEXT_LENGTH = 50000;

	/**
	 * Register AJAX actions (logged-in and guest).
	 */
	public static function register() {
		add_action( 'wp_ajax_lumination_summarizer_run',        array( __CLASS__, 'handle_run' ) );
		add_action( 'wp_ajax_nopriv_lumination_summarizer_run', array( __CLASS__, 'handle_run' ) );
	}

	/**
	 * Handle the summarization AJAX request.
	 */
	public static function handle_run() {
		check_ajax_referer( 'lumination_summarizer_nonce', 'nonce' );

		if ( ! Lumination_Core_Security::can_submit( 'summarizer' ) ) {
			Lumination_Core_Security::log_event( 'Unauthorized summarizer access attempt' );
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'lumination-summarizer' ) ) );
		}

		$rate_check = Lumination_Core_Security::check_rate_limit( 'summarizer_run', 10, MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
		}

		// ── Read & validate inputs ───────────────────────────────────────────

		$input_mode     = isset( $_POST['input_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['input_mode'] ) ) : '';
		$output_type    = isset( $_POST['output_type'] ) ? sanitize_text_field( wp_unslash( $_POST['output_type'] ) ) : 'sections';
		$summary_length = isset( $_POST['summary_length'] ) ? sanitize_text_field( wp_unslash( $_POST['summary_length'] ) ) : 'medium';
		$page_url       = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		$allowed_modes   = array( 'url', 'text', 'file' );
		$allowed_outputs = array( 'sections', 'raw', 'mindmap' );
		$allowed_lengths = array( 'short', 'medium', 'long' );

		if ( ! in_array( $input_mode, $allowed_modes, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid input mode.', 'lumination-summarizer' ) ) );
		}
		if ( ! in_array( $output_type, $allowed_outputs, true ) ) {
			$output_type = 'sections';
		}
		if ( ! in_array( $summary_length, $allowed_lengths, true ) ) {
			$summary_length = 'medium';
		}

		// ── Extract context based on input mode ──────────────────────────────

		$context    = '';
		$input_type = 'text'; // Analytics input_type.

		switch ( $input_mode ) {
			case 'url':
				$url = isset( $_POST['input_value'] ) ? esc_url_raw( wp_unslash( $_POST['input_value'] ) ) : '';
				if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
					wp_send_json_error( array( 'message' => __( 'Please enter a valid URL.', 'lumination-summarizer' ) ) );
				}
				$context    = self::extract_from_url( $url );
				$input_type = 'text';
				break;

			case 'text':
				$raw_text = isset( $_POST['input_value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['input_value'] ) ) : '';
				if ( empty( $raw_text ) ) {
					wp_send_json_error( array( 'message' => __( 'Please enter some text to summarise.', 'lumination-summarizer' ) ) );
				}
				$context    = mb_substr( $raw_text, 0, self::MAX_CONTEXT_LENGTH );
				$input_type = 'text';
				break;

			case 'file':
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- base64 data cannot be unslashed/sanitized with sanitize_text_field; sanitize_base64() is applied below.
				$file_data = isset( $_POST['input_value'] ) ? $_POST['input_value'] : '';
				if ( empty( $file_data ) ) {
					wp_send_json_error( array( 'message' => __( 'No file data provided.', 'lumination-summarizer' ) ) );
				}
				$file_data = Lumination_Core_Security::sanitize_base64( $file_data );
				if ( empty( $file_data ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid file data.', 'lumination-summarizer' ) ) );
				}
				$context    = self::extract_from_pdf( $file_data );
				$input_type = 'pdf';
				break;
		}

		if ( is_wp_error( $context ) ) {
			wp_send_json_error( array( 'message' => $context->get_error_message() ) );
		}

		if ( empty( $context ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not extract any text from the provided input.', 'lumination-summarizer' ) ) );
		}

		// ── Call the summarisation API ────────────────────────────────────────

		$endpoint = self::$endpoints[ $output_type ];

		$api_body = array(
			'context'          => $context,
			'language_code'    => 'default',
			'summary_settings' => $summary_length,
		);

		$result = Lumination_Core_API::request( $endpoint, $api_body, 'lumination-summarizer' );

		if ( is_wp_error( $result ) ) {
			Lumination_Core_Security::log_event( 'Summarizer API error', array( 'error' => $result->get_error_message() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to generate summary. Please try again.', 'lumination-summarizer' ) ) );
		}

		// ── Extract response fields ──────────────────────────────────────────

		$title   = '';
		$summary = '';

		if ( 'mindmap' === $output_type ) {
			$summary = isset( $result['mindmap'] ) ? $result['mindmap'] : '';
			$title   = isset( $result['title'] ) ? $result['title'] : __( 'Mindmap', 'lumination-summarizer' );
		} else {
			$summary = isset( $result['summary'] ) ? $result['summary'] : '';
			$title   = isset( $result['title'] ) ? $result['title'] : '';
		}

		if ( empty( $summary ) ) {
			wp_send_json_error( array( 'message' => __( 'The API returned an empty result.', 'lumination-summarizer' ) ) );
		}

		// ── Log analytics ────────────────────────────────────────────────────

		Lumination_Core_Analytics::log_usage(
			'summarizer',
			$page_url,
			isset( $result['token_count_input'] ) ? (int) $result['token_count_input'] : 0,
			isset( $result['token_count_output'] ) ? (int) $result['token_count_output'] : 0,
			isset( $result['credits_charged'] ) ? (float) $result['credits_charged'] : 0,
			$input_type
		);

		wp_send_json_success( array(
			'title'       => $title,
			'summary'     => $summary,
			'output_type' => $output_type,
		) );
	}

	/**
	 * Extract readable text from a URL.
	 *
	 * @param  string $url The URL to fetch.
	 * @return string|WP_Error Extracted text or error.
	 */
	private static function extract_from_url( $url ) {
		$response = wp_remote_get( $url, array(
			'timeout'    => 15,
			'user-agent' => 'LuminationSummarizer/1.0 (WordPress)',
		) );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'fetch_failed', __( 'Could not fetch the URL. Please check it and try again.', 'lumination-summarizer' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return new \WP_Error( 'fetch_failed', sprintf(
				/* translators: %d: HTTP status code */
				__( 'The URL returned an error (HTTP %d).', 'lumination-summarizer' ),
				$code
			) );
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return new \WP_Error( 'empty_body', __( 'The URL returned no content.', 'lumination-summarizer' ) );
		}

		// Strip tags that don't contribute to main content.
		$html = preg_replace( '#<script[^>]*>.*?</script>#is', '', $html );
		$html = preg_replace( '#<style[^>]*>.*?</style>#is', '', $html );
		$html = preg_replace( '#<nav[^>]*>.*?</nav>#is', '', $html );
		$html = preg_replace( '#<header[^>]*>.*?</header>#is', '', $html );
		$html = preg_replace( '#<footer[^>]*>.*?</footer>#is', '', $html );

		$text = wp_strip_all_tags( $html );

		// Normalise whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return mb_substr( $text, 0, self::MAX_CONTEXT_LENGTH );
	}

	/**
	 * Extract text from a base64-encoded PDF via the material-to-text endpoint.
	 *
	 * @param  string $base64_data Sanitised base64 string.
	 * @return string|WP_Error Extracted text or error.
	 */
	private static function extract_from_pdf( $base64_data ) {
		$result = Lumination_Core_API::request(
			'/api/material-to-text',
			array(
				'content'      => $base64_data,
				'content_type' => 'application/pdf',
			),
			'lumination-summarizer-extract'
		);

		if ( is_wp_error( $result ) ) {
			return new \WP_Error( 'pdf_extract_failed', __( 'Could not extract text from the PDF.', 'lumination-summarizer' ) );
		}

		if ( isset( $result['success'] ) && false === $result['success'] ) {
			$err_msg = isset( $result['error'] ) ? $result['error'] : __( 'PDF processing failed.', 'lumination-summarizer' );
			return new \WP_Error( 'pdf_extract_failed', $err_msg );
		}

		$text = isset( $result['text'] ) ? $result['text'] : '';

		return mb_substr( trim( $text ), 0, self::MAX_CONTEXT_LENGTH );
	}
}
