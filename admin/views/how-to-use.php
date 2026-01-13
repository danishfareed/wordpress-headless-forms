<?php
/**
 * Documentation / How To Use View.
 * Complete guide for all Headless Forms features.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$api_url = rest_url( 'headless-forms/v1/submit/your-form-slug' );
?>

<!-- Page Header -->
<div class="hf-page-header">
    <h2 class="hf-page-title">
        <span class="dashicons dashicons-book-alt"></span>
        <?php esc_html_e( 'Documentation', 'headless-forms' ); ?>
    </h2>
</div>

<!-- Table of Contents -->
<div class="hf-docs-toc">
    <h3><?php esc_html_e( 'Table of Contents', 'headless-forms' ); ?></h3>
    <ul>
        <li><a href="#getting-started"><?php esc_html_e( '1. Getting Started', 'headless-forms' ); ?></a></li>
        <li><a href="#create-form"><?php esc_html_e( '2. Creating a Form', 'headless-forms' ); ?></a></li>
        <li><a href="#api-usage"><?php esc_html_e( '3. API Usage & Headers', 'headless-forms' ); ?></a></li>
        <li><a href="#frontend-examples"><?php esc_html_e( '4. Frontend Code Examples', 'headless-forms' ); ?></a></li>
        <li><a href="#email-providers"><?php esc_html_e( '5. Email Provider Configuration', 'headless-forms' ); ?></a></li>
        <li><a href="#webhooks"><?php esc_html_e( '6. Webhooks & Integrations', 'headless-forms' ); ?></a></li>
        <li><a href="#google-sheets"><?php esc_html_e( '7. Google Sheets Integration', 'headless-forms' ); ?></a></li>
        <li><a href="#zapier"><?php esc_html_e( '8. Zapier Integration', 'headless-forms' ); ?></a></li>
        <li><a href="#slack"><?php esc_html_e( '9. Slack Integration', 'headless-forms' ); ?></a></li>
        <li><a href="#spam-protection"><?php esc_html_e( '10. Spam Protection', 'headless-forms' ); ?></a></li>
        <li><a href="#email-logs"><?php esc_html_e( '11. Email Logs & Bounce Handling', 'headless-forms' ); ?></a></li>
        <li><a href="#gdpr"><?php esc_html_e( '12. GDPR & Data Retention', 'headless-forms' ); ?></a></li>
    </ul>
</div>

<!-- Section 1: Getting Started -->
<div id="getting-started" class="hf-docs-section">
    <h2><span class="dashicons dashicons-flag"></span> <?php esc_html_e( '1. Getting Started', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Headless Forms is a production-ready form handler for headless WordPress sites. It provides a REST API endpoint to receive form submissions from any frontend (React, Vue, Next.js, Astro, mobile apps, etc.).', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Quick Setup (5 Minutes)', 'headless-forms' ); ?></h3>
    <ol>
        <li><strong><?php esc_html_e( 'Create a Form:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Go to Forms → Click "Create Form" → Enter a name (e.g., "Contact Form")', 'headless-forms' ); ?></li>
        <li><strong><?php esc_html_e( 'Configure Email:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Set the recipient email address in the form settings', 'headless-forms' ); ?></li>
        <li><strong><?php esc_html_e( 'Get Your API Key:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Go to Settings → Copy your API Key', 'headless-forms' ); ?></li>
        <li><strong><?php esc_html_e( 'Implement on Frontend:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Use the code examples below for your framework', 'headless-forms' ); ?></li>
    </ol>
</div>

<!-- Section 2: Creating a Form -->
<div id="create-form" class="hf-docs-section">
    <h2><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( '2. Creating a Form', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Forms in Headless Forms are "headless" - they define the backend behavior (email notifications, webhooks) without dictating the frontend UI.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Form Settings', 'headless-forms' ); ?></h3>
    <ul>
        <li><strong><?php esc_html_e( 'Form Name:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Internal identifier (e.g., "Contact Form", "Newsletter Signup")', 'headless-forms' ); ?></li>
        <li><strong><?php esc_html_e( 'Form Slug:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'URL-friendly identifier used in the API endpoint (auto-generated from name)', 'headless-forms' ); ?></li>
        <li><strong><?php esc_html_e( 'Notification Email:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Where to send submission notifications (comma-separated for multiple)', 'headless-forms' ); ?></li>
        <li><strong><?php esc_html_e( 'Success Message:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Returned in the API response after successful submission', 'headless-forms' ); ?></li>
        <li><strong><?php esc_html_e( 'Auto-Responder:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Send automatic confirmation email to the submitter', 'headless-forms' ); ?></li>
    </ul>
    
    <div class="hf-docs-tip">
        <strong><?php esc_html_e( 'Tip:', 'headless-forms' ); ?></strong>
        <?php esc_html_e( 'You can use dynamic placeholders like {{name}} or {{email}} in email subjects and auto-responder messages.', 'headless-forms' ); ?>
    </div>
</div>

<!-- Section 3: API Usage -->
<div id="api-usage" class="hf-docs-section">
    <h2><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( '3. API Usage & Headers', 'headless-forms' ); ?></h2>
    
    <h3><?php esc_html_e( 'Endpoint', 'headless-forms' ); ?></h3>
    <pre><code>POST <?php echo esc_url( $api_url ); ?></code></pre>
    
    <h3><?php esc_html_e( 'Required Headers', 'headless-forms' ); ?></h3>
    <pre><code>Content-Type: application/json
X-HF-API-Key: your-api-key-here</code></pre>

    <h3><?php esc_html_e( 'Request Body', 'headless-forms' ); ?></h3>
    <pre><code>{
  "name": "John Doe",
  "email": "john@example.com",
  "message": "Hello!",
  "_honey": ""
}</code></pre>

    <h3><?php esc_html_e( 'Success Response (200)', 'headless-forms' ); ?></h3>
    <pre><code>{
  "success": true,
  "message": "Thank you for contacting us!",
  "submission_id": 123
}</code></pre>

    <h3><?php esc_html_e( 'Error Response (4xx)', 'headless-forms' ); ?></h3>
    <pre><code>{
  "code": "invalid_api_key",
  "message": "Invalid API key",
  "data": { "status": 401 }
}</code></pre>

    <div class="hf-docs-warning">
        <strong><?php esc_html_e( 'Security:', 'headless-forms' ); ?></strong>
        <?php esc_html_e( 'Never expose your API key in client-side code for production. Use a backend proxy or serverless function to forward requests.', 'headless-forms' ); ?>
    </div>
</div>

<!-- Section 4: Frontend Examples -->
<div id="frontend-examples" class="hf-docs-section">
    <h2><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( '4. Frontend Code Examples', 'headless-forms' ); ?></h2>
    
    <h3><?php esc_html_e( 'React / Next.js', 'headless-forms' ); ?></h3>
    <pre><code>const handleSubmit = async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  
  const response = await fetch('<?php echo esc_url( $api_url ); ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-HF-API-Key': 'YOUR_API_KEY'
    },
    body: JSON.stringify({
      ...Object.fromEntries(formData),
      _honey: ''  // Honeypot must be empty
    })
  });
  
  const data = await response.json();
  if (data.success) {
    alert(data.message);
  }
};</code></pre>

    <h3><?php esc_html_e( 'Vue 3', 'headless-forms' ); ?></h3>
    <pre><code>const form = reactive({ name: '', email: '', message: '' });

const submit = async () => {
  const res = await fetch('<?php echo esc_url( $api_url ); ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-HF-API-Key': 'YOUR_API_KEY'
    },
    body: JSON.stringify({ ...form, _honey: '' })
  });
  const data = await res.json();
};</code></pre>

    <h3><?php esc_html_e( 'Next.js API Route (Recommended)', 'headless-forms' ); ?></h3>
    <pre><code>// app/api/contact/route.ts
export async function POST(req: Request) {
  const body = await req.json();
  
  const res = await fetch('<?php echo esc_url( $api_url ); ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-HF-API-Key': process.env.HF_API_KEY!
    },
    body: JSON.stringify(body)
  });
  
  return Response.json(await res.json());
}</code></pre>

    <h3><?php esc_html_e( 'Vanilla JavaScript', 'headless-forms' ); ?></h3>
    <pre><code>document.querySelector('form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  
  const res = await fetch('<?php echo esc_url( $api_url ); ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-HF-API-Key': 'YOUR_API_KEY'
    },
    body: JSON.stringify(Object.fromEntries(formData))
  });
  
  console.log(await res.json());
});</code></pre>
</div>

<!-- Section 5: Email Providers -->
<div id="email-providers" class="hf-docs-section">
    <h2><span class="dashicons dashicons-email"></span> <?php esc_html_e( '5. Email Provider Configuration', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Headless Forms supports 16+ email providers. Configure in Settings → Email Provider.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Supported Providers', 'headless-forms' ); ?></h3>
    <ul>
        <li><strong>WordPress Mail</strong> - <?php esc_html_e( 'Default, uses wp_mail()', 'headless-forms' ); ?></li>
        <li><strong>SMTP</strong> - <?php esc_html_e( 'Any SMTP server', 'headless-forms' ); ?></li>
        <li><strong>AWS SES</strong> - <?php esc_html_e( 'Amazon Simple Email Service', 'headless-forms' ); ?></li>
        <li><strong>SendGrid</strong> - <?php esc_html_e( 'Twilio SendGrid API', 'headless-forms' ); ?></li>
        <li><strong>Mailgun</strong> - <?php esc_html_e( 'Mailgun API', 'headless-forms' ); ?></li>
        <li><strong>Resend</strong> - <?php esc_html_e( 'Resend email API', 'headless-forms' ); ?></li>
        <li><strong>Postmark, SparkPost, Mandrill, Mailjet</strong> - <?php esc_html_e( 'And more!', 'headless-forms' ); ?></li>
    </ul>
    
    <div class="hf-docs-tip">
        <strong><?php esc_html_e( 'Tip:', 'headless-forms' ); ?></strong>
        <?php esc_html_e( 'Use the "Send Test Email" button in Settings to verify your configuration before going live.', 'headless-forms' ); ?>
    </div>
</div>

<!-- Section 6: Webhooks -->
<div id="webhooks" class="hf-docs-section">
    <h2><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( '6. Webhooks & Integrations', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Send form submissions to external services via webhooks. Add integrations from the Form Edit screen → Integrations tab.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Built-in Presets', 'headless-forms' ); ?></h3>
    <ul>
        <li><strong>Slack</strong> - <?php esc_html_e( 'Send notifications to a Slack channel', 'headless-forms' ); ?></li>
        <li><strong>Zapier</strong> - <?php esc_html_e( 'Connect to 5000+ apps via Zapier', 'headless-forms' ); ?></li>
        <li><strong>Google Sheets</strong> - <?php esc_html_e( 'Log submissions to a spreadsheet', 'headless-forms' ); ?></li>
        <li><strong>Custom Webhook</strong> - <?php esc_html_e( 'Any HTTP endpoint', 'headless-forms' ); ?></li>
    </ul>
    
    <h3><?php esc_html_e( 'Payload Template Placeholders', 'headless-forms' ); ?></h3>
    <ul>
        <li><code>{{form_name}}</code> - <?php esc_html_e( 'Name of the form', 'headless-forms' ); ?></li>
        <li><code>{{form_id}}</code> - <?php esc_html_e( 'Form ID', 'headless-forms' ); ?></li>
        <li><code>{{timestamp}}</code> - <?php esc_html_e( 'Submission timestamp', 'headless-forms' ); ?></li>
        <li><code>{{all_fields}}</code> - <?php esc_html_e( 'All submitted fields as text', 'headless-forms' ); ?></li>
        <li><code>{{field_name}}</code> - <?php esc_html_e( 'Specific field value (e.g., {{email}})', 'headless-forms' ); ?></li>
    </ul>
</div>

<!-- Section 7: Google Sheets -->
<div id="google-sheets" class="hf-docs-section">
    <h2><span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( '7. Google Sheets Integration', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Automatically log form submissions to a Google Sheet.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Step-by-Step Setup', 'headless-forms' ); ?></h3>
    <ol>
        <li><?php esc_html_e( 'Create a new Google Sheet', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Go to Extensions → Apps Script', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Paste this code:', 'headless-forms' ); ?></li>
    </ol>
    
    <pre><code>function doPost(e) {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var data = JSON.parse(e.postData.contents);
  
  // Add headers if sheet is empty
  if (sheet.getLastRow() === 0) {
    var headers = ["Timestamp", "Form ID"];
    for (var key in data.data) headers.push(key);
    sheet.appendRow(headers);
  }
  
  // Add row
  var row = [new Date(), data.form_id];
  for (var key in data.data) row.push(data.data[key]);
  sheet.appendRow(row);
  
  return ContentService.createTextOutput("Success");
}</code></pre>
    
    <ol start="4">
        <li><?php esc_html_e( 'Click Deploy → New deployment → Web app', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Set "Who has access" to "Anyone"', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Copy the Web App URL', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'In Headless Forms: Form → Integrations → Add Integration → Google Sheets → Paste URL', 'headless-forms' ); ?></li>
    </ol>
</div>

<!-- Section 8: Zapier -->
<div id="zapier" class="hf-docs-section">
    <h2><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( '8. Zapier Integration', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Connect Headless Forms to 5000+ apps via Zapier.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Setup', 'headless-forms' ); ?></h3>
    <ol>
        <li><?php esc_html_e( 'Go to zapier.com and create a new Zap', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Trigger: Choose "Webhooks by Zapier" → "Catch Hook"', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Copy the webhook URL provided by Zapier', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'In Headless Forms: Form → Integrations → Add Integration → Zapier → Paste URL', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Submit a test form entry', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'In Zapier: Test trigger, then add your desired action (send to CRM, email, etc.)', 'headless-forms' ); ?></li>
    </ol>
</div>

<!-- Section 9: Slack -->
<div id="slack" class="hf-docs-section">
    <h2><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( '9. Slack Integration', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Get instant Slack notifications for new form submissions.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Setup', 'headless-forms' ); ?></h3>
    <ol>
        <li><?php esc_html_e( 'Go to api.slack.com/apps and create a new app', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Enable "Incoming Webhooks" and create a new webhook for your channel', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Copy the webhook URL', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'In Headless Forms: Form → Integrations → Add Integration → Slack → Paste URL', 'headless-forms' ); ?></li>
    </ol>
    <p><?php esc_html_e( 'The payload is automatically formatted for Slack messages.', 'headless-forms' ); ?></p>
</div>

<!-- Section 10: Spam Protection -->
<div id="spam-protection" class="hf-docs-section">
    <h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e( '10. Spam Protection', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Headless Forms includes built-in spam protection mechanisms.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Honeypot Field', 'headless-forms' ); ?></h3>
    <p><?php esc_html_e( 'Include a hidden field named "_honey" in your form. If it contains any value, the submission is rejected as spam.', 'headless-forms' ); ?></p>
    <pre><code>&lt;input type="text" name="_honey" style="display:none" tabindex="-1" autocomplete="off" /&gt;</code></pre>
    
    <h3><?php esc_html_e( 'Rate Limiting', 'headless-forms' ); ?></h3>
    <p><?php esc_html_e( 'Configure in Settings. Default: 5 submissions per minute per IP address.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'API Key Authentication', 'headless-forms' ); ?></h3>
    <p><?php esc_html_e( 'All submissions require a valid API key, preventing unauthorized access.', 'headless-forms' ); ?></p>
</div>

<!-- Section 11: Email Logs -->
<div id="email-logs" class="hf-docs-section">
    <h2><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( '11. Email Logs & Bounce Handling', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Track all emails sent by the plugin and handle bounces automatically.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Email Status Types', 'headless-forms' ); ?></h3>
    <ul>
        <li><strong>Sent</strong> - <?php esc_html_e( 'Email was successfully sent to the provider', 'headless-forms' ); ?></li>
        <li><strong>Delivered</strong> - <?php esc_html_e( 'Email was delivered to the recipient', 'headless-forms' ); ?></li>
        <li><strong>Bounced</strong> - <?php esc_html_e( 'Email bounced (invalid address)', 'headless-forms' ); ?></li>
        <li><strong>Complaint</strong> - <?php esc_html_e( 'Recipient marked email as spam', 'headless-forms' ); ?></li>
        <li><strong>Failed</strong> - <?php esc_html_e( 'Email failed to send', 'headless-forms' ); ?></li>
    </ul>
    
    <h3><?php esc_html_e( 'AWS SES Bounce Webhook Setup', 'headless-forms' ); ?></h3>
    <ol>
        <li><?php esc_html_e( 'In AWS SES, create a Configuration Set', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Add an SNS Event Destination for bounces/complaints', 'headless-forms' ); ?></li>
        <li><?php esc_html_e( 'Create an SNS subscription with HTTPS protocol:', 'headless-forms' ); ?></li>
    </ol>
    <pre><code><?php echo esc_url( rest_url( 'headless-forms/v1/webhooks/incoming/aws-ses' ) ); ?></code></pre>
    <p><?php esc_html_e( 'The plugin will automatically confirm the subscription and process bounce notifications.', 'headless-forms' ); ?></p>
</div>

<!-- Section 12: GDPR -->
<div id="gdpr" class="hf-docs-section">
    <h2><span class="dashicons dashicons-privacy"></span> <?php esc_html_e( '12. GDPR & Data Retention', 'headless-forms' ); ?></h2>
    <p><?php esc_html_e( 'Headless Forms includes GDPR compliance features.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Data Retention', 'headless-forms' ); ?></h3>
    <p><?php esc_html_e( 'Configure automatic deletion of old submissions in Settings. Set to 0 to keep data forever.', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Data Export', 'headless-forms' ); ?></h3>
    <p><?php esc_html_e( 'Export submissions to CSV from the Submissions page (click Export while filtering by form).', 'headless-forms' ); ?></p>
    
    <h3><?php esc_html_e( 'Data Deletion', 'headless-forms' ); ?></h3>
    <p><?php esc_html_e( 'Submissions can be deleted individually or in bulk from the Submissions page.', 'headless-forms' ); ?></p>
    
    <div class="hf-docs-tip">
        <strong><?php esc_html_e( 'Tip:', 'headless-forms' ); ?></strong>
        <?php esc_html_e( 'When uninstalling the plugin, check "Keep data on delete" in Settings if you want to preserve submission data.', 'headless-forms' ); ?>
    </div>
</div>

<!-- Support Section -->
<div class="hf-card" style="margin-top: 30px; text-align: center; padding: 30px;">
    <h2><?php esc_html_e( 'Need Help?', 'headless-forms' ); ?></h2>
    <p style="color: #50575e; margin-bottom: 20px;"><?php esc_html_e( 'Check out our resources or reach out for support.', 'headless-forms' ); ?></p>
    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="https://github.com/danishfareed/wordpress-headless-forms" target="_blank" class="hf-button hf-button-secondary">
            <span class="dashicons dashicons-admin-site-alt3"></span>
            <?php esc_html_e( 'GitHub', 'headless-forms' ); ?>
        </a>
        <a href="https://wordpress.org/support/plugin/headless-forms/" target="_blank" class="hf-button hf-button-secondary">
            <span class="dashicons dashicons-sos"></span>
            <?php esc_html_e( 'Support Forum', 'headless-forms' ); ?>
        </a>
        <a href="https://codewithdanish.dev" target="_blank" class="hf-button hf-button-primary">
            <span class="dashicons dashicons-admin-users"></span>
            <?php esc_html_e( 'Contact Author', 'headless-forms' ); ?>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scroll for TOC links
    document.querySelectorAll('.hf-docs-toc a').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});
</script>
