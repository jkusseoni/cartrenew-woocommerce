=== CartRenew for WooCommerce - Abandoned Cart Recovery ===
Contributors: cartrenew
Tags: woocommerce, abandoned cart, whatsapp, cart recovery, cart abandonment
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.1.5
Requires PHP: 7.4
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned WooCommerce carts with automatic WhatsApp reminders, sent only to shoppers who opted in.

== Description ==

Most shoppers who add items to a cart never complete the purchase. CartRenew brings them back with a WhatsApp reminder and a direct link to their cart, sent only to customers who gave their number and consent.

**How it works:**

1. A customer adds items to their cart and opts in to WhatsApp reminders on the cart page (phone number + consent) or at checkout (consent, using their billing phone number).
2. If they leave without completing checkout, CartRenew detects the abandonment after a configurable wait time (default: 20 minutes).
3. A WhatsApp recovery message is automatically sent to the customer with a direct link back to their cart.

**Key Features:**

* **WhatsApp Recovery** — Automated messages sent via WhatsApp using the CartRenew platform.
* **Consent-First** — Phone number and opt-in captured on the cart page or at checkout with explicit customer consent (checkbox is never pre-ticked).
* **WooCommerce Native** — Works with both classic shortcode-based and Gutenberg block-based WooCommerce cart templates.
* **Easy Setup** — No coding required. Install the plugin and click "Connect with one click" (or paste your Store ID and API Key).
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
2. You are taken to **WooCommerce → CartRenew** automatically. Enter your email and click **Connect with one click**: your Store ID and API Key are filled in for you.
3. Already have an account? Paste your Store ID and API Key manually (find them at [cartrenew.com/en/woocommerce/connect](https://www.cartrenew.com/en/woocommerce/connect)) and save.
4. Start recovering abandoned carts!

== External services ==

This plugin connects to CartRenew (https://www.cartrenew.com) to send abandoned-cart recovery messages via WhatsApp.

It sends the following data when a cart is abandoned: store ID, cart contents (items and value), the customer's first name (if provided) and the customer's phone number (only if they have opted in via the consent checkbox on the cart or checkout page).

When you click "Connect with one click" on the settings page, the plugin also sends the email address you enter, your site URL and your site name to CartRenew (https://www.cartrenew.com/api/woocommerce/register-store) once, to create your store and return your Store ID and API Key. Nothing is sent until you click that button.

When you deactivate the plugin, an optional feedback form asks why. If, and only if, you choose a reason and click "Submit & Deactivate", the plugin sends your selected reason, your optional comment, the plugin version and your WordPress and WooCommerce versions to CartRenew (https://www.cartrenew.com/api/woocommerce/feedback). "Skip & Deactivate" sends nothing.

CartRenew is a third-party service (account required). The plugin itself has no locked or time-limited features: it captures consent and sends cart data to the service, and the service decides how many WhatsApp messages your CartRenew plan includes. When your plan's message allowance is used up, the plugin shows a dismissible admin notice with an upgrade link.

This service is provided by CartRenew. [Terms of Service](https://www.cartrenew.com/terms) | [Privacy Policy](https://www.cartrenew.com/privacy)

== Installation ==

1. Upload the `cartrenew-woocommerce` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **WooCommerce → CartRenew** and click **Connect with one click**, or paste your Store ID and API Key.
4. Existing account? Find your credentials at [cartrenew.com/en/woocommerce/connect](https://www.cartrenew.com/en/woocommerce/connect).

== Frequently Asked Questions ==

= Do I need a CartRenew account? =

Yes. CartRenew handles WhatsApp message delivery. You can create a free account with a 14-day trial at [cartrenew.com](https://www.cartrenew.com/en/woocommerce).

= Does this work with block-based WooCommerce cart? =

Yes. CartRenew for WooCommerce is compatible with both the classic shortcode-based cart (`[woocommerce_cart]`) and the Gutenberg block-based cart (WooCommerce Blocks).

= Is customer consent required before sending WhatsApp messages? =

Yes. The plugin adds an explicit opt-in checkbox (never pre-ticked) to the cart page, with a phone number field, and to the checkout page, where it uses the billing phone number. CartRenew only sends messages to customers who have provided their number and checked the consent box.

= Does the WhatsApp link work if the customer opens it on another phone or browser? =

Yes. The link contains a private, random token. Opening it rebuilds the saved cart (products, variations and quantities that are still in stock) and takes the customer to checkout. If the customer already has items in their cart, those are left untouched. Links stop working after 7 days.

= What happens if the customer completes their order? =

CartRenew automatically detects the completed purchase and cancels any pending recovery message for that cart.

= How long does CartRenew wait before sending a recovery message? =

The default wait time is 20 minutes. You can configure this from **WooCommerce → CartRenew → Abandonment wait time**.

= Is my customers' data stored securely? =

Phone numbers and cart data are stored in your WordPress database (`wp_cartrenew_carts` table) and transmitted securely to the CartRenew platform over HTTPS for message delivery. No data is shared with third parties beyond what is required for WhatsApp message delivery.

= Which countries are supported? =

Numbers typed without a country code get the calling code of the billing country at checkout, or of your WooCommerce store's country on the cart page. Numbers typed with a "+" or "00" prefix are used as entered.

= Does this use the WhatsApp Business API? =

Yes, CartRenew uses the official WhatsApp Business API to deliver recovery messages in line with WhatsApp's messaging policies.

= Does this help with GDPR and other privacy rules? =

The plugin only collects a phone number after an explicit, unticked-by-default opt-in, and removes its data table when you delete the plugin. Whether your store meets GDPR or other local rules also depends on your own privacy policy and how you use the data, so please check with your legal adviser.

= What happens to my data if I delete the plugin? =

Deleting the plugin from the Plugins screen removes the `wp_cartrenew_carts` table and the plugin settings from your database.

== Screenshots ==

1. WhatsApp opt-in on the WooCommerce cart page: phone number and an unticked consent checkbox
2. CartRenew settings page in WooCommerce admin, with one-click connect
3. WhatsApp opt-in at checkout, using the billing phone number

== Changelog ==
= 0.1.5 =
* Fixed: abandoned carts are now detected after the configured wait time on every timezone (the cutoff used the wrong sign for the site's UTC offset).
* Fixed: phone numbers now get the calling code of the billing country (checkout) or your store's country (cart) instead of a fixed prefix.
* New: "Connect with one click" on the settings page creates your CartRenew store and fills in your Store ID and API Key.
* New: WhatsApp opt-in at checkout (classic and block checkout), using the billing phone number and first name.
* New: redirect to the settings page after activation and a notice until the store is connected.
* New: the link in the WhatsApp message now rebuilds the shopper's cart wherever it is opened (another browser or device) and sends them to checkout. Links expire after 7 days and never restore an order that was already placed.
* New: when your CartRenew free message limit is reached, sending pauses and a notice with an upgrade link is shown (carts are no longer marked as failed).
* Improved: abandoned carts older than 2 days are no longer messaged.
* New: optional feedback form when deactivating (nothing is sent unless you submit it).
* New: deleting the plugin now removes its database table and settings.
* Improved: carts are no longer marked as failed while the plugin is not connected yet.
* Improved: readme wording and FAQ formatting.

= 0.1.4 =
* Updated plugin name for guideline compliance
* Added External services disclosure

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

= 0.1.5 =
Important fix: recovery messages are now sent at the right time in every timezone. Also adds one-click setup and a WhatsApp opt-in at checkout.

= 0.1.2 =
Phone number formatting fix — upgrade recommended for stores with international customers.
