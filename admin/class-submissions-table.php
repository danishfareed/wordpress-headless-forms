<?php
/**
 * Submissions List Table.
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
 * Submissions Table class.
 *
 * @since 1.0.0
 */
class Submissions_Table extends \WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'submission',
            'plural'   => 'submissions',
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
            'cb'         => '<input type="checkbox" />',
            'id'         => __( 'ID', 'headless-forms' ),
            'form_name'  => __( 'Form', 'headless-forms' ),
            'data'       => __( 'Data', 'headless-forms' ),
            'status'     => __( 'Status', 'headless-forms' ),
            'ip_address' => __( 'IP', 'headless-forms' ),
            'created_at' => __( 'Submitted', 'headless-forms' ),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'id'         => array( 'id', true ),
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
            'mark_read' => __( 'Mark as Read', 'headless-forms' ),
            'mark_spam' => __( 'Mark as Spam', 'headless-forms' ),
            'delete'    => __( 'Delete', 'headless-forms' ),
        );
    }

    /**
     * Extra table nav (filters).
     *
     * @param string $which Top or bottom.
     * @return void
     */
    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) {
            return;
        }

        global $wpdb;
        $forms_table = $wpdb->prefix . 'headless_forms';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $forms = $wpdb->get_results( "SELECT id, form_name FROM {$forms_table} ORDER BY form_name" );

        $current_form = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

        ?>
        <div class="alignleft actions">
            <select name="form_id">
                <option value=""><?php esc_html_e( 'All Forms', 'headless-forms' ); ?></option>
                <?php foreach ( $forms as $form ) : ?>
                    <option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $current_form, $form->id ); ?>>
                        <?php echo esc_html( $form->form_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value=""><?php esc_html_e( 'All Statuses', 'headless-forms' ); ?></option>
                <option value="new" <?php selected( $current_status, 'new' ); ?>><?php esc_html_e( 'New', 'headless-forms' ); ?></option>
                <option value="read" <?php selected( $current_status, 'read' ); ?>><?php esc_html_e( 'Read', 'headless-forms' ); ?></option>
                <option value="spam" <?php selected( $current_status, 'spam' ); ?>><?php esc_html_e( 'Spam', 'headless-forms' ); ?></option>
            </select>

            <?php submit_button( __( 'Filter', 'headless-forms' ), '', 'filter', false ); ?>

            <?php if ( $current_form ) : ?>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=headless-forms&view=submissions&action=export&form_id=' . $current_form ), 'export_submissions_' . $current_form ) ); ?>" class="button">
                    <?php esc_html_e( 'Export CSV', 'headless-forms' ); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
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
        $submissions_table = $wpdb->prefix . 'headless_submissions';
        $forms_table = $wpdb->prefix . 'headless_forms';

        // Build where clause.
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $_GET['form_id'] ) ) {
            $where[] = 's.form_id = %d';
            $values[] = (int) $_GET['form_id'];
        }

        if ( ! empty( $_GET['status'] ) ) {
            $where[] = 's.status = %s';
            $values[] = sanitize_text_field( $_GET['status'] );
        }

        $where_sql = implode( ' AND ', $where );

        // Get total items.
        $count_sql = "SELECT COUNT(*) FROM {$submissions_table} s WHERE {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $total_items = (int) $wpdb->get_var( empty( $values ) ? $count_sql : $wpdb->prepare( $count_sql, $values ) );

        // Handle sorting.
        $orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'created_at';
        $order = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Get items.
        $offset = ( $current_page - 1 ) * $per_page;
        $query_values = $values;
        $query_values[] = $per_page;
        $query_values[] = $offset;

        $sql = "SELECT s.*, f.form_name 
                FROM {$submissions_table} s 
                LEFT JOIN {$forms_table} f ON s.form_id = f.id 
                WHERE {$where_sql} 
                ORDER BY s.{$orderby} {$order} 
                LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $this->items = $wpdb->get_results( $wpdb->prepare( $sql, $query_values ) );

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
     * @param string $column_name Column.
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
        return sprintf( '<input type="checkbox" name="submission_ids[]" value="%d" />', $item->id );
    }

    /**
     * ID column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_id( $item ) {
        $url = admin_url( 'admin.php?page=headless-forms&view=submissions&submission_id=' . $item->id );
        $starred = $item->is_starred ? '★' : '☆';

        return sprintf(
            '<span class="hf-star" data-id="%d">%s</span> <a href="%s">#%d</a>',
            $item->id,
            $starred,
            $url,
            $item->id
        );
    }

    /**
     * Data column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_data( $item ) {
        $data = json_decode( $item->submission_data, true );

        if ( empty( $data ) ) {
            return '<em>' . esc_html__( 'No data', 'headless-forms' ) . '</em>';
        }

        $preview = array();
        $count = 0;
        foreach ( $data as $key => $value ) {
            if ( $count >= 2 ) {
                break;
            }
            $display_value = is_array( $value ) ? implode( ', ', $value ) : $value;
            $display_value = strlen( $display_value ) > 50 ? substr( $display_value, 0, 50 ) . '...' : $display_value;
            $preview[] = '<strong>' . esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $display_value );
            $count++;
        }

        $remaining = count( $data ) - $count;
        $more = $remaining > 0 ? sprintf( '<br><em>+%d more fields</em>', $remaining ) : '';

        $view_url = admin_url( 'admin.php?page=headless-forms&view=submissions&submission_id=' . $item->id );

        return implode( '<br>', $preview ) . $more . '<br><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View Details', 'headless-forms' ) . ' →</a>';
    }

    /**
     * Status column.
     *
     * @param object $item Item.
     * @return string
     */
    public function column_status( $item ) {
        $classes = array(
            'new'  => 'hf-status-new',
            'read' => 'hf-status-read',
            'spam' => 'hf-status-spam',
        );
        $class = isset( $classes[ $item->status ] ) ? $classes[ $item->status ] : '';

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
        $time = strtotime( $item->created_at );
        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr( date_i18n( 'Y-m-d H:i:s', $time ) ),
            esc_html( human_time_diff( $time, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'headless-forms' ) )
        );
    }

    /**
     * No items message.
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'No submissions found.', 'headless-forms' );
    }
}
