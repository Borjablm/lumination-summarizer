<?php
/**
 * Lumination Summarizer — AJAX Handlers
 *
 * The AI Tutor /summarize endpoint is asynchronous and works on an uploaded
 * document (PDF/DOCX/TXT). This handler therefore:
 *   1. handle_run    — packages the input as a file and submits the job.
 *   2. handle_status — polled from the browser until the job completes.
 *
 * Text and URL inputs are wrapped as a .txt file so the same document-based
 * endpoint can serve every input mode.
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
	 * Maximum context length in characters.
	 */
	const MAX_CONTEXT_LENGTH = 50000;

	/**
	 * Register AJAX actions (logged-in and guest).
	 */
	public static function register() {
		add_action( 'wp_ajax_lumination_summarizer_run',        array( __CLASS__, 'handle_run' ) );
		add_action( 'wp_ajax_nopriv_lumination_summarizer_run', array( __CLASS__, 'handle_run' ) );

		add_action( 'wp_ajax_lumination_summarizer_status',        array( __CLASS__, 'handle_status' ) );
		add_action( 'wp_ajax_nopriv_lumination_summarizer_status', array( __CLASS__, 'handle_status' ) );
	}

	/**
	 * Submit a summarization job. Returns a request_id for the browser to poll.
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

		// ── Build the document payload based on input mode ───────────────────

		$file_b64   = '';
		$file_name  = 'content.txt';
		$input_type = 'text'; // Analytics input_type.

		switch ( $input_mode ) {
			case 'url':
				$url = isset( $_POST['input_value'] ) ? esc_url_raw( wp_unslash( $_POST['input_value'] ) ) : '';
				if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
					wp_send_json_error( array( 'message' => __( 'Please enter a valid URL.', 'lumination-summarizer' ) ) );
				}
				$context = self::extract_from_url( $url );
				if ( is_wp_error( $context ) ) {
					wp_send_json_error( array( 'message' => $context->get_error_message() ) );
				}
				$file_b64 = base64_encode( $context ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- packaging plain text as a .txt upload, not obfuscation.
				break;

			case 'text':
				$raw_text = isset( $_POST['input_value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['input_value'] ) ) : '';
				if ( empty( $raw_text ) ) {
					wp_send_json_error( array( 'message' => __( 'Please enter some text to summarise.', 'lumination-summarizer' ) ) );
				}
				$context  = mb_substr( $raw_text, 0, self::MAX_CONTEXT_LENGTH );
				$file_b64 = base64_encode( $context ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- packaging plain text as a .txt upload, not obfuscation.
				break;

			case 'file':
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- base64 data; sanitize_base64() applied below.
				$raw_file = isset( $_POST['input_value'] ) ? $_POST['input_value'] : '';
				$file_b64 = Lumination_Core_Security::sanitize_base64( $raw_file );
				if ( empty( $file_b64 ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid file data.', 'lumination-summarizer' ) ) );
				}
				$file_name  = 'document.pdf';
				$input_type = 'pdf';
				break;
		}

		// ── Submit the job ───────────────────────────────────────────────────

		$submit = Lumination_Core_API::submit(
			'/summarize',
			array(
				'file_b64'       => $file_b64,
				'file_name'      => $file_name,
				'summary_length' => $summary_length,
			),
			'lumination-summarizer'
		);

		if ( is_wp_error( $submit ) ) {
			Lumination_Core_Security::log_event( 'Summarizer submit error', array( 'error' => $submit->get_error_message() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to start the summary. Please try again.', 'lumination-summarizer' ) ) );
		}

		if ( empty( $submit['request_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The API did not accept the request. Please try again.', 'lumination-summarizer' ) ) );
		}

		wp_send_json_success( array(
			'request_id'  => $submit['request_id'],
			'output_type' => $output_type,
			'input_type'  => $input_type,
		) );
	}

	/**
	 * Poll a submitted summarization job.
	 */
	public static function handle_status() {
		check_ajax_referer( 'lumination_summarizer_nonce', 'nonce' );

		$request_id  = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$output_type = isset( $_POST['output_type'] ) ? sanitize_text_field( wp_unslash( $_POST['output_type'] ) ) : 'sections';
		$input_type  = isset( $_POST['input_type'] ) ? sanitize_text_field( wp_unslash( $_POST['input_type'] ) ) : 'text';
		$page_url    = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		if ( empty( $request_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing request reference.', 'lumination-summarizer' ) ) );
		}

		$job = Lumination_Core_API::poll( $request_id );
		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to check the summary status.', 'lumination-summarizer' ) ) );
		}

		$status = isset( $job['status'] ) ? $job['status'] : 'processing';

		if ( 'failed' === $status ) {
			$msg = ( ! empty( $job['error'] ) && is_string( $job['error'] ) ) ? $job['error'] : __( 'The summary could not be generated.', 'lumination-summarizer' );
			wp_send_json_error( array( 'message' => $msg ) );
		}

		if ( 'completed' !== $status ) {
			wp_send_json_success( array( 'status' => 'processing' ) );
		}

		// Completed — extract the summary.
		$summary = isset( $job['result']['summary'] ) ? $job['result']['summary'] : '';
		$title   = isset( $job['result']['title'] ) ? $job['result']['title'] : '';

		if ( empty( $summary ) ) {
			wp_send_json_error( array( 'message' => __( 'The API returned an empty result.', 'lumination-summarizer' ) ) );
		}

		Lumination_Core_Analytics::log_usage(
			'summarizer',
			$page_url,
			isset( $job['input_tokens'] ) ? (int) $job['input_tokens'] : 0,
			isset( $job['output_tokens'] ) ? (int) $job['output_tokens'] : 0,
			isset( $job['credits_charged'] ) ? (float) $job['credits_charged'] : 0,
			$input_type
		);

		wp_send_json_success( array(
			'status'      => 'completed',
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

		if ( empty( $text ) ) {
			return new \WP_Error( 'empty_body', __( 'Could not extract any readable text from the URL.', 'lumination-summarizer' ) );
		}

		return mb_substr( $text, 0, self::MAX_CONTEXT_LENGTH );
	}
}
