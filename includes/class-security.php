<?php
/**
 * Security Helper Class.
 *
 * Handles API key generation, honeypot validation, and input sanitization.
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
 * Security class.
 *
 * Provides security utilities for API authentication, spam protection,
 * and input sanitization.
 *
 * @since 1.0.0
 */
class Security {

    /**
     * API key prefix for identification.
     *
     * @since 1.0.0
     * @var string
     */
    const API_KEY_PREFIX = 'hf_';

    /**
     * API key length (excluding prefix).
     *
     * @since 1.0.0
     * @var int
     */
    const API_KEY_LENGTH = 32;

    /**
     * Generate a new API key.
     *
     * Creates a cryptographically secure random API key with prefix.
     *
     * @since 1.0.0
     * @return string The generated API key.
     */
    public function generate_api_key() {
        $random_bytes = bin2hex( random_bytes( self::API_KEY_LENGTH / 2 ) );
        return self::API_KEY_PREFIX . $random_bytes;
    }

    /**
     * Validate an API key against the stored key.
     *
     * @since 1.0.0
     * @param string $provided_key The key to validate.
     * @return bool True if valid, false otherwise.
     */
    public function validate_api_key( $provided_key ) {
        $stored_key = get_option( 'headless_forms_api_key', '' );

        if ( empty( $stored_key ) || empty( $provided_key ) ) {
            return false;
        }

        // Use hash_equals for timing-safe comparison.
        return hash_equals( $stored_key, $provided_key );
    }

    /**
     * Check if honeypot field is triggered.
     *
     * Honeypot fields should be empty. If filled, it's likely a bot.
     *
     * @since 1.0.0
     * @param array $data The submission data.
     * @return bool True if spam detected, false otherwise.
     */
    public function is_honeypot_triggered( $data ) {
        $honeypot_field = get_option( 'headless_forms_honeypot_field', '_honey' );

        if ( isset( $data[ $honeypot_field ] ) && ! empty( $data[ $honeypot_field ] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get the client IP address.
     *
     * Handles proxy detection and returns the real client IP.
     *
     * @since 1.0.0
     * @return string The client IP address.
     */
    public function get_client_ip() {
        $ip = '';

        // Check for proxy headers in order of reliability.
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_X_FORWARDED_FOR',  // Standard proxy
            'REMOTE_ADDR',           // Direct connection
        );

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                break;
            }
        }

        // Handle comma-separated list (X-Forwarded-For).
        if ( strpos( $ip, ',' ) !== false ) {
            $ips = explode( ',', $ip );
            $ip  = trim( $ips[0] );
        }

        // Validate IP format.
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) === false ) {
            $ip = '0.0.0.0';
        }

        return $ip;
    }

    /**
     * Get hashed IP for storage/rate limiting.
     *
     * Uses HMAC with a salt for privacy.
     *
     * @since 1.0.0
     * @param string $ip The IP address.
     * @return string The hashed IP.
     */
    public function hash_ip( $ip ) {
        $salt = defined( 'NONCE_SALT' ) ? NONCE_SALT : 'headless-forms-salt';
        return hash_hmac( 'sha256', $ip, $salt );
    }

    /**
     * Check if an IP is in the blocklist.
     *
     * @since 1.0.0
     * @param string $ip The IP address to check.
     * @return bool True if blocked, false otherwise.
     */
    public function is_ip_blocked( $ip ) {
        $blocklist = get_option( 'headless_forms_ip_blocklist', '' );

        if ( empty( $blocklist ) ) {
            return false;
        }

        $blocked_ips = array_map( 'trim', explode( "\n", $blocklist ) );

        foreach ( $blocked_ips as $blocked_ip ) {
            if ( empty( $blocked_ip ) ) {
                continue;
            }

            // Check for CIDR notation.
            if ( strpos( $blocked_ip, '/' ) !== false ) {
                if ( $this->ip_in_range( $ip, $blocked_ip ) ) {
                    return true;
                }
            } elseif ( $ip === $blocked_ip ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is in the allowlist.
     *
     * @since 1.0.0
     * @param string $ip The IP address to check.
     * @return bool True if allowed (or no allowlist), false if not in allowlist.
     */
    public function is_ip_allowed( $ip ) {
        $allowlist = get_option( 'headless_forms_ip_allowlist', '' );

        // If no allowlist, all IPs are allowed.
        if ( empty( $allowlist ) ) {
            return true;
        }

        $allowed_ips = array_map( 'trim', explode( "\n", $allowlist ) );

        foreach ( $allowed_ips as $allowed_ip ) {
            if ( empty( $allowed_ip ) ) {
                continue;
            }

            // Check for CIDR notation.
            if ( strpos( $allowed_ip, '/' ) !== false ) {
                if ( $this->ip_in_range( $ip, $allowed_ip ) ) {
                    return true;
                }
            } elseif ( $ip === $allowed_ip ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     *
     * @since 1.0.0
     * @param string $ip   The IP address to check.
     * @param string $cidr The CIDR range (e.g., 192.168.1.0/24).
     * @return bool True if in range, false otherwise.
     */
    private function ip_in_range( $ip, $cidr ) {
        list( $subnet, $mask ) = explode( '/', $cidr );

        // Handle IPv6.
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return $this->ipv6_in_range( $ip, $subnet, (int) $mask );
        }

        // IPv4.
        $ip_long     = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        $mask_long   = -1 << ( 32 - (int) $mask );

        return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
    }

    /**
     * Check if an IPv6 address is within a range.
     *
     * @since 1.0.0
     * @param string $ip     The IPv6 address.
     * @param string $subnet The subnet.
     * @param int    $mask   The mask bits.
     * @return bool True if in range.
     */
    private function ipv6_in_range( $ip, $subnet, $mask ) {
        $ip_bin     = inet_pton( $ip );
        $subnet_bin = inet_pton( $subnet );

        if ( false === $ip_bin || false === $subnet_bin ) {
            return false;
        }

        $mask_bin = str_repeat( 'f', $mask / 4 );
        $mask_bin .= str_repeat( '0', 32 - strlen( $mask_bin ) );
        $mask_bin = pack( 'H*', $mask_bin );

        return ( $ip_bin & $mask_bin ) === ( $subnet_bin & $mask_bin );
    }

    /**
     * Sanitize form submission data.
     *
     * Recursively sanitizes all values in the submission.
     *
     * @since 1.0.0
     * @param array $data The raw submission data.
     * @return array The sanitized data.
     */
    public function sanitize_submission_data( $data ) {
        $sanitized = array();

        foreach ( $data as $key => $value ) {
            // Skip honeypot field.
            $honeypot_field = get_option( 'headless_forms_honeypot_field', '_honey' );
            if ( $key === $honeypot_field ) {
                continue;
            }

            // Skip internal fields.
            if ( strpos( $key, '_' ) === 0 ) {
                continue;
            }

            $sanitized_key = sanitize_key( $key );

            if ( is_array( $value ) ) {
                $sanitized[ $sanitized_key ] = $this->sanitize_submission_data( $value );
            } elseif ( is_email( $value ) ) {
                $sanitized[ $sanitized_key ] = sanitize_email( $value );
            } else {
                // Allow basic HTML for message fields.
                $sanitized[ $sanitized_key ] = wp_kses_post( $value );
            }
        }

        return $sanitized;
    }

    /**
     * Encrypt sensitive data.
     *
     * Uses sodium_crypto_secretbox if available, otherwise base64.
     *
     * @since 1.0.0
     * @param string $data The data to encrypt.
     * @return string The encrypted data.
     */
    public function encrypt( $data ) {
        if ( empty( $data ) ) {
            return '';
        }
        
        $encrypted = '';
        if ( function_exists( 'sodium_crypto_secretbox' ) ) {
            $key   = $this->get_encryption_key();
            $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

            $encrypted = base64_encode( $nonce . sodium_crypto_secretbox( $data, $nonce, $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        } else {
            // Fallback to base64 (not truly encrypted, but obscured).
            $encrypted = base64_encode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        }

        return 'hf_enc:' . $encrypted;
    }

    /**
     * Decrypt sensitive data.
     *
     * @since 1.0.0
     * @param string $data The encrypted data.
     * @return string|false The decrypted data or false on failure.
     */
    public function decrypt( $data ) {
        if ( strpos( $data, 'hf_enc:' ) !== 0 ) {
            return $data; // Not encrypted by our system.
        }

        $data = substr( $data, 7 );
        $decoded = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

        if ( function_exists( 'sodium_crypto_secretbox_open' ) && strlen( $decoded ) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
            $key   = $this->get_encryption_key();
            $nonce = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

            $decrypted = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

            return $decrypted !== false ? $decrypted : false;
        }

        // Fallback (base64 only).
        return $decoded;
    }

    /**
     * Get or generate encryption key.
     *
     * @since 1.0.0
     * @return string The encryption key.
     */
    private function get_encryption_key() {
        $key = get_option( 'headless_forms_encryption_key' );

        if ( empty( $key ) ) {
            $key = sodium_crypto_secretbox_keygen();
            update_option( 'headless_forms_encryption_key', base64_encode( $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        } else {
            $key = base64_decode( $key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        }

        return $key;
    }

    /**
     * Validate CORS origin.
     *
     * @since 1.0.0
     * @param string $origin The origin to validate.
     * @return bool True if valid, false otherwise.
     */
    public function validate_cors_origin( $origin ) {
        // Check if enforcement is enabled.
        $enforcement = get_option( 'headless_forms_cors_enforcement', false );

        if ( ! $enforcement ) {
            return true;
        }

        $allowed_origins = get_option( 'headless_forms_cors_origins', '' );

        // If enforcement is on but no origins configured, deny all (secure by default).
        if ( empty( $allowed_origins ) ) {
            return false;
        }

        $origins = array_map( 'trim', explode( "\n", $allowed_origins ) );

        foreach ( $origins as $allowed ) {
            if ( empty( $allowed ) ) {
                continue;
            }

            // Wildcard support.
            if ( $allowed === '*' ) {
                return true;
            }

            // Exact match.
            if ( $origin === $allowed ) {
                return true;
            }

            // Wildcard subdomain matching.
            if ( strpos( $allowed, '*.' ) === 0 ) {
                $domain = substr( $allowed, 2 );
                if ( substr( $origin, -strlen( $domain ) ) === $domain ) {
                    return true;
                }
            }
        }

        return false;
    }
}
