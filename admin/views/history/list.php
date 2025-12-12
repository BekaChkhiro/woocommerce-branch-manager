<?php
/**
 * Stock History List View
 *
 * Displays the stock history/log page.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include WP_List_Table if not available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * History List Table class
 */
class WBIM_History_List_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => __( 'Log', 'wbim' ),
                'plural'   => __( 'Logs', 'wbim' ),
                'ajax'     => false,
            )
        );
    }

    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'created_at'      => __( 'თარიღი', 'wbim' ),
            'product'         => __( 'პროდუქტი', 'wbim' ),
            'branch'          => __( 'ფილიალი', 'wbim' ),
            'action_type'     => __( 'ქმედება', 'wbim' ),
            'quantity_change' => __( 'ცვლილება', 'wbim' ),
            'stock_levels'    => __( 'მარაგი', 'wbim' ),
            'user'            => __( 'მომხმარებელი', 'wbim' ),
            'note'            => __( 'შენიშვნა', 'wbim' ),
        );
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'created_at'  => array( 'created_at', true ),
            'action_type' => array( 'action_type', false ),
        );
    }

    /**
     * Prepare items
     *
     * @return void
     */
    public function prepare_items() {
        $per_page = 30;
        $current_page = $this->get_pagenum();

        // Query args
        $args = array(
            'limit'  => $per_page,
            'offset' => ( $current_page - 1 ) * $per_page,
        );

        // Orderby
        if ( isset( $_GET['orderby'] ) ) {
            $args['orderby'] = sanitize_key( $_GET['orderby'] );
        }

        if ( isset( $_GET['order'] ) ) {
            $args['order'] = sanitize_key( $_GET['order'] );
        }

        // Filters
        if ( ! empty( $_GET['branch_id'] ) ) {
            $args['branch_id'] = absint( $_GET['branch_id'] );
        }

        if ( ! empty( $_GET['product_id'] ) ) {
            $args['product_id'] = absint( $_GET['product_id'] );
        }

        if ( ! empty( $_GET['action_type'] ) ) {
            $args['action_type'] = sanitize_key( $_GET['action_type'] );
        }

        if ( ! empty( $_GET['date_from'] ) ) {
            $args['date_from'] = sanitize_text_field( $_GET['date_from'] );
        }

        if ( ! empty( $_GET['date_to'] ) ) {
            $args['date_to'] = sanitize_text_field( $_GET['date_to'] );
        }

        if ( ! empty( $_GET['s'] ) ) {
            $args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
        }

        // Get items
        $this->items = WBIM_Stock_Log::get_all( $args );
        $total_items = WBIM_Stock_Log::get_count( $args );

        // Set pagination
        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page ),
            )
        );

        // Set column headers
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }

    /**
     * Date column
     *
     * @param object $item Log item.
     * @return string
     */
    public function column_created_at( $item ) {
        return esc_html( WBIM_Utils::format_date( $item->created_at, true ) );
    }

    /**
     * Product column
     *
     * @param object $item Log item.
     * @return string
     */
    public function column_product( $item ) {
        $name = $item->product_name ?: __( 'წაშლილი პროდუქტი', 'wbim' );
        $edit_url = get_edit_post_link( $item->variation_id ?: $item->product_id );

        if ( $edit_url ) {
            return sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                esc_html( $name )
            );
        }

        return esc_html( $name );
    }

    /**
     * Branch column
     *
     * @param object $item Log item.
     * @return string
     */
    public function column_branch( $item ) {
        return $item->branch_name ? esc_html( $item->branch_name ) : '—';
    }

    /**
     * Action type column
     *
     * @param object $item Log item.
     * @return string
     */
    public function column_action_type( $item ) {
        $labels = WBIM_Stock_Log::get_action_types();
        $label = isset( $labels[ $item->action_type ] ) ? $labels[ $item->action_type ] : $item->action_type;

        // Action type badges
        $badges = array(
            'sale'         => 'wbim-badge-sale',
            'restock'      => 'wbim-badge-restock',
            'transfer_in'  => 'wbim-badge-transfer',
            'transfer_out' => 'wbim-badge-transfer',
            'adjustment'   => 'wbim-badge-adjustment',
            'return'       => 'wbim-badge-return',
        );

        $class = isset( $badges[ $item->action_type ] ) ? $badges[ $item->action_type ] : 'wbim-badge-default';

        return sprintf(
            '<span class="wbim-action-badge %s">%s</span>',
            esc_attr( $class ),
            esc_html( $label )
        );
    }

    /**
     * Quantity change column
     *
     * @param object $item Log item.
     * @return string
     */
    public function column_quantity_change( $item ) {
        $change = $item->quantity_change;
        $class = $change > 0 ? 'positive' : ( $change < 0 ? 'negative' : '' );
        $prefix = $change > 0 ? '+' : '';

        return sprintf(
            '<span class="wbim-quantity-change %s">%s</span>',
            esc_attr( $class ),
            esc_html( $prefix . $change )
        );
    }

    /**
     * Stock levels column
     *
     * @param object $item Log item.
     * @return string
     */
    public function column_stock_levels( $item ) {
        return sprintf(
            '%d → %d',
            $item->quantity_before,
            $item->quantity_after
        );
    }

    /**
     * User column
     *
     * @param object $item Log item.
     * @return string
     */
    public function column_user( $item ) {
        return $item->user_name ? esc_html( $item->user_name ) : '—';
    }

    /**
     * Note column
     *
     * @param object $item Log item.
     * @return string
     */
    public function column_note( $item ) {
        if ( empty( $item->note ) ) {
            return '—';
        }

        $note = esc_html( $item->note );

        // Show reference link if available
        if ( $item->reference_id && $item->reference_type ) {
            if ( 'order' === $item->reference_type ) {
                $order_url = get_edit_post_link( $item->reference_id );
                if ( $order_url ) {
                    $note .= sprintf(
                        ' <a href="%s">#%d</a>',
                        esc_url( $order_url ),
                        $item->reference_id
                    );
                }
            } elseif ( 'transfer' === $item->reference_type ) {
                $note .= sprintf(
                    ' <a href="%s">#%d</a>',
                    esc_url( admin_url( 'admin.php?page=wbim-transfers&action=view&id=' . $item->reference_id ) ),
                    $item->reference_id
                );
            }
        }

        return $note;
    }

    /**
     * Display no items message
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'ისტორია არ მოიძებნა.', 'wbim' );
    }

    /**
     * Extra table nav (filters)
     *
     * @param string $which Top or bottom.
     * @return void
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        $branches = WBIM_Branch::get_all( array( 'limit' => -1 ) );
        $action_types = WBIM_Stock_Log::get_action_types();

        $selected_branch = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : '';
        $selected_action = isset( $_GET['action_type'] ) ? sanitize_key( $_GET['action_type'] ) : '';
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
        ?>
        <div class="alignleft actions wbim-history-filters">
            <select name="branch_id">
                <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
                <?php foreach ( $branches as $branch ) : ?>
                    <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $selected_branch, $branch->id ); ?>>
                        <?php echo esc_html( $branch->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="action_type">
                <option value=""><?php esc_html_e( 'ყველა ქმედება', 'wbim' ); ?></option>
                <?php foreach ( $action_types as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_action, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'თარიღიდან', 'wbim' ); ?>" />
            <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'თარიღამდე', 'wbim' ); ?>" />

            <?php submit_button( __( 'ფილტრი', 'wbim' ), '', 'filter_action', false ); ?>

            <?php if ( $selected_branch || $selected_action || $date_from || $date_to ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-history' ) ); ?>" class="button">
                    <?php esc_html_e( 'გასუფთავება', 'wbim' ); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Create and display the table
$history_table = new WBIM_History_List_Table();
$history_table->prepare_items();

// Build export URL with current filters
$export_args = array(
    'action' => 'wbim_export_history',
    'nonce'  => wp_create_nonce( 'wbim_admin' ),
);

if ( ! empty( $_GET['branch_id'] ) ) {
    $export_args['branch_id'] = absint( $_GET['branch_id'] );
}

if ( ! empty( $_GET['product_id'] ) ) {
    $export_args['product_id'] = absint( $_GET['product_id'] );
}

if ( ! empty( $_GET['action_type'] ) ) {
    $export_args['action_type'] = sanitize_key( $_GET['action_type'] );
}

if ( ! empty( $_GET['date_from'] ) ) {
    $export_args['date_from'] = sanitize_text_field( $_GET['date_from'] );
}

if ( ! empty( $_GET['date_to'] ) ) {
    $export_args['date_to'] = sanitize_text_field( $_GET['date_to'] );
}

$export_url = add_query_arg( $export_args, admin_url( 'admin-ajax.php' ) );

// Check if filtering by product
$product_filter = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
$product_name = '';
if ( $product_filter ) {
    $product = wc_get_product( $product_filter );
    if ( $product ) {
        $product_name = $product->get_name();
    }
}
?>

<div class="wrap wbim-history-list">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'მარაგის ისტორია', 'wbim' ); ?></h1>
    <a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action">
        <?php esc_html_e( 'ექსპორტი', 'wbim' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( $product_filter && $product_name ) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php
                printf(
                    /* translators: %s: product name */
                    esc_html__( 'ნაჩვენებია პროდუქტის ისტორია: %s', 'wbim' ),
                    '<strong>' . esc_html( $product_name ) . '</strong>'
                );
                ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-history' ) ); ?>">
                    <?php esc_html_e( 'ყველას ჩვენება', 'wbim' ); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="page" value="wbim-history" />
        <?php if ( $product_filter ) : ?>
            <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_filter ); ?>" />
        <?php endif; ?>
        <?php
        $history_table->search_box( __( 'ძებნა', 'wbim' ), 'log' );
        $history_table->display();
        ?>
    </form>
</div>

<style>
    .wbim-history-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .wbim-history-filters input[type="date"] {
        padding: 4px 8px;
    }

    .wbim-action-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 500;
    }

    .wbim-badge-sale {
        background: #fde7e7;
        color: #b32d2e;
    }

    .wbim-badge-restock {
        background: #e7f7e7;
        color: #1e7e1e;
    }

    .wbim-badge-transfer {
        background: #e5f3ff;
        color: #0073aa;
    }

    .wbim-badge-adjustment {
        background: #fff3e0;
        color: #996800;
    }

    .wbim-badge-return {
        background: #f0f6fc;
        color: #646970;
    }

    .wbim-badge-default {
        background: #f0f0f1;
        color: #50575e;
    }

    .wbim-quantity-change {
        font-weight: 600;
    }

    .wbim-quantity-change.positive {
        color: #46b450;
    }

    .wbim-quantity-change.negative {
        color: #dc3232;
    }
</style>
