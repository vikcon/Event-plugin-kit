<?php
/**
 * Plugin Name: TEC Event Page Kit (Elementor Pro)
 * Description: Tabs filter + Upcoming/Past queries + past button = Watch Recording (links to Event Website URL) + category color backgrounds for The Events Calendar + Elementor Pro.
 * Version: 1.0.0
 * Author: Vikcon Consulting
 */

if (!defined('ABSPATH')) exit;

final class TEC_Event_Page_Kit {
	const TAX = 'tribe_events_cat';
	const META_COLOR = 'tec_cat_color';

	public static function init(): void {
		// Shortcode: tabs
		add_shortcode('tec_event_category_filter', [__CLASS__, 'shortcode_tabs']);

		// Elementor Pro custom queries
		add_action('elementor/query/tec_upcoming', [__CLASS__, 'query_upcoming']);
		add_action('elementor/query/tec_past', [__CLASS__, 'query_past']);

		// Add category classes to events
		add_filter('post_class', [__CLASS__, 'add_category_classes'], 10, 3);

		// Expose _EventURL to REST so frontend can read it
		add_action('init', [__CLASS__, 'expose_event_url_meta_to_rest']);

		// Term color field (admin)
		add_action(self::TAX . '_add_form_fields', [__CLASS__, 'term_color_add_field']);
		add_action(self::TAX . '_edit_form_fields', [__CLASS__, 'term_color_edit_field']);
		add_action('created_' . self::TAX, [__CLASS__, 'term_color_save']);
		add_action('edited_' . self::TAX, [__CLASS__, 'term_color_save']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);

		// Frontend assets (tabs css + card css + js behaviors)
		add_action('wp_enqueue_scripts', [__CLASS__, 'frontend_assets']);

		// Output dynamic CSS mapping term colors -> card background
		add_action('wp_head', [__CLASS__, 'output_term_color_css'], 50);
	}

	/* ---------------------------
	 * Helpers
	 * --------------------------- */

	private static function selected_cat(): string {
		return isset($_GET['event_cat']) ? sanitize_title(wp_unslash($_GET['event_cat'])) : '';
	}

	/* ---------------------------
	 * Tabs shortcode
	 * --------------------------- */

	public static function shortcode_tabs(): string {
		$terms = get_terms([
			'taxonomy'   => self::TAX,
			'hide_empty' => false, // show all categories as requested
		]);

		if (is_wp_error($terms) || empty($terms)) return '';

		$selected = self::selected_cat();
		$base_url = remove_query_arg(['event_cat', 'paged']);

		$out  = '<div class="tec-cat-tabs" role="tablist" aria-label="Event Categories">';
		$out .= '<a class="tec-tab ' . ($selected === '' ? 'is-active' : '') . '" href="' . esc_url($base_url) . '">All</a>';

		foreach ($terms as $t) {
			$url = add_query_arg('event_cat', $t->slug, $base_url);
			$out .= '<a class="tec-tab ' . ($selected === $t->slug ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($t->name) . '</a>';
		}

		$out .= '</div>';
		return $out;
	}

	/* ---------------------------
	 * Elementor queries
	 * --------------------------- */

	public static function query_upcoming(\WP_Query $q): void {
		self::apply_event_query($q, 'upcoming');
	}

	public static function query_past(\WP_Query $q): void {
		self::apply_event_query($q, 'past');
	}

	private static function apply_event_query(\WP_Query $q, string $type): void {
		$now = current_time('Y-m-d H:i:s');

		$q->set('post_type', 'tribe_events');
		$q->set('post_status', 'publish');
		$q->set('ignore_sticky_posts', true);

		// Shared category filter via tabs
		$cat = self::selected_cat();
		if ($cat !== '') {
			$q->set('tax_query', [[
				'taxonomy' => self::TAX,
				'field'    => 'slug',
				'terms'    => [$cat],
			]]);
		}

		$q->set('meta_key', '_EventStartDate');
		$q->set('orderby', 'meta_value');
		$q->set('meta_type', 'DATETIME');

		if ($type === 'past') {
			$q->set('order', 'DESC');
			$q->set('meta_query', [[
				'key'     => '_EventStartDate',
				'value'   => $now,
				'compare' => '<',
				'type'    => 'DATETIME',
			]]);
		} else {
			$q->set('order', 'ASC');
			$q->set('meta_query', [[
				'key'     => '_EventStartDate',
				'value'   => $now,
				'compare' => '>=',
				'type'    => 'DATETIME',
			]]);
		}
	}

	/* ---------------------------
	 * Post classes (category slugs)
	 * --------------------------- */

	public static function add_category_classes(array $classes, array $class, int $post_id): array {
		if (get_post_type($post_id) !== 'tribe_events') return $classes;

		$terms = get_the_terms($post_id, self::TAX);
		if (is_wp_error($terms) || empty($terms)) return $classes;

		foreach ($terms as $t) {
			$classes[] = 'tec-cat-' . sanitize_html_class($t->slug);
		}
		return $classes;
	}

	/* ---------------------------
	 * REST: expose Event Website URL meta
	 * --------------------------- */

	public static function expose_event_url_meta_to_rest(): void {
		register_post_meta('tribe_events', '_EventURL', [
			'show_in_rest' => true,
			'single'       => true,
			'type'         => 'string',
			'auth_callback'=> '__return_true',
		]);
	}

	/* ---------------------------
	 * Admin: term color field
	 * --------------------------- */

	public static function admin_assets(string $hook): void {
		// Only load on taxonomy screens
		if (strpos($hook, 'edit-tags.php') === false && strpos($hook, 'term.php') === false) return;
		if (empty($_GET['taxonomy']) || $_GET['taxonomy'] !== self::TAX) return;

		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');

		wp_enqueue_style('tec-epk-admin', plugins_url('assets/admin.css', __FILE__), [], '1.0.0');
		wp_enqueue_script('tec-epk-admin', plugins_url('assets/admin.js', __FILE__), ['wp-color-picker'], '1.0.0', true);
	}

	public static function term_color_add_field(): void {
		?>
		<div class="form-field term-group">
			<label for="tec_cat_color"><?php esc_html_e('Category Color', 'tec-epk'); ?></label>
			<input type="text" id="tec_cat_color" name="tec_cat_color" value="#E7F3FF" class="tec-color-field" />
			<p class="description"><?php esc_html_e('Used as background color on event cards.', 'tec-epk'); ?></p>
		</div>
		<?php
	}

	public static function term_color_edit_field(\WP_Term $term): void {
		$color = get_term_meta($term->term_id, self::META_COLOR, true);
		if (!$color) $color = '#E7F3FF';
		?>
		<tr class="form-field term-group-wrap">
			<th scope="row"><label for="tec_cat_color"><?php esc_html_e('Category Color', 'tec-epk'); ?></label></th>
			<td>
				<input type="text" id="tec_cat_color" name="tec_cat_color" value="<?php echo esc_attr($color); ?>" class="tec-color-field" />
				<p class="description"><?php esc_html_e('Used as background color on event cards.', 'tec-epk'); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function term_color_save(int $term_id): void {
		if (!isset($_POST['tec_cat_color'])) return;
		$color = sanitize_text_field(wp_unslash($_POST['tec_cat_color']));
		// Basic normalize (#RRGGBB)
		if ($color && $color[0] !== '#') $color = '#' . $color;
		update_term_meta($term_id, self::META_COLOR, $color);
	}

	/* ---------------------------
	 * Frontend assets
	 * --------------------------- */

	public static function frontend_assets(): void {
		wp_enqueue_style('tec-epk-frontend', plugins_url('assets/frontend.css', __FILE__), [], '1.0.0');
		wp_enqueue_script('tec-epk-frontend', plugins_url('assets/frontend.js', __FILE__), [], '1.0.0', true);
	}

	/**
	 * Prints dynamic CSS like:
	 * .tec-cat-workshop .elementor-post__card{background:#...;}
	 */
	public static function output_term_color_css(): void {
		$terms = get_terms(['taxonomy' => self::TAX, 'hide_empty' => false]);
		if (is_wp_error($terms) || empty($terms)) return;

		$css = '';
		foreach ($terms as $t) {
			$color = get_term_meta($t->term_id, self::META_COLOR, true);
			if (!$color) continue;
			$slug = sanitize_html_class($t->slug);

			// Elementor Posts widget card wrapper
			$css .= ".tec-cat-{$slug} .elementor-post__card{background:{$color};}\n";
			// Loop Grid wrappers (optional support)
			$css .= ".tec-cat-{$slug} .e-loop-item, .tec-cat-{$slug} .elementor-loop-item{background:{$color};}\n";
		}

		if (!$css) return;

		echo "<style id='tec-epk-term-colors'>\n" . $css . "</style>\n";
	}
}

TEC_Event_Page_Kit::init();