<?php
/**
 * Background Sync Handler
 *
 * Handles background processing for heavy operations like marking
 * products as out of stock after import.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WBIM Background Sync class
 */
class WBIM_Background_Sync {

    /**
     * Action hook name for the background task
     */
    const ACTION_HOOK = 'wbim_background_mark_out_of_stock';

    /**
     * Option name for storing sync status
     */
    const STATUS_OPTION = 'wbim_sync_status';

    /**
     * Initialize the background sync handler
     */
    public static function init() {
        // Register the action handler
        add_action( self::ACTION_HOOK, array( __CLASS__, 'process_mark_out_of_stock' ), 10, 3 );

        // AJAX handler for checking status
        add_action( 'wp_ajax_wbim_check_sync_status', array( __CLASS__, 'ajax_check_status' ) );
    }

    /**
     * Schedule background task to mark missing products as out of stock
     *
     * @param int   $branch_id            Branch ID.
     * @param array $imported_product_ids Array of imported product IDs.
     * @param int   $user_id              User who initiated the import.
     * @return bool|string Task ID or false on failure.
     */
    public static function schedule_mark_out_of_stock( $branch_id, $imported_product_ids, $user_id = 0 ) {
        if ( empty( $branch_id ) ) {
            return false;
        }

        // Generate unique task ID
        $task_id = 'wbim_sync_' . $branch_id . '_' . time();

        // Store initial status
        $status = array(
            'task_id'    => $task_id,
            'branch_id'  => $branch_id,
            'user_id'    => $user_id ?: get_current_user_id(),
            'status'     => 'pending',
            'started_at' => current_time( 'mysql' ),
            'completed_at' => null,
            'results'    => null,
            'imported_count' => count( $imported_product_ids ),
        );

        self::save_status( $task_id, $status );

        // Check if Action Scheduler is available (comes with WooCommerce)
        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action(
                time(),
                self::ACTION_HOOK,
                array(
                    'branch_id'            => $branch_id,
                    'imported_product_ids' => $imported_product_ids,
                    'task_id'              => $task_id,
                ),
                'wbim'
            );
        } else {
            // Fallback to WP Cron
            wp_schedule_single_event(
                time(),
                self::ACTION_HOOK,
                array( $branch_id, $imported_product_ids, $task_id )
            );
        }

        return $task_id;
    }

    /**
     * Process the background task
     *
     * @param int    $branch_id            Branch ID.
     * @param array  $imported_product_ids Array of imported product IDs.
     * @param string $task_id              Task ID for status tracking.
     */
    public static function process_mark_out_of_stock( $branch_id, $imported_product_ids, $task_id ) {
        // Update status to processing
        $status = self::get_status( $task_id );
        if ( $status ) {
            $status['status'] = 'processing';
            self::save_status( $task_id, $status );
        }

        // Run the sync operation
        $results = WBIM_CSV_Handler::mark_missing_out_of_stock( $branch_id, $imported_product_ids );

        // Update status to completed
        if ( $status ) {
            $status['status'] = 'completed';
            $status['completed_at'] = current_time( 'mysql' );
            $status['results'] = array(
                'marked_out_of_stock' => $results['marked_out_of_stock'],
                'errors_count'        => count( $results['errors'] ),
            );
            self::save_status( $task_id, $status );
        }

        // Send admin notification if enabled
        self::maybe_send_notification( $status, $results );
    }

    /**
     * Save sync status
     *
     * @param string $task_id Task ID.
     * @param array  $status  Status data.
     */
    public static function save_status( $task_id, $status ) {
        $all_statuses = get_option( self::STATUS_OPTION, array() );
        $all_statuses[ $task_id ] = $status;

        // Keep only last 20 statuses
        if ( count( $all_statuses ) > 20 ) {
            $all_statuses = array_slice( $all_statuses, -20, 20, true );
        }

        update_option( self::STATUS_OPTION, $all_statuses );
    }

    /**
     * Get sync status
     *
     * @param string $task_id Task ID.
     * @return array|null Status data or null.
     */
    public static function get_status( $task_id ) {
        $all_statuses = get_option( self::STATUS_OPTION, array() );
        return isset( $all_statuses[ $task_id ] ) ? $all_statuses[ $task_id ] : null;
    }

    /**
     * Get latest sync status for a branch
     *
     * @param int $branch_id Branch ID.
     * @return array|null Latest status or null.
     */
    public static function get_latest_status( $branch_id = null ) {
        $all_statuses = get_option( self::STATUS_OPTION, array() );

        if ( empty( $all_statuses ) ) {
            return null;
        }

        // Get latest
        $statuses = array_reverse( $all_statuses, true );

        foreach ( $statuses as $status ) {
            if ( $branch_id === null || $status['branch_id'] == $branch_id ) {
                return $status;
            }
        }

        return null;
    }

    /**
     * AJAX handler for checking sync status
     */
    public static function ajax_check_status() {
        check_ajax_referer( 'wbim_admin', 'nonce' );

        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbim' ) ) );
        }

        $task_id = isset( $_POST['task_id'] ) ? sanitize_text_field( $_POST['task_id'] ) : '';

        if ( empty( $task_id ) ) {
            // Get latest status
            $status = self::get_latest_status();
        } else {
            $status = self::get_status( $task_id );
        }

        if ( ! $status ) {
            wp_send_json_error( array( 'message' => __( 'სტატუსი ვერ მოიძებნა.', 'wbim' ) ) );
        }

        wp_send_json_success( $status );
    }

    /**
     * Maybe send notification when sync completes
     *
     * @param array $status  Sync status.
     * @param array $results Sync results.
     */
    private static function maybe_send_notification( $status, $results ) {
        // Check if notifications are enabled
        $notify = WBIM_Utils::get_setting( 'notify_sync_complete', false );

        if ( ! $notify ) {
            return;
        }

        $user = get_user_by( 'id', $status['user_id'] );
        if ( ! $user || ! $user->user_email ) {
            return;
        }

        $branch = WBIM_Branch::get_by_id( $status['branch_id'] );
        $branch_name = $branch ? $branch->name : __( 'უცნობი', 'wbim' );

        $subject = sprintf(
            /* translators: %s: Branch name */
            __( '[WBIM] მარაგის სინქრონიზაცია დასრულდა - %s', 'wbim' ),
            $branch_name
        );

        $message = sprintf(
            /* translators: 1: Branch name, 2: Count of marked products */
            __( "მარაგის სინქრონიზაცია ფილიალში \"%1\$s\" დასრულდა.\n\n%2\$d პროდუქტი მოინიშნა როგორც \"არ არის მარაგში\".", 'wbim' ),
            $branch_name,
            $results['marked_out_of_stock']
        );

        wp_mail( $user->user_email, $subject, $message );
    }

    /**
     * Check if there's a pending/processing sync for a branch
     *
     * @param int $branch_id Branch ID.
     * @return bool True if sync is in progress.
     */
    public static function is_sync_in_progress( $branch_id ) {
        $status = self::get_latest_status( $branch_id );

        if ( ! $status ) {
            return false;
        }

        return in_array( $status['status'], array( 'pending', 'processing' ), true );
    }
}
