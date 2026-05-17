<?php
/**
 * Plugin Name: Category Icon
 * Plugin URI:  http://pixelgrade.com
 * Description: Easily attach an icon and/or an image to a category, tag or any other taxonomy term.
 * Version: 1.0.4
 * Author: Pixelgrade
 * Author URI: http://pixelgrade.com
 * Author Email: contact@pixelgrade.com
 * Text Domain: category-icon
 * License:     GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.9.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Retain the legacy global for backward compatibility.
global $pixtaxonomyicons_plugin;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Retain the legacy global for backward compatibility.
$pixtaxonomyicons_plugin = PixTaxonomyIconsPlugin::get_instance();

class PixTaxonomyIconsPlugin {
	protected static $instance;

	protected $plugin_basepath = null;
	protected $plugin_baseurl = null;
	protected $plugin_screen_hook_suffix = null;
	protected $version = '1.0.4';
	protected $plugin_slug = 'category-icon';
	protected $plugin_key = 'category-icon';

	protected $default_settings = array(
		'taxonomies' => array(
			'category' => 'on',
			'post_tag' => 'on'
		)
	);

	/**
	 * Initialize the plugin by setting localization, filters, and administration functions.
	 * @since     1.0.0
	 */
	protected function __construct() {

		$this->plugin_basepath = plugin_dir_path( __FILE__ );
		$this->plugin_baseurl = plugin_dir_url( __FILE__ );

		require_once( 'inc/extras.php');

		$options = get_option('category-icon');
		// ensure some defaults
		if ( empty( $options ) ) {
			$options = $this->get_defaults();
			update_option( 'category-icon', $options);
		}

		/**
		 * As we code this, WordPress has a problem with uploading and viewing svg files.
		 * Until they get it done in core, we use these filters
		 */
		add_filter('upload_mimes', array( $this, 'allow_svg_in_mime_types' ) );
		add_filter('wp_handle_upload_prefilter', array($this, 'sanitize_svg_upload'));
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_svg_attachment_for_js' ), 10, 3 );

		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		add_action( 'admin_init', array( $this, 'plugin_admin_init' ) );

		// Load plugin text domain
		add_action( 'init', array( $this, 'plugin_init' ), 9999999999 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Checks whether the current admin request belongs to Category Icon.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return bool
	 */
	private function is_category_icon_admin_context( $hook_suffix = '' ) {
		if ( ! is_admin() ) {
			return false;
		}

		if ( ! empty( $hook_suffix ) && $this->plugin_screen_hook_suffix === $hook_suffix ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( $screen ) {
				if ( $this->plugin_screen_hook_suffix === $screen->id ) {
					return true;
				}

				if (
					in_array( $screen->base, array( 'edit-tags', 'term' ), true )
					&& ! empty( $screen->taxonomy )
					&& $this->is_selected_taxonomy( $screen->taxonomy )
				) {
					return true;
				}
			}
		}

		$referer = wp_get_referer();
		if ( ! $referer && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$referer_taxonomy = $this->get_taxonomy_from_admin_url( $referer );
		return $referer_taxonomy && $this->is_selected_taxonomy( $referer_taxonomy );
	}

	/**
	 * Extracts taxonomy from an admin taxonomy URL.
	 *
	 * @param string|false $url URL to inspect.
	 * @return string
	 */
	private function get_taxonomy_from_admin_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path || ! in_array( basename( $path ), array( 'edit-tags.php', 'term.php' ), true ) ) {
			return '';
		}

		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( empty( $query ) ) {
			return '';
		}

		parse_str( $query, $args );
		if ( empty( $args['taxonomy'] ) ) {
			return '';
		}

		return sanitize_key( $args['taxonomy'] );
	}

	/**
	 * Checks whether a taxonomy is enabled in Category Icon settings.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	private function is_selected_taxonomy( $taxonomy ) {
		$selected_taxonomies = $this->get_plugin_option( 'taxonomies' );

		return is_array( $selected_taxonomies )
			&& isset( $selected_taxonomies[ $taxonomy ] )
			&& 'on' === $selected_taxonomies[ $taxonomy ];
	}

	/**
	 * Handles the SVG upload process by sanitizing the file content,
	 * generating a random filename, and preserving the original upload path.
	 *
	 * @param array $file The uploaded file information.
	 * @return array The modified file information with sanitized content and updated filename.
	 */
	public function sanitize_svg_upload($file) {
		$is_svg = isset( $file['type'] ) && 'image/svg+xml' === $file['type'];

		if ( ! $is_svg && isset( $file['name'] ) ) {
			$is_svg = 'svg' === strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		}

		if ( $is_svg && ! $this->is_category_icon_admin_context() ) {
			$file['error'] = esc_html__( 'SVG uploads are only allowed from Category Icon taxonomy screens.', 'category-icon' );
			return $file;
		}

		// Check if the uploaded file is an SVG
		if ($is_svg) {
			// Step 1: Read the original SVG content from the temporary file location
			$svg_content = file_get_contents($file['tmp_name']);

			if ( false === $svg_content ) {
				$file['error'] = esc_html__( 'The SVG file could not be read.', 'category-icon' );
				return $file;
			}

			// Step 2: Sanitize the SVG content to remove any potentially unsafe elements
			$clean_svg = $this->sanitize_svg_content($svg_content);

			if ( empty( $clean_svg ) ) {
				$file['error'] = esc_html__( 'The SVG file could not be sanitized and was not uploaded.', 'category-icon' );
				return $file;
			}

			// Step 3: Extract the original file name (without extension) for use in the new name
			$original_name = pathinfo($file['name'], PATHINFO_FILENAME);

			// Step 4: Sanitize the original name to ensure no special characters remain
			$sanitized_name = sanitize_file_name($original_name);

			// Step 5: Generate a unique filename by appending a random suffix to the sanitized name
			$random_suffix = wp_generate_password(6, false);
			$new_filename = "{$sanitized_name}-{$random_suffix}.svg";

			// Step 6: Overwrite the temporary file with the sanitized SVG content
			$write_result = file_put_contents($file['tmp_name'], $clean_svg);

			if ( false === $write_result ) {
				$file['error'] = esc_html__( 'The sanitized SVG file could not be written.', 'category-icon' );
				return $file;
			}

			// Step 7: Update the file's displayed name, leaving tmp_name intact for WordPress to handle
			$file['name'] = $new_filename;
		}

		// Return the updated file information back to WordPress
		return $file;
	}

	/**
	 * Makes sanitized SVG attachments render in the media modal without editing
	 * WordPress admin output buffers.
	 *
	 * @param array   $response   Attachment response data.
	 * @param WP_Post $attachment Attachment object.
	 * @param array   $meta       Attachment metadata.
	 * @return array
	 */
	public function prepare_svg_attachment_for_js( $response, $attachment, $meta ) {
		if ( empty( $response['mime'] ) || 'image/svg+xml' !== $response['mime'] || empty( $response['url'] ) ) {
			return $response;
		}

		$width  = ! empty( $meta['width'] ) ? absint( $meta['width'] ) : 150;
		$height = ! empty( $meta['height'] ) ? absint( $meta['height'] ) : 150;

		$response['type']    = 'image';
		$response['subtype'] = 'svg+xml';
		$response['icon']    = $response['url'];
		$response['sizes']   = array(
			'full' => array(
				'url'         => $response['url'],
				'width'       => $width,
				'height'      => $height,
				'orientation' => $width >= $height ? 'landscape' : 'portrait',
			),
		);

		return $response;
	}

	/**
	 * Sanitize raw SVG markup before it is stored in the uploads directory.
	 *
	 * The function loads the SVG into a DOMDocument *without* expanding external
	 * entities or allowing network I/O, preventing XXE/SSRF.  After a successful
	 * parse it walks the document and strips any element or attribute that is not
	 * explicitly allow-listed.
	 *
	 * @param  string $svg_content Raw SVG file contents.
	 * @return string              Clean SVG (empty string on failure or if unsafe).
	 */
	private function sanitize_svg_content( $svg_content ) {
		// Collect libxml errors internally – we decide what to do with them.
		libxml_use_internal_errors( true );

		$dom       = new DOMDocument();
		$load_opts = LIBXML_NONET        // deny all network access (blocks XXE/SSRF)
					| LIBXML_NOERROR     // suppress warnings
					| LIBXML_NOWARNING;  // suppress notices

		// PHP < 8.0 still allows the entity loader unless we disable it globally.
		if ( PHP_VERSION_ID < 80000 ) {
			$previous_loader_state = libxml_disable_entity_loader( true ); // phpcs:ignore PHPCompatibility.Extensions.RemovedFunctions
		}

		// Abort if XML is invalid *or* references external entities.
		if ( ! $dom->loadXML( $svg_content, $load_opts ) || ! $dom->documentElement || 'svg' !== $dom->documentElement->nodeName ) {
			if ( PHP_VERSION_ID < 80000 ) {
				libxml_disable_entity_loader( $previous_loader_state ); // restore
			}
			return ''; // fail closed
		}

		// Restore global entity loader setting for legacy PHP versions.
		if ( PHP_VERSION_ID < 80000 ) {
			libxml_disable_entity_loader( $previous_loader_state );
		}

		/* --------------------------------------------------------------------
		* Phase 2 – Allow-list-based cleanup
		* ------------------------------------------------------------------ */

		$allowed_tags = array(
			'svg', 'g', 'path', 'polygon', 'circle', 'ellipse', 'line',
			'polyline', 'rect', 'use', 'defs', 'linearGradient',
			'radialGradient', 'stop', 'clipPath', 'mask', 'title', 'desc',
		);

		$allowed_attrs = array(
			'id', 'class', 'fill', 'stroke', 'stroke-width', 'stroke-linecap',
			'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray',
			'stroke-dashoffset', 'transform', 'd', 'points', 'x', 'y', 'cx',
			'cy', 'r', 'rx', 'ry', 'width', 'height', 'x1', 'y1', 'x2', 'y2',
			'gradientUnits', 'gradientTransform', 'offset', 'stop-color',
			'stop-opacity', 'viewBox', 'xmlns', 'version',
			'preserveAspectRatio',
		);

		$xpath = new DOMXPath( $dom );

		/** @var DOMElement $node */
		foreach ( $xpath->query( '//*' ) as $node ) {
			// Drop any element that is not allowed.
			if ( ! in_array( $node->nodeName, $allowed_tags, true ) ) {
				$node->parentNode->removeChild( $node );
				continue;
			}

			// Prune disallowed attributes.
			if ( $node->hasAttributes() ) {
				/** @var DOMAttr $attr */
				foreach ( iterator_to_array( $node->attributes ) as $attr ) {
					if ( ! in_array( $attr->nodeName, $allowed_attrs, true ) ) {
						$node->removeAttributeNode( $attr );
					}
				}
			}
		}

		// Return compact, sanitized SVG.
		$dom->formatOutput = false;
		return $dom->saveXML( $dom->documentElement );
	}

	/**
	 * Return an instance of this class.
	 * @since     1.0.0
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	function plugin_init() {

		$selected_taxonomies = $this->get_plugin_option('taxonomies');

		if ( ! is_wp_error( $selected_taxonomies ) && ! empty( $selected_taxonomies ) ) {
			foreach ( $selected_taxonomies as $tax_name => $somerandomvalue ) {
				add_action( $tax_name . '_add_form_fields', array( $this, 'taxonomy_add_new_meta_field'), 10, 2 );
				add_action( $tax_name . '_edit_form_fields', array( $this, 'taxonomy_edit_new_meta_field'), 10, 2 );
				add_action( 'edited_' . $tax_name,  array( $this, 'save_taxonomy_custom_meta' ), 10, 2 );
				add_action( 'create_' . $tax_name,  array( $this, 'save_taxonomy_custom_meta' ), 10, 2 );
				add_filter( "manage_edit-" . $tax_name . "_columns", array( $this, 'add_custom_tax_column' ) );
				add_filter( "manage_" . $tax_name . "_custom_column", array( $this, 'output_custom_tax_column' ), 10, 3 );
			}
		}
	}

	function enqueue_admin_scripts ( $hook_suffix ) {
		if ( ! $this->is_category_icon_admin_context( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style( $this->plugin_slug . '-admin-style', plugins_url( 'assets/admin/css/category-icon.css', __FILE__ ), array(  ), $this->version );
		wp_enqueue_media();
		wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/admin/js/category-icon.js', __FILE__ ), array( 'jquery' ), $this->version, true );
		wp_localize_script( $this->plugin_slug . '-admin-script', 'locals', array(
			'ajax_url' => admin_url( 'admin-ajax.php' )
		) );
	}

	function taxonomy_add_new_meta_field ( $tax ) { ?>
		<div class="open_term_icon_preview form-field">
			<input type="hidden" name="term_icon_value" id="term_icon_value" value="">
			<span class="open_term_icon_upload button button-secondary">
				<?php esc_html_e( 'Select Icon', 'category-icon' ); ?>
			</span>
		</div>

		<div class="open_term_image_preview form-field">
			<input type="hidden" name="term_image_value" id="term_image_value" value="">
			<span class="open_term_image_upload button button-secondary">
				<?php esc_html_e( 'Select Image', 'category-icon' ); ?>
			</span>
		</div>
		<?php
	}

	function taxonomy_edit_new_meta_field ( $term ) {
		$current_value = '';
		if ( isset( $term->term_id ) ) {
			$current_value = get_term_meta( $term->term_id, 'pix_term_icon', true );
		} ?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="term_icon_value"><?php esc_html_e( 'Icon', 'category-icon' ); ?></label></th>
			<td>
				<div class="open_term_icon_preview">
					<input type="hidden" name="term_icon_value" id="term_icon_value" value="<?php echo esc_attr( $current_value ); ?>">
					<?php if ( empty( $current_value ) ) { ?>
						<span class="open_term_icon_upload button button-secondary">
							<?php esc_html_e( 'Select Icon', 'category-icon' );?>
						</span>
						<?php } else {
							$thumb_src = $this->get_attachment_preview_url( $current_value );?>
							<?php if ( $thumb_src ) { ?>
							<img src="<?php echo esc_url( $thumb_src ); ?>" style="width: 90%; height:90%; padding: 5%" />
							<span class="open_term_icon_upload button button-secondary">
								<?php esc_html_e( 'Select', 'category-icon' );?>
							</span>
							<span class="open_term_icon_delete button button-secondary">
								<?php esc_html_e( 'Remove', 'category-icon' );?>
							</span>
							<?php } else { ?>
							<span class="open_term_icon_upload button button-secondary">
								<?php esc_html_e( 'Select Icon', 'category-icon' );?>
							</span>
							<?php } ?>
					<?php } ?>
					</div>
				</td>
		</tr>
		<?php
		$current_image_value = '';
		if ( isset( $term->term_id ) ) {
			$current_image_value = get_term_meta( $term->term_id, 'pix_term_image', true );
		} ?>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="term_image_value"><?php esc_html_e( 'Image', 'category-icon' ); ?></label></th>
			<td>
				<div class="open_term_image_preview">
					<input type="hidden" name="term_image_value" id="term_image_value" value="<?php echo esc_attr( $current_image_value ); ?>">
					<?php if ( empty( $current_image_value ) ) { ?>
						<span class="open_term_image_upload button button-secondary">
							<?php esc_html_e( 'Select Image', 'category-icon' );?>
						</span>
						<?php } else {
							$thumb_src = $this->get_attachment_preview_url( $current_image_value );?>
							<?php if ( $thumb_src ) { ?>
							<img src="<?php echo esc_url( $thumb_src ); ?>" style="width: 90%; height:90%; padding: 5%" />
							<span class="open_term_image_upload button button-secondary">
								<?php esc_html_e( 'Select', 'category-icon' );?>
							</span>
							<span class="open_term_image_delete button button-secondary">
								<?php esc_html_e( 'Remove', 'category-icon' );?>
							</span>
							<?php } else { ?>
							<span class="open_term_image_upload button button-secondary">
								<?php esc_html_e( 'Select Image', 'category-icon' );?>
							</span>
							<?php } ?>
						<?php } ?>
					</div>
				</td>
		</tr>
		<?php
	}

	function save_taxonomy_custom_meta ( $term_id ) {
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$taxonomy_object = $taxonomy ? get_taxonomy( $taxonomy ) : null;
		$capability = $taxonomy_object && isset( $taxonomy_object->cap->edit_terms ) ? $taxonomy_object->cap->edit_terms : 'manage_categories';

		if ( ! current_user_can( $capability ) ) {
			return;
		}

		if ( isset( $_POST['_wpnonce'] ) ) {
			check_admin_referer( 'update-tag_' . $term_id );
		} elseif ( isset( $_POST['_wpnonce_add-tag'] ) ) {
			check_admin_referer( 'add-tag', '_wpnonce_add-tag' );
		} else {
			return;
		}

		if ( isset( $_POST['term_icon_value'] ) ) {
			$value = absint( wp_unslash( $_POST['term_icon_value'] ) );
			if ( $value <= 0 ) {
				$value = '';
			}
			$current_value = get_term_meta( $term_id, 'pix_term_icon', true );

			if ( empty( $current_value ) ) {
				$updated = update_term_meta( $term_id, 'pix_term_icon', $value );
			} else {
				$updated = update_term_meta( $term_id, 'pix_term_icon', $value, $current_value );
			}
			update_termmeta_cache( array( $term_id ) );
		}

		if ( isset( $_POST['term_image_value'] ) ) {
			$value_image = absint( wp_unslash( $_POST['term_image_value'] ) );
			if ( $value_image <= 0 ) {
				$value_image = '';
			}
			$current_value_image = get_term_meta( $term_id, 'pix_term_image', true );

			if ( empty( $current_value_image ) ) {
				$updated = update_term_meta( $term_id, 'pix_term_image', $value_image );
			} else {
				$updated = update_term_meta( $term_id, 'pix_term_image', $value_image, $current_value_image );
			}
			update_termmeta_cache( array( $term_id ) );
		}
	}

	/**
	 * Taxonomy columns
	 */
	function add_custom_tax_column( $current_columns ) {

		$input = array_shift( $current_columns );
		$new_columns = array(
			'cb' => $input,
			'pix-taxonomy-icon' => __( 'Icon', 'category-icon' ),
		);

		$new_columns = $new_columns + $current_columns;
		return $new_columns;
	}

	function output_custom_tax_column( $value, $column_name, $id ) {

	    if( $column_name !== 'pix-taxonomy-icon'){
	        return $value;
        }

		    $icon_id = absint( get_term_meta( $id, 'pix_term_icon', true ) );
			if ( $icon_id ) {
				$src = $this->get_attachment_preview_url( $icon_id, 'thumbnail' );
				if ( $src ) {
					$value = '<div class="pix-taxonomy-icon-column_wrap media-icon"><img src="' . esc_url( $src ) . '" width="60px" height="60px" /></div>';
				}
			}

		return $value;

	}


	/**
	 * create an admin page
	 */
	function add_plugin_admin_menu( ) {
		$this->plugin_screen_hook_suffix = add_options_page(
			__( 'Category Icon', 'category-icon' ),
			__( 'Category Icon', 'category-icon' ),
			'manage_options',
			$this->plugin_slug, array( $this, 'display_plugin_admin_page' )
		);
	}

	function plugin_admin_init() {
		register_setting( 'category-icon', 'category-icon', array( $this, 'save_setting_values' ) );
		add_settings_section(
			'category-icon',
			null,
			array( $this, 'render_settings_section_title' ),
			'category-icon'
		);
		add_settings_field('taxonomies', __( 'Select Taxonomies', 'category-icon' ), array( $this, 'render_taxonomies_select' ), 'category-icon', 'category-icon');
	}

	function render_taxonomies_select ( ) {
		$taxonomies = get_taxonomies();

		// get the current selected taxonomies
		$options = get_option('category-icon');

		$selected_taxonomies = array();

		if ( isset( $options['taxonomies'] ) ) {
			$selected_taxonomies = $options['taxonomies'];
		} ?>
		<fieldset class="select_taxonomies">
			<?php
			if ( ! empty( $taxonomies ) && ! is_wp_error( $taxonomies ) ) {
				foreach ( $taxonomies as $key => $tax ) {
					$full_key = 'category-icon[taxonomies][' . $key  . ']'; ?>
						<label for="<?php echo esc_attr( $full_key ); ?>">
							<input id="<?php echo esc_attr( $full_key ); ?>" name="<?php echo esc_attr( $full_key ); ?>" size="40" type="checkbox" <?php checked( isset( $selected_taxonomies[ $key ] ) && 'on' === $selected_taxonomies[ $key ] ); ?>/>
							<?php echo esc_html( $key ); ?>
							<br>
						</label>
					<?php }
				}?>

		</fieldset>
	<?php }

	// this should sanitize things around
	function save_setting_values( $input ) {
		$output = array(
			'taxonomies' => array(),
		);

		$taxonomies = get_taxonomies();
		$selected_taxonomies = isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ? $input['taxonomies'] : array();

		foreach ( $selected_taxonomies as $taxonomy => $value ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( isset( $taxonomies[ $taxonomy ] ) && 'on' === $value ) {
				$output['taxonomies'][ $taxonomy ] = 'on';
			}
		}

		return $output;
	}

	function render_settings_section_title() { ?>
		<h2><?php esc_html_e( 'Category Icon Options', 'category-icon' ); ?></h2>
	<?php }

	/**
	 * Render the settings page for this plugin.
	 */
	function display_plugin_admin_page() { ?>
		<div class="wrap" id="taxonomy_icons_form">
			<div id="icon-options-general" class="icon32"></div>
			<form action="options.php" method="post">
				<?php
				settings_fields('category-icon');
				do_settings_sections('category-icon'); ?>
				<input name="Submit" type="submit" value="<?php esc_attr_e( 'Save Changes', 'category-icon' ); ?>" />
			</form>
		</div>
	<?php }

	function get_plugin_option( $key ) {

		$options = get_option('category-icon');

		if ( isset( $options [$key] ) ) {
			return $options [$key];
		}

		return null;
	}

	function get_defaults() {
		return $this->default_settings;
	}

	/**
	 * Gets an attachment preview URL, falling back to the raw attachment URL
	 * for SVGs and other files without generated sizes.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Requested image size.
	 * @return string
	 */
	private function get_attachment_preview_url( $attachment_id, $size = 'thumbnail' ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return '';
		}

		$src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( is_array( $src ) && ! empty( $src[0] ) ) {
			return $src[0];
		}

		$url = wp_get_attachment_url( $attachment_id );
		return $url ? $url : '';
	}

	/**
	 * Allow svg files to be uploaded
	 * @param $mimes
	 *
	 * @return mixed
	 */
	function allow_svg_in_mime_types($mimes) {
		if ( ! current_user_can( 'upload_files' ) || ! $this->is_category_icon_admin_context() ) {
			return $mimes;
		}

		if ( ! isset( $mimes['svg'] ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}
}
