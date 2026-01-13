<?php
/**
 * Forms List Table.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Forms Table class.
 *
 * @since 1.0.0
 */
class Forms_Table extends \WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'form',
            'plural'   => 'forms',
            'ajax'     => false,
        ) );
    }

    /**
     * Get columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'form_name'    => __( 'Form Name', 'headless-forms' ),
            'form_slug'    => __( 'Slug', 'headless-forms' ),
            'submissions'  => __( 'Submissions', 'headless-forms' ),
            'status'       => __( 'Status', 'headless-forms' ),
            'created_at'   => __( 'Created', 'headless-forms' ),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'form_name'  => array( 'form_name', false ),
            'created_at' => array( 'created_at', true ),
        );
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __( 'Delete', 'headless-forms' ),
        );
    }

    /**
     * Prepare items.
     *
     * @return void
     */
    public function prepare_items() {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $table = $wpdb->prefix . 'headless_forms';

        // Get total items.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        // Handle sorting.
        $orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'created_at';
        $order = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Get items.
        $offset = ( $current_page - 1 ) * $per_page;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        // Get submission counts.
        $submissions_table = $wpdb->prefix . 'headless_submissions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $counts = $wpdb->get_results(
            "SELECT form_id, COUNT(*) as count FROM {$submissions_table} GROUP BY form_id",
            OBJECT_K
        );

        foreach ( $items as &$item ) {
            $item->submission_count = isset( $counts[ $item->id ] ) ? $counts[ $item->id ]->count : 0;
        }

        $this->items = $items;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }

    /**
     * Column default.
     *
     * @param object $item        Item.
     * @param string $column_name Column name.
     * @return string
     */
    public function column_default( $item, $column_name ) {
        return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
    }

    /**
     * Checkbox column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="form_ids[]" value="%d" />', $item->id );
    }

    /**
     * Form name column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_form_name( $item ) {
        $edit_url = admin_url( 'admin.php?page=headless-forms-new&form_id=' . $item->id );
        $delete_url = wp_nonce_url(
            admin_url( 'admin.php?page=headless-forms-forms&action=delete&form_id=' . $item->id ),
            'delete_form_' . $item->id
        );
        $duplicate_url = wp_nonce_url(
            admin_url( 'admin.php?page=headless-forms-forms&action=duplicate&form_id=' . $item->id ),
            'duplicate_form_' . $item->id
        );
        $submissions_url = admin_url( 'admin.php?page=headless-forms-submissions&form_id=' . $item->id );

        $actions = array(
            'edit'        => sprintf( '<a href="%s">%s</a>', $edit_url, __( 'Edit', 'headless-forms' ) ),
            'submissions' => sprintf( '<a href="%s">%s</a>', $submissions_url, __( 'View Submissions', 'headless-forms' ) ),
            'duplicate'   => sprintf( '<a href="%s">%s</a>', $duplicate_url, __( 'Duplicate', 'headless-forms' ) ),
            'delete'      => sprintf( '<a href="%s" class="hf-delete-link" style="color:#b32d2e;">%s</a>', $delete_url, __( 'Delete', 'headless-forms' ) ),
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            $edit_url,
            esc_html( $item->form_name ),
            $this->row_actions( $actions )
        );
    }

    /**
     * Form slug column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_form_slug( $item ) {
        $endpoint = rest_url( 'headless-forms/v1/submit/' . $item->form_slug );
        return sprintf(
            '<code>%s</code><br><small class="hf-endpoint" title="%s">%s</small>',
            esc_html( $item->form_slug ),
            esc_attr( $endpoint ),
            esc_html__( 'Click to copy endpoint', 'headless-forms' )
        );
    }

    /**
     * Submissions column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_submissions( $item ) {
        $url = admin_url( 'admin.php?page=headless-forms-submissions&form_id=' . $item->id );
        return sprintf(
            '<a href="%s" class="hf-submission-count">%d</a>',
            $url,
            $item->submission_count
        );
    }

    /**
     * Status column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_status( $item ) {
        $class = $item->status === 'active' ? 'hf-status-active' : 'hf-status-inactive';
        return sprintf(
            '<span class="hf-status %s">%s</span>',
            $class,
            esc_html( ucfirst( $item->status ) )
        );
    }

    /**
     * Created at column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_created_at( $item ) {
        return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) );
    }

    /**
     * No items message.
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'No forms found. Create your first form to get started!', 'headless-forms' );
    }
}
