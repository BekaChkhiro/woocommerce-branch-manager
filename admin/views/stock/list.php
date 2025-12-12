<?php
/**
 * Stock List View
 *
 * Displays the stock management page with bulk editing capabilities.
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
 * Stock List Table class
 */
class WBIM_Stock_List_Table extends WP_List_Table {

    /**
     * Branches cache
     *
     * @var array
     */
    private $branches = array();

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => __( 'Stock', 'wbim' ),
                'plural'   => __( 'Stock', 'wbim' ),
                'ajax'     => true,
            )
        );

        // Cache branches
        $this->branches = WBIM_Branch::get_active();
    }

    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns() {
        $columns = array(
            'cb'      => '<input type="checkbox" />',
            'product' => __( 'პროდუქტი', 'wbim' ),
            'sku'     => __( 'SKU', 'wbim' ),
            'type'    => __( 'ტიპი', 'wbim' ),
        );

        // Add a column for each branch
        foreach ( $this->branches as $branch ) {
            $columns[ 'branch_' . $branch->id ] = esc_html( $branch->name );
        }

        $columns['total'] = __( 'ჯამი', 'wbim' );

        return $columns;
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'product' => array( 'product', false ),
            'sku'     => array( 'sku', false ),
            'total'   => array( 'total', true ),
        );
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'zero_stock' => __( 'მარაგის განულება', 'wbim' ),
            'set_stock'  => __( 'მარაგის დაყენება', 'wbim' ),
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

        // Process bulk actions
        $this->process_bulk_action();

        // Query args
        $args = array(
            'limit'  => $per_page,
            'offset' => ( $current_page - 1 ) * $per_page,
        );

        // Branch filter
        if ( ! empty( $_GET['branch_id'] ) ) {
            $args['branch_id'] = absint( $_GET['branch_id'] );
        }

        // Search
        if ( ! empty( $_GET['s'] ) ) {
            $args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
        }

        // Stock status filter
        if ( ! empty( $_GET['stock_status'] ) ) {
            $args['stock_status'] = sanitize_key( $_GET['stock_status'] );
        }

        // Category filter
        if ( ! empty( $_GET['category'] ) ) {
            $args['category'] = absint( $_GET['category'] );
        }

        // Product type filter
        if ( ! empty( $_GET['product_type'] ) ) {
            $args['product_type'] = sanitize_key( $_GET['product_type'] );
        }

        // Orderby
        if ( isset( $_GET['orderby'] ) ) {
            $args['orderby'] = sanitize_key( $_GET['orderby'] );
        }

        if ( isset( $_GET['order'] ) ) {
            $args['order'] = sanitize_key( $_GET['order'] );
        }

        // Get items - we need to get products with their stock
        $this->items = $this->get_products_with_stock( $args );
        $total_items = $this->get_products_count( $args );

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
     * Get products with stock
     *
     * @param array $args Query arguments.
     * @return array
     */
    private function get_products_with_stock( $args ) {
        global $wpdb;

        $stock_table = $wpdb->prefix . 'wbim_stock';

        // Base: Only get main products (not variations directly)
        $where = array( "p.post_type = 'product'", "p.post_status = 'publish'" );
        $values = array();
        $join = '';

        // Search
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        // Category
        if ( ! empty( $args['category'] ) ) {
            $join .= " INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
            $join .= " INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $where[] = 'tt.term_id = %d';
            $values[] = $args['category'];
        }

        // Stock status filter
        if ( ! empty( $args['stock_status'] ) ) {
            switch ( $args['stock_status'] ) {
                case 'low_stock':
                    $join .= " INNER JOIN {$stock_table} ss ON (p.ID = ss.product_id AND ss.variation_id = 0)";
                    $where[] = 'ss.quantity <= ss.low_stock_threshold AND ss.low_stock_threshold > 0';
                    break;
                case 'out_of_stock':
                    $join .= " INNER JOIN {$stock_table} ss ON (p.ID = ss.product_id AND ss.variation_id = 0)";
                    $where[] = 'ss.quantity = 0';
                    break;
                case 'in_stock':
                    $join .= " INNER JOIN {$stock_table} ss ON (p.ID = ss.product_id AND ss.variation_id = 0)";
                    $where[] = 'ss.quantity > 0';
                    break;
            }
        }

        // Branch filter
        if ( ! empty( $args['branch_id'] ) ) {
            if ( strpos( $join, $stock_table ) === false ) {
                $join .= " INNER JOIN {$stock_table} ss ON (p.ID = ss.product_id AND ss.variation_id = 0)";
            }
            $where[] = 'ss.branch_id = %d';
            $values[] = $args['branch_id'];
        }

        // Product type filter
        if ( ! empty( $args['product_type'] ) ) {
            $join .= " INNER JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id";
            $join .= " INNER JOIN {$wpdb->term_taxonomy} tt_type ON tr_type.term_taxonomy_id = tt_type.term_taxonomy_id AND tt_type.taxonomy = 'product_type'";
            $join .= " INNER JOIN {$wpdb->terms} t_type ON tt_type.term_id = t_type.term_id";
            $where[] = 't_type.slug = %s';
            $values[] = $args['product_type'];
        }

        $where_clause = implode( ' AND ', $where );

        // Order
        $orderby = isset( $args['orderby'] ) ? $args['orderby'] : 'p.post_title';
        $order = isset( $args['order'] ) && strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        // Map orderby
        $orderby_map = array(
            'product' => 'p.post_title',
            'sku'     => 'pm_sku.meta_value',
            'total'   => 'total_stock',
        );

        if ( isset( $orderby_map[ $orderby ] ) ) {
            $orderby = $orderby_map[ $orderby ];
        } else {
            $orderby = 'p.post_title';
        }

        // Build query
        $sql = "SELECT DISTINCT p.ID, p.post_title, p.post_parent, p.post_type,
                       pm_sku.meta_value as sku,
                       COALESCE(SUM(st.quantity), 0) as total_stock
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
                LEFT JOIN {$stock_table} st ON p.ID = st.product_id AND st.variation_id = 0
                {$join}
                WHERE {$where_clause}
                GROUP BY p.ID
                ORDER BY {$orderby} {$order}";

        if ( isset( $args['limit'] ) && $args['limit'] > 0 ) {
            $sql .= ' LIMIT %d OFFSET %d';
            $values[] = $args['limit'];
            $values[] = $args['offset'];
        }

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $sql );
        }

        // Fetch stock data and variations for each product
        foreach ( $results as &$item ) {
            $item->branch_stock = WBIM_Stock::get_product_stock_by_branch( $item->ID, 0 );
            $item->product_id = $item->ID;
            $item->variation_id = 0;

            // Check if variable product
            $product = wc_get_product( $item->ID );
            $item->is_variable = $product && $product->is_type( 'variable' );
            $item->variations = array();

            if ( $item->is_variable ) {
                $variation_ids = $product->get_children();
                foreach ( $variation_ids as $var_id ) {
                    $variation = wc_get_product( $var_id );
                    if ( $variation ) {
                        $var_data = new stdClass();
                        $var_data->ID = $var_id;
                        $var_data->name = wc_get_formatted_variation( $variation, true, false );
                        $var_data->sku = $variation->get_sku();
                        $var_data->branch_stock = WBIM_Stock::get_product_stock_by_branch( $item->ID, $var_id );
                        $var_data->product_id = $item->ID;
                        $var_data->variation_id = $var_id;
                        $item->variations[] = $var_data;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Get products count
     *
     * @param array $args Query arguments.
     * @return int
     */
    private function get_products_count( $args ) {
        global $wpdb;

        $stock_table = $wpdb->prefix . 'wbim_stock';
        $where = array( "p.post_type = 'product'", "p.post_status = 'publish'" );
        $values = array();
        $join = '';

        // Search
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $join = "LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'";
        }

        // Category
        if ( ! empty( $args['category'] ) ) {
            $join .= " INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
            $join .= " INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $where[] = 'tt.term_id = %d';
            $values[] = $args['category'];
        }

        // Stock status filter
        if ( ! empty( $args['stock_status'] ) ) {
            switch ( $args['stock_status'] ) {
                case 'low_stock':
                    $join .= " INNER JOIN {$stock_table} ss ON (p.ID = ss.product_id AND ss.variation_id = 0)";
                    $where[] = 'ss.quantity <= ss.low_stock_threshold AND ss.low_stock_threshold > 0';
                    break;
                case 'out_of_stock':
                    $join .= " INNER JOIN {$stock_table} ss ON (p.ID = ss.product_id AND ss.variation_id = 0)";
                    $where[] = 'ss.quantity = 0';
                    break;
                case 'in_stock':
                    $join .= " INNER JOIN {$stock_table} ss ON (p.ID = ss.product_id AND ss.variation_id = 0)";
                    $where[] = 'ss.quantity > 0';
                    break;
            }
        }

        // Branch filter
        if ( ! empty( $args['branch_id'] ) ) {
            if ( strpos( $join, $stock_table ) === false ) {
                $join .= " INNER JOIN {$stock_table} ss ON (p.ID = ss.product_id AND ss.variation_id = 0)";
            }
            $where[] = 'ss.branch_id = %d';
            $values[] = $args['branch_id'];
        }

        // Product type filter
        if ( ! empty( $args['product_type'] ) ) {
            $join .= " INNER JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id";
            $join .= " INNER JOIN {$wpdb->term_taxonomy} tt_type ON tr_type.term_taxonomy_id = tt_type.term_taxonomy_id AND tt_type.taxonomy = 'product_type'";
            $join .= " INNER JOIN {$wpdb->terms} t_type ON tt_type.term_id = t_type.term_id";
            $where[] = 't_type.slug = %s';
            $values[] = $args['product_type'];
        }

        $where_clause = implode( ' AND ', $where );

        $sql = "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                {$join}
                WHERE {$where_clause}";

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var( $sql );
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
        $product_ids = isset( $_REQUEST['product'] ) ? array_map( 'absint', $_REQUEST['product'] ) : array();

        if ( empty( $product_ids ) ) {
            return;
        }

        switch ( $action ) {
            case 'zero_stock':
                foreach ( $product_ids as $product_id ) {
                    $product = wc_get_product( $product_id );
                    if ( ! $product ) {
                        continue;
                    }

                    foreach ( $this->branches as $branch ) {
                        WBIM_Stock::set( $product_id, 0, $branch->id, array( 'quantity' => 0 ) );
                    }

                    WBIM_Stock::sync_wc_stock( $product_id, 0 );
                }

                set_transient(
                    'wbim_admin_notice',
                    array(
                        'type'    => 'success',
                        'message' => __( 'მონიშნული პროდუქტების მარაგი განულდა.', 'wbim' ),
                    ),
                    30
                );
                break;

            case 'set_stock':
                $bulk_quantity = isset( $_REQUEST['bulk_stock_quantity'] ) ? intval( $_REQUEST['bulk_stock_quantity'] ) : 0;
                $bulk_branch_id = isset( $_REQUEST['bulk_branch_id'] ) ? absint( $_REQUEST['bulk_branch_id'] ) : 0;

                foreach ( $product_ids as $product_id ) {
                    $product = wc_get_product( $product_id );
                    if ( ! $product ) {
                        continue;
                    }

                    if ( $bulk_branch_id ) {
                        // Set for specific branch
                        WBIM_Stock::set( $product_id, 0, $bulk_branch_id, array( 'quantity' => $bulk_quantity ) );
                    } else {
                        // Set for all branches
                        foreach ( $this->branches as $branch ) {
                            WBIM_Stock::set( $product_id, 0, $branch->id, array( 'quantity' => $bulk_quantity ) );
                        }
                    }

                    WBIM_Stock::sync_wc_stock( $product_id, 0 );
                }

                set_transient(
                    'wbim_admin_notice',
                    array(
                        'type'    => 'success',
                        'message' => sprintf(
                            /* translators: %d: quantity */
                            __( 'მონიშნული პროდუქტების მარაგი დაყენდა: %d', 'wbim' ),
                            $bulk_quantity
                        ),
                    ),
                    30
                );
                break;
        }
    }

    /**
     * Checkbox column
     *
     * @param object $item Stock item.
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="product[]" value="%d" />',
            $item->ID
        );
    }

    /**
     * Product column
     *
     * @param object $item Stock item.
     * @return string
     */
    public function column_product( $item ) {
        $product = wc_get_product( $item->ID );
        if ( ! $product ) {
            return '—';
        }

        $edit_url = get_edit_post_link( $item->ID );
        $name = $item->post_title;

        $output = sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>',
            esc_url( $edit_url ),
            esc_html( $name )
        );

        // Show expand button for variable products
        if ( $item->is_variable && ! empty( $item->variations ) ) {
            $output .= sprintf(
                ' <button type="button" class="wbim-expand-variations button-link" data-product-id="%d" title="%s"><span class="dashicons dashicons-arrow-down-alt2"></span> <span class="wbim-var-count">%d</span></button>',
                esc_attr( $item->ID ),
                esc_attr__( 'ვარიაციების ჩვენება', 'wbim' ),
                count( $item->variations )
            );
        }

        // Row actions
        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                __( 'რედაქტირება', 'wbim' )
            ),
            'history' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(
                    add_query_arg(
                        array(
                            'page'       => 'wbim-history',
                            'product_id' => $item->ID,
                        ),
                        admin_url( 'admin.php' )
                    )
                ),
                __( 'ისტორია', 'wbim' )
            ),
        );

        return $output . $this->row_actions( $actions );
    }

    /**
     * SKU column
     *
     * @param object $item Stock item.
     * @return string
     */
    public function column_sku( $item ) {
        return $item->sku ? esc_html( $item->sku ) : '—';
    }

    /**
     * Type column
     *
     * @param object $item Stock item.
     * @return string
     */
    public function column_type( $item ) {
        if ( $item->is_variable ) {
            return '<span class="wbim-type-badge wbim-type-variable">' . esc_html__( 'ვარიაციული', 'wbim' ) . '</span>';
        }
        return '<span class="wbim-type-badge wbim-type-simple">' . esc_html__( 'მარტივი', 'wbim' ) . '</span>';
    }

    /**
     * Total column
     *
     * @param object $item Stock item.
     * @return string
     */
    public function column_total( $item ) {
        $total = 0;
        if ( ! empty( $item->branch_stock ) ) {
            foreach ( $item->branch_stock as $stock ) {
                $total += $stock['quantity'];
            }
        }

        // For variable products, show total of all variations
        if ( $item->is_variable && ! empty( $item->variations ) ) {
            $total = 0;
            foreach ( $item->variations as $var ) {
                if ( ! empty( $var->branch_stock ) ) {
                    foreach ( $var->branch_stock as $stock ) {
                        $total += $stock['quantity'];
                    }
                }
            }
        }

        return '<strong class="wbim-row-total">' . esc_html( $total ) . '</strong>';
    }

    /**
     * Default column handler (branch columns)
     *
     * @param object $item        Stock item.
     * @param string $column_name Column name.
     * @return string
     */
    public function column_default( $item, $column_name ) {
        // Check if this is a branch column
        if ( strpos( $column_name, 'branch_' ) === 0 ) {
            $branch_id = (int) str_replace( 'branch_', '', $column_name );

            // For variable products, show summary
            if ( $item->is_variable ) {
                $branch_total = 0;
                foreach ( $item->variations as $var ) {
                    if ( isset( $var->branch_stock[ $branch_id ] ) ) {
                        $branch_total += $var->branch_stock[ $branch_id ]['quantity'];
                    }
                }
                return '<span class="wbim-branch-summary">' . esc_html( $branch_total ) . '</span>';
            }

            // For simple products, show editable input
            $quantity = 0;
            $is_low = false;

            if ( isset( $item->branch_stock[ $branch_id ] ) ) {
                $stock = $item->branch_stock[ $branch_id ];
                $quantity = $stock['quantity'];
                $is_low = $stock['low_stock_threshold'] > 0 && $quantity <= $stock['low_stock_threshold'];
            }

            $class = $is_low ? 'wbim-low-stock' : '';

            return sprintf(
                '<input type="number" class="wbim-stock-input %s" data-product-id="%d" data-variation-id="%d" data-branch-id="%d" data-original="%d" value="%d" min="0" />',
                esc_attr( $class ),
                esc_attr( $item->product_id ),
                esc_attr( $item->variation_id ),
                esc_attr( $branch_id ),
                esc_attr( $quantity ),
                esc_attr( $quantity )
            );
        }

        return '';
    }

    /**
     * Generate row
     *
     * @param object $item Item.
     * @return void
     */
    public function single_row( $item ) {
        echo '<tr data-product-id="' . esc_attr( $item->ID ) . '" class="wbim-product-row">';
        $this->single_row_columns( $item );
        echo '</tr>';

        // Output variation rows (hidden by default)
        if ( $item->is_variable && ! empty( $item->variations ) ) {
            foreach ( $item->variations as $var ) {
                echo '<tr data-product-id="' . esc_attr( $item->ID ) . '" data-variation-id="' . esc_attr( $var->ID ) . '" class="wbim-variation-row" style="display:none;">';
                echo '<td></td>'; // checkbox
                echo '<td class="column-product wbim-variation-name">';
                echo '<span class="wbim-variation-indent">↳</span> ' . esc_html( $var->name );
                echo '</td>';
                echo '<td class="column-sku">' . ( $var->sku ? esc_html( $var->sku ) : '—' ) . '</td>';
                echo '<td class="column-type"></td>';

                // Branch columns for variation
                foreach ( $this->branches as $branch ) {
                    $quantity = 0;
                    $is_low = false;
                    if ( isset( $var->branch_stock[ $branch->id ] ) ) {
                        $stock = $var->branch_stock[ $branch->id ];
                        $quantity = $stock['quantity'];
                        $is_low = $stock['low_stock_threshold'] > 0 && $quantity <= $stock['low_stock_threshold'];
                    }
                    $class = $is_low ? 'wbim-low-stock' : '';

                    echo '<td class="column-branch_' . esc_attr( $branch->id ) . '">';
                    printf(
                        '<input type="number" class="wbim-stock-input %s" data-product-id="%d" data-variation-id="%d" data-branch-id="%d" data-original="%d" value="%d" min="0" />',
                        esc_attr( $class ),
                        esc_attr( $var->product_id ),
                        esc_attr( $var->variation_id ),
                        esc_attr( $branch->id ),
                        esc_attr( $quantity ),
                        esc_attr( $quantity )
                    );
                    echo '</td>';
                }

                // Total for variation
                $var_total = 0;
                if ( ! empty( $var->branch_stock ) ) {
                    foreach ( $var->branch_stock as $stock ) {
                        $var_total += $stock['quantity'];
                    }
                }
                echo '<td class="column-total"><strong class="wbim-row-total">' . esc_html( $var_total ) . '</strong></td>';

                echo '</tr>';
            }
        }
    }

    /**
     * Display no items message
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'პროდუქტები არ მოიძებნა.', 'wbim' );
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

        $branches = WBIM_Branch::get_active();
        $categories = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
            )
        );

        $selected_branch = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : '';
        $selected_category = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : '';
        $selected_status = isset( $_GET['stock_status'] ) ? sanitize_key( $_GET['stock_status'] ) : '';
        $selected_type = isset( $_GET['product_type'] ) ? sanitize_key( $_GET['product_type'] ) : '';
        ?>
        <div class="alignleft actions wbim-stock-filters">
            <select name="branch_id">
                <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
                <?php foreach ( $branches as $branch ) : ?>
                    <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $selected_branch, $branch->id ); ?>>
                        <?php echo esc_html( $branch->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="category">
                <option value=""><?php esc_html_e( 'ყველა კატეგორია', 'wbim' ); ?></option>
                <?php foreach ( $categories as $category ) : ?>
                    <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $selected_category, $category->term_id ); ?>>
                        <?php echo esc_html( $category->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="stock_status">
                <option value=""><?php esc_html_e( 'ყველა სტატუსი', 'wbim' ); ?></option>
                <option value="in_stock" <?php selected( $selected_status, 'in_stock' ); ?>><?php esc_html_e( 'მარაგშია', 'wbim' ); ?></option>
                <option value="low_stock" <?php selected( $selected_status, 'low_stock' ); ?>><?php esc_html_e( 'დაბალი მარაგი', 'wbim' ); ?></option>
                <option value="out_of_stock" <?php selected( $selected_status, 'out_of_stock' ); ?>><?php esc_html_e( 'ამოწურულია', 'wbim' ); ?></option>
            </select>

            <select name="product_type">
                <option value=""><?php esc_html_e( 'ყველა ტიპი', 'wbim' ); ?></option>
                <option value="simple" <?php selected( $selected_type, 'simple' ); ?>><?php esc_html_e( 'მარტივი', 'wbim' ); ?></option>
                <option value="variable" <?php selected( $selected_type, 'variable' ); ?>><?php esc_html_e( 'ვარიაციული', 'wbim' ); ?></option>
            </select>

            <?php submit_button( __( 'ფილტრი', 'wbim' ), '', 'filter_action', false ); ?>
        </div>

        <!-- Bulk set stock modal trigger -->
        <div class="alignleft actions wbim-bulk-set-stock" style="display:none;">
            <input type="number" name="bulk_stock_quantity" min="0" placeholder="<?php esc_attr_e( 'რაოდენობა', 'wbim' ); ?>" class="small-text" />
            <select name="bulk_branch_id">
                <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
                <?php foreach ( $branches as $branch ) : ?>
                    <option value="<?php echo esc_attr( $branch->id ); ?>">
                        <?php echo esc_html( $branch->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
}

// Create and display the table
$stock_table = new WBIM_Stock_List_Table();
$stock_table->prepare_items();

$branches = WBIM_Branch::get_active();
$export_url = add_query_arg(
    array(
        'action' => 'wbim_export_stock',
        'nonce'  => wp_create_nonce( 'wbim_admin' ),
    ),
    admin_url( 'admin-ajax.php' )
);
?>

<div class="wrap wbim-stock-list">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'მარაგის მართვა', 'wbim' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-stock&action=import' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'იმპორტი', 'wbim' ); ?>
    </a>
    <a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action">
        <?php esc_html_e( 'ექსპორტი', 'wbim' ); ?>
    </a>
    <button type="button" id="wbim-sync-wc" class="page-title-action">
        <?php esc_html_e( 'WC სინქრონიზაცია', 'wbim' ); ?>
    </button>
    <hr class="wp-header-end">

    <?php if ( empty( $branches ) ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    /* translators: %s: add branch URL */
                    esc_html__( 'მარაგის სამართავად ჯერ დაამატეთ %s.', 'wbim' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=wbim-branches&action=add' ) ) . '">' . esc_html__( 'ფილიალი', 'wbim' ) . '</a>'
                );
                ?>
            </p>
        </div>
    <?php else : ?>
        <form method="get" id="wbim-stock-form">
            <input type="hidden" name="page" value="wbim-stock" />
            <?php
            $stock_table->search_box( __( 'ძებნა', 'wbim' ), 'product' );
            $stock_table->display();
            ?>
        </form>

        <div class="wbim-stock-actions">
            <button type="button" id="wbim-save-all-stock" class="button button-primary" disabled>
                <?php esc_html_e( 'ცვლილებების შენახვა', 'wbim' ); ?>
            </button>
            <span class="wbim-save-status"></span>
            <span class="wbim-modified-count" style="display:none;">
                (<span class="count">0</span> <?php esc_html_e( 'შეცვლილი', 'wbim' ); ?>)
            </span>
        </div>
    <?php endif; ?>
</div>

<style>
    .wbim-stock-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: center;
    }

    .wbim-type-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
    }

    .wbim-type-simple {
        background: #e7f7e7;
        color: #1e7e1e;
    }

    .wbim-type-variable {
        background: #e5f3ff;
        color: #0073aa;
    }

    .wbim-expand-variations {
        color: #0073aa;
        cursor: pointer;
        padding: 0 5px;
    }

    .wbim-expand-variations .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
        vertical-align: middle;
        transition: transform 0.2s;
    }

    .wbim-expand-variations.expanded .dashicons {
        transform: rotate(180deg);
    }

    .wbim-var-count {
        font-size: 11px;
        color: #666;
    }

    .wbim-variation-row {
        background: #f9f9f9;
    }

    .wbim-variation-name {
        padding-left: 30px !important;
    }

    .wbim-variation-indent {
        color: #999;
        margin-right: 5px;
    }

    .wbim-branch-summary {
        color: #666;
        font-style: italic;
    }

    .wbim-modified-count {
        color: #996800;
        margin-left: 10px;
    }

    .column-type {
        width: 100px;
    }
</style>
