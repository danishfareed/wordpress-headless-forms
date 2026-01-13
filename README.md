# Headless Forms for WordPress

[![GPLv2 License](https://img.shields.io/badge/license-GPLv2-purple.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D%205.8-blue.svg)](https://wordpress.org/download/)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-orange.svg)](https://www.php.net/downloads)

**Headless Forms** is a powerful REST API form handler designed specifically for headless WordPress architectures. Accept form submissions from React, Next.js, Vue, Astro, or any modern frontend with ease and security.

---

## ✨ Key Features

- 🚀 **Secure REST API** – API key authentication with rate limiting.
- 📧 **16+ Email Providers** – Native support for AWS SES, SendGrid, Resend, Mailgun, Postmark, and more.
- 🔌 **Webhook Integrations** – Connect to Zapier, Google Sheets, Slack, and Make.
- 🛡️ **Spam Protection** – Built-in honeypot fields and IP-based rate limiting.
- 🔒 **GDPR Ready** – Tools for data export, deletion, and automatic retention policies.
- 📊 **Modern Admin Dashboard** – Futuristic UI (Purple/Orange themed) with submission analytics.
- 📨 **Auto-Responders** – Send customized confirmation emails to your users.
- 📜 **Email Logs** – Comprehensive tracking of all outgoing emails with unique Message IDs.

---

## 🚀 Quick Start

### 1. Installation
1. Clone this repository into your `wp-content/plugins/` directory.
2. Activate the plugin in your WordPress Admin.
3. Go to **Headless Forms > Settings** to get your API Key.

### 2. Frontend Integration
Submit your forms to: `POST /wp-json/headless-forms/v1/submit/{form-slug}`

```javascript
fetch('https://yoursite.com/wp-json/headless-forms/v1/submit/contact-us', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-HF-API-Key': 'your-api-key'
  },
  body: JSON.stringify({
    name: 'John Doe',
    email: 'john@example.com',
    message: 'Hello from the headless frontend!',
    _honey: '' // Ensure this hidden honeypot field is empty
  })
});
```

---

## 🛠 Supported Email Providers

Headless Forms supports a wide range of transactional email services out of the box:
- **AWS SES** (with Bounce/Delivery tracking)
- **SendGrid**, **Resend**, **Mailgun**, **Postmark**
- **Brevo**, **Mailjet**, **Postmark**, **SparkPost**
- **SMTP**, **WP Mail** (Internal)

---

## 🎨 Modern Dashboard

The plugin features a futuristic, premium UI inspired by modern performance plugins, providing:
- Real-time submission analytics.
- Integration management.
- Detailed email delivery logs.
- Easy-to-use form configuration.

---

## 📄 License

This project is licensed under the [GNU General Public License v2 or later](LICENSE).

---

## 👨‍💻 Created By
[Danish Mohammed](https://codewithdanish.dev) - Professional WordPress & Headless Developer.