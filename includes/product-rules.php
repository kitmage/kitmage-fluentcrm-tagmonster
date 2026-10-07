<?php
/** Admin-managed product-page messages for visitors without a FluentCRM tag. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'kitmage-crm-redirects', __( 'Product Tag Rules', 'kitmage-fluentcrm-tagger' ), __( 'Product Tag Rules', 'kitmage-fluentcrm-tagger' ), 'manage_options', 'kitmage-crm-products', 'kitmage_fluentcrm_tagger_product_rules_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'kitmage_crm_products', 'kitmage_crm_product_rules', array(
		'type' => 'array',
		'default' => array(),
		'sanitize_callback' => 'kitmage_fluentcrm_tagger_sanitize_product_rules',
	) );
} );

/** Validate the entire submission so a bad row cannot silently remove saved rules. */
function kitmage_fluentcrm_tagger_sanitize_product_rules( $input ) {
	$rules = array();
	$categories = array();
	$invalid = ! is_array( $input );
	$row_number = 0;
	foreach ( is_array( $input ) ? $input : array() as $row ) {
		$row_number++;
		if ( ! is_array( $row ) ) {
			$invalid = true;
			continue;
		}
		$values = array();
		foreach ( array( 'tag_id', 'category_slug', 'message' ) as $key ) {
			// WordPress can sanitize twice when creating an option. Accept our own integer tag ID.
			$valid_type = isset( $row[ $key ] ) && ( is_string( $row[ $key ] ) || ( 'tag_id' === $key && is_int( $row[ $key ] ) ) );
			if ( isset( $row[ $key ] ) && ! $valid_type ) {
				$invalid = true;
			}
			$values[ $key ] = $valid_type ? trim( (string) $row[ $key ] ) : '';
		}
		if ( array( 'tag_id' => '', 'category_slug' => '', 'message' => '' ) === $values && empty( $row['product_id'] ) ) {
			continue;
		}
		$tag_id = filter_var( $values['tag_id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		$message = trim( wp_kses_post( $values['message'] ) );
		$woocommerce_ready = function_exists( 'wc_get_product' ) && taxonomy_exists( 'product_cat' );
		$category = $woocommerce_ready && '' !== $values['category_slug'] ? get_term_by( 'slug', $values['category_slug'], 'product_cat' ) : false;
		$reason = '';
		if ( ! $tag_id ) {
			$reason = __( 'Enter a positive FluentCRM tag ID.', 'kitmage-fluentcrm-tagger' );
		} elseif ( ! $woocommerce_ready ) {
			$reason = __( 'WooCommerce must be active to validate product categories.', 'kitmage-fluentcrm-tagger' );
		} elseif ( ! $category || is_wp_error( $category ) ) {
			$reason = __( 'Enter an existing WooCommerce product category slug from Products → Categories.', 'kitmage-fluentcrm-tagger' );
		} elseif ( '' === $message ) {
			$reason = __( 'Enter a message.', 'kitmage-fluentcrm-tagger' );
		} elseif ( isset( $categories[ $category->slug ] ) ) {
			$reason = __( 'Use only one rule per category slug.', 'kitmage-fluentcrm-tagger' );
		}
		if ( '' !== $reason ) {
			add_settings_error( 'kitmage_crm_product_rules', 'invalid_product_rule_row_' . $row_number, sprintf( __( 'Row %1$d: %2$s', 'kitmage-fluentcrm-tagger' ), $row_number, $reason ) );
			$invalid = true;
			continue;
		}
		$categories[ $category->slug ] = true;
		$rules[] = array( 'tag_id' => $tag_id, 'category_slug' => $category->slug, 'message' => $message );
	}
	if ( $invalid ) {
		add_settings_error( 'kitmage_crm_product_rules', 'invalid_product_rule', __( 'Rules were not saved. Each row needs a positive FluentCRM tag ID, an existing WooCommerce product category slug, and a message. Use only one rule per category. Previous rules have been kept.', 'kitmage-fluentcrm-tagger' ) );
		return get_option( 'kitmage_crm_product_rules', array() );
	}
	return $rules;
}

/** Settings API supplies the nonce and capability check when saving to options.php. */
function kitmage_fluentcrm_tagger_product_rules_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$rules = get_option( 'kitmage_crm_product_rules', array() );
	$rules[] = array( 'tag_id' => '', 'category_slug' => '', 'message' => '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Product Tag Rules', 'kitmage-fluentcrm-tagger' ); ?></h1>
		<?php settings_errors(); ?>
		<p><?php esc_html_e( 'On product pages in the listed categories, visitors without the required FluentCRM tag see your message in place of the Add to Cart form. Guests and unavailable CRM contacts also see the message. Visitors with the tag retain the normal form.', 'kitmage-fluentcrm-tagger' ); ?></p>
		<p><?php esc_html_e( 'Use numeric tag IDs and category slugs from Products → Categories, with one rule per category. A product must be assigned directly to that category; variations use the parent product’s categories. If multiple categories match, the first rule wins. Messages allow standard WordPress post HTML; scripts are removed. Clear all three fields and save to delete a rule.', 'kitmage-fluentcrm-tagger' ); ?></p>
		<p><?php esc_html_e( 'These rules change the product-page display only. Exclude products in the listed categories from full-page and CDN caches so each visitor sees the correct message.', 'kitmage-fluentcrm-tagger' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'kitmage_crm_products' ); ?>
			<table class="widefat">
				<thead><tr>
					<th><?php esc_html_e( 'FluentCRM Tag ID', 'kitmage-fluentcrm-tagger' ); ?></th>
					<th><?php esc_html_e( 'WooCommerce Product Category Slug', 'kitmage-fluentcrm-tagger' ); ?></th>
					<th><?php esc_html_e( 'Message (HTML)', 'kitmage-fluentcrm-tagger' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'kitmage-fluentcrm-tagger' ); ?></th>
				</tr></thead>
				<tbody id="kitmage-product-rows">
				<?php foreach ( $rules as $index => $rule ) : ?>
					<tr>
						<td><input type="number" min="1" step="1" class="widefat" aria-label="<?php esc_attr_e( 'FluentCRM Tag ID', 'kitmage-fluentcrm-tagger' ); ?>" name="kitmage_crm_product_rules[<?php echo esc_attr( $index ); ?>][tag_id]" value="<?php echo esc_attr( $rule['tag_id'] ); ?>"></td>
						<td>
							<input type="text" class="widefat" aria-label="<?php esc_attr_e( 'WooCommerce Product Category Slug', 'kitmage-fluentcrm-tagger' ); ?>" name="kitmage_crm_product_rules[<?php echo esc_attr( $index ); ?>][category_slug]" value="<?php echo esc_attr( isset( $rule['category_slug'] ) ? $rule['category_slug'] : '' ); ?>">
							<?php if ( ( ! isset( $rule['category_slug'] ) || '' === $rule['category_slug'] ) && ! empty( $rule['product_id'] ) ) : ?>
								<input type="hidden" name="kitmage_crm_product_rules[<?php echo esc_attr( $index ); ?>][product_id]" value="<?php echo esc_attr( $rule['product_id'] ); ?>">
								<p class="description"><?php echo esc_html( sprintf( __( 'Existing rule for product ID %d remains active. Enter a category slug to replace it before saving, or clear the rule to delete it.', 'kitmage-fluentcrm-tagger' ), $rule['product_id'] ) ); ?></p>
							<?php endif; ?>
						</td>
						<td><textarea rows="5" class="widefat" aria-label="<?php esc_attr_e( 'Message (HTML)', 'kitmage-fluentcrm-tagger' ); ?>" name="kitmage_crm_product_rules[<?php echo esc_attr( $index ); ?>][message]"><?php echo esc_textarea( $rule['message'] ); ?></textarea></td>
						<td><button type="button" class="button kitmage-remove-product-rule"><?php esc_html_e( 'Clear rule', 'kitmage-fluentcrm-tagger' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="kitmage-add-product-rule"><?php esc_html_e( 'Add rule', 'kitmage-fluentcrm-tagger' ); ?></button></p>
			<?php submit_button(); ?>
		</form>
	</div>
	<script>
	(function () {
		var body = document.getElementById('kitmage-product-rows');
		document.getElementById('kitmage-add-product-rule').addEventListener('click', function () {
			var row = body.lastElementChild.cloneNode(true);
			Array.prototype.forEach.call(row.querySelectorAll('input[type="hidden"], .description'), function (field) {
				field.parentNode.removeChild(field);
			});
			Array.prototype.forEach.call(row.querySelectorAll('input, textarea'), function (field) {
				field.name = field.name.replace(/\[\d+\]/, '[' + body.children.length + ']');
				field.value = '';
			});
			body.appendChild(row);
			row.querySelector('input').focus();
		});
		body.addEventListener('click', function (event) {
			if (!event.target.classList.contains('kitmage-remove-product-rule')) { return; }
			var row = event.target.closest('tr');
			Array.prototype.forEach.call(row.querySelectorAll('input, textarea'), function (field) {
				field.value = '';
			});
			Array.prototype.forEach.call(row.querySelectorAll('.description'), function (field) {
				field.parentNode.removeChild(field);
			});
		});
	})();
	</script>
	<?php
}

/** First matching category wins; retain old product-ID rules until explicitly replaced. */
function kitmage_fluentcrm_tagger_product_rule( $product_id ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
	if ( ! $product ) {
		return null;
	}
	if ( $product->is_type( 'variation' ) ) {
		$product_id = $product->get_parent_id();
	}
	foreach ( get_option( 'kitmage_crm_product_rules', array() ) as $rule ) {
		if ( isset( $rule['category_slug'] ) && '' !== $rule['category_slug'] ) {
			if ( has_term( array( (string) $rule['category_slug'] ), 'product_cat', $product_id ) ) {
				return $rule;
			}
		} elseif ( isset( $rule['product_id'] ) && (int) $rule['product_id'] === (int) $product_id ) {
			return $rule;
		}
	}
	return null;
}

/** Missing contacts, unavailable FluentCRM, and lookup failures use the message. */
function kitmage_fluentcrm_tagger_restricted_product_rule( $product_id ) {
	$rule = kitmage_fluentcrm_tagger_product_rule( $product_id );
	if ( null === $rule ) {
		return null;
	}
	try {
		$allowed = kitmage_fluentcrm_tagger_current_contact_matches( (string) $rule['tag_id'] );
	} catch ( Throwable $exception ) {
		$allowed = false;
	}
	return $allowed ? null : $rule;
}

/** Build safe HTML for either the classic template or WooCommerce purchase block. */
function kitmage_fluentcrm_tagger_product_message( array $rule ) {
	return '<div class="kitmage-crm-product-message">' . wp_kses_post( $rule['message'] ) . '</div>';
}

/** Replace WooCommerce's standard single-product form at its normal position. */
function kitmage_fluentcrm_tagger_prepare_product_rule() {
	if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product_id = get_queried_object_id();
	if ( null === kitmage_fluentcrm_tagger_product_rule( $product_id ) ) {
		return;
	}
	// Tag-dependent output must not be cached for another visitor, including allowed users.
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	if ( null !== kitmage_fluentcrm_tagger_restricted_product_rule( $product_id ) ) {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		add_action( 'woocommerce_single_product_summary', 'kitmage_fluentcrm_tagger_render_product_message', 30 );
	}
}
// Evaluate after the plugin's URL/button tag actions have updated the contact.
add_action( 'template_redirect', 'kitmage_fluentcrm_tagger_prepare_product_rule', 20 );

function kitmage_fluentcrm_tagger_render_product_message() {
	global $product;
	if ( ! $product || (int) $product->get_id() !== (int) get_queried_object_id() ) {
		return;
	}
	$rule = kitmage_fluentcrm_tagger_restricted_product_rule( $product->get_id() );
	if ( null !== $rule ) {
		// HTML is sanitized on save and again on output.
		echo kitmage_fluentcrm_tagger_product_message( $rule ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/** Also replace forms rendered directly by themes and page-builder widgets. */
function kitmage_fluentcrm_tagger_product_cart_template( $template, $template_name ) {
	global $product;
	if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $template;
	}
	$cart_templates = array(
		'single-product/add-to-cart/simple.php',
		'single-product/add-to-cart/variable.php',
		'single-product/add-to-cart/grouped.php',
		'single-product/add-to-cart/external.php',
	);
	if ( ! in_array( $template_name, $cart_templates, true ) || ! $product || (int) $product->get_id() !== (int) get_queried_object_id() ) {
		return $template;
	}
	if ( null === kitmage_fluentcrm_tagger_restricted_product_rule( $product->get_id() ) ) {
		return $template;
	}
	return __DIR__ . '/templates/product-tag-message.php';
}
add_filter( 'wc_get_template', 'kitmage_fluentcrm_tagger_product_cart_template', 10, 2 );

/** Elementor's custom cart button can render a link without a WooCommerce template. */
function kitmage_fluentcrm_tagger_elementor_product_cart( $content, $widget ) {
	global $product;
	if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $content;
	}
	$name = $widget->get_name();
	if ( 'wc-add-to-cart' === $name ) {
		// This widget can target a different product, even on a product page.
		$selected_id = $widget->get_settings_for_display( 'product_id' );
		$product_id = empty( $selected_id ) ? get_queried_object_id() : ( is_scalar( $selected_id ) ? (int) $selected_id : 0 );
	} elseif ( 'woocommerce-product-add-to-cart' === $name && $product ) {
		// Elementor sets the global product while rendering, including loop items.
		$product_id = $product->get_id();
	} else {
		return $content;
	}
	if ( (int) $product_id !== (int) get_queried_object_id() ) {
		return $content;
	}
	$rule = kitmage_fluentcrm_tagger_restricted_product_rule( $product_id );
	return null === $rule ? $content : kitmage_fluentcrm_tagger_product_message( $rule );
}
add_filter( 'elementor/widget/render_content', 'kitmage_fluentcrm_tagger_elementor_product_cart', 10, 2 );

/** Cached Elementor widget HTML would skip the visitor's tag check entirely. */
function kitmage_fluentcrm_tagger_elementor_product_cache( $pre_option ) {
	if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $pre_option;
	}
	return null === kitmage_fluentcrm_tagger_product_rule( get_queried_object_id() ) ? $pre_option : 'disable';
}
// Bypass Elementor element caching for this request only; preserve the saved setting.
add_filter( 'pre_option_elementor_element_cache_ttl', 'kitmage_fluentcrm_tagger_elementor_product_cache' );

/** Support single-product block templates without replacing related product controls. */
function kitmage_fluentcrm_tagger_product_cart_block( $content, $parsed_block, $block = null ) {
	if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $content;
	}
	$product_id = get_queried_object_id();
	if ( isset( $block->context['postId'] ) && (int) $block->context['postId'] !== (int) $product_id ) {
		return $content;
	}
	$rule = kitmage_fluentcrm_tagger_restricted_product_rule( $product_id );
	return null === $rule ? $content : kitmage_fluentcrm_tagger_product_message( $rule );
}
add_filter( 'render_block_woocommerce/add-to-cart-form', 'kitmage_fluentcrm_tagger_product_cart_block', 10, 3 );
add_filter( 'render_block_woocommerce/add-to-cart-with-options', 'kitmage_fluentcrm_tagger_product_cart_block', 10, 3 );
