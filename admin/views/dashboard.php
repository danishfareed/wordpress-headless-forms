<?php
/**
 * Dashboard View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap hf-wrap">
    <h1 class="hf-page-title">
        <span class="dashicons dashicons-feedback"></span>
        <?php esc_html_e( 'Headless Forms', 'headless-forms' ); ?>
    </h1>

    <!-- Stats Cards -->
    <div class="hf-stats-grid">
        <div class="hf-stat-card">
            <div class="hf-stat-icon"><span class="dashicons dashicons-format-aside"></span></div>
            <div class="hf-stat-content">
                <span class="hf-stat-number"><?php echo esc_html( $stats['total_forms'] ); ?></span>
                <span class="hf-stat-label"><?php esc_html_e( 'Total Forms', 'headless-forms' ); ?></span>
            </div>
        </div>

        <div class="hf-stat-card">
            <div class="hf-stat-icon"><span class="dashicons dashicons-email-alt"></span></div>
            <div class="hf-stat-content">
                <span class="hf-stat-number"><?php echo esc_html( $stats['total_submissions'] ); ?></span>
                <span class="hf-stat-label"><?php esc_html_e( 'Total Submissions', 'headless-forms' ); ?></span>
            </div>
        </div>

        <div class="hf-stat-card hf-stat-highlight">
            <div class="hf-stat-icon"><span class="dashicons dashicons-bell"></span></div>
            <div class="hf-stat-content">
                <span class="hf-stat-number"><?php echo esc_html( $stats['unread_submissions'] ); ?></span>
                <span class="hf-stat-label"><?php esc_html_e( 'Unread', 'headless-forms' ); ?></span>
            </div>
        </div>

        <div class="hf-stat-card">
            <div class="hf-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
            <div class="hf-stat-content">
                <span class="hf-stat-number"><?php echo esc_html( $stats['today_submissions'] ); ?></span>
                <span class="hf-stat-label"><?php esc_html_e( 'Today', 'headless-forms' ); ?></span>
            </div>
        </div>
    </div>

    <div class="hf-dashboard-grid">
        <!-- Chart -->
        <div class="hf-card hf-chart-card">
            <h2><?php esc_html_e( 'Submissions (Last 30 Days)', 'headless-forms' ); ?></h2>
            <canvas id="hf-submissions-chart" height="250"></canvas>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof Chart === 'undefined') return;
                    
                    const ctx = document.getElementById('hf-submissions-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo wp_json_encode( $chart_data['labels'] ); ?>,
                            datasets: [{
                                label: '<?php esc_html_e( 'Submissions', 'headless-forms' ); ?>',
                                data: <?php echo wp_json_encode( $chart_data['data'] ); ?>,
                                borderColor: '#2271b1',
                                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
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
                <table class="hf-recent-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Form', 'headless-forms' ); ?></th>
                            <th><?php esc_html_e( 'Preview', 'headless-forms' ); ?></th>
                            <th><?php esc_html_e( 'Time', 'headless-forms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent as $submission ) : ?>
                            <?php
                            $preview = '';
                            if ( is_array( $submission->submission_data ) ) {
                                $first_value = reset( $submission->submission_data );
                                $preview = is_string( $first_value ) ? substr( $first_value, 0, 40 ) : '';
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $submission->form_name ); ?></strong></td>
                                <td><?php echo esc_html( $preview ); ?><?php echo strlen( $preview ) >= 40 ? '...' : ''; ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms-submissions&submission_id=' . $submission->id ) ); ?>">
                                        <?php echo esc_html( human_time_diff( strtotime( $submission->created_at ), current_time( 'timestamp' ) ) ); ?> ago
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="hf-empty-state"><?php esc_html_e( 'No submissions yet.', 'headless-forms' ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="hf-card">
        <h2><?php esc_html_e( 'Quick Actions', 'headless-forms' ); ?></h2>
        <div class="hf-quick-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms-new' ) ); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Create New Form', 'headless-forms' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms-submissions' ) ); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-email"></span>
                <?php esc_html_e( 'View All Submissions', 'headless-forms' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms-settings' ) ); ?>" class="button button-secondary button-hero">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e( 'Settings', 'headless-forms' ); ?>
            </a>
        </div>
    </div>

    <!-- API Info -->
    <div class="hf-card hf-api-info">
        <h2><?php esc_html_e( 'API Endpoint', 'headless-forms' ); ?></h2>
        <p><?php esc_html_e( 'Use this endpoint to submit forms from your headless frontend:', 'headless-forms' ); ?></p>
        <div class="hf-code-block">
            <code id="hf-api-endpoint">POST <?php echo esc_url( rest_url( 'headless-forms/v1/submit/{form_slug}' ) ); ?></code>
            <button type="button" class="button hf-copy-btn" data-copy="hf-api-endpoint">
                <span class="dashicons dashicons-admin-page"></span>
            </button>
        </div>
        <p class="description">
            <?php esc_html_e( 'Replace {form_slug} with your form\'s slug. Include your API key in the X-HF-API-Key header.', 'headless-forms' ); ?>
        </p>
    </div>
</div>
