=== Headless Forms ===
Contributors: danish17
Tags: headless, forms, rest api, decoupled, jamstack, submissions
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful REST API form handler for headless WordPress architectures. Accept form submissions from any frontend.

== Description ==

Headless Forms is a production-ready WordPress plugin designed for headless/decoupled architectures. It provides a secure REST API endpoint to receive form submissions from static sites, SPAs, and JAMstack applications.

**Key Features:**

* Secure REST API with API key authentication
* 16 email provider integrations (SendGrid, AWS SES, Resend, and more)
* Webhook support for Zapier, Make, and custom endpoints
* Honeypot spam protection & rate limiting
* GDPR compliance tools (export, delete, retention)
* Beautiful admin dashboard with analytics

**Perfect for:**

* Next.js / React applications
* Astro static sites
* Vue.js SPAs
* Any headless WordPress setup

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/headless-forms/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Headless Forms > Settings to configure your API key
4. Create your first form and start receiving submissions!

== Frequently Asked Questions ==

= How do I authenticate API requests? =

Include your API key in the `X-HF-API-Key` header or as a Bearer token in the `Authorization` header.

= Which email providers are supported? =

We support 16 providers: WP Mail, SMTP, AWS SES, SendGrid, Resend, Mailgun, Postmark, SparkPost, Mandrill, Elastic Email, Brevo, MailerSend, Mailjet, SMTP2GO, Moosend, and Loops.

= Is it GDPR compliant? =

Yes! The plugin includes data export, deletion, and retention features to help you comply with GDPR.

== Screenshots ==

1. Dashboard with analytics and recent submissions
2. Forms list with submission counts
3. Submission detail view
4. Settings page with provider configuration

== Changelog ==

= 1.0.0 =
* Initial release
* 16 email provider integrations
* REST API with authentication
* Webhook support
* GDPR compliance tools
* Admin dashboard with analytics

== Upgrade Notice ==

= 1.0.0 =
Initial release of Headless Forms.
