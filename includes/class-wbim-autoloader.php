<?php
/**
 * Autoloader class for WBIM
 *
 * Handles automatic loading of plugin classes.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Autoloader class
 *
 * @since 1.0.0
 */
class WBIM_Autoloader {

    /**
     * Path to the includes directory
     *
     * @var string
     */
    private static $include_path = '';

    /**
     * Initialize the autoloader
     *
     * @return void
     */
    public static function init() {
        if ( '' === self::$include_path ) {
            self::$include_path = WBIM_PLUGIN_DIR;
        }

        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload classes
     *
     * @param string $class Class name to load.
     * @return void
     */
    public static function autoload( $class ) {
        // Only autoload WBIM classes
        if ( 0 !== strpos( $class, 'WBIM_' ) ) {
            return;
        }

        $class = strtolower( $class );
        $file  = self::get_file_name_from_class( $class );

        $paths = self::get_class_paths( $class );

        foreach ( $paths as $path ) {
            self::load_file( $path . $file );
        }
    }

    /**
     * Get file name from class name
     *
     * @param string $class Class name.
     * @return string
     */
    private static function get_file_name_from_class( $class ) {
        return 'class-' . str_replace( '_', '-', $class ) . '.php';
    }

    /**
     * Get possible paths for a class
     *
     * @param string $class Class name.
     * @return array
     */
    private static function get_class_paths( $class ) {
        $paths = array(
            self::$include_path . 'includes/',
            self::$include_path . 'includes/models/',
            self::$include_path . 'includes/helpers/',
            self::$include_path . 'admin/',
            self::$include_path . 'public/',
            self::$include_path . 'api/',
        );

        // Add specific paths based on class name patterns
        if ( strpos( $class, 'wbim_admin' ) !== false ) {
            array_unshift( $paths, self::$include_path . 'admin/' );
        }

        if ( strpos( $class, '_model' ) !== false || in_array( $class, array( 'wbim_branch', 'wbim_stock', 'wbim_transfer', 'wbim_transfer_item', 'wbim_stock_log', 'wbim_order_allocation' ), true ) ) {
            array_unshift( $paths, self::$include_path . 'includes/models/' );
        }

        if ( strpos( $class, 'wbim_utils' ) !== false || strpos( $class, 'wbim_export' ) !== false ) {
            array_unshift( $paths, self::$include_path . 'includes/helpers/' );
        }

        if ( strpos( $class, 'wbim_public' ) !== false || strpos( $class, 'wbim_checkout' ) !== false ) {
            array_unshift( $paths, self::$include_path . 'public/' );
        }

        if ( strpos( $class, 'wbim_rest' ) !== false ) {
            array_unshift( $paths, self::$include_path . 'api/' );
        }

        return $paths;
    }

    /**
     * Load a file if it exists
     *
     * @param string $path File path.
     * @return bool
     */
    private static function load_file( $path ) {
        if ( $path && is_readable( $path ) ) {
            include_once $path;
            return true;
        }
        return false;
    }
}
