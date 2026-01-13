<?php
/**
 * Webhook Handler Class.
 *
 * Dispatches webhooks on form submission events.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Webhook Handler class.
 *
 * @since 1.0.0
 */
class Webhook_Handler {

    /**
     * Trigger webhooks for an event.
     *
     * @since 1.0.0
     * @param int    $form_id Form ID.
     * @param string $event   Event name.
     * @param array  $payload Payload data.
     * @return void
     */
    public function trigger( $form_id, $event, $payload ) {
        $webhooks = $this->get_webhooks( $form_id, $event );

        foreach ( $webhooks as $webhook ) {
            if ( ! $webhook->is_active ) {
                continue;
            }

            $this->dispatch( $webhook, $payload );
        }
    }

    /**
     * Get webhooks for a form and event.
     *
     * @since 1.0.0
     * @param int    $form_id Form ID.
     * @param string $event   Event name.
     * @return array
     */
    public function get_webhooks( $form_id, $event = null ) {
        global $wpdb;

        $table = $wpdb->prefix . 'headless_webhooks';

        if ( $event ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE form_id = %d AND trigger_event = %s AND is_active = 1",
                    $form_id,
                    $event
                )
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE form_id = %d",
                $form_id
            )
        );
    }

    /**
     * Dispatch a webhook.
     *
     * @since 1.0.0
     * @param object $webhook Webhook object.
     * @param array  $payload Payload data.
     * @return bool
     */
    private function dispatch( $webhook, $payload ) {
        $url     = $webhook->webhook_url;
        $method  = strtoupper( $webhook->http_method );
        $timeout = (int) $webhook->timeout_seconds ?: 30;

        // Build headers.
        $headers = array( 'Content-Type' => $webhook->content_type );

        if ( ! empty( $webhook->headers ) ) {
            $custom_headers = json_decode( $webhook->headers, true );
            if ( is_array( $custom_headers ) ) {
                $headers = array_merge( $headers, $custom_headers );
            }
        }

        // Add authentication.
        $headers = $this->add_auth_headers( $headers, $webhook );

        // Prepare payload.
        $body = $this->prepare_payload( $webhook, $payload );

        // Make request.
        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'body'    => $body,
            'timeout' => $timeout,
        );

        $response = wp_remote_request( $url, $args );
        $code     = wp_remote_retrieve_response_code( $response );
        $success  = ! is_wp_error( $response ) && $code >= 200 && $code < 300;

        // Update webhook status.
        $this->update_webhook_status( $webhook->id, $success, $code );

        // Schedule retry if failed and retries enabled.
        if ( ! $success && $webhook->retry_enabled ) {
            $this->schedule_retry( $webhook, $payload );
        }

        return $success;
    }

    /**
     * Add authentication headers.
     *
     * @since 1.0.0
     * @param array  $headers Headers array.
     * @param object $webhook Webhook object.
     * @return array
     */
    private function add_auth_headers( $headers, $webhook ) {
        if ( empty( $webhook->auth_type ) ) {
            return $headers;
        }

        $credentials = json_decode( $webhook->auth_credentials, true ) ?: array();

        switch ( $webhook->auth_type ) {
            case 'basic':
                if ( ! empty( $credentials['username'] ) && ! empty( $credentials['password'] ) ) {
                    $headers['Authorization'] = 'Basic ' . base64_encode( $credentials['username'] . ':' . $credentials['password'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                }
                break;

            case 'bearer':
                if ( ! empty( $credentials['token'] ) ) {
                    $headers['Authorization'] = 'Bearer ' . $credentials['token'];
                }
                break;

            case 'api_key':
                if ( ! empty( $credentials['header'] ) && ! empty( $credentials['key'] ) ) {
                    $headers[ $credentials['header'] ] = $credentials['key'];
                }
                break;
        }

        return $headers;
    }

    /**
     * Prepare payload for webhook.
     *
     * @since 1.0.0
     * @param object $webhook Webhook object.
     * @param array  $payload Raw payload.
     * @return string
     */
    private function prepare_payload( $webhook, $payload ) {
        // Add event info.
        $payload['event'] = $webhook->trigger_event;
        $payload['timestamp'] = current_time( 'c' );

        if ( $webhook->content_type === 'application/json' ) {
            return wp_json_encode( $payload );
        }

        // Form encoded.
        return http_build_query( $payload );
    }

    /**
     * Update webhook status after dispatch.
     *
     * @since 1.0.0
     * @param int  $webhook_id   Webhook ID.
     * @param bool $success      Success status.
     * @param int  $response_code HTTP response code.
     * @return void
     */
    private function update_webhook_status( $webhook_id, $success, $response_code ) {
        global $wpdb;

        $table = $wpdb->prefix . 'headless_webhooks';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            array(
                'last_triggered_at'   => current_time( 'mysql' ),
                'last_status'         => $success ? 'success' : 'failed',
                'last_response_code'  => $response_code,
            ),
            array( 'id' => $webhook_id )
        );
    }

    /**
     * Schedule a webhook retry.
     *
     * @since 1.0.0
     * @param object $webhook Webhook object.
     * @param array  $payload Payload data.
     * @return void
     */
    private function schedule_retry( $webhook, $payload ) {
        // Use transient to track retry count.
        $retry_key = 'hf_webhook_retry_' . $webhook->id;
        $retries = (int) get_transient( $retry_key );

        if ( $retries >= $webhook->max_retries ) {
            delete_transient( $retry_key );
            return;
        }

        // Schedule retry with exponential backoff.
        $delay = pow( 2, $retries ) * 60; // 1 min, 2 min, 4 min, 8 min.

        set_transient( $retry_key, $retries + 1, HOUR_IN_SECONDS );

        wp_schedule_single_event(
            time() + $delay,
            'headless_forms_webhook_retry',
            array( $webhook->id, $payload )
        );
    }

    /**
     * Create a webhook.
     *
     * @since 1.0.0
     * @param array $data Webhook data.
     * @return int|false Webhook ID or false.
     */
    public function create( $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'headless_webhooks';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            array(
                'form_id'          => $data['form_id'],
                'webhook_name'     => sanitize_text_field( $data['webhook_name'] ),
                'webhook_url'      => esc_url_raw( $data['webhook_url'] ),
                'http_method'      => $data['http_method'] ?? 'POST',
                'content_type'     => $data['content_type'] ?? 'application/json',
                'headers'          => isset( $data['headers'] ) ? wp_json_encode( $data['headers'] ) : null,
                'auth_type'        => $data['auth_type'] ?? null,
                'auth_credentials' => isset( $data['auth_credentials'] ) ? wp_json_encode( $data['auth_credentials'] ) : null,
                'trigger_event'    => $data['trigger_event'] ?? 'submission.created',
                'is_active'        => $data['is_active'] ?? 1,
                'retry_enabled'    => $data['retry_enabled'] ?? 1,
                'max_retries'      => $data['max_retries'] ?? 3,
                'timeout_seconds'  => $data['timeout_seconds'] ?? 30,
                'created_at'       => current_time( 'mysql' ),
            )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Delete a webhook.
     *
     * @since 1.0.0
     * @param int $webhook_id Webhook ID.
     * @return bool
     */
    public function delete( $webhook_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'headless_webhooks';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete( $table, array( 'id' => $webhook_id ) ) !== false;
    }
}
