<?php
/**
 * Incoming Webhook Listener Class.
 *
 * Handles incoming webhooks from email providers (bounce/delivery notifications).
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Webhook Listener class.
 *
 * @since 1.0.0
 */
class Webhook_Listener {

    /**
     * REST API namespace.
     *
     * @since 1.0.0
     * @var string
     */
    const API_NAMESPACE = 'headless-forms/v1';

    /**
     * Email logger instance.
     *
     * @since 1.0.0
     * @var Email_Logger
     */
    private $email_logger;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param Email_Logger $email_logger Email logger instance.
     */
    public function __construct( $email_logger ) {
        $this->email_logger = $email_logger;
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/webhooks/incoming/(?P<provider>[a-z0-9_-]+)',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_webhook' ),
                'permission_callback' => '__return_true', // Validation happens inside via signature verification
            )
        );
    }

    /**
     * Handle incoming webhook.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function handle_webhook( $request ) {
        $provider = $request->get_param( 'provider' );
        $body     = $request->get_json_params(); // Parsed JSON body
        
        // If JSON parsing failed or empty, try raw body for AWS SNS (sometimes text/plain)
        if ( empty( $body ) ) {
            $body = json_decode( $request->get_body(), true );
        }

        switch ( $provider ) {
            case 'aws-ses':
                return $this->handle_aws_ses( $request, $body );
            case 'sendgrid':
                return $this->handle_sendgrid( $request, $body );
            case 'resend':
                return $this->handle_resend( $request, $body );
            default:
                return new \WP_Error( 'invalid_provider', 'Provider not supported', array( 'status' => 400 ) );
        }
    }

    /**
     * Handle AWS SES (via SNS) Webhook.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The request.
     * @param array            $body    The parsed body.
     * @return \WP_REST_Response
     */
    private function handle_aws_ses( $request, $body ) {
        // AWS SNS sends a subscription confirmation first.
        if ( isset( $body['Type'] ) && $body['Type'] === 'SubscriptionConfirmation' ) {
            // Auto-confirm subscription.
            if ( isset( $body['SubscribeURL'] ) ) {
                wp_remote_get( $body['SubscribeURL'] );
                return new \WP_REST_Response( array( 'message' => 'Subscription confirmed' ), 200 );
            }
        }

        if ( isset( $body['Type'] ) && $body['Type'] === 'Notification' ) {
            $message = json_decode( $body['Message'], true ); // SNS Message is a stringified JSON

            if ( ! $message ) {
                 // Try treating body as the message itself (if not wrapped in SNS)
                 $message = $body;
            }

            if ( isset( $message['notificationType'] ) ) {
                $type = $message['notificationType'];
                
                // Get MessageId (SES Message ID, matches our provider_message_id)
                $message_id = isset( $message['mail']['messageId'] ) ? $message['mail']['messageId'] : null;

                if ( ! $message_id ) {
                    return new \WP_REST_Response( array( 'message' => 'No Message ID found' ), 200 );
                }

                $status = '';
                $error  = '';

                if ( $type === 'Bounce' ) {
                    $status = 'bounced';
                    $bounce_type = isset($message['bounce']['bounceType']) ? $message['bounce']['bounceType'] : 'Unknown';
                    $error = "SES Bounce ($bounce_type)";
                } elseif ( $type === 'Complaint' ) {
                    $status = 'complaint';
                    $error = 'SES Complaint';
                } elseif ( $type === 'Delivery' ) {
                    $status = 'delivered';
                }

                if ( $status ) {
                    $this->update_log_status( $message_id, $status, $error );
                }
            }
        }

        return new \WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Handle SendGrid Webhook.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The request.
     * @param array            $body    The parsed body (array of events).
     * @return \WP_REST_Response
     */
    private function handle_sendgrid( $request, $body ) {
        // SendGrid sends an array of events.
        if ( is_array( $body ) ) {
            foreach ( $body as $event ) {
                if ( isset( $event['sg_message_id'] ) ) {
                    // SendGrid appends filter info to ID, split it.
                    $message_id = explode( '.', $event['sg_message_id'] )[0];
                    
                    $status = '';
                    $error = '';

                    switch ( $event['event'] ) {
                        case 'delivered':
                            $status = 'delivered';
                            break;
                        case 'bounce':
                        case 'dropped':
                            $status = 'bounced';
                            $error = isset( $event['reason'] ) ? $event['reason'] : 'Bounced';
                            break;
                        case 'spamreport':
                            $status = 'complaint';
                            break;
                    }

                    if ( $status ) {
                        $this->update_log_status( $message_id, $status, $error );
                    }
                }
            }
        }
        return new \WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Handle Resend Webhook.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The request.
     * @param array            $body    The parsed body.
     * @return \WP_REST_Response
     */
    private function handle_resend( $request, $body ) {
        if ( isset( $body['type'] ) && isset( $body['data']['email_id'] ) ) {
            $message_id = $body['data']['email_id'];
            $type = $body['type'];

            $status = '';
            $error = '';

            switch ( $type ) {
                case 'email.delivered':
                    $status = 'delivered';
                    break;
                case 'email.bounced':
                    $status = 'bounced';
                     // Resend doesn't always send reason in basic hook, but we mark it.
                    $error = 'Resend Bounce';
                    break;
                case 'email.complained':
                    $status = 'complaint';
                    break;
            }

            if ( $status ) {
                $this->update_log_status( $message_id, $status, $error );
            }
        }
        return new \WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Update email log status by provider message ID.
     *
     * @since 1.0.0
     * @param string $message_id Provider's message ID.
     * @param string $status     New status.
     * @param string $error      Optional error message.
     */
    private function update_log_status( $message_id, $status, $error = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'headless_email_logs';

        // Find log by provider_message_id
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log_id = $wpdb->get_var( $wpdb->prepare( 
            "SELECT id FROM {$table} WHERE provider_message_id LIKE %s LIMIT 1", 
            $message_id . '%' 
        ) );

        if ( $log_id ) {
            $data = array( 'status' => $status );
            if ( $error ) {
                $data['error_message'] = $error;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $table, $data, array( 'id' => $log_id ) );
        }
    }
}
