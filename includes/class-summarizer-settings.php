<?php
/**
 * Lumination Summarizer — Admin Settings Tab
 *
 * Registers plugin options and renders the Summarizer tab inside
 * the Lumination Core admin panel.
 *
 * @package    LuminationSummarizer
 * @since      1.0.0
 * @license    GPL-3.0-or-later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lumination_Summarizer_Settings {

	/**
	 * Register settings with WordPress.
	 */
	public static function register_settings() {
		register_setting( 'lumination_summarizer_settings', 'lumination_summarizer_default_length', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_length' ),
			'default'           => 'medium',
		) );
	}

	/**
	 * Sanitise the summary length option.
	 *
	 * @param  string $value Raw value.
	 * @return string
	 */
	public static function sanitize_length( $value ) {
		$allowed = array( 'short', 'medium', 'long' );
		return in_array( $value, $allowed, true ) ? $value : 'medium';
	}

	/**
	 * Render the Summarizer admin tab.
	 */
	public static function render_tab() {
		settings_errors( 'lumination_summarizer_messages' );
		$current_length = get_option( 'lumination_summarizer_default_length', 'medium' );
		?>
		<form action="options.php" method="post" style="max-width: 800px;">
			<?php settings_fields( 'lumination_summarizer_settings' ); ?>

			<h2><?php esc_html_e( 'Settings', 'lumination-summarizer' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="lumination_summarizer_default_length"><?php esc_html_e( 'Default Summary Length', 'lumination-summarizer' ); ?></label>
					</th>
					<td>
						<select id="lumination_summarizer_default_length" name="lumination_summarizer_default_length">
							<option value="short"  <?php selected( $current_length, 'short' ); ?>><?php esc_html_e( 'Short', 'lumination-summarizer' ); ?></option>
							<option value="medium" <?php selected( $current_length, 'medium' ); ?>><?php esc_html_e( 'Medium', 'lumination-summarizer' ); ?></option>
							<option value="long"   <?php selected( $current_length, 'long' ); ?>><?php esc_html_e( 'Long', 'lumination-summarizer' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Default length for generated summaries. Can be overridden per shortcode.', 'lumination-summarizer' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'lumination-summarizer' ) ); ?>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Shortcode Usage', 'lumination-summarizer' ); ?></h2>
		<p><?php esc_html_e( 'Use the [lumination_summarizer] shortcode on any page or post. Use presets for quick setup:', 'lumination-summarizer' ); ?></p>

		<table class="widefat striped" style="max-width: 800px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Use Case', 'lumination-summarizer' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'lumination-summarizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'YouTube Summariser', 'lumination-summarizer' ); ?></td>
					<td><code>[lumination_summarizer preset="youtube"]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Book Summary', 'lumination-summarizer' ); ?></td>
					<td><code>[lumination_summarizer preset="book"]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'PDF Summary', 'lumination-summarizer' ); ?></td>
					<td><code>[lumination_summarizer preset="pdf"]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'AI Mindmap', 'lumination-summarizer' ); ?></td>
					<td><code>[lumination_summarizer preset="mindmap"]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'All Modes (tabbed)', 'lumination-summarizer' ); ?></td>
					<td><code>[lumination_summarizer]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Custom', 'lumination-summarizer' ); ?></td>
					<td><code>[lumination_summarizer mode="url" output="raw" title="Quick Summary"]</code></td>
				</tr>
			</tbody>
		</table>

		<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Shortcode Parameters', 'lumination-summarizer' ); ?></h3>
		<table class="widefat striped" style="max-width: 800px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Parameter', 'lumination-summarizer' ); ?></th>
					<th><?php esc_html_e( 'Values', 'lumination-summarizer' ); ?></th>
					<th><?php esc_html_e( 'Default', 'lumination-summarizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>preset</code></td>
					<td>youtube, book, pdf, mindmap</td>
					<td><?php esc_html_e( '(none)', 'lumination-summarizer' ); ?></td>
				</tr>
				<tr>
					<td><code>mode</code></td>
					<td>url, text, file, auto</td>
					<td>auto</td>
				</tr>
				<tr>
					<td><code>output</code></td>
					<td>sections, raw, mindmap</td>
					<td>sections</td>
				</tr>
				<tr>
					<td><code>title</code></td>
					<td><?php esc_html_e( 'Any text', 'lumination-summarizer' ); ?></td>
					<td>AI Summariser</td>
				</tr>
				<tr>
					<td><code>description</code></td>
					<td><?php esc_html_e( 'Any text', 'lumination-summarizer' ); ?></td>
					<td><?php esc_html_e( '(varies by preset)', 'lumination-summarizer' ); ?></td>
				</tr>
				<tr>
					<td><code>placeholder</code></td>
					<td><?php esc_html_e( 'Any text', 'lumination-summarizer' ); ?></td>
					<td><?php esc_html_e( '(varies by preset)', 'lumination-summarizer' ); ?></td>
				</tr>
				<tr>
					<td><code>button_text</code></td>
					<td><?php esc_html_e( 'Any text', 'lumination-summarizer' ); ?></td>
					<td>Summarise</td>
				</tr>
				<tr>
					<td><code>summary_length</code></td>
					<td>short, medium, long</td>
					<td>medium</td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}
