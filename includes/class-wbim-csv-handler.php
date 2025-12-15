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
     * Validate uploaded CSV or JSON file
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

        // Check file extension
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        // Allowed extensions
        $allowed_extensions = array( 'csv', 'json' );
        if ( ! in_array( $ext, $allowed_extensions, true ) ) {
            return new WP_Error(
                'invalid_extension',
                __( 'არასწორი ფაილის ტიპი. გთხოვთ ატვირთოთ CSV ან JSON ფაილი.', 'wbim' )
            );
        }

        // Check MIME type based on extension
        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime_type = $finfo->file( $file['tmp_name'] );

        if ( 'json' === $ext ) {
            $allowed_types = array(
                'application/json',
                'text/json',
                'text/plain',
            );
        } else {
            $allowed_types = array(
                'text/plain',
                'text/csv',
                'application/csv',
                'application/vnd.ms-excel',
                'text/x-csv',
            );
        }

        if ( ! in_array( $mime_type, $allowed_types, true ) ) {
            return new WP_Error(
                'invalid_type',
                __( 'არასწორი ფაილის ტიპი.', 'wbim' )
            );
        }

        return $file['tmp_name'];
    }

    /**
     * Get file type from path
     *
     * @param string $file_path File path.
     * @return string File extension (csv or json).
     */
    public static function get_file_type( $file_path ) {
        // Check original filename if stored in $_FILES
        if ( isset( $_FILES['import_file']['name'] ) ) {
            return strtolower( pathinfo( $_FILES['import_file']['name'], PATHINFO_EXTENSION ) );
        }
        return 'csv'; // Default to CSV
    }

    /**
     * Import stock from JSON file
     *
     * @param string $file_path Path to uploaded JSON file.
     * @param int    $branch_id Branch ID to import to.
     * @param array  $options   Import options.
     * @return array Import results.
     */
    public static function import_json( $file_path, $branch_id, $options = array() ) {
        $defaults = array(
            'update_existing'          => true,
            'skip_empty'               => true,
            'distribute_to_variations' => false,
        );

        $options = wp_parse_args( $options, $defaults );

        $results = array(
            'success'         => 0,
            'skipped'         => 0,
            'errors'          => array(),
            'total_rows'      => 0,
            'skipped_details' => array(),
        );

        // Validate branch
        if ( empty( $branch_id ) ) {
            $results['errors'][] = __( 'ფილიალი არ არის არჩეული.', 'wbim' );
            return $results;
        }

        $branch = WBIM_Branch::get_by_id( $branch_id );
        if ( ! $branch ) {
            $results['errors'][] = __( 'არჩეული ფილიალი არ არსებობს.', 'wbim' );
            return $results;
        }

        // Check file exists
        if ( ! file_exists( $file_path ) ) {
            $results['errors'][] = __( 'იმპორტის ფაილი ვერ მოიძებნა.', 'wbim' );
            return $results;
        }

        // Read JSON file
        $json_content = file_get_contents( $file_path );
        if ( false === $json_content ) {
            $results['errors'][] = __( 'ფაილის წაკითხვა ვერ მოხერხდა.', 'wbim' );
            return $results;
        }

        // Clean up JSON content - remove BOM and trim
        $json_content = trim( $json_content );
        // Remove UTF-8 BOM if present
        if ( substr( $json_content, 0, 3 ) === "\xEF\xBB\xBF" ) {
            $json_content = substr( $json_content, 3 );
        }
        $json_content = trim( $json_content );

        // Check if JSON is missing array brackets (Excel export format)
        if ( ! empty( $json_content ) && $json_content[0] !== '[' ) {
            // Wrap in array brackets
            $json_content = '[' . $json_content . ']';
        }

        // Parse JSON
        $data = json_decode( $json_content, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $results['errors'][] = sprintf(
                __( 'არასწორი JSON ფორმატი: %s', 'wbim' ),
                json_last_error_msg()
            );
            return $results;
        }

        // Ensure we have an array
        if ( ! is_array( $data ) ) {
            $results['errors'][] = __( 'JSON ფაილი უნდა შეიცავდეს მასივს.', 'wbim' );
            return $results;
        }

        // Process each row
        $row_number = 0;
        foreach ( $data as $row ) {
            $row_number++;

            // Skip null entries
            if ( null === $row || ! is_array( $row ) ) {
                continue;
            }

            // Support both formats:
            // Format 1: Column2 = SKU, Column5 = quantity (from Excel export)
            // Format 2: sku, quantity (standard format)
            $sku = '';
            $quantity = 0;

            if ( isset( $row['Column2'] ) ) {
                // Excel export format
                $sku = trim( (string) $row['Column2'] );
                $quantity = isset( $row['Column5'] ) ? intval( $row['Column5'] ) : 0;
            } elseif ( isset( $row['sku'] ) ) {
                // Standard format
                $sku = trim( (string) $row['sku'] );
                $quantity = isset( $row['quantity'] ) ? intval( $row['quantity'] ) : 0;
            }

            // Skip header rows (e.g., "კოდი", "№")
            if ( $sku === 'კოდი' || $sku === '№' || $sku === 'sku' || $sku === 'SKU' ) {
                continue;
            }

            // Skip rows where quantity is not numeric (header rows)
            if ( isset( $row['Column5'] ) && ! is_numeric( $row['Column5'] ) ) {
                continue;
            }

            $results['total_rows']++;

            // Skip empty SKUs
            if ( empty( $sku ) && $options['skip_empty'] ) {
                $results['skipped']++;
                continue;
            }

            // Validate SKU
            if ( empty( $sku ) ) {
                $results['errors'][] = sprintf(
                    __( 'სტრიქონი %d: SKU არ არის მითითებული.', 'wbim' ),
                    $row_number
                );
                continue;
            }

            // Find product by SKU
            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                $results['errors'][] = sprintf(
                    __( 'სტრიქონი %d: პროდუქტი SKU "%s" ვერ მოიძებნა.', 'wbim' ),
                    $row_number,
                    $sku
                );
                continue;
            }

            // Get product to determine variation
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $results['errors'][] = sprintf(
                    __( 'სტრიქონი %d: პროდუქტის ჩატვირთვა ვერ მოხერხდა SKU "%s".', 'wbim' ),
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
            } elseif ( $product->is_type( 'variable' ) ) {
                // Handle variable products
                $variations = $product->get_children();

                if ( empty( $variations ) ) {
                    $results['skipped']++;
                    $results['skipped_details'][] = sprintf(
                        __( 'SKU "%s" - ვარიაბელურ პროდუქტს ვარიაციები არ აქვს', 'wbim' ),
                        $sku
                    );
                    continue;
                }

                if ( ! $options['distribute_to_variations'] ) {
                    // Skip variable products if distribution is disabled
                    $results['skipped']++;
                    $results['skipped_details'][] = sprintf(
                        __( 'SKU "%s" - ვარიაბელური პროდუქტი (აქვს %d ვარიაცია). ჩართეთ "მარაგის განაწილება ვარიაციებზე" იმპორტისთვის.', 'wbim' ),
                        $sku,
                        count( $variations )
                    );
                    continue;
                }

                // Distribute stock evenly across variations
                $variation_count = count( $variations );
                $qty_per_variation = floor( $quantity / $variation_count );
                $remainder = $quantity % $variation_count;
                $distributed_count = 0;

                foreach ( $variations as $index => $var_id ) {
                    $var_qty = $qty_per_variation;
                    // Add remainder to first variations
                    if ( $index < $remainder ) {
                        $var_qty++;
                    }

                    // Check if stock exists for this variation
                    $existing = WBIM_Stock::get( $product_id, $branch_id, $var_id );

                    if ( $existing && ! $options['update_existing'] ) {
                        continue;
                    }

                    // Set stock for variation
                    $stock_data = array( 'quantity' => $var_qty );
                    $result = WBIM_Stock::set( $product_id, $var_id, $branch_id, $stock_data );

                    if ( ! is_wp_error( $result ) ) {
                        $distributed_count++;
                    }
                }

                if ( $distributed_count > 0 ) {
                    $results['success']++;
                } else {
                    $results['skipped']++;
                }
                continue;
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

            // Set stock
            $result = WBIM_Stock::set( $main_product_id, $variation_id, $branch_id, $stock_data );

            if ( is_wp_error( $result ) ) {
                $results['errors'][] = sprintf(
                    __( 'სტრიქონი %d: %s', 'wbim' ),
                    $row_number,
                    $result->get_error_message()
                );
                continue;
            }

            $results['success']++;

            // Free memory periodically during large imports
            if ( $results['success'] % 100 === 0 ) {
                wp_cache_flush();
                if ( function_exists( 'gc_collect_cycles' ) ) {
                    gc_collect_cycles();
                }
            }
        }

        return $results;
    }

    /**
     * Preview JSON data with validation
     *
     * @param string $file_path Path to JSON file.
     * @param int    $branch_id Branch ID to import to.
     * @param int    $limit     Number of rows to preview.
     * @return array|WP_Error Preview data or error.
     */
    public static function preview_json( $file_path, $branch_id, $limit = 20 ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'ფაილი ვერ მოიძებნა.', 'wbim' ) );
        }

        // Read JSON file
        $json_content = file_get_contents( $file_path );
        if ( false === $json_content ) {
            return new WP_Error( 'file_error', __( 'ფაილის წაკითხვა ვერ მოხერხდა.', 'wbim' ) );
        }

        // Clean up JSON content - remove BOM and trim
        $json_content = trim( $json_content );
        // Remove UTF-8 BOM if present
        if ( substr( $json_content, 0, 3 ) === "\xEF\xBB\xBF" ) {
            $json_content = substr( $json_content, 3 );
        }
        $json_content = trim( $json_content );

        // Check if JSON is missing array brackets (Excel export format)
        if ( ! empty( $json_content ) && $json_content[0] !== '[' ) {
            // Wrap in array brackets
            $json_content = '[' . $json_content . ']';
        }

        // Parse JSON
        $data = json_decode( $json_content, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'invalid_json', sprintf(
                __( 'არასწორი JSON ფორმატი: %s', 'wbim' ),
                json_last_error_msg()
            ) );
        }

        if ( ! is_array( $data ) ) {
            return new WP_Error( 'invalid_format', __( 'JSON ფაილი უნდა შეიცავდეს მასივს.', 'wbim' ) );
        }

        // Get branch name
        $branch = WBIM_Branch::get_by_id( $branch_id );
        $branch_name = $branch ? $branch->name : '';

        $result = array(
            'total_rows' => 0,
            'valid_rows' => 0,
            'preview'    => array(),
            'errors'     => array(),
        );

        // Process rows
        $row_number = 0;
        $preview_count = 0;

        foreach ( $data as $row ) {
            $row_number++;

            // Skip null entries
            if ( null === $row || ! is_array( $row ) ) {
                continue;
            }

            // Support both formats
            $sku = '';
            $quantity = 0;

            if ( isset( $row['Column2'] ) ) {
                $sku = trim( (string) $row['Column2'] );
                $quantity = isset( $row['Column5'] ) ? intval( $row['Column5'] ) : 0;
            } elseif ( isset( $row['sku'] ) ) {
                $sku = trim( (string) $row['sku'] );
                $quantity = isset( $row['quantity'] ) ? intval( $row['quantity'] ) : 0;
            }

            // Skip header rows (e.g., "კოდი", "№")
            if ( $sku === 'კოდი' || $sku === '№' || $sku === 'sku' || $sku === 'SKU' ) {
                continue;
            }

            // Skip rows where quantity is not numeric (header rows)
            if ( isset( $row['Column5'] ) && ! is_numeric( $row['Column5'] ) ) {
                continue;
            }

            // Skip rows without SKU data
            if ( empty( $sku ) ) {
                continue;
            }

            $result['total_rows']++;

            $row_data = array(
                'sku'         => $sku,
                'branch_id'   => $branch_id,
                'branch_name' => $branch_name,
                'quantity'    => $quantity,
                'status'      => 'valid',
                'error'       => '',
            );

            // Validate row
            $error = '';

            // Validate SKU exists
            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                $error = sprintf( __( 'პროდუქტი SKU "%s" ვერ მოიძებნა', 'wbim' ), $sku );
            }

            if ( ! empty( $error ) ) {
                $row_data['status'] = 'invalid';
                $row_data['error'] = $error;
                $result['errors'][] = sprintf( __( 'სტრიქონი %d: %s', 'wbim' ), $row_number, $error );
            } else {
                $result['valid_rows']++;
            }

            // Only add to preview if within limit
            if ( $preview_count < $limit ) {
                $result['preview'][] = $row_data;
                $preview_count++;
            }
        }

        return $result;
    }

    /**
     * Import stock from CSV file with specified branch
     *
     * @param string $file_path Path to uploaded CSV file.
     * @param int    $branch_id Branch ID to import to.
     * @param array  $options   Import options.
     * @return array Import results.
     */
    public static function import_with_branch( $file_path, $branch_id, $options = array() ) {
        $defaults = array(
            'update_existing'          => true,
            'skip_empty'               => true,
            'distribute_to_variations' => false,
        );

        $options = wp_parse_args( $options, $defaults );

        $results = array(
            'success'         => 0,
            'skipped'         => 0,
            'errors'          => array(),
            'total_rows'      => 0,
            'skipped_details' => array(),
        );

        // Validate branch
        if ( empty( $branch_id ) ) {
            $results['errors'][] = __( 'ფილიალი არ არის არჩეული.', 'wbim' );
            return $results;
        }

        $branch = WBIM_Branch::get_by_id( $branch_id );
        if ( ! $branch ) {
            $results['errors'][] = __( 'არჩეული ფილიალი არ არსებობს.', 'wbim' );
            return $results;
        }

        // Check file exists
        if ( ! file_exists( $file_path ) ) {
            $results['errors'][] = __( 'იმპორტის ფაილი ვერ მოიძებნა.', 'wbim' );
            return $results;
        }

        // Open file
        $handle = fopen( $file_path, 'r' );
        if ( false === $handle ) {
            $results['errors'][] = __( 'ფაილის გახსნა ვერ მოხერხდა.', 'wbim' );
            return $results;
        }

        // Read header row
        $header = fgetcsv( $handle );
        if ( false === $header || empty( $header ) ) {
            fclose( $handle );
            $results['errors'][] = __( 'არასწორი CSV ფორმატი.', 'wbim' );
            return $results;
        }

        // Normalize header (lowercase, trim)
        $header = array_map( function( $col ) {
            return strtolower( trim( $col ) );
        }, $header );

        // Validate required columns - only SKU and quantity required now
        $required_columns = array( 'sku', 'quantity' );
        foreach ( $required_columns as $required ) {
            if ( ! in_array( $required, $header, true ) ) {
                fclose( $handle );
                $results['errors'][] = sprintf(
                    __( 'სავალდებულო სვეტი არ მოიძებნა: %s', 'wbim' ),
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
                    __( 'სტრიქონი %d: SKU არ არის მითითებული.', 'wbim' ),
                    $row_number
                );
                continue;
            }

            // Find product by SKU
            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                $results['errors'][] = sprintf(
                    __( 'სტრიქონი %d: პროდუქტი SKU "%s" ვერ მოიძებნა.', 'wbim' ),
                    $row_number,
                    $sku
                );
                continue;
            }

            // Get product to determine variation
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $results['errors'][] = sprintf(
                    __( 'სტრიქონი %d: პროდუქტის ჩატვირთვა ვერ მოხერხდა SKU "%s".', 'wbim' ),
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
            } elseif ( $product->is_type( 'variable' ) ) {
                // Handle variable products
                $variations = $product->get_children();

                if ( empty( $variations ) ) {
                    $results['skipped']++;
                    $results['skipped_details'][] = sprintf(
                        __( 'SKU "%s" - ვარიაბელურ პროდუქტს ვარიაციები არ აქვს', 'wbim' ),
                        $sku
                    );
                    continue;
                }

                if ( ! $options['distribute_to_variations'] ) {
                    // Skip variable products if distribution is disabled
                    $results['skipped']++;
                    $results['skipped_details'][] = sprintf(
                        __( 'SKU "%s" - ვარიაბელური პროდუქტი (აქვს %d ვარიაცია). ჩართეთ "მარაგის განაწილება ვარიაციებზე" იმპორტისთვის.', 'wbim' ),
                        $sku,
                        count( $variations )
                    );
                    continue;
                }

                // Distribute stock evenly across variations
                $variation_count = count( $variations );
                $qty_per_variation = floor( $quantity / $variation_count );
                $remainder = $quantity % $variation_count;
                $distributed_count = 0;

                foreach ( $variations as $index => $var_id ) {
                    $var_qty = $qty_per_variation;
                    // Add remainder to first variations
                    if ( $index < $remainder ) {
                        $var_qty++;
                    }

                    // Check if stock exists for this variation
                    $existing = WBIM_Stock::get( $product_id, $branch_id, $var_id );

                    if ( $existing && ! $options['update_existing'] ) {
                        continue;
                    }

                    // Prepare stock data for variation
                    $var_stock_data = array( 'quantity' => $var_qty );

                    if ( null !== $low_stock_threshold ) {
                        $var_stock_data['low_stock_threshold'] = $low_stock_threshold;
                    }

                    if ( ! empty( $shelf_location ) ) {
                        $var_stock_data['shelf_location'] = $shelf_location;
                    }

                    // Set stock for variation
                    $result = WBIM_Stock::set( $product_id, $var_id, $branch_id, $var_stock_data );

                    if ( ! is_wp_error( $result ) ) {
                        $distributed_count++;
                        // Sync WooCommerce stock for variation
                        WBIM_Stock::sync_wc_stock( $product_id, $var_id );
                    }
                }

                if ( $distributed_count > 0 ) {
                    $results['success']++;
                } else {
                    $results['skipped']++;
                }
                continue;
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
                    __( 'სტრიქონი %d: %s', 'wbim' ),
                    $row_number,
                    $result->get_error_message()
                );
                continue;
            }

            // Sync WooCommerce stock
            WBIM_Stock::sync_wc_stock( $main_product_id, $variation_id );

            $results['success']++;
        }

        fclose( $handle );

        return $results;
    }

    /**
     * Preview CSV data with branch validation
     *
     * @param string $file_path Path to CSV file.
     * @param int    $branch_id Branch ID.
     * @param int    $limit     Number of rows to preview.
     * @return array|WP_Error Preview data or error.
     */
    public static function preview_with_branch( $file_path, $branch_id, $limit = 20 ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'ფაილი ვერ მოიძებნა.', 'wbim' ) );
        }

        $handle = fopen( $file_path, 'r' );
        if ( false === $handle ) {
            return new WP_Error( 'file_error', __( 'ფაილის გახსნა ვერ მოხერხდა.', 'wbim' ) );
        }

        // Get branch name
        $branch = WBIM_Branch::get_by_id( $branch_id );
        $branch_name = $branch ? $branch->name : '';

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
            return new WP_Error( 'invalid_csv', __( 'არასწორი CSV ფორმატი.', 'wbim' ) );
        }

        // Normalize header
        $header = array_map( function( $col ) {
            return strtolower( trim( $col ) );
        }, $header );

        // Validate required columns
        $required_columns = array( 'sku', 'quantity' );
        foreach ( $required_columns as $required ) {
            if ( ! in_array( $required, $header, true ) ) {
                fclose( $handle );
                return new WP_Error(
                    'missing_column',
                    sprintf(
                        __( 'სავალდებულო სვეტი არ მოიძებნა: %s', 'wbim' ),
                        $required
                    )
                );
            }
        }

        // Map column indices
        $col_indices = array_flip( $header );

        // Process all rows
        $row_number = 1;
        $preview_count = 0;

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_number++;
            $result['total_rows']++;

            $sku = isset( $row[ $col_indices['sku'] ] ) ? trim( $row[ $col_indices['sku'] ] ) : '';
            $quantity = isset( $row[ $col_indices['quantity'] ] ) ? intval( $row[ $col_indices['quantity'] ] ) : 0;

            $row_data = array(
                'sku'         => $sku,
                'branch_id'   => $branch_id,
                'branch_name' => $branch_name,
                'quantity'    => $quantity,
                'status'      => 'valid',
                'error'       => '',
            );

            // Validate row
            $error = '';

            if ( empty( $sku ) ) {
                $error = __( 'SKU არ არის მითითებული', 'wbim' );
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
