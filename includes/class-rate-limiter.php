<?php
/**
 * Rate Limiter Class.
 *
 * Handles rate limiting for API requests using WordPress transients.
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
 * Rate limiter class.
 *
 * Implements sliding window rate limiting using WordPress transients.
 *
 * @since 1.0.0
 */
class Rate_Limiter {

    /**
     * Transient prefix for rate limiting.
     *
     * @since 1.0.0
     * @var string
     */
    const TRANSIENT_PREFIX = 'hf_rate_';

    /**
     * Security instance.
     *
     * @since 1.0.0
     * @var Security
     */
    private $security;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->security = new Security();
    }

    /**
     * Check if a request is rate limited.
     *
     * @since 1.0.0
     * @param string|null $ip Optional. IP address to check. Defaults to client IP.
     * @return bool True if rate limited, false otherwise.
     */
    public function is_rate_limited( $ip = null ) {
        if ( null === $ip ) {
            $ip = $this->security->get_client_ip();
        }

        $ip_hash    = $this->security->hash_ip( $ip );
        $limit      = $this->get_rate_limit();
        $window     = $this->get_rate_window();
        $key        = self::TRANSIENT_PREFIX . substr( $ip_hash, 0, 32 );
        $current    = get_transient( $key );

        if ( false === $current ) {
            // First request in window.
            return false;
        }

        $data = json_decode( $current, true );

        if ( ! is_array( $data ) || ! isset( $data['count'] ) ) {
            return false;
        }

        /**
         * Filter the rate limit for an IP.
         *
         * @since 1.0.0
         * @param int    $limit The rate limit.
         * @param string $ip    The IP address.
         */
        $limit = apply_filters( 'headless_forms_rate_limit', $limit, $ip );

        return $data['count'] >= $limit;
    }

    /**
     * Record a request for rate limiting.
     *
     * @since 1.0.0
     * @param string|null $ip Optional. IP address to record. Defaults to client IP.
     * @return int The current request count.
     */
    public function record_request( $ip = null ) {
        if ( null === $ip ) {
            $ip = $this->security->get_client_ip();
        }

        $ip_hash = $this->security->hash_ip( $ip );
        $window  = $this->get_rate_window();
        $key     = self::TRANSIENT_PREFIX . substr( $ip_hash, 0, 32 );
        $current = get_transient( $key );

        if ( false === $current ) {
            // First request - start counting.
            $data = array(
                'count'      => 1,
                'first_time' => time(),
            );
        } else {
            $data = json_decode( $current, true );

            if ( ! is_array( $data ) ) {
                $data = array(
                    'count'      => 1,
                    'first_time' => time(),
                );
            } else {
                $data['count'] = isset( $data['count'] ) ? $data['count'] + 1 : 1;
            }
        }

        set_transient( $key, wp_json_encode( $data ), $window );

        return $data['count'];
    }

    /**
     * Get remaining requests for an IP.
     *
     * @since 1.0.0
     * @param string|null $ip Optional. IP address to check. Defaults to client IP.
     * @return int The number of requests remaining.
     */
    public function get_remaining( $ip = null ) {
        if ( null === $ip ) {
            $ip = $this->security->get_client_ip();
        }

        $ip_hash = $this->security->hash_ip( $ip );
        $limit   = $this->get_rate_limit();
        $key     = self::TRANSIENT_PREFIX . substr( $ip_hash, 0, 32 );
        $current = get_transient( $key );

        /**
         * Filter the rate limit for an IP.
         *
         * @since 1.0.0
         * @param int    $limit The rate limit.
         * @param string $ip    The IP address.
         */
        $limit = apply_filters( 'headless_forms_rate_limit', $limit, $ip );

        if ( false === $current ) {
            return $limit;
        }

        $data = json_decode( $current, true );

        if ( ! is_array( $data ) || ! isset( $data['count'] ) ) {
            return $limit;
        }

        return max( 0, $limit - $data['count'] );
    }

    /**
     * Get time until rate limit resets.
     *
     * @since 1.0.0
     * @param string|null $ip Optional. IP address to check. Defaults to client IP.
     * @return int Seconds until reset, or 0 if not rate limited.
     */
    public function get_retry_after( $ip = null ) {
        if ( null === $ip ) {
            $ip = $this->security->get_client_ip();
        }

        $ip_hash = $this->security->hash_ip( $ip );
        $window  = $this->get_rate_window();
        $key     = self::TRANSIENT_PREFIX . substr( $ip_hash, 0, 32 );
        $current = get_transient( $key );

        if ( false === $current ) {
            return 0;
        }

        $data = json_decode( $current, true );

        if ( ! is_array( $data ) || ! isset( $data['first_time'] ) ) {
            return 0;
        }

        $elapsed = time() - $data['first_time'];
        $remaining = $window - $elapsed;

        return max( 0, $remaining );
    }

    /**
     * Reset rate limit for an IP.
     *
     * @since 1.0.0
     * @param string $ip The IP address to reset.
     * @return bool True on success.
     */
    public function reset( $ip ) {
        $ip_hash = $this->security->hash_ip( $ip );
        $key     = self::TRANSIENT_PREFIX . substr( $ip_hash, 0, 32 );

        return delete_transient( $key );
    }

    /**
     * Get the configured rate limit.
     *
     * @since 1.0.0
     * @return int The rate limit (requests per window).
     */
    public function get_rate_limit() {
        return (int) get_option( 'headless_forms_rate_limit', 5 );
    }

    /**
     * Get the configured rate window.
     *
     * @since 1.0.0
     * @return int The rate window in seconds.
     */
    public function get_rate_window() {
        return (int) get_option( 'headless_forms_rate_limit_window', 60 );
    }

    /**
     * Get rate limit headers for API response.
     *
     * @since 1.0.0
     * @param string|null $ip Optional. IP address. Defaults to client IP.
     * @return array Array of headers.
     */
    public function get_headers( $ip = null ) {
        $limit     = $this->get_rate_limit();
        $remaining = $this->get_remaining( $ip );
        $retry     = $this->get_retry_after( $ip );

        /**
         * Filter the rate limit for headers.
         *
         * @since 1.0.0
         * @param int    $limit The rate limit.
         * @param string $ip    The IP address.
         */
        $limit = apply_filters( 'headless_forms_rate_limit', $limit, $ip ?? $this->security->get_client_ip() );

        return array(
            'X-RateLimit-Limit'     => $limit,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset'     => time() + $retry,
        );
    }
}
