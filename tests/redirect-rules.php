<?php
// Standalone behavioral tests: php tests/redirect-rules.php
// WordPress/FluentCRM doubles; no database required.
define( 'ABSPATH', __DIR__ );
$GLOBALS['logged_in'] = true;
$GLOBALS['tags'] = array();
$GLOBALS['saved'] = array();
$GLOBALS['errors'] = array();
$GLOBALS['hooks'] = array();
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][] = array( $hook, $callback, $priority ); }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_shortcode( $name, $callback ) {}
function __( $text, $domain ) { return $text; }
function home_url( $path = '' ) { return 'https://example.com' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function esc_url_raw( $url, $protocols ) {
	$scheme = parse_url( $url, PHP_URL_SCHEME );
	return $scheme && ! in_array( $scheme, $protocols, true ) ? '' : $url;
}
function get_option( $name, $default ) { return $GLOBALS['saved']; }
function add_settings_error( $name, $code, $message ) { $GLOBALS['errors'][] = $code; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
if ( ! in_array( '--without-crm', $argv, true ) ) {
function fluentcrm_get_current_contact() {
	if ( ! empty( $GLOBALS['lookup_error'] ) ) { throw new RuntimeException( 'CRM failure' ); }
	return ! empty( $GLOBALS['missing'] ) ? null : new TestContact();
}
}
class TestContact {
	function tags() { return $this; }
	function get() {
		if ( ! empty( $GLOBALS['tags_error'] ) ) { throw new RuntimeException( 'Tag failure' ); }
		return array_map( function ( $id ) { return (object) array( 'id' => $id ); }, $GLOBALS['tags'] );
	}
}
require dirname( __DIR__ ) . '/kitmage-fluentcrm-tagger.php';
if ( in_array( '--without-crm', $argv, true ) ) {
    $target = kitmage_fluentcrm_tagger_rule_destination( '/premium', array( array( 'slug' => '/premium', 'tags' => '12', 'target' => 'https://example.com/join/' ) ) );
    if ( 'https://example.com/join/' !== $target ) { exit( 1 ); }
    echo "Passed FluentCRM unavailable check.\n";
    exit( 0 );
}
$count = 0;
function check( $expected, $actual, $label ) {
	global $count;
	$count++;
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}
$rule = array( 'slug' => '/premium', 'tags' => '12,34', 'target' => '/join/' );
$rules = kitmage_fluentcrm_tagger_sanitize_rules( array( $rule ) );
check( 'https://example.com/join/', $rules[0]['target'], 'Relative target' );
check( '', kitmage_fluentcrm_tagger_rule_destination( '/other?next=/premium', $rules ), 'Ignore query' );
check( 'https://example.com/join/', kitmage_fluentcrm_tagger_rule_destination( '/premium/lesson', $rules ), 'Missing tag' );
$GLOBALS['tags'] = array( 34 );
check( '', kitmage_fluentcrm_tagger_rule_destination( '/premium/lesson', $rules ), 'Any tag allows' );
$GLOBALS['logged_in'] = false;
check( 'https://example.com/join/', kitmage_fluentcrm_tagger_rule_destination( '/premium/lesson', $rules ), 'Guest denied' );
$GLOBALS['logged_in'] = true;
$GLOBALS['missing'] = true;
check( 'https://example.com/join/', kitmage_fluentcrm_tagger_rule_destination( '/premium', $rules ), 'Missing contact' );
$GLOBALS['missing'] = false;
$GLOBALS['lookup_error'] = true;
check( 'https://example.com/join/', kitmage_fluentcrm_tagger_rule_destination( '/premium', $rules ), 'Contact exception' );
$GLOBALS['lookup_error'] = false;
$GLOBALS['tags_error'] = true;
check( 'https://example.com/join/', kitmage_fluentcrm_tagger_rule_destination( '/premium', $rules ), 'Tag exception' );
$GLOBALS['tags_error'] = false;
$GLOBALS['tags'] = array();
check( 'https://example.com/join/', kitmage_fluentcrm_tagger_rule_destination( '/%70remium', $rules ), 'Encoded path' );
check( '', kitmage_fluentcrm_tagger_rule_destination( '/Premium', $rules ), 'Case-sensitive' );
$overlap = array_merge( $rules, array( array( 'slug' => '/', 'tags' => '99', 'target' => 'https://example.com/fallback' ) ) );
check( '', kitmage_fluentcrm_tagger_rule_destination( '/join?campaign=x', $overlap ), 'Landing page exempt across rules' );
check( '', kitmage_fluentcrm_tagger_rule_destination( '/fallback/', $overlap ), 'Cycle target exempt' );
$GLOBALS['tags'] = array( 12 );
check( '', kitmage_fluentcrm_tagger_rule_destination( '/premium', $overlap ), 'First matching rule permits' );
$GLOBALS['tags'] = array();
check( 'https://example.com/join/', kitmage_fluentcrm_tagger_rule_destination( '/premium', $overlap ), 'First matching rule denies' );
$GLOBALS['saved'] = $rules;
foreach ( array( '0', '-1', '1+2', '1,no', '1,', '1.2' ) as $bad ) {
	$input = $rule; $input['tags'] = $bad;
	check( $rules, kitmage_fluentcrm_tagger_sanitize_rules( array( $input ) ), 'Reject invalid tags ' . $bad );
}
foreach ( array( 'javascript:alert(1)', '//evil.test', 'https://user:password@example.com/' ) as $bad ) {
	$input = $rule; $input['target'] = $bad;
	check( $rules, kitmage_fluentcrm_tagger_sanitize_rules( array( $input ) ), 'Reject invalid target' );
}
check( $rules, kitmage_fluentcrm_tagger_sanitize_rules( 'bad' ), 'Reject malformed submission' );
check( array(), kitmage_fluentcrm_tagger_sanitize_rules( array( array( 'slug' => '', 'tags' => '', 'target' => '' ) ) ), 'Delete final rule' );
$input = $rule; $input['tags'] = '12, 34,12'; $input['target'] = 'https://other.example/landing';
$clean = kitmage_fluentcrm_tagger_sanitize_rules( array( $input ) );
check( '12,34', $clean[0]['tags'], 'Normalize IDs' );
check( 'https://other.example/landing', kitmage_fluentcrm_tagger_rule_destination( '/premium', $clean ), 'External target' );
check( true, in_array( array( 'template_redirect', 'kitmage_fluentcrm_tagger_handle_rules', 0 ), $GLOBALS['hooks'], true ), 'Run before mutations' );
// Regression: saved slugs with trailing slashes must match the page itself.
$product_rules = kitmage_fluentcrm_tagger_sanitize_rules( array( array(
    'slug' => '/product/rbt-exam-preparation/', 'tags' => '26', 'target' => '/help',
) ) );
$GLOBALS['tags'] = array( 99 );
foreach ( array( '/product/rbt-exam-preparation/', '/product/rbt-exam-preparation', '/product/rbt-exam-preparation/?ref=email', '/product/rbt-exam-preparation/lesson/' ) as $request ) {
    check( 'https://example.com/help', kitmage_fluentcrm_tagger_rule_destination( $request, $product_rules ), 'Product rule without tag: ' . $request );
}
check( '', kitmage_fluentcrm_tagger_rule_destination( '/product/rbt-exam-preparation-extra/', $product_rules ), 'Trailing slash retains segment boundary' );
$GLOBALS['tags'] = array( 26 );
check( '', kitmage_fluentcrm_tagger_rule_destination( '/product/rbt-exam-preparation/', $product_rules ), 'Product tag allows access' );
$GLOBALS['logged_in'] = false;
check( 'https://example.com/help', kitmage_fluentcrm_tagger_rule_destination( '/product/rbt-exam-preparation/', $product_rules ), 'Product guest redirects' );
$GLOBALS['logged_in'] = true;
echo "Passed $count checks.\n";
