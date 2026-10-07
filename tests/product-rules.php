<?php
// Standalone behavioral tests; WordPress/WooCommerce/FluentCRM doubles, no database.
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['errors'] = array();
$GLOBALS['logged_in'] = true;
$GLOBALS['tags'] = array();
$GLOBALS['admin'] = false;
$GLOBALS['can_manage'] = true;
$GLOBALS['product_page'] = true;
$GLOBALS['queried_id'] = 123;
$GLOBALS['no_cache'] = 0;
$GLOBALS['settings_fields'] = array();
$GLOBALS['sanitized'] = array();
$GLOBALS['categories'] = array( 'membership' => 10, 'courses' => 11, '123' => 12, 'empty-category' => 13, '0' => 14 );
$GLOBALS['product_categories'] = array( 123 => array( 'membership' ), 789 => array( 'membership' ), 456 => array( 'courses' ), 790 => array( 'membership', 'courses' ), 791 => array( 'membership-child' ) );

function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['actions'][ $hook ][ $priority ][] = $callback; }
function remove_action( $hook, $callback, $priority = 10 ) {
	foreach ( isset( $GLOBALS['actions'][ $hook ][ $priority ] ) ? $GLOBALS['actions'][ $hook ][ $priority ] : array() as $index => $registered ) {
		if ( $registered === $callback ) { unset( $GLOBALS['actions'][ $hook ][ $priority ][ $index ] ); }
	}
}
function do_action( $hook ) {
	$priorities = isset( $GLOBALS['actions'][ $hook ] ) ? $GLOBALS['actions'][ $hook ] : array();
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) { foreach ( $callbacks as $callback ) { call_user_func( $callback ); } }
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['filters'][ $hook ][] = $callback; }
function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 1 );
	foreach ( isset( $GLOBALS['filters'][ $hook ] ) ? $GLOBALS['filters'][ $hook ] : array() as $callback ) {
		$args[0] = $value;
		$value = call_user_func_array( $callback, $args );
	}
	return $value;
}
function add_shortcode( $name, $callback ) {}
function __( $text, $domain ) { return $text; }
function get_option( $name, $default = false ) { return isset( $GLOBALS['options'][ $name ] ) ? $GLOBALS['options'][ $name ] : $default; }
function add_settings_error( $name, $code, $message ) { $GLOBALS['errors'][] = $code; $GLOBALS['error_messages'][] = $message; }
function taxonomy_exists( $taxonomy ) { return 'product_cat' === $taxonomy && function_exists( 'wc_get_product' ); }
function get_term_by( $field, $slug, $taxonomy ) {
	return 'slug' === $field && 'product_cat' === $taxonomy && isset( $GLOBALS['categories'][ $slug ] ) ? (object) array( 'slug' => (string) $slug, 'term_id' => $GLOBALS['categories'][ $slug ] ) : false;
}
function is_wp_error( $value ) { return false; }
function has_term( $slug, $taxonomy, $id ) {
	if ( 'product_cat' !== $taxonomy || empty( $GLOBALS['product_categories'][ $id ] ) ) { return false; }
	// WordPress treats an empty scalar (including "0") as "any term".
	if ( empty( $slug ) ) { return true; }
	foreach ( (array) $slug as $term ) {
		if ( in_array( $term, $GLOBALS['product_categories'][ $id ], true ) ) { return true; }
	}
	return false;
}
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function is_admin() { return $GLOBALS['admin']; }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function get_queried_object_id() { return $GLOBALS['queried_id']; }
function nocache_headers() { $GLOBALS['no_cache']++; }
function current_user_can( $capability ) { return 'manage_options' === $capability && $GLOBALS['can_manage']; }
function add_menu_page() {}
function add_submenu_page( $parent, $title, $menu, $capability, $slug, $callback ) { $GLOBALS['submenu'] = func_get_args(); }
function register_setting( $group, $name, $args ) { $GLOBALS['settings'][ $name ] = array( $group, $args ); }
function settings_fields( $group ) { $GLOBALS['settings_fields'][] = $group; }
function settings_errors() {}
function submit_button() {}
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $value ) { return esc_attr( $value ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_attr_e( $text, $domain ) { echo esc_attr( $text ); }
function esc_html_e( $text, $domain ) { echo esc_attr( $text ); }
function wp_kses_post( $html ) {
	// A sentinel response checks delegation to WP's sanitizer, not its implementation.
	$GLOBALS['sanitized'][] = $html;
	return '<script>unsafe()</script><p>Join</p>' === $html ? '<p>Join</p>' : $html;
}
class ProductRuleTestProduct {
	private $id;
	function __construct( $id ) { $this->id = $id; }
	function get_id() { return $this->id; }
	function is_type( $type ) { return 'variation' === $type && 124 === $this->id; }
	function get_parent_id() { return 124 === $this->id ? 123 : 0; }
}
class ProductRuleTestWidget {
	private $name;
	private $product_id;
	function __construct( $name, $product_id ) { $this->name = $name; $this->product_id = $product_id; }
	function get_name() { return $this->name; }
	function get_settings_for_display( $key ) { return 'product_id' === $key ? $this->product_id : null; }
}
if ( ! in_array( '--without-woocommerce', $argv, true ) ) {
	function is_product() { return $GLOBALS['product_page']; }
	function wc_get_product( $id ) { return in_array( $id, array( 123, 124, 456, 789, 790, 791 ), true ) ? new ProductRuleTestProduct( $id ) : false; }
}
if ( ! in_array( '--without-crm', $argv, true ) ) {
	function fluentcrm_get_current_contact() {
		if ( ! empty( $GLOBALS['lookup_error'] ) ) { throw new RuntimeException( 'CRM lookup error' ); }
		return ! empty( $GLOBALS['missing_contact'] ) ? null : new ProductRuleTestContact();
	}
}
class ProductRuleTestContact {
	function attachTags( $ids ) { $GLOBALS['tags'] = array_unique( array_merge( $GLOBALS['tags'], $ids ) ); }
	function detachTags( $ids ) { $GLOBALS['tags'] = array_values( array_diff( $GLOBALS['tags'], $ids ) ); }
	function tags() { return $this; }
	function get() {
		if ( ! empty( $GLOBALS['tags_error'] ) ) { throw new RuntimeException( 'CRM tags error' ); }
		return array_map( function ( $id ) { return (object) array( 'id' => $id ); }, $GLOBALS['tags'] );
	}
}
function wc_get_template( $template_name ) {
	$template = apply_filters( 'wc_get_template', __DIR__ . '/fixtures/product-cart.php', $template_name );
	include $template;
}
function woocommerce_template_single_add_to_cart() { wc_get_template( 'single-product/add-to-cart/simple.php' ); }

require dirname( __DIR__ ) . '/kitmage-fluentcrm-tagger.php';
$count = 0;
function check( $expected, $actual, $label ) {
	global $count;
	$count++;
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}
function summary_output() { ob_start(); do_action( 'woocommerce_single_product_summary' ); return ob_get_clean(); }
function direct_cart_output( $template_name ) { ob_start(); wc_get_template( $template_name ); return ob_get_clean(); }
function reset_summary() {
	$GLOBALS['actions']['woocommerce_single_product_summary'] = array();
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
}
function filtered_cart_block( $name, $content, $context_id = 123 ) {
	foreach ( $GLOBALS['filters'][ 'render_block_' . $name ] as $callback ) {
		$content = call_user_func( $callback, $content, array( 'blockName' => $name ), (object) array( 'context' => array( 'postId' => $context_id ) ) );
	}
	return $content;
}
function elementor_cart_output( $name, $content, $product_id = '' ) {
	return apply_filters( 'elementor/widget/render_content', $content, new ProductRuleTestWidget( $name, $product_id ) );
}
function elementor_cache_setting( $pre_option = false ) {
	return apply_filters( 'pre_option_elementor_element_cache_ttl', $pre_option );
}
$rule = array( 'tag_id' => '26', 'category_slug' => 'membership', 'message' => '<p>Please <a href="/join/">join</a>.</p>' );
$saved_rule = array( 'tag_id' => 26, 'category_slug' => 'membership', 'message' => $rule['message'] );
$GLOBALS['options']['kitmage_crm_product_rules'] = array( $saved_rule );
$GLOBALS['product'] = new ProductRuleTestProduct( 123 );
$cart = '<form class="cart"><button>Add to Cart</button></form>';
$ajax_cart = '<a class="elementor-button add_to_cart_button ajax_add_to_cart" href="?add-to-cart=123" data-product_id="123">Add to Cart</a>';
$message = '<div class="kitmage-crm-product-message">' . $rule['message'] . '</div>';

do_action( 'admin_menu' );
check( 'kitmage-crm-redirects', $GLOBALS['submenu'][0], 'Submenu under CRM Redirects' );
check( 'manage_options', $GLOBALS['submenu'][3], 'Submenu restricted to admins' );
do_action( 'admin_init' );
check( 'kitmage_fluentcrm_tagger_sanitize_product_rules', $GLOBALS['settings']['kitmage_crm_product_rules'][1]['sanitize_callback'], 'Sanitizer registered' );
check( true, in_array( 'kitmage_fluentcrm_tagger_prepare_product_rule', $GLOBALS['actions']['template_redirect'][20], true ), 'Product rule runs after tag actions' );

if ( in_array( '--without-woocommerce', $argv, true ) ) {
	reset_summary();
	kitmage_fluentcrm_tagger_prepare_product_rule();
	check( $cart, summary_output(), 'Missing WooCommerce is harmless' );
	check( $cart, filtered_cart_block( 'woocommerce/add-to-cart-form', $cart ), 'No block changes without WooCommerce' );
	check( $cart, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'No direct cart changes without WooCommerce' );
	check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'No Elementor changes without WooCommerce' );
	check( false, elementor_cache_setting(), 'Elementor cache unchanged without WooCommerce' );
	check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $rule ) ), 'Existing settings retained without WooCommerce' );
	check( 'invalid_product_rule', end( $GLOBALS['errors'] ), 'Missing WooCommerce validation error' );
	check( array(), kitmage_fluentcrm_tagger_sanitize_product_rules( array() ), 'Rules can be removed without WooCommerce' );
	echo "Passed $count checks without WooCommerce.\n";
	exit( 0 );
}

reset_summary();
kitmage_fluentcrm_tagger_prepare_product_rule();
check( $message, summary_output(), 'Missing tag replaces entire cart form' );
check( true, defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, 'Disable page caching' );
check( 1, $GLOBALS['no_cache'], 'Send no-cache headers' );
// Builders may call the cart template without using woocommerce_single_product_summary.
foreach ( array( 'simple', 'variable', 'grouped', 'external' ) as $type ) {
	check( $message, direct_cart_output( 'single-product/add-to-cart/' . $type . '.php' ), 'Direct ' . $type . ' form replaced for missing tag' );
}
check( $cart, direct_cart_output( 'single-product/add-to-cart/variation-add-to-cart-button.php' ), 'Nested variation fragment untouched' );
check( $cart, direct_cart_output( 'single-product/price.php' ), 'Unrelated template untouched' );
// Elementor's Custom Add To Cart AJAX button never calls wc_get_template().
check( $message, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Elementor AJAX button replaced without a WooCommerce template' );
check( $message, elementor_cart_output( 'wc-add-to-cart', $cart, '123' ), 'Elementor explicit current-product form replaced' );
check( $message, elementor_cart_output( 'woocommerce-product-add-to-cart', '<div class="elementor-add-to-cart">' . $cart . '</div>' ), 'Elementor standard widget replaced once' );
check( $message, elementor_cart_output( 'woocommerce-product-add-to-cart', '<div class="elementor-add-to-cart">' . $message . '</div>' ), 'Already replaced Elementor form does not duplicate message' );
check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart, 789 ), 'Another product in same restricted category is unchanged' );
check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart, 456 ), 'Elementor explicit unrelated product untouched' );
check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart, 999 ), 'Elementor invalid product untouched' );
check( $ajax_cart, elementor_cart_output( 'button', $ajax_cart ), 'Ordinary Elementor button untouched' );
check( 'disable', elementor_cache_setting(), 'Restricted Elementor output bypasses element cache' );
check( 'disable', elementor_cache_setting( 'existing-override' ), 'Existing cache override cannot reuse visitor-dependent HTML' );
foreach ( array( 'woocommerce/add-to-cart-form', 'woocommerce/add-to-cart-with-options' ) as $block_name ) {
	check( $message, filtered_cart_block( $block_name, $cart ), 'Replace purchase block ' . $block_name );
	check( $cart, filtered_cart_block( $block_name, $cart, 456 ), 'Related product block untouched ' . $block_name );
}
if ( in_array( '--without-crm', $argv, true ) ) {
	echo "Passed $count checks without FluentCRM.\n";
	exit( 0 );
}

check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $rule ) ), 'Save valid rule with formatted HTML' );
// options.php calls update_option(), which sanitizes again in add_option() on first save.
$callback = $GLOBALS['settings']['kitmage_crm_product_rules'][1]['sanitize_callback'];
unset( $GLOBALS['options']['kitmage_crm_product_rules'] );
$GLOBALS['errors'] = array();
$first_pass = call_user_func( $callback, array( $rule ) );
$second_pass = call_user_func( $callback, $first_pass );
check( array( $saved_rule ), $second_pass, 'First save survives the Settings API double sanitization' );
check( array(), $GLOBALS['errors'], 'First save raises no false validation errors' );
$GLOBALS['options']['kitmage_crm_product_rules'] = $second_pass;
$edited = $rule; $edited['tag_id'] = '27';
check( 27, call_user_func( $callback, call_user_func( $callback, array( $edited ) ) )[0]['tag_id'], 'Edited rule remains valid on repeated sanitization' );
$unsafe = $rule; $unsafe['message'] = '<script>unsafe()</script><p>Join</p>';
$clean = kitmage_fluentcrm_tagger_sanitize_product_rules( array( $unsafe ) );
check( '<p>Join</p>', $clean[0]['message'], 'Use sanitized HTML from WordPress when saving' );
check( '<div class="kitmage-crm-product-message"><p>Join</p></div>', kitmage_fluentcrm_tagger_product_message( $unsafe ), 'Sanitize HTML again when rendering' );
foreach ( array( '0', '-1', '1.5', '26,27', 'abc', '', '999999999999999999999999', array( '26' ), true, 26.0 ) as $bad ) {
	$input = $rule; $input['tag_id'] = $bad;
	check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $input ) ), 'Retain previous rules for bad tag ID' );
}
foreach ( array( '', 'nonexistent', '/product-category/membership/', array( 'membership' ), true, 123 ) as $bad_category ) {
	$input = $rule; $input['category_slug'] = $bad_category;
	check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $input ) ), 'Reject missing, nonexistent, or malformed category' );
}
$trimmed = $rule; $trimmed['category_slug'] = ' membership ';
check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $trimmed ) ), 'Trim whitespace around category slugs' );
$numeric = $rule; $numeric['category_slug'] = '123';
check( '123', kitmage_fluentcrm_tagger_sanitize_product_rules( array( $numeric ) )[0]['category_slug'], 'Numeric slug is a slug, not a term ID' );
$numeric['category_slug'] = '0';
$zero_rules = kitmage_fluentcrm_tagger_sanitize_product_rules( array( $numeric ) );
check( '0', $zero_rules[0]['category_slug'], 'Zero is also a valid existing category slug' );
$GLOBALS['options']['kitmage_crm_product_rules'] = $zero_rules;
check( null, kitmage_fluentcrm_tagger_product_rule( 123 ), 'Zero slug does not match every category' );
$GLOBALS['product_categories'][789] = array( '0' );
check( $zero_rules[0], kitmage_fluentcrm_tagger_product_rule( 789 ), 'Zero slug matches a product assigned to it' );
$GLOBALS['product_categories'][789] = array( 'membership' );
$GLOBALS['options']['kitmage_crm_product_rules'] = array( $saved_rule );
$empty_category = $rule; $empty_category['category_slug'] = 'empty-category';
check( 'empty-category', kitmage_fluentcrm_tagger_sanitize_product_rules( array( $empty_category ) )[0]['category_slug'], 'Existing empty category can have a rule' );
foreach ( array( '', array( 'html' ) ) as $bad_message ) {
	$input = $rule; $input['message'] = $bad_message;
	check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $input ) ), 'Reject missing or malformed message' );
}
check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $rule, $rule ) ), 'Duplicate categories rejected atomically' );
check( true, false !== strpos( implode( ' ', $GLOBALS['error_messages'] ), 'Row 2: Use only one rule per category slug.' ), 'Error identifies duplicate row and reason' );
check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( 'invalid' ), 'Malformed submission preserves settings' );
check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( 'invalid' ) ), 'Malformed row preserves settings' );
check( array(), kitmage_fluentcrm_tagger_sanitize_product_rules( array( array( 'tag_id' => '', 'category_slug' => '', 'message' => '' ) ) ), 'Clear final rule' );
check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $rule, array( 'tag_id' => '', 'category_slug' => '', 'message' => '' ) ) ), 'Ignore blank extra row' );
$other = $rule; $other['category_slug'] = 'courses'; $other['tag_id'] = '27';
check( 2, count( kitmage_fluentcrm_tagger_sanitize_product_rules( array( $rule, $other ) ) ), 'Multiple different categories saved' );
check( null, kitmage_fluentcrm_tagger_product_rule( 456 ), 'Unlisted product has no rule' );
check( $saved_rule, kitmage_fluentcrm_tagger_product_rule( 789 ), 'Same category applies to another product' );
check( $saved_rule, kitmage_fluentcrm_tagger_product_rule( 124 ), 'Variation uses parent categories' );
check( null, kitmage_fluentcrm_tagger_product_rule( 791 ), 'Child category alone does not match parent slug' );
check( null, kitmage_fluentcrm_tagger_product_rule( 999 ), 'Nonexistent product has no rule' );
$GLOBALS['options']['kitmage_crm_product_rules'] = kitmage_fluentcrm_tagger_sanitize_product_rules( array( $rule, $other ) );
$GLOBALS['tags'] = array( 26 );
check( $saved_rule, kitmage_fluentcrm_tagger_product_rule( 790 ), 'First rule wins for products in multiple configured categories' );
check( null, kitmage_fluentcrm_tagger_restricted_product_rule( 790 ), 'Later category does not override access granted by first rule' );
$GLOBALS['options']['kitmage_crm_product_rules'] = array( $saved_rule );

$GLOBALS['tags'] = array( 26 );
reset_summary();
kitmage_fluentcrm_tagger_prepare_product_rule();
check( $cart, summary_output(), 'Required tag retains normal cart' );
check( $cart, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'Direct cart retained for allowed contact' );
check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Tagged contact retains Elementor AJAX button' );
check( $cart, elementor_cart_output( 'woocommerce-product-add-to-cart', $cart ), 'Tagged contact retains standard Elementor widget' );
check( 'disable', elementor_cache_setting(), 'Allowed Elementor output also bypasses element cache' );
check( 2, $GLOBALS['no_cache'], 'Allowed contact output also uncacheable' );
check( $cart, filtered_cart_block( 'woocommerce/add-to-cart-with-options', $cart ), 'Required tag retains purchase block' );
$GLOBALS['tags'] = array( 99 );
check( $saved_rule, kitmage_fluentcrm_tagger_restricted_product_rule( 123 ), 'Different tag does not grant access' );
$GLOBALS['tags'] = array( 26 );
$GLOBALS['logged_in'] = false;
check( $saved_rule, kitmage_fluentcrm_tagger_restricted_product_rule( 123 ), 'Guest gets message' );
check( $message, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'Direct cart replaced for guest' );
check( $message, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Guest receives message instead of Elementor AJAX button' );
$GLOBALS['logged_in'] = true;
foreach ( array( 'missing_contact', 'lookup_error', 'tags_error' ) as $failure ) {
	$GLOBALS[ $failure ] = true;
	check( $saved_rule, kitmage_fluentcrm_tagger_restricted_product_rule( 123 ), 'CRM failure gets message: ' . $failure );
	check( $message, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Elementor CRM failure gets message: ' . $failure );
	$GLOBALS[ $failure ] = false;
}

$GLOBALS['tags'] = array();
foreach ( array( 789, 124 ) as $matching_product ) {
	$GLOBALS['queried_id'] = $matching_product;
	$GLOBALS['product'] = new ProductRuleTestProduct( $matching_product );
	reset_summary(); kitmage_fluentcrm_tagger_prepare_product_rule();
	check( $message, summary_output(), 'Classic form replaced for another category product or variation' );
	check( $message, filtered_cart_block( 'woocommerce/add-to-cart-form', $cart, $matching_product ), 'Block replaced for another category product or variation' );
	check( $message, elementor_cart_output( 'woocommerce-product-add-to-cart', $cart ), 'Elementor widget replaced for another category product or variation' );
}
$GLOBALS['queried_id'] = 123;
$GLOBALS['product'] = new ProductRuleTestProduct( 123 );
$GLOBALS['admin'] = true;
reset_summary(); kitmage_fluentcrm_tagger_prepare_product_rule();
check( $cart, summary_output(), 'Admin request untouched' );
check( $cart, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'Direct admin cart untouched' );
check( $cart, filtered_cart_block( 'woocommerce/add-to-cart-form', $cart ), 'Admin blocks untouched' );
check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Admin Elementor widgets untouched' );
check( 'existing-override', elementor_cache_setting( 'existing-override' ), 'Admin Elementor cache untouched' );
$GLOBALS['admin'] = false;
$GLOBALS['product_page'] = false;
reset_summary(); kitmage_fluentcrm_tagger_prepare_product_rule();
check( $cart, summary_output(), 'Archives untouched' );
check( $cart, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'Direct archive cart untouched' );
check( $cart, filtered_cart_block( 'woocommerce/add-to-cart-form', $cart ), 'Archive blocks untouched' );
check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Archive Elementor widgets untouched' );
check( false, elementor_cache_setting(), 'Archive Elementor cache untouched' );
$GLOBALS['product_page'] = true;
$GLOBALS['queried_id'] = 456;
reset_summary(); kitmage_fluentcrm_tagger_prepare_product_rule();
check( $cart, summary_output(), 'Unlisted product cart untouched' );
check( $cart, filtered_cart_block( 'woocommerce/add-to-cart-form', $cart, 456 ), 'Unlisted product block untouched' );
check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Unlisted product Elementor widget untouched' );
check( 'existing-override', elementor_cache_setting( 'existing-override' ), 'Unlisted product Elementor cache untouched' );
$GLOBALS['queried_id'] = 123;
reset_summary(); kitmage_fluentcrm_tagger_prepare_product_rule();
$GLOBALS['product'] = new ProductRuleTestProduct( 456 );
check( '', summary_output(), 'Message not printed for a different global product' );
check( $cart, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'Direct related product cart untouched' );
check( $cart, elementor_cart_output( 'woocommerce-product-add-to-cart', $cart ), 'Elementor related product widget untouched' );
check( $message, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Custom Elementor widget uses its current-product setting instead of stale global product' );
$GLOBALS['product'] = null;
check( $cart, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'Direct cart without a product is harmless' );
check( $cart, elementor_cart_output( 'woocommerce-product-add-to-cart', $cart ), 'Elementor standard widget without product is harmless' );
$GLOBALS['product'] = new ProductRuleTestProduct( 123 );

$GLOBALS['can_manage'] = false;
ob_start(); kitmage_fluentcrm_tagger_product_rules_page(); $html = ob_get_clean();
check( '', $html, 'Unauthorized visitor cannot render settings' );
check( array(), $GLOBALS['settings_fields'], 'No settings fields for unauthorized visitor' );
$GLOBALS['can_manage'] = true;
$GLOBALS['options']['kitmage_crm_product_rules'][0]['message'] = '</textarea><script>unsafe()</script>';
ob_start(); kitmage_fluentcrm_tagger_product_rules_page(); $html = ob_get_clean();
check( array( 'kitmage_crm_products' ), $GLOBALS['settings_fields'], 'Settings API security fields included' );
check( true, false !== strpos( $html, 'kitmage_crm_product_rules[0][tag_id]' ), 'Tag field rendered' );
check( true, false !== strpos( $html, 'kitmage_crm_product_rules[0][category_slug]' ), 'Category slug field rendered' );
check( false, false !== strpos( $html, 'kitmage_crm_product_rules[0][product_id]' ), 'Category rules have no product ID field' );
check( true, false !== strpos( $html, 'kitmage_crm_product_rules[0][message]' ), 'HTML message field rendered' );
check( true, false !== strpos( $html, '&lt;/textarea&gt;&lt;script&gt;unsafe()&lt;/script&gt;' ), 'HTML escaped inside admin textarea' );
check( false, false !== strpos( $html, '</textarea><script>unsafe()</script>' ), 'No textarea breakout' );

$legacy = array( 'tag_id' => 26, 'product_id' => 123, 'message' => $rule['message'] );
$GLOBALS['options']['kitmage_crm_product_rules'] = array( $legacy );
check( $legacy, kitmage_fluentcrm_tagger_product_rule( 123 ), 'Legacy product rule remains active' );
check( $legacy, kitmage_fluentcrm_tagger_product_rule( 124 ), 'Legacy rule applies to parent of a variation' );
check( null, kitmage_fluentcrm_tagger_product_rule( 789 ), 'Legacy rule does not silently expand to category' );
check( array( $legacy ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $legacy ) ), 'Legacy row without category is preserved with an error' );
check( array( $legacy ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( array( 'tag_id' => '', 'category_slug' => '', 'message' => '', 'product_id' => 123 ) ) ), 'Hidden legacy ID prevents silent deletion of an incomplete row' );
ob_start(); kitmage_fluentcrm_tagger_product_rules_page(); $html = ob_get_clean();
check( true, false !== strpos( $html, 'Existing rule for product ID 123 remains active.' ), 'Legacy rule explains category replacement' );
check( true, false !== strpos( $html, 'kitmage_crm_product_rules[0][product_id]' ), 'Legacy ID carried in hidden field' );
$replacement = $rule; $replacement['product_id'] = '123';
check( array( $saved_rule ), kitmage_fluentcrm_tagger_sanitize_product_rules( array( $replacement ) ), 'Explicit category replaces legacy product ID' );
check( array(), kitmage_fluentcrm_tagger_sanitize_product_rules( array( array( 'tag_id' => '', 'category_slug' => '', 'message' => '', 'product_id' => '' ) ) ), 'Clear legacy rule intentionally' );

// Exercise the real plugin hook order with a tag mutation in the same request.
$GLOBALS['options']['kitmage_crm_product_rules'] = array( $saved_rule );
$GLOBALS['tags'] = array();
$_GET = array( 'fcrm_tag' => '26' );
reset_summary(); do_action( 'template_redirect' );
check( $cart, summary_output(), 'Adding required tag in this request keeps the cart form' );
check( $cart, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'Direct cart sees tag added in this request' );
check( $ajax_cart, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Elementor button sees tag added in this request' );
$_GET = array( 'fcrm_untag' => '26' );
reset_summary(); do_action( 'template_redirect' );
check( $message, summary_output(), 'Removing required tag in this request replaces the cart form' );
check( $message, direct_cart_output( 'single-product/add-to-cart/simple.php' ), 'Direct cart sees tag removed in this request' );
check( $message, elementor_cart_output( 'wc-add-to-cart', $ajax_cart ), 'Elementor button sees tag removed in this request' );
$_GET = array();
echo "Passed $count product-rule checks.\n";
