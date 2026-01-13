<?php
/**
 * Dashboard View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Page Header -->
<div class="hf-page-header">
    <h2 class="hf-page-title">
        <span class="dashicons dashicons-chart-area"></span>
        <?php esc_html_e( 'Dashboard', 'headless-forms' ); ?>
    </h2>
    <div class="hf-page-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=new-form' ) ); ?>" class="hf-button hf-button-primary">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Create Form', 'headless-forms' ); ?>
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="hf-stats-grid">
    <div class="hf-stat-card">
        <div class="hf-stat-icon">
            <span class="dashicons dashicons-clipboard"></span>
        </div>
        <div class="hf-stat-content">
            <div class="hf-stat-number"><?php echo esc_html( $stats['total_forms'] ); ?></div>
            <div class="hf-stat-label"><?php esc_html_e( 'Total Forms', 'headless-forms' ); ?></div>
        </div>
    </div>

    <div class="hf-stat-card">
        <div class="hf-stat-icon">
            <span class="dashicons dashicons-email-alt"></span>
        </div>
        <div class="hf-stat-content">
            <div class="hf-stat-number"><?php echo esc_html( $stats['total_submissions'] ); ?></div>
            <div class="hf-stat-label"><?php esc_html_e( 'Total Submissions', 'headless-forms' ); ?></div>
        </div>
    </div>

    <div class="hf-stat-card">
        <div class="hf-stat-icon" style="background: #fef3c7;">
            <span class="dashicons dashicons-bell" style="color: #d97706;"></span>
        </div>
        <div class="hf-stat-content">
            <div class="hf-stat-number"><?php echo esc_html( $stats['unread_submissions'] ); ?></div>
            <div class="hf-stat-label"><?php esc_html_e( 'Pending Review', 'headless-forms' ); ?></div>
        </div>
    </div>

    <div class="hf-stat-card">
        <div class="hf-stat-icon" style="background: #d1fae5;">
            <span class="dashicons dashicons-calendar-alt" style="color: #059669;"></span>
        </div>
        <div class="hf-stat-content">
            <div class="hf-stat-number"><?php echo esc_html( $stats['today_submissions'] ); ?></div>
            <div class="hf-stat-label"><?php esc_html_e( 'Today', 'headless-forms' ); ?></div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <!-- Chart -->
    <div class="hf-card">
        <h2><?php esc_html_e( 'Submissions (Last 30 Days)', 'headless-forms' ); ?></h2>
        <div style="height: 300px;">
            <canvas id="hf-submissions-chart"></canvas>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Chart === 'undefined') return;
                
                const ctx = document.getElementById('hf-submissions-chart').getContext('2d');
                let gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(34, 113, 177, 0.2)');
                gradient.addColorStop(1, 'rgba(34, 113, 177, 0)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo wp_json_encode( $chart_data['labels'] ); ?>,
                        datasets: [{
                            label: '<?php esc_html_e( 'Submissions', 'headless-forms' ); ?>',
                            data: <?php echo wp_json_encode( $chart_data['data'] ); ?>,
                            borderColor: '#2271b1',
                            backgroundColor: gradient,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { borderDash: [2, 4] }, ticks: { stepSize: 1 } },
                            x: { grid: { display: false }, ticks: { maxTicksLimit: 7 } }
                        }
                    }
                });
            });
        </script>
    </div>

    <!-- Recent Submissions -->
    <div class="hf-card">
        <h2><?php esc_html_e( 'Recent Submissions', 'headless-forms' ); ?></h2>
        <?php if ( ! empty( $recent ) ) : ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ( $recent as $submission ) : ?>
                    <div style="padding: 12px; background: #f6f7f7; border-radius: 4px;">
                        <div style="font-weight: 600; font-size: 13px; color: #1d2327;">
                            <?php echo esc_html( $submission->form_name ); ?>
                        </div>
                        <div style="font-size: 12px; color: #50575e; margin-top: 4px;">
                            <?php echo esc_html( human_time_diff( strtotime( $submission->created_at ), current_time( 'timestamp' ) ) ); ?> ago
                        </div>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=submission-detail&submission_id=' . $submission->id ) ); ?>" 
                           style="font-size: 12px; margin-top: 6px; display: inline-block;">
                            <?php esc_html_e( 'View →', 'headless-forms' ); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p style="color: #50575e; font-size: 13px;"><?php esc_html_e( 'No submissions yet.', 'headless-forms' ); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="hf-card" style="margin-top: 20px;">
    <h2><?php esc_html_e( 'Quick Actions', 'headless-forms' ); ?></h2>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=new-form' ) ); ?>" class="hf-button hf-button-primary">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Create New Form', 'headless-forms' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=submissions' ) ); ?>" class="hf-button hf-button-secondary">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'View Submissions', 'headless-forms' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=how-to' ) ); ?>" class="hf-button hf-button-secondary">
            <span class="dashicons dashicons-book"></span>
            <?php esc_html_e( 'Documentation', 'headless-forms' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=settings' ) ); ?>" class="hf-button hf-button-secondary">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Settings', 'headless-forms' ); ?>
        </a>
    </div>
</div>

<!-- API Endpoint Info -->
<div class="hf-card" style="margin-top: 20px;">
    <h2><?php esc_html_e( 'API Endpoint', 'headless-forms' ); ?></h2>
    <div style="background: #1d2327; color: #e0e0e0; padding: 16px; border-radius: 4px; font-family: monospace; font-size: 13px; display: flex; justify-content: space-between; align-items: center;">
        <code id="hf-api-endpoint" style="color: #e0e0e0;">POST <?php echo esc_url( rest_url( 'headless-forms/v1/submit/{form_slug}' ) ); ?></code>
        <button type="button" class="hf-button hf-button-secondary hf-button-small hf-copy-btn" data-copy="hf-api-endpoint">
            <?php esc_html_e( 'Copy', 'headless-forms' ); ?>
        </button>
    </div>
    <p class="hf-form-hint" style="margin-top: 10px;">
        <?php esc_html_e( 'Replace {form_slug} with your form\'s slug. Include your API key in the X-HF-API-Key header.', 'headless-forms' ); ?>
    </p>
</div>
