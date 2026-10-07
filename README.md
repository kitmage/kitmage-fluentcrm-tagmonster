# KitMage FluentCRM Tagger

KitMage FluentCRM Tagger adds a small set of frontend tools for working with the current logged-in user's FluentCRM tags.

It can:

- add or remove FluentCRM tags from ordinary URLs;
- render buttons that add or remove a tag and then redirect;
- show or hide content based on FluentCRM tag conditions;
- redirect users based on FluentCRM tag conditions; and
- replace the Add to Cart form on WooCommerce products in a category with an HTML message when the visitor lacks a required tag.

Configure URL access rules in **CRM Redirects** in the WordPress admin menu. Tag actions and conditional content also use URL query parameters and WordPress shortcodes.

Configure product-page messages in **CRM Redirects → Product Tag Rules**. WooCommerce is required only for this feature.

## Requirements

- WordPress 5.2 or later
- PHP 7.0 or later
- FluentCRM
- A FluentCRM contact associated with the logged-in WordPress user

All tag values are numeric FluentCRM **tag IDs**, not tag names.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate **KitMage FluentCRM Tagger** in WordPress.
3. Confirm FluentCRM is active.
4. Confirm the WordPress users who will use these features have corresponding FluentCRM contacts.

## Feature reference

| Feature | Syntax |
| --- | --- |
| Add tag from URL | `?fcrm_tag=4` |
| Remove tag from URL | `?fcrm_untag=4` |
| Tag-action button | `[crm_tag_button ...]` |
| Conditional content | `[crm_restrict ...]...[/crm_restrict]` |
| Conditional redirect | `[crm_tag_redirect ...]` |

## Add or remove tags with URLs

Tag actions run for logged-in frontend visitors and affect only the current user's FluentCRM contact.

### Add a tag

```text
https://example.com/resources/?fcrm_tag=4
```

This adds FluentCRM tag `4`.

### Remove a tag

```text
https://example.com/preferences/?fcrm_untag=4
```

This removes FluentCRM tag `4`.

### Add and remove tags in one request

```text
https://example.com/welcome/?fcrm_tag=8&fcrm_untag=4
```

The add action runs first, followed by the remove action.

If both parameters contain the same tag ID, the tag will be removed at the end of the request.

### Existing query strings

If the destination already contains a query string, append the FluentCRM parameter with `&`:

```text
https://example.com/page/?source=email&fcrm_tag=4
```

### URL-action behavior

URL actions are silently ignored when:

- the visitor is logged out;
- the request is for a WordPress admin page;
- FluentCRM is unavailable;
- the current user has no FluentCRM contact; or
- the supplied tag ID is not a positive integer.

These URL parameters intentionally act as frontend actions on the **current logged-in user's own contact record**.

## Tag-action buttons

Use `[crm_tag_button]` to render a button that adds or removes a FluentCRM tag and then continues to another URL.

### Add a tag and continue

```text
[crm_tag_button text="Next Lesson" action="add" tag_id="12" url="/lesson-2/"]
```

### Remove a tag and continue

```text
[crm_tag_button text="Leave Program" action="remove" tag_id="12" url="/account/"]
```

### Add CSS classes

```text
[crm_tag_button text="Continue" action="add" tag_id="12" url="/next/" class="button button-primary"]
```

### Attributes

| Attribute | Required | Description |
| --- | --- | --- |
| `text` | No | Button label. Defaults to `Continue`. |
| `action` | Yes | `add` or `remove`. |
| `tag_id` | Yes | Numeric FluentCRM tag ID. |
| `url` | No | Destination. Defaults to `/`. |
| `class` | No | Space-separated CSS classes added to the button. |

### Internal destinations

For a site-relative destination:

```text
[crm_tag_button text="Continue" action="add" tag_id="12" url="/member-dashboard/"]
```

The plugin:

1. submits the button action;
2. updates the current contact's FluentCRM tag;
3. redirects the current tab to the destination.

### External destinations

For an external HTTP or HTTPS URL:

```text
[crm_tag_button text="Open Resource" action="add" tag_id="12" url="https://example.org/resource/"]
```

The external URL is opened in a new tab while the current tab processes the tag action and returns to the current page.

Opening the new tab is best-effort and remains subject to browser popup behavior.

### Button behavior

Tag-action buttons:

- render only for logged-in users;
- use a WordPress nonce for the tag-action request;
- prevent accidental double submission in JavaScript;
- display `Loading...` after submission; and
- fail silently if FluentCRM or the current contact is unavailable.

## Show or hide content by tag

Use `[crm_restrict]` to conditionally render enclosed content.

### Show content when a tag is present

```text
[crm_restrict tag_id="3"]
Download your member guide.
[/crm_restrict]
```

The default `mode` is `show`.

### Hide content when a tag is present

```text
[crm_restrict tag_id="3" mode="hide"]
This appears only when the user does not have tag 3.
[/crm_restrict]
```

### Fallback behavior

By default, restricted content is hidden when the plugin cannot evaluate the current contact.

You can change that with `fallback="show"`:

```text
[crm_restrict tag_id="12" fallback="show"]
Visible to tag 12 and when contact data cannot be checked.
[/crm_restrict]
```

The fallback value acts as the expression result **before** `mode` is applied.

For example:

```text
[crm_restrict tag_id="12" mode="hide" fallback="show"]
...
[/crm_restrict]
```

will hide the content when contact data is unavailable.

### Attributes

| Attribute | Default | Description |
| --- | --- | --- |
| `tag_id` | empty | Tag expression to evaluate. |
| `mode` | `show` | `show` renders matching content; `hide` renders nonmatching content. |
| `fallback` | `hide` | Expression result when the current contact cannot be evaluated. |

An empty `tag_id` does not restrict the content.

Nested shortcodes inside displayed content are processed normally.

## Tag expressions

Both `[crm_restrict]` and `[crm_tag_redirect]` support tag expressions.

| Operator | Meaning | Example |
| --- | --- | --- |
| `,` | OR | `3,4` |
| `+` | AND | `4+5` |
| `!` | NOT | `!6` |

Examples:

```text
3,4
```

Matches tag `3` **or** tag `4`.

```text
4+5
```

Matches contacts that have **both** tags `4` and `5`.

```text
4+!24
```

Matches contacts that have tag `4` and do **not** have tag `24`.

```text
3,4+5,!6
```

Matches any of these conditions:

- tag `3`;
- both tags `4` and `5`; or
- absence of tag `6`.

Each comma-separated group is an OR condition. Every `+`-separated term inside a group must match.

The `&` character is also accepted as AND internally, but `+` is recommended in shortcode attributes.

Parentheses and nested expressions are not supported.

## Redirect users by tag

Use `[crm_tag_redirect]` to redirect a logged-in user when their FluentCRM tags match an expression.

### Basic redirect

```text
[crm_tag_redirect tag_id="3" destination="/member-dashboard/"]
```

### Redirect using multiple conditions

```text
[crm_tag_redirect tag_id="4+5,!6" destination="/member-dashboard/"]
```

### Full URL

```text
[crm_tag_redirect tag_id="9" destination="https://members.example.com/start/"]
```

External destinations are subject to WordPress safe-redirect host rules.

### HTTP status

Redirects use HTTP `302` by default.

Use `status="301"` only when the redirect should be permanent:

```text
[crm_tag_redirect tag_id="9" destination="/new-home/" status="301"]
```

### Attributes

| Attribute | Default | Description |
| --- | --- | --- |
| `tag_id` | empty | Tag expression to evaluate. |
| `destination` | empty | Site-relative or HTTP/HTTPS destination. |
| `status` | `302` | `302` or `301`. |

The shortcode does nothing when:

- the visitor is logged out;
- the current FluentCRM contact is unavailable;
- the expression does not match;
- required attributes are missing; or
- the destination resolves to the current page.

When headers are still available, the plugin sends a normal HTTP redirect. If output has already started, it returns a JavaScript redirect with a no-JavaScript fallback.

## Common patterns

### Button that records progress

```text
[crm_tag_button text="Complete Lesson" action="add" tag_id="42" url="/lesson-2/"]
```

The user receives tag `42` and continues to the next lesson.

### Unlock content after an action

First add a tag:

```html
<a href="/course/?fcrm_tag=20">Unlock the course</a>
```

Then restrict the content:

```text
[crm_restrict tag_id="20"]
Welcome to the course.
[/crm_restrict]
```

### Show a completion message

```text
[crm_restrict tag_id="42"]
You completed this lesson.
[/crm_restrict]
```

### Require one tag and exclude another

```text
[crm_restrict tag_id="4+!24"]
This content requires tag 4 and excludes tag 24.
[/crm_restrict]
```

### Route different audiences

```text
[crm_tag_redirect tag_id="30" destination="/customers/"]
[crm_tag_redirect tag_id="31" destination="/partners/"]
```

The first matching redirect that executes will send the user to its destination.

## FluentCRM behavior

FluentCRM is the source of truth for this plugin.

The plugin does **not** maintain a secondary tag list in WordPress user meta and does **not** automatically create a FluentCRM contact when one is missing.

If the current WordPress user does not have an available FluentCRM contact, tag actions fail silently and conditional features use their documented fallback behavior.

## Aspen Smart Links compatibility

Version 1.1.0 incorporates the frontend Smart Links behavior directly into KitMage FluentCRM Tagger.

Existing Smart Links shortcode content using:

```text
[crm_tag_button ...]
```

can continue to use the same shortcode syntax.

For compatibility, the integrated button implementation also preserves:

- the `asl_action`, `asl_tag_id`, `asl_redirect`, and `_aspen_smart_links_nonce` request fields;
- the `AspenSmartLinks` JavaScript localization object;
- the `aspen_smart_links_handle_tag_action` filter; and
- the `aspen_smart_links_tag_action` action.

The standalone Aspen Smart Links user-meta fallback and automatic FluentCRM contact-creation behavior are not included.

After confirming your existing `[crm_tag_button]` usage works with KitMage FluentCRM Tagger, the standalone Aspen Smart Links plugin is no longer required for that functionality.

## Developer hooks

### `aspen_smart_links_handle_tag_action`

Allows another integration to take over a `[crm_tag_button]` tag action.

Return `null` to let KitMage FluentCRM Tagger handle the action normally. Return `true` or `false` to mark the action as externally handled.

Arguments:

```text
$handled
$user_id
$action
$tag_id
$context
```

### `aspen_smart_links_tag_action`

Runs after a `[crm_tag_button]` action has been processed.

Arguments:

```text
$user_id
$action
$tag_id
$result
$context
```

## Troubleshooting

**A URL does not add or remove a tag**

Confirm:

- the visitor is logged in;
- FluentCRM is active;
- the WordPress user has a corresponding FluentCRM contact; and
- the tag ID is a positive integer.

**A tag-action button does not appear**

`[crm_tag_button]` returns no output for logged-out visitors or when `action` / `tag_id` is invalid.

**The button tags the user but does not reach an external URL**

External destinations are opened with JavaScript and may be affected by browser popup restrictions.

**Restricted content never appears**

Check the contact's assigned FluentCRM tag IDs and verify the expression syntax. Tag names are not accepted.

**An AND expression does not work**

Use `+`:

```text
4+!24
```

rather than relying on `&` in shortcode content.

**A redirect does not run**

Confirm:

- `tag_id` and `destination` are present;
- the current contact matches the expression; and
- WordPress allows the destination host.

Place `[crm_tag_redirect]` as early as practical in the page content or template so an HTTP redirect can occur before output begins.

## License

KitMage FluentCRM Tagger is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Admin URL redirect rules (1.2.0)

Open **CRM Redirects** as an administrator. Each row contains a partial path (for example `/courses/premium`), comma-separated FluentCRM tag IDs (`12,34`), and a target (`/join/` or a full HTTP/HTTPS URL). Save Changes to apply the rules. Add rule creates more rows; clear every field in a row and save to remove it. Invalid submissions preserve the previous rules.

Paths use case-sensitive substring matching, including decoded slugs; query strings are ignored. Rules run in displayed order and the first matching rule decides access. A logged-in contact with **any** listed tag continues normally. Guests, missing contacts, unavailable FluentCRM, and tag lookup failures receive a temporary HTTP 302 redirect. Administrators visiting the frontend follow the same rules.

Rules run before the existing URL/button tag actions. All configured same-host destination paths are public exemptions (ignoring query strings and trailing slashes) to avoid redirect loops. Choose dedicated landing pages outside the restricted content. External HTTP/HTTPS targets explicitly saved by an administrator are supported.

Admin, AJAX, cron, and REST requests are excluded. These rules apply to WordPress frontend template requests, not direct media files or API access. Matching responses send no-cache headers, but **exclude matching paths from full-page and CDN caches** because caches can serve responses before WordPress executes. Existing tag-changing URLs and buttons still let users change their own tags on unrestricted pages; do not treat those tags as immutable purchase/authorization records.

### 1.2.1

Fixed rules ending in `/` failing to match the page itself. Matching now treats request paths with and without trailing slashes consistently, while retaining path-segment boundaries. Existing saved rules need no changes.

## Product category tag rules (1.4.0)

Administrators can open **CRM Redirects → Product Tag Rules** and add rules with three fields:

| Field | Example | Meaning |
| --- | --- | --- |
| FluentCRM Tag ID | `26` | The single numeric tag ID required to see the normal purchase form. |
| WooCommerce Product Category Slug | `membership` | An existing category slug from **Products → Categories**. Applies to every product assigned directly to that category. Variations use the parent product's categories. |
| Message (HTML) | `<p>Please <a href="/membership/">join our membership</a> to purchase.</p>` | Content displayed in place of the Add to Cart form. |

Use **Add rule** to create rows, then **Save Changes** to apply them. **Clear rule** empties a row; save to delete it. Each category slug can have one rule. If a product belongs to multiple configured categories, the first matching rule decides access and the message. Products assigned only to a child category do not match a parent category's slug; add a separate rule for the child category if needed. Existing categories without products can also have rules.

Invalid submissions preserve all previous rules and identify the row and reason (invalid tag ID, missing category, duplicate category, missing message, or inactive WooCommerce). Messages allow the same HTML as WordPress posts; scripts and unsafe attributes are removed. Shortcodes in messages are not executed.

On a matching product's single-product page, logged-in visitors whose FluentCRM contact has the tag see the normal WooCommerce form. Visitors without the tag, guests, missing contacts, unavailable FluentCRM, and CRM lookup failures see the configured message instead. Other products and admin screens remain unaffected. Tag changes from this plugin's URL/button actions are evaluated before the product rule.

**Upgrading from 1.3.0:** Existing product-ID rules continue to apply only to their original products until you explicitly replace or clear them. The settings page displays the old product ID with an empty category field. Before saving, enter a category slug for each old row you want to replace, or use **Clear rule** to delete it. Rules are not automatically expanded to categories. Version 1.4.0 also fixes valid first saves being rejected when WordPress runs the sanitizer twice: the validator now accepts its own normalized integer tag IDs.

The feature supports the standard WooCommerce single-product summary, themes and page builders that load WooCommerce's simple/variable/grouped/external purchase templates through `wc_get_template()`, Elementor's **Add To Cart** and **Custom Add To Cart** widgets, and the WooCommerce **Add to Cart Form** and **Add to Cart + Options** blocks. Elementor widgets targeting another product remain unchanged. Custom themes or plugins that render their own purchase controls outside these hooks and templates may need an integration. Rules replace the whole purchase form, including variable-product selectors, with the message.

This is a product-page display feature. It does not reject direct add-to-cart requests, change shop/archive buttons, or block cart/checkout purchases. Exclude product pages in the configured categories from full-page and CDN caches because those caches may serve a page before WordPress can evaluate the visitor's tags. The plugin also sends no-cache headers for matching products, including visitors who have the required tag.

### 1.4.1

Fixed saved product rules being bypassed by themes and page builders that render WooCommerce purchase templates directly instead of using the standard single-product summary hook. The saved HTML message now replaces those forms for visitors without the required tag. Forms for tagged contacts and unrelated products remain unaffected.

### 1.4.2

Added direct support for Elementor's **Add To Cart** and **Custom Add To Cart** widgets, including the custom widget's AJAX button that bypasses WooCommerce purchase templates. On matching product pages, the saved HTML message replaces the widget for visitors without the required tag. Tagged contacts keep the normal widget, and widgets for other products remain unchanged.

Elementor element caching is bypassed on matching product pages for both allowed and restricted visitors, without changing the saved Elementor setting. After upgrading, clear Elementor's generated files/data and any page/CDN caches before testing the frontend as a guest and as contacts with and without the required tag.

### Developer checks

Run the standalone behavioral suites without a database:

```sh
php tests/redirect-rules.php
php tests/redirect-rules.php --without-crm
php tests/product-rules.php
php tests/product-rules.php --without-crm
php tests/product-rules.php --without-woocommerce
```

These suites use WordPress, WooCommerce, and FluentCRM doubles and cover repeated Settings API sanitization, category matching, validation, and legacy product-ID rules. For integration testing on a real site, save a category rule with formatted HTML for the first time, reload and edit it, visit two products in that category as a guest and as contacts with/without the required tag, and confirm both classic and block product templates display the expected message or purchase form. Also verify a variable product using its parent's categories, a product in multiple configured categories, and an unrelated product.
