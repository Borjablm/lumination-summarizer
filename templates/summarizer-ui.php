<?php
/**
 * Summarizer widget template.
 *
 * Variables available (set in Lumination_Summarizer::render_shortcode):
 *   $lms_mode, $lms_output, $lms_title, $lms_description,
 *   $lms_placeholder, $lms_button_text, $lms_summary_length
 *
 * @package LuminationSummarizer
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lms_is_auto  = ( 'auto' === $lms_mode );
$lms_show_url  = $lms_is_auto || 'url' === $lms_mode;
$lms_show_text = $lms_is_auto || 'text' === $lms_mode;
$lms_show_file = $lms_is_auto || 'file' === $lms_mode;
?>
<div class="lms-summarizer"
     data-mode="<?php echo esc_attr( $lms_mode ); ?>"
     data-output="<?php echo esc_attr( $lms_output ); ?>"
     data-length="<?php echo esc_attr( $lms_summary_length ); ?>">

	<!-- Header -->
	<div class="lms-header">
		<?php if ( ! empty( $lms_title ) ) : ?>
			<h3 class="lms-title"><?php echo esc_html( $lms_title ); ?></h3>
		<?php endif; ?>
		<?php if ( ! empty( $lms_description ) ) : ?>
			<p class="lms-description"><?php echo esc_html( $lms_description ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Input section -->
	<div class="lms-input-section">

		<?php if ( $lms_is_auto ) : ?>
		<!-- Mode tabs -->
		<div class="lms-tabs" role="tablist">
			<button class="lms-tab lms-tab--active" data-tab="url" role="tab" aria-selected="true">
				<?php esc_html_e( 'URL', 'lumination-summarizer' ); ?>
			</button>
			<button class="lms-tab" data-tab="text" role="tab" aria-selected="false">
				<?php esc_html_e( 'Text', 'lumination-summarizer' ); ?>
			</button>
			<button class="lms-tab" data-tab="file" role="tab" aria-selected="false">
				<?php esc_html_e( 'File', 'lumination-summarizer' ); ?>
			</button>
		</div>
		<?php endif; ?>

		<?php if ( $lms_show_url ) : ?>
		<!-- URL input -->
		<div class="lms-tab-panel lms-tab-panel--url<?php echo ( ! $lms_show_url || ( $lms_is_auto && 'url' !== $lms_mode ) ) ? '' : ''; ?>"
		     data-panel="url" role="tabpanel">
			<input type="url"
			       class="lms-url-input"
			       placeholder="<?php echo esc_attr( $lms_placeholder ); ?>"
			       aria-label="<?php esc_attr_e( 'URL to summarise', 'lumination-summarizer' ); ?>" />
		</div>
		<?php endif; ?>

		<?php if ( $lms_show_text ) : ?>
		<!-- Text input -->
		<div class="lms-tab-panel lms-tab-panel--text<?php echo ( $lms_is_auto || 'text' !== $lms_mode ) ? ' lms-hidden' : ''; ?>"
		     data-panel="text" role="tabpanel">
			<textarea class="lms-text-input"
			          placeholder="<?php echo esc_attr( 'text' === $lms_mode ? $lms_placeholder : esc_attr__( 'Paste your text here...', 'lumination-summarizer' ) ); ?>"
			          rows="6"
			          aria-label="<?php esc_attr_e( 'Text to summarise', 'lumination-summarizer' ); ?>"></textarea>
		</div>
		<?php endif; ?>

		<?php if ( $lms_show_file ) : ?>
		<!-- File input -->
		<div class="lms-tab-panel lms-tab-panel--file<?php echo ( $lms_is_auto || 'file' !== $lms_mode ) ? ' lms-hidden' : ''; ?>"
		     data-panel="file" role="tabpanel">
			<div class="lms-drop-zone" tabindex="0" role="button"
			     aria-label="<?php esc_attr_e( 'Drop a PDF here or click to upload', 'lumination-summarizer' ); ?>">
				<svg class="lms-drop-icon" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
					<polyline points="14 2 14 8 20 8"></polyline>
					<line x1="12" y1="18" x2="12" y2="12"></line>
					<line x1="9" y1="15" x2="15" y2="15"></line>
				</svg>
				<p class="lms-drop-text"><?php esc_html_e( 'Drop a PDF here or click to upload', 'lumination-summarizer' ); ?></p>
				<input type="file" class="lms-file-input" accept=".pdf,application/pdf" hidden />
			</div>
			<p class="lms-file-name lms-hidden"></p>
		</div>
		<?php endif; ?>

	</div>

	<!-- Options row -->
	<div class="lms-options">
		<div class="lms-option-group">
			<label class="lms-option-label"><?php esc_html_e( 'Format', 'lumination-summarizer' ); ?></label>
			<div class="lms-pill-group lms-output-pills">
				<button class="lms-pill<?php echo 'sections' === $lms_output ? ' lms-pill--active' : ''; ?>" data-value="sections">
					<?php esc_html_e( 'Detailed', 'lumination-summarizer' ); ?>
				</button>
				<button class="lms-pill<?php echo 'raw' === $lms_output ? ' lms-pill--active' : ''; ?>" data-value="raw">
					<?php esc_html_e( 'Concise', 'lumination-summarizer' ); ?>
				</button>
				<button class="lms-pill<?php echo 'mindmap' === $lms_output ? ' lms-pill--active' : ''; ?>" data-value="mindmap">
					<?php esc_html_e( 'Mindmap', 'lumination-summarizer' ); ?>
				</button>
			</div>
		</div>
		<div class="lms-option-group">
			<label class="lms-option-label"><?php esc_html_e( 'Length', 'lumination-summarizer' ); ?></label>
			<div class="lms-pill-group lms-length-pills">
				<button class="lms-pill<?php echo 'short' === $lms_summary_length ? ' lms-pill--active' : ''; ?>" data-value="short">
					<?php esc_html_e( 'Short', 'lumination-summarizer' ); ?>
				</button>
				<button class="lms-pill<?php echo 'medium' === $lms_summary_length ? ' lms-pill--active' : ''; ?>" data-value="medium">
					<?php esc_html_e( 'Medium', 'lumination-summarizer' ); ?>
				</button>
				<button class="lms-pill<?php echo 'long' === $lms_summary_length ? ' lms-pill--active' : ''; ?>" data-value="long">
					<?php esc_html_e( 'Long', 'lumination-summarizer' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Submit -->
	<div class="lms-submit-section">
		<button class="lms-submit-btn" disabled>
			<?php echo esc_html( $lms_button_text ); ?>
		</button>
	</div>

	<!-- Output section (hidden initially) -->
	<div class="lms-output lms-hidden">
		<div class="lms-output-header">
			<h4 class="lms-output-title"></h4>
			<div class="lms-output-actions">
				<button class="lms-copy-btn" title="<?php esc_attr_e( 'Copy to clipboard', 'lumination-summarizer' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
						<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
					</svg>
					<span><?php esc_html_e( 'Copy', 'lumination-summarizer' ); ?></span>
				</button>
				<button class="lms-download-btn lms-hidden" title="<?php esc_attr_e( 'Download as image', 'lumination-summarizer' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
						<polyline points="7 10 12 15 17 10"></polyline>
						<line x1="12" y1="15" x2="12" y2="3"></line>
					</svg>
					<span><?php esc_html_e( 'Download', 'lumination-summarizer' ); ?></span>
				</button>
				<button class="lms-fullscreen-btn lms-hidden" title="<?php esc_attr_e( 'Full screen', 'lumination-summarizer' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<polyline points="15 3 21 3 21 9"></polyline>
						<polyline points="9 21 3 21 3 15"></polyline>
						<line x1="21" y1="3" x2="14" y2="10"></line>
						<line x1="3" y1="21" x2="10" y2="14"></line>
					</svg>
					<span><?php esc_html_e( 'Full screen', 'lumination-summarizer' ); ?></span>
				</button>
			</div>
		</div>
		<div class="lms-output-content"></div>
		<div class="lms-mindmap-container lms-hidden"></div>
	</div>

	<!-- Loading state -->
	<div class="lms-loading lms-hidden">
		<div class="lms-spinner"></div>
		<p class="lms-loading-text"><?php esc_html_e( 'Generating summary...', 'lumination-summarizer' ); ?></p>
	</div>

</div>
