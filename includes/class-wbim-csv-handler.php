<?php
/**
 * CSV Handler
 *
 * Handles CSV import/export for stock data.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CSV Handler class
 *
 * @since 1.0.0
 */
class WBIM_CSV_Handler {

    /**
     * Export stock to CSV
     *
     * @param array $args Export arguments.
     * @return void
     */
    public static function export( $args = array() ) {
        $defaults = array(
            'branch_id' => 0,
            'filename'  => 'wbim-stock-export-' . gmdate( 'Y-m-d' ) . '.csv',
        );

        $args = wp_parse_args( $args, $defaults );

        // Get stock data
        $stock_args = array(
            'limit' => -1,
        );

        if ( ! empty( $args['branch_id'] ) ) {
            $stock_args['branch_id'] = $args['branch_id'];
        }

        $stock_items = WBIM_Stock::get_all( $stock_args );

        if ( empty( $stock_items ) ) {
            wp_die( esc_html__( 'No stock data to export.', 'wbim' ) );
        }

        // Set headers for CSV download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $args['filename'] ) );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Open output stream
        $output = fopen( 'php://output', 'w' );

        // Add UTF-8 BOM for Excel compatibility
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // CSV header
        $header = array(
            __( 'SKU', 'wbim' ),
            __( 'Product Name', 'wbim' ),
            __( 'Variation', 'wbim' ),
            __( 'Branch ID', 'wbim' ),
            __( 'Branch Name', 'wbim' ),
            __( 'Quantity', 'wbim' ),
            __( 'Low Stock Threshold', 'wbim' ),
            __( 'Shelf Location', 'wbim' ),
        );

        fputcsv( $output, $header );

        // Get branches for name lookup
        $branches = WBIM_Branch::get_all( array( 'limit' => -1 ) );
        $branch_names = array();
        foreach ( $branches as $branch ) {
            $branch_names[ $branch->id ] = $branch->name;
        }

        // Export data
        foreach ( $stock_items as $item ) {
            $product = wc_get_product( $item->variation_id ? $item->variation_id : $item->product_id );
            if ( ! $product ) {
                continue;
            }

            $parent_product = null;
            $variation_info = '';
            if ( $item->variation_id ) {
                $parent_product = wc_get_product( $item->product_id );
                $variation_attributes = $product->get_variation_attributes();
                $variation_info = implode( ', ', array_map( function( $key, $value ) {
                    return str_replace( 'attribute_', '', $key ) . ': ' . $value;
                }, array_keys( $variation_attributes ), $variation_attributes ) );
            }

            $row = array(
                $product->get_sku(),
                $parent_product ? $parent_product->get_name() : $product->get_name(),
                $variation_info,
                $item->branch_id,
                isset( $branch_names[ $item->branch_id ] ) ? $branch_names[ $item->branch_id ] : '',
                $item->quantity,
                $item->low_stock_threshold,
                $item->shelf_location,
            );

            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }

    /**
     * Get CSV template for import
     *
     * @return void
     */
    public static function download_template() {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=wbim-stock-import-template.csv' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // Add UTF-8 BOM
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Template header
        $header = array(
            'sku',
            'branch_id',
            'quantity',
            'low_stock_threshold',
            'shelf_location',
        );

        fputcsv( $output, $header );

        // Add example row
        $example = array(
            'PROD-001',
            '1',
            '50',
            '5',
            'A1-B2',
        );

        fputcsv( $output, $example );

        fclose( $output );
        exit;
    }

    /**
     * Import stock from CSV
     *
     * @param string $file_path Path to uploaded CSV file.
     * @param array  $options   Import options.
     * @return array Import results.
     */
    public static function import( $file_path, $options = array() ) {
        $defaults = array(
            'update_existing' => true,
            'skip_empty'      => true,
        );

        $options = wp_parse_args( $options, $defaults );

        $results = array(
            'success'    => 0,
            'skipped'    => 0,
            'errors'     => array(),
            'total_rows' => 0,
        );

        // Check file exists
        if ( ! file_exists( $file_path ) ) {
            $results['errors'][] = __( 'Import file not found.', 'wbim' );
            return $results;
        }

        // Open file
        $handle = fopen( $file_path, 'r' );
        if ( false === $handle ) {
            $results['errors'][] = __( 'Unable to open import file.', 'wbim' );
            return $results;
        }

        // Read header row
        $header = fgetcsv( $handle );
        if ( false === $header || empty( $header ) ) {
            fclose( $handle );
            $results['errors'][] = __( 'Invalid CSV file format.', 'wbim' );
            return $results;
        }

        // Normalize header (lowercase, trim)
        $header = array_map( function( $col ) {
            return strtolower( trim( $col ) );
        }, $header );

        // Validate required columns
        $required_columns = array( 'sku', 'branch_id', 'quantity' );
        foreach ( $required_columns as $required ) {
            if ( ! in_array( $required, $header, true ) ) {
                fclose( $handle );
                $results['errors'][] = sprintf(
                    /* translators: %s: column name */
                    __( 'Required column missing: %s', 'wbim' ),
                    $required
                );
                return $results;
            }
        }

        // Map column indices
        $col_indices = array_flip( $header );

        // Process rows
        $row_number = 1;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_number++;
            $results['total_rows']++;

            // Get values from row
            $sku = isset( $row[ $col_indices['sku'] ] ) ? trim( $row[ $col_indices['sku'] ] ) : '';
            $branch_id = isset( $row[ $col_indices['branch_id'] ] ) ? absint( $row[ $col_indices['branch_id'] ] ) : 0;
            $quantity = isset( $row[ $col_indices['quantity'] ] ) ? intval( $row[ $col_indices['quantity'] ] ) : 0;
            $low_stock_threshold = isset( $col_indices['low_stock_threshold'] ) && isset( $row[ $col_indices['low_stock_threshold'] ] )
                ? absint( $row[ $col_indices['low_stock_threshold'] ] )
                : null;
            $shelf_location = isset( $col_indices['shelf_location'] ) && isset( $row[ $col_indices['shelf_location'] ] )
                ? sanitize_text_field( $row[ $col_indices['shelf_location'] ] )
                : '';

            // Skip empty rows
            if ( empty( $sku ) && $options['skip_empty'] ) {
                $results['skipped']++;
                continue;
            }

            // Validate SKU
            if ( empty( $sku ) ) {
                $results['errors'][] = sprintf(
                    /* translators: %d: row number */
                    __( 'Row %d: SKU is required.', 'wbim' ),
                    $row_number
                );
                continue;
            }

            // Validate branch
            if ( empty( $branch_id ) ) {
                $results['errors'][] = sprintf(
                    /* translators: %d: row number */
                    __( 'Row %d: Branch ID is required.', 'wbim' ),
                    $row_number
                );
                continue;
            }

            // Verify branch exists
            $branch = WBIM_Branch::get_by_id( $branch_id );
            if ( ! $branch ) {
                $results['errors'][] = sprintf(
                    /* translators: 1: row number, 2: branch ID */
                    __( 'Row %1$d: Branch ID %2$d does not exist.', 'wbim' ),
                    $row_number,
                    $branch_id
                );
                continue;
            }

            // Find product by SKU
            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                $results['errors'][] = sprintf(
                    /* translators: 1: row number, 2: SKU */
                    __( 'Row %1$d: Product with SKU "%2$s" not found.', 'wbim' ),
                    $row_number,
                    $sku
                );
                continue;
            }

            // Get product to determine variation
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $results['errors'][] = sprintf(
                    /* translators: 1: row number, 2: SKU */
                    __( 'Row %1$d: Unable to load product with SKU "%2$s".', 'wbim' ),
                    $row_number,
                    $sku
                );
                continue;
            }

            // Determine product_id and variation_id
            $variation_id = 0;
            $main_product_id = $product_id;

            if ( $product->is_type( 'variation' ) ) {
                $variation_id = $product_id;
                $main_product_id = $product->get_parent_id();
            }

            // Check if stock exists
            $existing = WBIM_Stock::get( $main_product_id, $branch_id, $variation_id );

            if ( $existing && ! $options['update_existing'] ) {
                $results['skipped']++;
                continue;
            }

            // Prepare stock data
            $stock_data = array(
                'quantity' => $quantity,
            );

            if ( null !== $low_stock_threshold ) {
                $stock_data['low_stock_threshold'] = $low_stock_threshold;
            }

            if ( ! empty( $shelf_location ) ) {
                $stock_data['shelf_location'] = $shelf_location;
            }

            // Set stock
            $result = WBIM_Stock::set( $main_product_id, $variation_id, $branch_id, $stock_data );

            if ( is_wp_error( $result ) ) {
                $results['errors'][] = sprintf(
                    /* translators: 1: row number, 2: error message */
                    __( 'Row %1$d: %2$s', 'wbim' ),
                    $row_number,
                    $result->get_error_message()
                );
                continue;
            }

            $results['success']++;
        }

        fclose( $handle );

        return $results;
    }

    /**
     * Validate uploaded CSV file
     *
     * @param array $file $_FILES array element.
     * @return string|WP_Error File path on success, WP_Error on failure.
     */
    public static function validate_upload( $file ) {
        // Check for upload errors
        if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
            return new WP_Error( 'invalid_upload', __( 'Invalid file upload.', 'wbim' ) );
        }

        switch ( $file['error'] ) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return new WP_Error( 'no_file', __( 'No file was uploaded.', 'wbim' ) );
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return new WP_Error( 'file_too_large', __( 'File is too large.', 'wbim' ) );
            default:
                return new WP_Error( 'upload_error', __( 'Unknown upload error.', 'wbim' ) );
        }

        // Check file size (max 5MB)
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            return new WP_Error( 'file_too_large', __( 'File exceeds maximum size of 5MB.', 'wbim' ) );
        }

        // Check MIME type
        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime_type = $finfo->file( $file['tmp_name'] );

        $allowed_types = array(
            'text/plain',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
            'text/x-csv',
        );

        if ( ! in_array( $mime_type, $allowed_types, true ) ) {
            return new WP_Error(
                'invalid_type',
                __( 'Invalid file type. Please upload a CSV file.', 'wbim' )
            );
        }

        // Check file extension
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( 'csv' !== $ext ) {
            return new WP_Error(
                'invalid_extension',
                __( 'Invalid file extension. Please upload a .csv file.', 'wbim' )
            );
        }

        return $file['tmp_name'];
    }

    /**
     * Preview CSV data with validation
     *
     * @param string $file_path Path to CSV file.
     * @param int    $limit     Number of rows to preview.
     * @return array|WP_Error Preview data or error.
     */
    public static function preview( $file_path, $limit = 20 ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found.', 'wbim' ) );
        }

        $handle = fopen( $file_path, 'r' );
        if ( false === $handle ) {
            return new WP_Error( 'file_error', __( 'Unable to open file.', 'wbim' ) );
        }

        $result = array(
            'total_rows' => 0,
            'valid_rows' => 0,
            'preview'    => array(),
            'errors'     => array(),
        );

        // Read header
        $header = fgetcsv( $handle );
        if ( false === $header || empty( $header ) ) {
            fclose( $handle );
            return new WP_Error( 'invalid_csv', __( 'Invalid CSV format.', 'wbim' ) );
        }

        // Normalize header
        $header = array_map( function( $col ) {
            return strtolower( trim( $col ) );
        }, $header );

        // Validate required columns
        $required_columns = array( 'sku', 'branch_id', 'quantity' );
        foreach ( $required_columns as $required ) {
            if ( ! in_array( $required, $header, true ) ) {
                fclose( $handle );
                return new WP_Error(
                    'missing_column',
                    sprintf(
                        /* translators: %s: column name */
                        __( 'Required column missing: %s', 'wbim' ),
                        $required
                    )
                );
            }
        }

        // Map column indices
        $col_indices = array_flip( $header );

        // Cache branches for validation
        $branches = WBIM_Branch::get_all();
        $branch_ids = array();
        foreach ( $branches as $branch ) {
            $branch_ids[ $branch->id ] = $branch->name;
        }

        // Process all rows
        $row_number = 1;
        $preview_count = 0;

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_number++;
            $result['total_rows']++;

            $sku = isset( $row[ $col_indices['sku'] ] ) ? trim( $row[ $col_indices['sku'] ] ) : '';
            $branch_id = isset( $row[ $col_indices['branch_id'] ] ) ? absint( $row[ $col_indices['branch_id'] ] ) : 0;
            $quantity = isset( $row[ $col_indices['quantity'] ] ) ? intval( $row[ $col_indices['quantity'] ] ) : 0;

            $row_data = array(
                'sku'       => $sku,
                'branch_id' => $branch_id,
                'quantity'  => $quantity,
                'status'    => 'valid',
                'error'     => '',
            );

            // Validate row
            $error = '';

            if ( empty( $sku ) ) {
                $error = __( 'SKU არ არის მითითებული', 'wbim' );
            } elseif ( empty( $branch_id ) ) {
                $error = __( 'ფილიალის ID არ არის მითითებული', 'wbim' );
            } elseif ( ! isset( $branch_ids[ $branch_id ] ) ) {
                $error = sprintf( __( 'ფილიალი ID %d არ არსებობს', 'wbim' ), $branch_id );
            } else {
                // Validate SKU exists
                $product_id = wc_get_product_id_by_sku( $sku );
                if ( ! $product_id ) {
                    $error = sprintf( __( 'პროდუქტი SKU "%s" ვერ მოიძებნა', 'wbim' ), $sku );
                }
            }

            if ( ! empty( $error ) ) {
                $row_data['status'] = 'invalid';
                $row_data['error'] = $error;
                $result['errors'][] = sprintf( __( 'სტრიქონი %d: %s', 'wbim' ), $row_number, $error );
            } else {
                $result['valid_rows']++;
                $row_data['branch_name'] = $branch_ids[ $branch_id ];
            }

            // Only add to preview if within limit
            if ( $preview_count < $limit ) {
                $result['preview'][] = $row_data;
                $preview_count++;
            }
        }

        fclose( $handle );

        return $result;
    }

    /**
     * Export stock history to CSV
     *
     * @param array $args Export arguments.
     * @return void
     */
    public static function export_history( $args = array() ) {
        $defaults = array(
            'branch_id'   => 0,
            'product_id'  => 0,
            'action_type' => '',
            'date_from'   => '',
            'date_to'     => '',
            'filename'    => 'wbim-stock-history-' . gmdate( 'Y-m-d' ) . '.csv',
        );

        $args = wp_parse_args( $args, $defaults );

        // Get history data
        $log_args = array(
            'limit' => -1,
        );

        if ( ! empty( $args['branch_id'] ) ) {
            $log_args['branch_id'] = $args['branch_id'];
        }

        if ( ! empty( $args['product_id'] ) ) {
            $log_args['product_id'] = $args['product_id'];
        }

        if ( ! empty( $args['action_type'] ) ) {
            $log_args['action_type'] = $args['action_type'];
        }

        if ( ! empty( $args['date_from'] ) ) {
            $log_args['date_from'] = $args['date_from'];
        }

        if ( ! empty( $args['date_to'] ) ) {
            $log_args['date_to'] = $args['date_to'];
        }

        $history_items = WBIM_Stock_Log::get_all( $log_args );

        if ( empty( $history_items ) ) {
            wp_die( esc_html__( 'No history data to export.', 'wbim' ) );
        }

        // Set headers for CSV download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $args['filename'] ) );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // Add UTF-8 BOM
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // CSV header
        $header = array(
            __( 'Date', 'wbim' ),
            __( 'Product', 'wbim' ),
            __( 'Branch', 'wbim' ),
            __( 'Action', 'wbim' ),
            __( 'Quantity Change', 'wbim' ),
            __( 'Before', 'wbim' ),
            __( 'After', 'wbim' ),
            __( 'User', 'wbim' ),
            __( 'Note', 'wbim' ),
        );

        fputcsv( $output, $header );

        // Export data
        foreach ( $history_items as $item ) {
            $row = array(
                WBIM_Utils::format_date( $item->created_at, true ),
                $item->product_name,
                $item->branch_name,
                WBIM_Stock_Log::get_action_label( $item->action_type ),
                $item->quantity_change > 0 ? '+' . $item->quantity_change : $item->quantity_change,
                $item->quantity_before,
                $item->quantity_after,
                $item->user_name,
                $item->note,
            );

            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }
}
