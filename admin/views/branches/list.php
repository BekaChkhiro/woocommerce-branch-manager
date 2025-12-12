<?php
/**
 * Branches List View
 *
 * Displays the list of branches using WP_List_Table.
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
 * Branches List Table class
 */
class WBIM_Branches_List_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => __( 'Branch', 'wbim' ),
                'plural'   => __( 'Branches', 'wbim' ),
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
            'cb'         => '<input type="checkbox" />',
            'drag'       => '',
            'id'         => __( 'ID', 'wbim' ),
            'name'       => __( 'სახელი', 'wbim' ),
            'city'       => __( 'ქალაქი', 'wbim' ),
            'manager'    => __( 'მენეჯერი', 'wbim' ),
            'status'     => __( 'სტატუსი', 'wbim' ),
            'created_at' => __( 'თარიღი', 'wbim' ),
        );
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'id'         => array( 'id', false ),
            'name'       => array( 'name', false ),
            'city'       => array( 'city', false ),
            'created_at' => array( 'created_at', true ),
        );
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'activate'   => __( 'გააქტიურება', 'wbim' ),
            'deactivate' => __( 'დეაქტივაცია', 'wbim' ),
            'delete'     => __( 'წაშლა', 'wbim' ),
        );
    }

    /**
     * Prepare items
     *
     * @return void
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();

        // Handle bulk actions
        $this->process_bulk_action();

        // Query args
        $args = array(
            'orderby' => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'sort_order',
            'order'   => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'ASC',
            'limit'   => $per_page,
            'offset'  => ( $current_page - 1 ) * $per_page,
        );

        // Search
        if ( ! empty( $_GET['s'] ) ) {
            $args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
        }

        // Get items
        $this->items = WBIM_Branch::get_all( $args );
        $total_items = WBIM_Branch::get_count();

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
            array(), // Hidden columns
            $this->get_sortable_columns(),
        );
    }

    /**
     * Process bulk actions
     *
     * @return void
     */
    public function process_bulk_action() {
        $action = $this->current_action();

        if ( ! $action ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'bulk-' . $this->_args['plural'] ) ) {
            return;
        }

        // Get selected items
        $branch_ids = isset( $_REQUEST['branch'] ) ? array_map( 'absint', $_REQUEST['branch'] ) : array();

        if ( empty( $branch_ids ) ) {
            return;
        }

        switch ( $action ) {
            case 'activate':
                foreach ( $branch_ids as $id ) {
                    WBIM_Branch::update( $id, array( 'is_active' => 1 ) );
                }
                set_transient( 'wbim_admin_notice', array(
                    'type'    => 'success',
                    'message' => __( 'Selected branches have been activated.', 'wbim' ),
                ), 30 );
                break;

            case 'deactivate':
                foreach ( $branch_ids as $id ) {
                    WBIM_Branch::update( $id, array( 'is_active' => 0 ) );
                }
                set_transient( 'wbim_admin_notice', array(
                    'type'    => 'success',
                    'message' => __( 'Selected branches have been deactivated.', 'wbim' ),
                ), 30 );
                break;

            case 'delete':
                foreach ( $branch_ids as $id ) {
                    WBIM_Branch::delete( $id, false );
                }
                set_transient( 'wbim_admin_notice', array(
                    'type'    => 'success',
                    'message' => __( 'Selected branches have been deleted.', 'wbim' ),
                ), 30 );
                break;
        }
    }

    /**
     * Checkbox column
     *
     * @param object $item Branch item.
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="branch[]" value="%d" />',
            $item->id
        );
    }

    /**
     * Default column handler
     *
     * @param object $item        Branch item.
     * @param string $column_name Column name.
     * @return string
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return $item->id;

            case 'city':
                return $item->city ? esc_html( $item->city ) : '—';

            case 'manager':
                return esc_html( WBIM_Branch::get_manager_name( $item ) );

            case 'status':
                return WBIM_Utils::get_status_badge( $item->is_active );

            case 'created_at':
                return esc_html( WBIM_Utils::format_date( $item->created_at ) );

            default:
                return '';
        }
    }

    /**
     * Drag handle column
     *
     * @param object $item Branch item.
     * @return string
     */
    public function column_drag( $item ) {
        return '<span class="dashicons dashicons-menu wbim-drag-handle" title="' . esc_attr__( 'გადაადგილება', 'wbim' ) . '"></span>';
    }

    /**
     * Generate row with data attribute for sorting
     *
     * @param object $item        The current item.
     * @param string $column_name The current column name.
     * @return void
     */
    public function single_row( $item ) {
        echo '<tr data-branch-id="' . esc_attr( $item->id ) . '">';
        $this->single_row_columns( $item );
        echo '</tr>';
    }

    /**
     * Name column with row actions
     *
     * @param object $item Branch item.
     * @return string
     */
    public function column_name( $item ) {
        $edit_url = admin_url( 'admin.php?page=wbim-branches&action=edit&id=' . $item->id );

        // Row actions
        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                __( 'რედაქტირება', 'wbim' )
            ),
        );

        // Toggle status action
        if ( $item->is_active ) {
            $actions['deactivate'] = sprintf(
                '<a href="#" class="wbim-toggle-status" data-branch-id="%d" data-action="deactivate">%s</a>',
                $item->id,
                __( 'დეაქტივაცია', 'wbim' )
            );
        } else {
            $actions['activate'] = sprintf(
                '<a href="#" class="wbim-toggle-status" data-branch-id="%d" data-action="activate">%s</a>',
                $item->id,
                __( 'გააქტიურება', 'wbim' )
            );
        }

        // Delete action
        $actions['delete'] = sprintf(
            '<a href="#" class="wbim-delete-branch" data-branch-id="%d" style="color:#b32d2e;">%s</a>',
            $item->id,
            __( 'წაშლა', 'wbim' )
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url( $edit_url ),
            esc_html( $item->name ),
            $this->row_actions( $actions )
        );
    }

    /**
     * Display no items message
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'ფილიალები არ მოიძებნა.', 'wbim' );
    }

    /**
     * Extra table nav (search box)
     *
     * @param string $which Top or bottom.
     * @return void
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' === $which ) {
            ?>
            <div class="alignleft actions">
                <?php
                // Add filter dropdowns here if needed
                ?>
            </div>
            <?php
        }
    }
}

// Create and display the table
$branches_table = new WBIM_Branches_List_Table();
$branches_table->prepare_items();
?>

<div class="wrap wbim-branches-list">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'ფილიალები', 'wbim' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-branches&action=add' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'დამატება', 'wbim' ); ?>
    </a>
    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="wbim-branches" />
        <?php
        $branches_table->search_box( __( 'ძებნა', 'wbim' ), 'branch' );
        $branches_table->display();
        ?>
    </form>
</div>
