<?php
/**
 * Plugin Name:       KitMage FluentCRM Tagger
 * Description:       Adds/removes FluentCRM tags for logged-in contacts and provides tag-aware links and shortcodes.
 * Version:           1.4.1
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author: Mike@KitMage
 * Author URI: http://kitmage.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kitmage-fluentcrm-tagger
 *
 * @package KitMage_FluentCRM_Tagger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! defined( 'KITMAGE_FLUENTCRM_TAGGER_VERSION' ) ) {
	define( 'KITMAGE_FLUENTCRM_TAGGER_VERSION', '1.4.1' );
}

/**
 * Applies URL-requested FluentCRM tag actions to the current contact.
 *
 * Add a tag with `?fcrm_tag=4` and remove a tag with `?fcrm_untag=4`.
 * When both parameters are present, the add action runs before the remove action.
 *
 * @return void
 */
function kitmage_fluentcrm_tagger_handle_url_action() {
	// These actions are only intended for authenticated frontend visitors.
	if ( is_admin() || ! is_user_logged_in() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The query parameters intentionally provide a user-facing URL action.
	if ( empty( $_GET['fcrm_tag'] ) && empty( $_GET['fcrm_untag'] ) ) {
		return;
	}

	if ( ! function_exists( 'fluentcrm_get_current_contact' ) ) {
		return;
	}

	$contact = fluentcrm_get_current_contact();

	if ( ! $contact ) {
		return;
	}

	try {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See the URL-action explanation above.
		$tag_id = isset( $_GET['fcrm_tag'] ) && is_scalar( $_GET['fcrm_tag'] )
			? absint( wp_unslash( $_GET['fcrm_tag'] ) )
			: 0;

		if ( $tag_id > 0 ) {
			$contact->attachTags( array( $tag_id ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See the URL-action explanation above.
		$untag_id = isset( $_GET['fcrm_untag'] ) && is_scalar( $_GET['fcrm_untag'] )
			? absint( wp_unslash( $_GET['fcrm_untag'] ) )
			: 0;

		if ( $untag_id > 0 ) {
			$contact->detachTags( array( $untag_id ) );
		}
	} catch ( Throwable $exception ) {
		// FluentCRM failures must not interrupt the frontend request.
		return;
	}
}
add_action( 'template_redirect', 'kitmage_fluentcrm_tagger_handle_url_action', 1 );


/**
 * Registers the Smart Links frontend script.
 *
 * The script is enqueued only when the crm_tag_button shortcode renders.
 *
 * @return void
 */
function kitmage_fluentcrm_tagger_register_assets() {
	wp_register_script(
		'kitmage-fluentcrm-tagger-smart-links',
		plugin_dir_url( __FILE__ ) . 'assets/js/aspen-smart-links.js',
		array(),
		KITMAGE_FLUENTCRM_TAGGER_VERSION,
		true
	);

	// Preserve the original object name for compatibility with Aspen Smart Links behavior.
	wp_localize_script(
		'kitmage-fluentcrm-tagger-smart-links',
		'AspenSmartLinks',
		array(
			'loadingText' => __( 'Loading...', 'kitmage-fluentcrm-tagger' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'kitmage_fluentcrm_tagger_register_assets' );

/**
 * Applies one tag action for the current FluentCRM contact.
 *
 * @param string $action add|remove.
 * @param int    $tag_id FluentCRM tag ID.
 * @return bool
 */
function kitmage_fluentcrm_tagger_apply_button_tag_action( $action, $tag_id ) {
	$action = sanitize_key( (string) $action );
	$tag_id = absint( $tag_id );

	if ( ! is_user_logged_in() || ! in_array( $action, array( 'add', 'remove' ), true ) || $tag_id < 1 ) {
		return false;
	}

	if ( ! function_exists( 'fluentcrm_get_current_contact' ) ) {
		return false;
	}

	$contact = fluentcrm_get_current_contact();

	if ( ! $contact ) {
		return false;
	}

	try {
		if ( 'add' === $action ) {
			$contact->attachTags( array( $tag_id ) );
		} else {
			$contact->detachTags( array( $tag_id ) );
		}
	} catch ( Throwable $exception ) {
		return false;
	}

	return true;
}

/**
 * Returns the current frontend URL.
 *
 * @return string
 */
function kitmage_fluentcrm_tagger_get_current_url() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	return home_url( add_query_arg( array(), $request_uri ) );
}

/**
 * Sanitizes a whitespace-delimited CSS class list.
 *
 * @param string $class_list CSS classes.
 * @return string
 */
function kitmage_fluentcrm_tagger_sanitize_class_list( $class_list ) {
	$class_list = trim( (string) $class_list );

	if ( '' === $class_list ) {
		return '';
	}

	$classes = preg_split( '/\\s+/', $class_list );
	$classes = is_array( $classes ) ? $classes : array();
	$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

	return implode( ' ', $classes );
}

/**
 * Determines whether a URL points to a different host than the WordPress home URL.
 *
 * @param string $url URL to inspect.
 * @return bool
 */
function kitmage_fluentcrm_tagger_is_external_url( $url ) {
	$url_parts  = wp_parse_url( $url );
	$site_parts = wp_parse_url( home_url() );

	if ( empty( $url_parts ) || empty( $site_parts ) ) {
		return false;
	}

	$url_host  = isset( $url_parts['host'] ) ? strtolower( (string) $url_parts['host'] ) : '';
	$site_host = isset( $site_parts['host'] ) ? strtolower( (string) $site_parts['host'] ) : '';

	if ( '' === $url_host ) {
		return false;
	}

	if ( 0 === strpos( $url_host, 'www.' ) ) {
		$url_host = substr( $url_host, 4 );
	}

	if ( 0 === strpos( $site_host, 'www.' ) ) {
		$site_host = substr( $site_host, 4 );
	}

	return '' !== $site_host && $url_host !== $site_host;
}

/**
 * Normalizes a site-internal URL/path.
 *
 * @param string $url URL or path.
 * @return string
 */
function kitmage_fluentcrm_tagger_normalize_internal_url( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '/';
	}

	$parts = wp_parse_url( $url );

	if ( is_array( $parts ) && ( isset( $parts['scheme'] ) || isset( $parts['host'] ) ) ) {
		return $url;
	}

	if ( 0 === strpos( $url, '?' ) || 0 === strpos( $url, '#' ) ) {
		return home_url( '/' ) . $url;
	}

	if ( 0 !== strpos( $url, '/' ) ) {
		$url = '/' . $url;
	}

	return $url;
}

/**
 * Redirects to a validated safe location and exits.
 *
 * @param string $redirect Redirect target.
 * @return void
 */
function kitmage_fluentcrm_tagger_safe_redirect( $redirect = '' ) {
	$redirect = trim( (string) $redirect );

	if ( '' === $redirect ) {
		$redirect = wp_get_referer();
	}

	if ( '' === $redirect || false === $redirect ) {
		$redirect = home_url( '/' );
	}

	$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Renders a button that adds/removes a tag and then redirects.
 *
 * Shortcode syntax is compatible with Aspen Smart Links.
 *
 * @param array $attributes Shortcode attributes.
 * @return string
 */
function kitmage_fluentcrm_tagger_render_tag_button( $attributes ) {
	$attributes = shortcode_atts(
		array(
			'text'   => __( 'Continue', 'kitmage-fluentcrm-tagger' ),
			'action' => '',
			'tag_id' => '',
			'url'    => '/',
			'class'  => '',
		),
		$attributes,
		'crm_tag_button'
	);

	if ( ! is_user_logged_in() ) {
		return '';
	}

	$text   = sanitize_text_field( $attributes['text'] );
	$action = sanitize_key( $attributes['action'] );
	$tag_id = absint( $attributes['tag_id'] );
	$url    = trim( (string) $attributes['url'] );
	$class  = kitmage_fluentcrm_tagger_sanitize_class_list( $attributes['class'] );

	if ( ! in_array( $action, array( 'add', 'remove' ), true ) || $tag_id < 1 ) {
		return '';
	}

	$url = '' === $url ? '/' : esc_url_raw( $url, array( 'http', 'https' ) );
	$url = '' === $url ? '/' : $url;

	$is_external = kitmage_fluentcrm_tagger_is_external_url( $url );

	if ( ! $is_external ) {
		$url = kitmage_fluentcrm_tagger_normalize_internal_url( $url );
	}

	$form_id = function_exists( 'wp_unique_id' )
		? wp_unique_id( 'kitmage-fcrm-tag-button-' )
		: 'kitmage-fcrm-tag-button-' . wp_generate_password( 8, false, false );

	wp_enqueue_script( 'kitmage-fluentcrm-tagger-smart-links' );

	$current_url     = kitmage_fluentcrm_tagger_get_current_url();
	$redirect_target = $is_external ? $current_url : $url;
	$nonce_action    = 'aspen_smart_links|' . $action . '|' . $tag_id;
	$nonce_value     = wp_create_nonce( $nonce_action );

	ob_start();
	?>
	<form
		method="get"
		id="<?php echo esc_attr( $form_id ); ?>"
		action="<?php echo esc_url( $current_url ); ?>"
		style="display:inline;"
		data-aspen-smart-links="1"
		<?php if ( $is_external ) : ?>
			data-aspen-external-url="<?php echo esc_url( $url ); ?>"
		<?php endif; ?>
	>
		<input type="hidden" name="asl_action" value="<?php echo esc_attr( $action ); ?>">
		<input type="hidden" name="asl_tag_id" value="<?php echo esc_attr( $tag_id ); ?>">
		<input type="hidden" name="asl_redirect" value="<?php echo esc_attr( rawurlencode( $redirect_target ) ); ?>">
		<input type="hidden" name="_aspen_smart_links_nonce" value="<?php echo esc_attr( $nonce_value ); ?>">

		<button type="submit" <?php echo '' !== $class ? 'class="' . esc_attr( $class ) . '"' : ''; ?>>
			<?php echo esc_html( $text ); ?>
		</button>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'crm_tag_button', 'kitmage_fluentcrm_tagger_render_tag_button' );

/**
 * Handles nonce-protected tag button requests.
 *
 * Aspen Smart Links query names and nonce action are preserved for migration compatibility.
 *
 * @return void
 */
function kitmage_fluentcrm_tagger_handle_tag_button_action() {
	if ( empty( $_GET['asl_action'] ) || empty( $_GET['asl_tag_id'] ) || empty( $_GET['_aspen_smart_links_nonce'] ) ) {
		return;
	}

	$current_url = kitmage_fluentcrm_tagger_get_current_url();
	$clean_url   = remove_query_arg(
		array(
			'asl_action',
			'asl_tag_id',
			'asl_redirect',
			'_aspen_smart_links_nonce',
		),
		$current_url
	);

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( $clean_url ) );
		exit;
	}

	$action = sanitize_key( (string) wp_unslash( $_GET['asl_action'] ) );
	$tag_id = absint( wp_unslash( $_GET['asl_tag_id'] ) );
	$nonce  = sanitize_text_field( (string) wp_unslash( $_GET['_aspen_smart_links_nonce'] ) );

	if ( ! in_array( $action, array( 'add', 'remove' ), true ) || $tag_id < 1 ) {
		kitmage_fluentcrm_tagger_safe_redirect( $clean_url );
	}

	if ( ! wp_verify_nonce( $nonce, 'aspen_smart_links|' . $action . '|' . $tag_id ) ) {
		kitmage_fluentcrm_tagger_safe_redirect( $clean_url );
	}

	$redirect = isset( $_GET['asl_redirect'] ) && is_scalar( $_GET['asl_redirect'] )
		? rawurldecode( (string) wp_unslash( $_GET['asl_redirect'] ) )
		: '';

	$user_id = get_current_user_id();
	$context = array(
		'request_action' => 'template_redirect',
		'referer'        => wp_get_referer(),
	);

	$handled = apply_filters( 'aspen_smart_links_handle_tag_action', null, $user_id, $action, $tag_id, $context );
	$result  = null !== $handled
		? (bool) $handled
		: kitmage_fluentcrm_tagger_apply_button_tag_action( $action, $tag_id );

	do_action( 'aspen_smart_links_tag_action', $user_id, $action, $tag_id, $result, $context );

	kitmage_fluentcrm_tagger_safe_redirect( '' !== $redirect ? $redirect : $clean_url );
}
add_action( 'template_redirect', 'kitmage_fluentcrm_tagger_handle_tag_button_action', 1 );

/**
 * Renders shortcode content according to the current contact's FluentCRM tags.
 *
 * @param array       $attributes Shortcode attributes.
 * @param string|null $content    Enclosed shortcode content.
 * @return string
 */
function kitmage_fluentcrm_tagger_render_restricted_content( $attributes, $content = null ) {
	$attributes = shortcode_atts(
		array(
			'tag_id'   => '',
			'mode'     => 'show',
			'fallback' => 'hide',
		),
		$attributes,
		'crm_restrict'
	);

	$expression = trim( (string) $attributes['tag_id'] );
	$mode       = strtolower( trim( (string) $attributes['mode'] ) );
	$fallback   = strtolower( trim( (string) $attributes['fallback'] ) );

	if ( ! in_array( $mode, array( 'show', 'hide' ), true ) ) {
		$mode = 'show';
	}

	if ( ! in_array( $fallback, array( 'show', 'hide' ), true ) ) {
		$fallback = 'hide';
	}

	// An empty expression does not restrict the enclosed content.
	if ( '' === $expression ) {
		return do_shortcode( (string) $content );
	}

	$matches       = kitmage_fluentcrm_tagger_current_contact_matches( $expression, $fallback );
	$should_render = 'hide' === $mode ? ! $matches : $matches;

	return $should_render ? do_shortcode( (string) $content ) : '';
}
add_shortcode( 'crm_restrict', 'kitmage_fluentcrm_tagger_render_restricted_content' );

/**
 * Determines whether the current FluentCRM contact matches a tag expression.
 *
 * @param string $expression Tag expression to evaluate.
 * @param string $fallback   Whether an unavailable contact should match: show or hide.
 * @return bool
 */
function kitmage_fluentcrm_tagger_current_contact_matches( $expression, $fallback = 'hide' ) {
	$fallback_match = 'show' === $fallback;

	if ( ! is_user_logged_in() || ! function_exists( 'fluentcrm_get_current_contact' ) ) {
		return $fallback_match;
	}

	$contact = fluentcrm_get_current_contact();

	if ( ! $contact ) {
		return $fallback_match;
	}

	$tag_ids = array();

	try {
		// Explicitly load the relationship because FluentCRM does not always preload it.
		if ( method_exists( $contact, 'tags' ) ) {
			$tags = $contact->tags()->get();
		} elseif ( ! empty( $contact->tags ) ) {
			$tags = $contact->tags;
		} else {
			$tags = array();
		}

		if ( is_array( $tags ) || $tags instanceof Traversable ) {
			foreach ( $tags as $tag ) {
				if ( isset( $tag->id ) ) {
					$tag_ids[ (int) $tag->id ] = true;
				}
			}
		}
	} catch ( Throwable $exception ) {
		return $fallback_match;
	}

	return kitmage_fluentcrm_tagger_evaluate_expression( $expression, $tag_ids );
}

/**
 * Evaluates an OR/AND/NOT tag expression against a tag ID lookup table.
 *
 * Commas separate OR groups, plus signs combine AND terms, and an exclamation
 * mark negates a term. For example, `3,4+5,!6` matches tag 3, both tags 4 and
 * 5, or the absence of tag 6.
 *
 * @param string $expression Tag expression to evaluate.
 * @param array  $tag_ids    Tag IDs keyed by integer ID.
 * @return bool
 */
function kitmage_fluentcrm_tagger_evaluate_expression( $expression, array $tag_ids ) {
	$expression = html_entity_decode( (string) $expression, ENT_QUOTES, 'UTF-8' );
	$expression = preg_replace( '/\s+/', '', $expression );
	$expression = str_replace( '&', '+', $expression );

	if ( empty( $expression ) ) {
		return false;
	}

	$or_groups = array_filter( explode( ',', $expression ), 'strlen' );

	foreach ( $or_groups as $group ) {
		$and_terms = array_filter( explode( '+', $group ), 'strlen' );

		if ( empty( $and_terms ) ) {
			continue;
		}

		$group_matches = true;

		foreach ( $and_terms as $term ) {
			$negated = isset( $term[0] ) && '!' === $term[0];

			if ( $negated ) {
				$term = substr( $term, 1 );
			}

			if ( '' === $term || ! ctype_digit( $term ) ) {
				$group_matches = false;
				break;
			}

			$has_tag = isset( $tag_ids[ (int) $term ] );

			if ( $negated ) {
				$has_tag = ! $has_tag;
			}

			if ( ! $has_tag ) {
				$group_matches = false;
				break;
			}
		}

		if ( $group_matches ) {
			return true;
		}
	}

	return false;
}

/**
 * Redirects matching FluentCRM contacts to a configured destination.
 *
 * @param array $attributes Shortcode attributes.
 * @return string Redirect fallback markup, or an empty string.
 */
function kitmage_fluentcrm_tagger_render_redirect( $attributes ) {
	$attributes = shortcode_atts(
		array(
			'tag_id'      => '',
			'destination' => '',
			'status'      => '302',
		),
		$attributes,
		'crm_tag_redirect'
	);

	$expression  = trim( (string) $attributes['tag_id'] );
	$destination = trim( (string) $attributes['destination'] );
	$status      = 301 === (int) $attributes['status'] ? 301 : 302;

	if ( '' === $expression || '' === $destination || ! is_user_logged_in() ) {
		return '';
	}

	if ( ! kitmage_fluentcrm_tagger_current_contact_matches( $expression ) ) {
		return '';
	}

	// Site-relative paths are resolved against the WordPress home URL.
	if ( 0 === strpos( $destination, '/' ) && 0 !== strpos( $destination, '//' ) ) {
		$destination = home_url( $destination );
	}

	$destination = esc_url_raw( $destination, array( 'http', 'https' ) );

	if ( empty( $destination ) ) {
		return '';
	}

	$host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $host . $request_uri;
	$current_url = preg_replace( '#\?.*$#', '', $current_url );
	$target_url  = preg_replace( '#\?.*$#', '', $destination );

	if ( untrailingslashit( $current_url ) === untrailingslashit( $target_url ) ) {
		return '';
	}

	if ( ! headers_sent() ) {
		wp_safe_redirect( $destination, $status );
		exit;
	}

	$destination_json = wp_json_encode( $destination );

	if ( false === $destination_json ) {
		return '';
	}

	return sprintf(
		'<script>window.location.replace(%1$s);</script><noscript><meta http-equiv="refresh" content="%2$s"><p><a href="%3$s">%4$s</a></p></noscript>',
		$destination_json,
		esc_attr( '0;url=' . $destination ),
		esc_url( $destination ),
		esc_html__( 'Continue', 'kitmage-fluentcrm-tagger' )
	);
}
add_shortcode( 'crm_tag_redirect', 'kitmage_fluentcrm_tagger_render_redirect' );

require_once __DIR__ . '/includes/redirect-rules.php';
require_once __DIR__ . '/includes/product-rules.php';
