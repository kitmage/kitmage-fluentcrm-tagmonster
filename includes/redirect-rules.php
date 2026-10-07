<?php
/** Admin-managed, tag-based URL redirect rules. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( __( 'CRM Redirect Rules', 'kitmage-fluentcrm-tagger' ), __( 'CRM Redirects', 'kitmage-fluentcrm-tagger' ), 'manage_options', 'kitmage-crm-redirects', 'kitmage_fluentcrm_tagger_rules_page', 'dashicons-randomize' );
} );

add_action( 'admin_init', function () {
	register_setting( 'kitmage_crm_redirects', 'kitmage_crm_redirect_rules', array(
		'type' => 'array',
		'default' => array(),
		'sanitize_callback' => 'kitmage_fluentcrm_tagger_sanitize_rules',
	) );
} );

/** Validate whole rows; retain existing rules if any submitted row is invalid. */
function kitmage_fluentcrm_tagger_sanitize_rules( $input ) {
	$rules = array();
	$invalid = ! is_array( $input );
	foreach ( is_array( $input ) ? $input : array() as $row ) {
		if ( ! is_array( $row ) ) {
			$invalid = true;
			continue;
		}
		$values = array();
		foreach ( array( 'slug', 'tags', 'target' ) as $key ) {
			$values[ $key ] = isset( $row[ $key ] ) && is_string( $row[ $key ] ) ? trim( $row[ $key ] ) : '';
		}
		if ( array( 'slug' => '', 'tags' => '', 'target' => '' ) === $values ) {
			continue;
		}
		$slug = sanitize_text_field( $values['slug'] );
		$tags = preg_replace( '/\s+/', '', $values['tags'] );
		$target = $values['target'];
		if ( 0 === strpos( $target, '/' ) && 0 !== strpos( $target, '//' ) ) {
			$target = home_url( $target );
		}
		$target = esc_url_raw( $target, array( 'http', 'https' ) );
		$parts = wp_parse_url( $target );
		if ( '' === $slug || strpbrk( $slug, '?#' ) !== false || ! preg_match( '/^[1-9][0-9]*(,[1-9][0-9]*)*$/D', $tags ) || ! $parts || empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			$invalid = true;
			continue;
		}
		$rules[] = array( 'slug' => $slug, 'tags' => implode( ',', array_unique( explode( ',', $tags ) ) ), 'target' => $target );
	}
	if ( $invalid ) {
		add_settings_error( 'kitmage_crm_redirect_rules', 'invalid_rule', __( 'Rules were not saved. Each rule needs a partial path (no ? or #), positive comma-separated tag IDs, and an HTTP/HTTPS URL or site-relative path.', 'kitmage-fluentcrm-tagger' ) );
		return get_option( 'kitmage_crm_redirect_rules', array() );
	}
	return $rules;
}

/** Render a capability-protected Settings API form (options.php verifies its nonce). */
function kitmage_fluentcrm_tagger_rules_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$rules = get_option( 'kitmage_crm_redirect_rules', array() );
	$rules[] = array( 'slug' => '', 'tags' => '', 'target' => '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CRM Redirect Rules', 'kitmage-fluentcrm-tagger' ); ?></h1>
		<?php settings_errors(); ?>
		<p><?php esc_html_e( 'Enter a partial URL path, tag IDs separated by commas, and a target URL. Visitors need any one listed tag. Guests and unavailable contacts are redirected. The first matching rule wins. Matching is case-sensitive and ignores query strings.', 'kitmage-fluentcrm-tagger' ); ?></p>
		<p><?php esc_html_e( 'All configured destination paths are exempt to prevent loops. Use separate public landing pages. Clear all three fields to delete a rule. Exclude matching paths from page/CDN caches.', 'kitmage-fluentcrm-tagger' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'kitmage_crm_redirects' ); ?>
			<table class="widefat"><thead><tr>
				<th><?php esc_html_e( 'Partial slug / path', 'kitmage-fluentcrm-tagger' ); ?></th>
				<th><?php esc_html_e( 'Tag IDs (any)', 'kitmage-fluentcrm-tagger' ); ?></th>
				<th><?php esc_html_e( 'Target URL', 'kitmage-fluentcrm-tagger' ); ?></th>
			</tr></thead><tbody id="kitmage-redirect-rows">
			<?php foreach ( $rules as $index => $rule ) : ?>
				<tr><?php foreach ( array( 'slug' => 'Partial slug / path', 'tags' => 'Tag IDs (any)', 'target' => 'Target URL' ) as $key => $label ) : ?>
					<td><input type="text" class="widefat" aria-label="<?php echo esc_attr( $label ); ?>" name="kitmage_crm_redirect_rules[<?php echo esc_attr( $index ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $rule[ $key ] ); ?>"></td>
				<?php endforeach; ?></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p><button type="button" class="button" id="kitmage-add-redirect"><?php esc_html_e( 'Add rule', 'kitmage-fluentcrm-tagger' ); ?></button></p>
			<?php submit_button(); ?>
		</form>
	</div>
	<script>
	document.getElementById('kitmage-add-redirect').addEventListener('click', function () {
		var body = document.getElementById('kitmage-redirect-rows');
		var row = body.lastElementChild.cloneNode(true);
		Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
			input.name = input.name.replace(/\[\d+\]/, '[' + body.children.length + ']');
			input.value = '';
		});
		body.appendChild(row);
		row.querySelector('input').focus();
	});
	</script>
	<?php
}

/** Canonical path comparison, including percent-encoded slugs and trailing slashes. */
function kitmage_fluentcrm_tagger_rule_path( $url ) {
	$path = wp_parse_url( $url, PHP_URL_PATH );
	return '/' . trim( rawurldecode( is_string( $path ) ? $path : '' ), '/' );
}

/** Match partial paths with a consistent terminal slash and intact segment boundaries. */
function kitmage_fluentcrm_tagger_rule_matches_path( $path, $slug ) {
	return '' !== $slug && false !== strpos( rtrim( $path, '/' ) . '/', rawurldecode( $slug ) );
}

/** Return the first rule's destination, or an empty string if access is allowed. */
function kitmage_fluentcrm_tagger_rule_destination( $request_uri, array $rules ) {
	$path = kitmage_fluentcrm_tagger_rule_path( $request_uri );
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	// Exempt landing paths globally, preventing self-redirects and multi-rule cycles.
	foreach ( $rules as $rule ) {
		if ( 0 === strcasecmp( (string) wp_parse_url( $rule['target'], PHP_URL_HOST ), (string) $site_host ) && $path === kitmage_fluentcrm_tagger_rule_path( $rule['target'] ) ) {
			return '';
		}
	}
	foreach ( $rules as $rule ) {
		if ( ! kitmage_fluentcrm_tagger_rule_matches_path( $path, $rule['slug'] ) ) {
			continue;
		}
		try {
			$allowed = kitmage_fluentcrm_tagger_current_contact_matches( $rule['tags'] );
		} catch ( Throwable $exception ) {
			$allowed = false;
		}
		return $allowed ? '' : $rule['target'];
	}
	return '';
}

/** Enforce rules before URL tag mutations and before template output. */
function kitmage_fluentcrm_tagger_handle_rules() {
	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	$rules = get_option( 'kitmage_crm_redirect_rules', array() );
	if ( empty( $rules ) ) {
		return;
	}
	$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path = kitmage_fluentcrm_tagger_rule_path( $request );
	foreach ( $rules as $rule ) {
		if ( kitmage_fluentcrm_tagger_rule_matches_path( $path, $rule['slug'] ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
			break;
		}
	}
	$destination = kitmage_fluentcrm_tagger_rule_destination( $request, $rules );
	if ( '' !== $destination && ! headers_sent() ) {
		// External targets are intentional, validated URLs saved only by administrators.
		if ( wp_redirect( $destination, 302, 'KitMage FluentCRM Tagger' ) ) {
			exit;
		}
	}
}
add_action( 'template_redirect', 'kitmage_fluentcrm_tagger_handle_rules', 0 );
