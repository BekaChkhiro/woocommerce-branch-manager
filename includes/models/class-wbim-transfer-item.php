<?php
/**
 * Transfer Item Model
 *
 * Handles individual items within a stock transfer.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Transfer Item model class
 *
 * @since 1.0.0
 */
class WBIM_Transfer_Item {

    /**
     * Database table name
     *
     * @var string
     */
    private static $table_name;

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        if ( ! self::$table_name ) {
            self::$table_name = $wpdb->prefix . 'wbim_transfer_items';
        }
        return self::$table_name;
    }

    /**
     * Get items for a transfer
     *
     * @param int $transfer_id Transfer ID.
     * @return array Array of transfer items.
     */
    public static function get_by_transfer( $transfer_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE transfer_id = %d ORDER BY id ASC",
                $transfer_id
            )
        );

        return $items ? $items : array();
    }

    /**
     * Get single item by ID
     *
     * @param int $id Item ID.
     * @return object|null Item object or null.
     */
    public static function get_by_id( $id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Add item to transfer
     *
     * @param int $transfer_id  Transfer ID.
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID (0 for simple products).
     * @param int $quantity     Quantity to transfer.
     * @return int|false Item ID on success, false on failure.
     */
    public static function add( $transfer_id, $product_id, $variation_id, $quantity ) {
        global $wpdb;
        $table = self::get_table_name();

        // Validate transfer exists and is editable
        $transfer = WBIM_Transfer::get_by_id( $transfer_id );
        if ( ! $transfer ) {
            return false;
        }

        // Fix empty status (database migration issue)
        if ( empty( $transfer->status ) ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'wbim_transfers',
                array( 'status' => WBIM_Transfer::STATUS_DRAFT ),
                array( 'id' => $transfer_id ),
                array( '%s' ),
                array( '%d' )
            );
            $transfer->status = WBIM_Transfer::STATUS_DRAFT;
        }

        // Only allow adding items to draft transfers
        if ( $transfer->status !== WBIM_Transfer::STATUS_DRAFT ) {
            return false;
        }

        // Validate product exists
        $product = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product ) {
            return false;
        }

        // Check if item already exists in transfer
        $existing = self::get_item_by_product( $transfer_id, $product_id, $variation_id );
        if ( $existing ) {
            // Update quantity instead of adding new item
            $new_quantity = $existing->quantity + $quantity;
            return self::update( $existing->id, $new_quantity ) ? $existing->id : false;
        }

        // Get product details
        $product_name = $product->get_name();
        $sku = $product->get_sku();

        $result = $wpdb->insert(
            $table,
            array(
                'transfer_id'  => $transfer_id,
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'product_name' => $product_name,
                'sku'          => $sku,
                'quantity'     => $quantity,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
        );

        if ( $result ) {
            $item_id = $wpdb->insert_id;

            // Update transfer updated_at
            self::touch_transfer( $transfer_id );

            /**
             * Action fired when transfer item is added
             *
             * @param int $item_id     Item ID.
             * @param int $transfer_id Transfer ID.
             * @param int $product_id  Product ID.
             * @param int $quantity    Quantity.
             */
            do_action( 'wbim_transfer_item_added', $item_id, $transfer_id, $product_id, $quantity );

            return $item_id;
        }

        return false;
    }

    /**
     * Update item quantity
     *
     * @param int $id       Item ID.
     * @param int $quantity New quantity.
     * @return bool True on success, false on failure.
     */
    public static function update( $id, $quantity ) {
        global $wpdb;
        $table = self::get_table_name();

        $item = self::get_by_id( $id );
        if ( ! $item ) {
            return false;
        }

        // Validate transfer is editable
        $transfer = WBIM_Transfer::get_by_id( $item->transfer_id );
        if ( ! $transfer ) {
            return false;
        }

        // Fix empty status
        if ( empty( $transfer->status ) ) {
            $wpdb->update(
                $wpdb->prefix . 'wbim_transfers',
                array( 'status' => WBIM_Transfer::STATUS_DRAFT ),
                array( 'id' => $item->transfer_id ),
                array( '%s' ),
                array( '%d' )
            );
            $transfer->status = WBIM_Transfer::STATUS_DRAFT;
        }

        if ( $transfer->status !== WBIM_Transfer::STATUS_DRAFT ) {
            return false;
        }

        if ( $quantity <= 0 ) {
            return self::delete( $id );
        }

        $result = $wpdb->update(
            $table,
            array(
                'quantity'   => $quantity,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            self::touch_transfer( $item->transfer_id );

            /**
             * Action fired when transfer item is updated
             *
             * @param int $id       Item ID.
             * @param int $quantity New quantity.
             */
            do_action( 'wbim_transfer_item_updated', $id, $quantity );

            return true;
        }

        return false;
    }

    /**
     * Delete item from transfer
     *
     * @param int $id Item ID.
     * @return bool True on success, false on failure.
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = self::get_table_name();

        $item = self::get_by_id( $id );
        if ( ! $item ) {
            return false;
        }

        // Validate transfer is editable
        $transfer = WBIM_Transfer::get_by_id( $item->transfer_id );
        if ( ! $transfer ) {
            return false;
        }

        // Fix empty status
        if ( empty( $transfer->status ) ) {
            $wpdb->update(
                $wpdb->prefix . 'wbim_transfers',
                array( 'status' => WBIM_Transfer::STATUS_DRAFT ),
                array( 'id' => $item->transfer_id ),
                array( '%s' ),
                array( '%d' )
            );
            $transfer->status = WBIM_Transfer::STATUS_DRAFT;
        }

        if ( $transfer->status !== WBIM_Transfer::STATUS_DRAFT ) {
            return false;
        }

        $result = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( $result ) {
            self::touch_transfer( $item->transfer_id );

            /**
             * Action fired when transfer item is deleted
             *
             * @param int $id          Item ID.
             * @param int $transfer_id Transfer ID.
             */
            do_action( 'wbim_transfer_item_deleted', $id, $item->transfer_id );

            return true;
        }

        return false;
    }

    /**
     * Delete all items for a transfer
     *
     * @param int $transfer_id Transfer ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_by_transfer( $transfer_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->delete(
            $table,
            array( 'transfer_id' => $transfer_id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Get item by product within a transfer
     *
     * @param int $transfer_id  Transfer ID.
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return object|null Item object or null.
     */
    public static function get_item_by_product( $transfer_id, $product_id, $variation_id = 0 ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE transfer_id = %d
                AND product_id = %d
                AND variation_id = %d",
                $transfer_id,
                $product_id,
                $variation_id
            )
        );
    }

    /**
     * Get item with full product details
     *
     * @param int $id Item ID.
     * @return object|null Item object with product data or null.
     */
    public static function get_with_product( $id ) {
        $item = self::get_by_id( $id );
        if ( ! $item ) {
            return null;
        }

        $product_id = $item->variation_id ? $item->variation_id : $item->product_id;
        $product = wc_get_product( $product_id );

        if ( $product ) {
            $item->product = $product;
            $item->product_url = get_edit_post_link( $item->product_id );
            $item->product_image = $product->get_image( 'thumbnail' );
            $item->current_sku = $product->get_sku();
            $item->current_name = $product->get_name();

            // Get variation attributes if variable
            if ( $item->variation_id && $product->is_type( 'variation' ) ) {
                $item->variation_attributes = $product->get_variation_attributes();
            }
        }

        return $item;
    }

    /**
     * Get items with product details for a transfer
     *
     * @param int $transfer_id Transfer ID.
     * @return array Array of items with product data.
     */
    public static function get_by_transfer_with_products( $transfer_id ) {
        $items = self::get_by_transfer( $transfer_id );

        foreach ( $items as &$item ) {
            $product_id = $item->variation_id ? $item->variation_id : $item->product_id;
            $product = wc_get_product( $product_id );

            if ( $product ) {
                $item->product = $product;
                $item->product_url = get_edit_post_link( $item->product_id );
                $item->product_image = $product->get_image( 'thumbnail' );
                $item->current_sku = $product->get_sku();
                $item->current_name = $product->get_name();

                if ( $item->variation_id && $product->is_type( 'variation' ) ) {
                    $item->variation_attributes = $product->get_variation_attributes();
                }
            }
        }

        return $items;
    }

    /**
     * Get total items count for a transfer
     *
     * @param int $transfer_id Transfer ID.
     * @return int Items count.
     */
    public static function get_count( $transfer_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE transfer_id = %d",
                $transfer_id
            )
        );
    }

    /**
     * Get total quantity for a transfer
     *
     * @param int $transfer_id Transfer ID.
     * @return int Total quantity.
     */
    public static function get_total_quantity( $transfer_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(quantity) FROM {$table} WHERE transfer_id = %d",
                $transfer_id
            )
        );
    }

    /**
     * Validate stock availability for transfer items
     *
     * @param int $transfer_id Transfer ID.
     * @param int $branch_id   Source branch ID.
     * @return array Array of validation results.
     */
    public static function validate_stock( $transfer_id, $branch_id ) {
        $items = self::get_by_transfer( $transfer_id );
        $results = array(
            'valid'    => true,
            'items'    => array(),
            'errors'   => array(),
        );

        foreach ( $items as $item ) {
            $stock = WBIM_Stock::get( $item->product_id, $branch_id, $item->variation_id );
            $available = $stock ? $stock->quantity : 0;

            $item_result = array(
                'item_id'      => $item->id,
                'product_id'   => $item->product_id,
                'variation_id' => $item->variation_id,
                'product_name' => $item->product_name,
                'requested'    => $item->quantity,
                'available'    => $available,
                'valid'        => $available >= $item->quantity,
            );

            $results['items'][] = $item_result;

            if ( ! $item_result['valid'] ) {
                $results['valid'] = false;
                $results['errors'][] = sprintf(
                    /* translators: 1: Product name, 2: Requested quantity, 3: Available quantity */
                    __( '%1$s: მოთხოვნილია %2$d, ხელმისაწვდომია %3$d', 'wbim' ),
                    $item->product_name,
                    $item->quantity,
                    $available
                );
            }
        }

        return $results;
    }

    /**
     * Bulk add items to transfer
     *
     * @param int   $transfer_id Transfer ID.
     * @param array $items       Array of items (product_id, variation_id, quantity).
     * @return array Results array with success and failed counts.
     */
    public static function bulk_add( $transfer_id, $items ) {
        $results = array(
            'success' => 0,
            'failed'  => 0,
            'errors'  => array(),
        );

        foreach ( $items as $item ) {
            $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
            $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
            $quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

            if ( ! $product_id || ! $quantity ) {
                $results['failed']++;
                $results['errors'][] = __( 'არასწორი პროდუქტი ან რაოდენობა.', 'wbim' );
                continue;
            }

            $result = self::add( $transfer_id, $product_id, $variation_id, $quantity );
            if ( $result ) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(
                    /* translators: %d: Product ID */
                    __( 'პროდუქტის დამატება ვერ მოხერხდა: %d', 'wbim' ),
                    $product_id
                );
            }
        }

        return $results;
    }

    /**
     * Update transfer's updated_at timestamp
     *
     * @param int $transfer_id Transfer ID.
     */
    private static function touch_transfer( $transfer_id ) {
        global $wpdb;
        $transfers_table = $wpdb->prefix . 'wbim_transfers';

        $wpdb->update(
            $transfers_table,
            array( 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $transfer_id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            transfer_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            variation_id bigint(20) UNSIGNED DEFAULT 0,
            product_name varchar(255) NOT NULL,
            sku varchar(100) DEFAULT '',
            quantity int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY transfer_id (transfer_id),
            KEY product_id (product_id),
            KEY variation_id (variation_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
