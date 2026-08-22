=== CartRenew for WooCommerce ===
Contributors: cartrenew
Tags: woocommerce, abandoned cart, whatsapp, cart recovery, cart abandonment
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.1.2
Requires PHP: 7.4
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned WooCommerce carts by sending automated WhatsApp reminders to customers who leave items behind.

== Description ==

CartRenew for WooCommerce helps store owners recover lost revenue by automatically sending WhatsApp reminders to customers who abandon their shopping carts.

**How it works:**

1. A customer adds items to their cart and enters their WhatsApp number with consent on the cart page.
2. If they leave without completing checkout, CartRenew detects the abandonment after a configurable wait time (default: 20 minutes).
3. A WhatsApp recovery message is automatically sent to the customer with a direct link back to their cart.

**Key Features:**

* **WhatsApp Recovery** — Automated messages sent via WhatsApp using the CartRenew platform.
* **Consent-First** — Phone number and opt-in captured on the cart page with explicit customer consent.
* **WooCommerce Native** — Works with both classic shortcode-based and Gutenberg block-based WooCommerce cart templates.
* **Easy Setup** — No coding required. Install plugin, enter your Store ID and API Key, done.
* **Configurable Wait Time** — Set how long to wait before sending a recovery message (5–180 minutes).
* **Automatic Recovery Detection** — If a customer completes their order, the recovery message is automatically cancelled.
* **Dashboard Analytics** — Monitor cart recoveries and performance from the CartRenew dashboard.

**Requirements:**

* A CartRenew account (free trial available at [cartrenew.com](https://www.cartrenew.com/en/woocommerce))
* WooCommerce 7.0 or higher
* WordPress 6.0 or higher
* PHP 7.4 or higher

**Getting Started:**

1. Install and activate this plugin.
2. Visit [cartrenew.com/en/woocommerce/connect](https://www.cartrenew.com/en/woocommerce/connect) to create your free account and get your Store ID and API Key.
3. In your WordPress admin, go to **WooCommerce → CartRenew** and enter your Store ID and API Key.
4. Save changes and start recovering abandoned carts!

== Installation ==

1. Upload the `cartrenew-woocommerce` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **WooCommerce → CartRenew** to configure your Store ID and API Key.
4. Get your credentials at [cartrenew.com/en/woocommerce/connect](https://www.cartrenew.com/en/woocommerce/connect).

== Frequently Asked Questions ==

= Do I need a CartRenew account? =

Yes. CartRenew handles WhatsApp message delivery. You can create a free account with a 14-day trial at [cartrenew.com](https://www.cartrenew.com/en/woocommerce).

= Does this work with block-based WooCommerce cart? =

Yes. CartRenew for WooCommerce is compatible with both the classic shortcode-based cart (`[woocommerce_cart]`) and the Gutenberg block-based cart (WooCommerce Blocks).

= Is customer consent required before sending WhatsApp messages? =

Yes. The plugin adds a phone number field and an explicit opt-in checkbox to the cart page. CartRenew only sends messages to customers who have provided their number and checked the consent box.

= What happens if the customer completes their order? =

CartRenew automatically detects the completed purchase and cancels any pending recovery message for that cart.

= How long does CartRenew wait before sending a recovery message? =

The default wait time is 20 minutes. You can configure this from **WooCommerce → CartRenew → Abandonment wait time**.

= Is my customers' data stored securely? =

Phone numbers and cart data are stored in your WordPress database (`wp_cartrenew_carts` table) and transmitted securely to the CartRenew platform over HTTPS for message delivery. No data is shared with third parties beyond what is required for WhatsApp message delivery.

= Which countries are supported? =

CartRenew supports WhatsApp delivery globally. Phone numbers entered without a country code are automatically prefixed based on your WooCommerce store settings.

== Screenshots ==

1. WhatsApp consent form on the WooCommerce cart page
2. CartRenew settings page in WooCommerce admin
3. CartRenew dashboard showing recovery analytics

== Changelog ==

= 0.1.2 =
* Fixed phone number format — automatically adds country code prefix for 10-digit numbers
* Improved compatibility with block-based WooCommerce cart themes (JS-based DOM insertion)

= 0.1.1 =
* Added WooCommerce Blocks (Gutenberg) cart compatibility
* Consent form now renders via wp_footer with JS relocation for block-based cart pages

= 0.1.0 =
* Initial release
* Cart tracking via WooCommerce hooks
* Consent form with phone number and opt-in checkbox on cart page
* WP-Cron sweep for abandoned cart detection
* WooCommerce admin settings page
* CartRenew API integration for WhatsApp message delivery

== Upgrade Notice ==

= 0.1.2 =
Phone number formatting fix — upgrade recommended for stores with international customers.
