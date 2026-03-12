<?php
/**
 * Lumination Summarizer — Shortcode & Asset Enqueuing
 *
 * Registers the [lumination_summarizer] shortcode with preset support
 * and enqueues front-end assets only when the shortcode is present.
 *
 * @package    LuminationSummarizer
 * @since      1.0.0
 * @license    GPL-3.0-or-later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lumination_Summarizer {

	/**
	 * Built-in presets for common use cases.
	 *
	 * @var array
	 */
	private static $presets = array(
		'youtube' => array(
			'mode'        => 'url',
			'output'      => 'sections',
			'title'       => 'YouTube Summariser',
			'description' => 'Paste a YouTube video URL to get a summary',
			'placeholder' => 'https://youtube.com/watch?v=...',
			'button_text' => 'Summarise',
		),
		'book'    => array(
			'mode'        => 'text',
			'output'      => 'sections',
			'title'       => 'Book Summary',
			'description' => 'Paste the text you want summarised',
			'placeholder' => 'Paste book text or chapter here...',
			'button_text' => 'Summarise',
		),
		'pdf'     => array(
			'mode'        => 'file',
			'output'      => 'sections',
			'title'       => 'PDF Summary',
			'description' => 'Upload a PDF to get a summary',
			'placeholder' => '',
			'button_text' => 'Summarise',
		),
		'mindmap' => array(
			'mode'        => 'url',
			'output'      => 'mindmap',
			'title'       => 'AI Mindmap',
			'description' => 'Paste a URL to generate a visual mindmap',
			'placeholder' => 'https://example.com/article',
			'button_text' => 'Generate Mindmap',
		),
	);

	/**
	 * Default attribute values (no preset).
	 *
	 * @var array
	 */
	private static $defaults = array(
		'preset'         => '',
		'mode'           => 'auto',
		'output'         => 'sections',
		'title'          => 'AI Summariser',
		'description'    => 'Paste a URL, enter text, or upload a PDF to summarise',
		'placeholder'    => 'https://example.com/article',
		'button_text'    => 'Summarise',
		'summary_length' => 'medium',
	);

	/**
	 * Register shortcode, assets hook, and AJAX handlers.
	 */
	public static function init() {
		add_shortcode( 'lumination_summarizer', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		Lumination_Summarizer_Ajax::register();
	}

	/**
	 * Render the [lumination_summarizer] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		if ( ! Lumination_Core_Security::can_submit( 'summarizer' ) ) {
			return '<p class="lumination-notice">' .
				esc_html__( 'You do not have permission to use this tool.', 'lumination-summarizer' ) . '</p>';
		}

		if ( ! Lumination_Core_API::is_configured() ) {
			return '<p class="lumination-notice">' .
				esc_html__( 'Lumination API is not configured. Please set up your API key in Settings.', 'lumination-summarizer' ) . '</p>';
		}

		// Resolve preset first, then merge explicit atts on top.
		$atts = shortcode_atts( self::$defaults, $atts, 'lumination_summarizer' );

		if ( ! empty( $atts['preset'] ) && isset( self::$presets[ $atts['preset'] ] ) ) {
			$preset = self::$presets[ $atts['preset'] ];
			foreach ( $preset as $key => $value ) {
				// Only apply preset value if the user didn't explicitly override it.
				if ( $atts[ $key ] === self::$defaults[ $key ] ) {
					$atts[ $key ] = $value;
				}
			}
		}

		// Get default length from settings if not overridden.
		if ( 'medium' === $atts['summary_length'] ) {
			$saved_length = get_option( 'lumination_summarizer_default_length', 'medium' );
			if ( $saved_length ) {
				$atts['summary_length'] = $saved_length;
			}
		}

		// Sanitise values for template.
		$lms_mode           = sanitize_text_field( $atts['mode'] );
		$lms_output         = sanitize_text_field( $atts['output'] );
		$lms_title          = sanitize_text_field( $atts['title'] );
		$lms_description    = sanitize_text_field( $atts['description'] );
		$lms_placeholder    = sanitize_text_field( $atts['placeholder'] );
		$lms_button_text    = sanitize_text_field( $atts['button_text'] );
		$lms_summary_length = sanitize_text_field( $atts['summary_length'] );

		ob_start();
		include LUMINATION_SUMMARIZER_DIR . 'templates/summarizer-ui.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue front-end assets when the shortcode is present.
	 */
	public static function enqueue_assets() {
		if ( ! Lumination_Core_API::is_configured() ) {
			return;
		}

		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'lumination_summarizer' ) ) {
			return;
		}

		// ── CSS ──
		wp_enqueue_style(
			'lumination-summarizer',
			LUMINATION_SUMMARIZER_URL . 'assets/css/summarizer.css',
			array(),
			LUMINATION_SUMMARIZER_VERSION
		);

		$color_css = self::get_color_css();
		if ( $color_css ) {
			wp_add_inline_style( 'lumination-summarizer', $color_css );
		}

		// ── Vendor scripts (shared handles, de-duplicated) ──
		if ( ! wp_script_is( 'lumination-marked', 'registered' ) ) {
			wp_register_script(
				'lumination-marked',
				LUMINATION_SUMMARIZER_URL . 'assets/js/vendor/marked.min.js',
				array(),
				LUMINATION_SUMMARIZER_VERSION,
				true
			);
		}
		if ( ! wp_script_is( 'lumination-purify', 'registered' ) ) {
			wp_register_script(
				'lumination-purify',
				LUMINATION_SUMMARIZER_URL . 'assets/js/vendor/purify.min.js',
				array(),
				LUMINATION_SUMMARIZER_VERSION,
				true
			);
		}

		wp_enqueue_script( 'lumination-marked' );
		wp_enqueue_script( 'lumination-purify' );

		// ── MathJax from Core ──
		Lumination_Core_Math::enqueue( 'lumination-summarizer' );

		// ── Main script ──
		wp_enqueue_script(
			'lumination-summarizer',
			LUMINATION_SUMMARIZER_URL . 'assets/js/summarizer.js',
			array( 'lumination-marked', 'lumination-purify', 'lumination-core-math-renderer' ),
			LUMINATION_SUMMARIZER_VERSION,
			true
		);

		wp_localize_script( 'lumination-summarizer', 'luminationSummarizerConfig', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'lumination_summarizer_nonce' ),
			'i18n'    => array(
				'generating'    => __( 'Generating summary...', 'lumination-summarizer' ),
				'generatingMap' => __( 'Generating mindmap...', 'lumination-summarizer' ),
				'error'         => __( 'Something went wrong. Please try again.', 'lumination-summarizer' ),
				'copied'        => __( 'Copied!', 'lumination-summarizer' ),
				'copy'          => __( 'Copy', 'lumination-summarizer' ),
				'dropPdf'       => __( 'Drop a PDF here or click to upload', 'lumination-summarizer' ),
				'fileTooBig'    => __( 'File must be under 10 MB.', 'lumination-summarizer' ),
				'invalidType'   => __( 'Only PDF files are accepted.', 'lumination-summarizer' ),
			),
		) );
	}

	/**
	 * Build inline CSS from Core colour settings.
	 *
	 * @return string
	 */
	private static function get_color_css() {
		$primary    = Lumination_Core_Settings::get_color( 'primary' );
		$hover      = Lumination_Core_Settings::get_color( 'primary_hover' );
		$text       = Lumination_Core_Settings::get_color( 'button_text' );
		$background = Lumination_Core_Settings::get_color( 'tool_background' );
		$tool_text  = Lumination_Core_Settings::get_color( 'tool_text' );

		$vars = array();
		if ( $primary ) {
			$vars[] = '--lms-primary:' . sanitize_hex_color( $primary );
		}
		if ( $hover ) {
			$vars[] = '--lms-primary-hover:' . sanitize_hex_color( $hover );
		}
		if ( $text ) {
			$vars[] = '--lms-btn-text:' . sanitize_hex_color( $text );
		}
		if ( $background ) {
			$vars[] = '--lms-bg:' . sanitize_hex_color( $background );
		}
		if ( $tool_text ) {
			$vars[] = '--lms-text:' . sanitize_hex_color( $tool_text );
		}

		if ( empty( $vars ) ) {
			return '';
		}

		return '.lms-summarizer{' . implode( ';', $vars ) . '}';
	}
}
