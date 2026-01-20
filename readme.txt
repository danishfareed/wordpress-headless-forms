=== Headless Forms ===
Contributors: danish17
Donate link: https://codewithdanish.dev
Tags: headless, forms, rest api, decoupled, jamstack, api forms, contact form
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful REST API form handler for headless WordPress. Accept form submissions from React, Next.js, Vue, Astro, or any frontend.

== Description ==

**Headless Forms** is a production-ready WordPress plugin designed specifically for headless and decoupled architectures. It provides a secure REST API endpoint to receive form submissions from static sites, SPAs, mobile apps, and JAMstack applications.

Unlike traditional form plugins that require WordPress themes, Headless Forms is built from the ground up for modern web development workflows where WordPress serves purely as a backend CMS.

= Key Features =

* **Secure REST API** – API key authentication with rate limiting protection
* **16+ Email Providers** – SendGrid, AWS SES, Resend, Mailgun, Postmark, and more
* **Webhook Integrations** – Zapier, Google Sheets, Slack, Make, and custom endpoints
* **Spam Protection** – Honeypot fields, rate limiting, and IP blocking
* **GDPR Compliance** – Data export, deletion, and automatic retention policies
* **Modern Admin UI** – Beautiful dashboard with analytics and submission management
* **Auto-Responders** – Send confirmation emails to form submitters
* **Email Logs** – Track delivery status with bounce handling
* **File Uploads** – Support for attachments (images, PDFs, documents) up to 10MB

= Perfect For =

* Next.js and React applications
* Vue.js and Nuxt.js SPAs
* Astro and Gatsby static sites
* Flutter and React Native mobile apps
* Any frontend using WordPress as a headless CMS

= Supported Email Providers =

WordPress Mail (default), SMTP, AWS SES, SendGrid, Resend, Mailgun, Postmark, SparkPost, Mandrill, Elastic Email, Brevo (Sendinblue), MailerSend, Mailjet, SMTP2GO, Moosend, and Loops.

= Third-Party Integrations =

Connect your forms to 5000+ apps via webhooks:

* **Zapier** – Automate workflows to any app
* **Google Sheets** – Log submissions to spreadsheets
* **Slack** – Get instant notifications
* **Custom Webhooks** – Send data to any HTTP endpoint

= Documentation =

Comprehensive documentation with code examples for React, Vue, Next.js, Astro, Svelte, and vanilla JavaScript is available right inside the plugin's "Documentation" tab.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/headless-forms/` directory, or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Headless Forms in your admin menu.
4. Go to Settings and copy your API Key.
5. Configure your email provider (optional).
6. Create your first form and start receiving submissions!

= Quick Start =

After activation, use this endpoint to submit forms:

`POST /wp-json/headless-forms/v1/submit/{form-slug}`

Include your API key in the `X-HF-API-Key` header:

`
fetch('https://yoursite.com/wp-json/headless-forms/v1/submit/contact', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-HF-API-Key': 'your-api-key'
  },
  body: JSON.stringify({
    name: 'John Doe',
    email: 'john@example.com',
    message: 'Hello!',
    _honey: '' // Honeypot field (must be empty)
  })
});
`

== Frequently Asked Questions ==

= How do I authenticate API requests? =

Include your API key in the `X-HF-API-Key` header. For production, we recommend proxying requests through your backend to keep the API key secure.

= Which email providers are supported? =

We support 16+ providers: WP Mail (default), SMTP, AWS SES, SendGrid, Resend, Mailgun, Postmark, SparkPost, Mandrill, Elastic Email, Brevo, MailerSend, Mailjet, SMTP2GO, Moosend, and Loops.

= Is it GDPR compliant? =

Yes! The plugin includes data export, deletion, and automatic retention features to help you comply with GDPR and other privacy regulations.

= Can I use this with Next.js / React / Vue? =

Absolutely! Headless Forms is designed specifically for frontend frameworks. The plugin includes code examples for React, Next.js, Vue 3, Nuxt, Astro, Svelte, and vanilla JavaScript.

= How does spam protection work? =

We use a honeypot field approach (a hidden field that bots fill out but humans don't) plus rate limiting per IP address. No annoying CAPTCHAs required.

= Can I send submissions to third-party services? =

Yes! You can set up webhooks to send submissions to Zapier, Google Sheets, Slack, or any custom HTTP endpoint.

= Does it support file uploads? =

Yes! As of version 1.1.0, Headless Forms supports file uploads. You can enable file uploads per form, set limits on the number of files, and files are automatically sent as email attachments. Security measures include MIME type validation, UUID renaming, and protected storage.

= Can I customize the email templates? =

The email content is generated from the form fields. Custom HTML email templates are planned for a future update.

== Screenshots ==

1. Dashboard with analytics showing submission trends, stats, and recent activity
2. Forms list with submission counts and quick actions
3. Form configuration with email settings and integrations
4. Submission detail view with all form data
5. Settings page with API key and email provider configuration
6. Comprehensive documentation with code examples

== Changelog ==

= 1.0.0 =
* Initial release
* 16+ email provider integrations (AWS SES, SendGrid, Resend, Mailgun, etc.)
* REST API with API key authentication
* Webhook support for Zapier, Google Sheets, Slack
* Honeypot spam protection and rate limiting
* GDPR compliance tools (export, delete, retention)
* Email logs with bounce and delivery tracking
* Modern admin dashboard with analytics
* Auto-responder emails
* Form-specific settings and notifications

= 1.1.0 =
* Added support for File Uploads in REST API submissions
* Implementation of secure file storage with .htaccess protection
* Added file attachment support for all major email providers (SES, SendGrid, Mailgun, Resend, WP Mail)
* Added file management in Admin Dashboard (view and download)
* Added 'Uploaded Files' column to submission CSV export
* Added cleanup logic for uploaded files on plugin uninstallation

== Upgrade Notice ==

= 1.0.0 =
Initial release of Headless Forms. A powerful form handler for headless WordPress sites.

== Privacy Policy ==

Headless Forms stores form submission data in your WordPress database. This data may include personal information submitted by your website visitors.

**Data Collected:**
* Form field values (as submitted by users)
* IP address (for spam protection)
* User agent (browser information)
* Timestamp

**Data Retention:**
You can configure automatic data retention in the plugin settings. Data can be automatically deleted after a specified number of days.

**Third-Party Services:**
If you configure webhooks, form data will be sent to the third-party services you specify. Please review the privacy policies of those services.

**GDPR Compliance:**
The plugin includes tools to export and delete user data to help you comply with GDPR and similar regulations.
