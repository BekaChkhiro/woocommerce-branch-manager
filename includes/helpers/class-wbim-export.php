<?php
/**
 * Export Helper Class
 *
 * Handles export functionality for reports (CSV, PDF).
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Export class
 *
 * @since 1.0.0
 */
class WBIM_Export {

    /**
     * Export directory
     *
     * @var string
     */
    private static $export_dir = '';

    /**
     * Export URL
     *
     * @var string
     */
    private static $export_url = '';

    /**
     * Initialize export paths
     *
     * @return void
     */
    private static function init_paths() {
        if ( empty( self::$export_dir ) ) {
            $upload_dir = wp_upload_dir();
            self::$export_dir = $upload_dir['basedir'] . '/wbim-exports/';
            self::$export_url = $upload_dir['baseurl'] . '/wbim-exports/';

            // Create directory if it doesn't exist
            if ( ! file_exists( self::$export_dir ) ) {
                wp_mkdir_p( self::$export_dir );

                // Add index.php for security
                file_put_contents( self::$export_dir . 'index.php', '<?php // Silence is golden' );

                // Add .htaccess to deny direct access
                file_put_contents( self::$export_dir . '.htaccess', 'deny from all' );
            }
        }
    }

    /**
     * Export to CSV
     *
     * @param array  $rows     Data rows.
     * @param array  $headers  Column headers.
     * @param string $filename Filename without extension.
     * @return string|WP_Error File URL on success, WP_Error on failure.
     */
    public static function to_csv( $rows, $headers, $filename ) {
        self::init_paths();

        $filename = sanitize_file_name( $filename ) . '-' . time() . '.csv';
        $filepath = self::$export_dir . $filename;

        // Open file for writing
        $file = fopen( $filepath, 'w' );
        if ( ! $file ) {
            return new WP_Error( 'file_error', __( 'ფაილის შექმნა ვერ მოხერხდა.', 'wbim' ) );
        }

        // Add BOM for UTF-8 Excel compatibility
        fwrite( $file, "\xEF\xBB\xBF" );

        // Write headers
        fputcsv( $file, $headers, ';' );

        // Write rows
        foreach ( $rows as $row ) {
            // Convert HTML entities and strip tags
            $row = array_map( function( $cell ) {
                $cell = wp_strip_all_tags( $cell );
                $cell = html_entity_decode( $cell, ENT_QUOTES, 'UTF-8' );
                return $cell;
            }, $row );

            fputcsv( $file, $row, ';' );
        }

        fclose( $file );

        // Schedule cleanup
        self::schedule_cleanup();

        return self::$export_url . $filename;
    }

    /**
     * Export to PDF
     *
     * @param array  $rows     Data rows.
     * @param array  $headers  Column headers.
     * @param string $title    Report title.
     * @param string $filename Filename without extension.
     * @return string|WP_Error File URL on success, WP_Error on failure.
     */
    public static function to_pdf( $rows, $headers, $title, $filename ) {
        self::init_paths();

        // Since we don't want to include a PDF library, generate HTML for print
        $filename = sanitize_file_name( $filename ) . '-' . time() . '.html';
        $filepath = self::$export_dir . $filename;

        $settings = get_option( 'wbim_settings', array() );
        $company_name = isset( $settings['company_name'] ) ? $settings['company_name'] : get_bloginfo( 'name' );
        $footer_text = isset( $settings['pdf_footer_text'] ) ? $settings['pdf_footer_text'] : '';

        // Build HTML content
        $html = '<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html( $title ) . '</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@400;600;700&display=swap");

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Noto Sans Georgian", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .report-title {
            font-size: 18px;
            color: #666;
        }

        .report-date {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
        }

        th {
            background-color: #4a5568;
            color: white;
            font-weight: 600;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:last-child {
            font-weight: bold;
            background-color: #e2e8f0;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }

            @page {
                margin: 1cm;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4a5568;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .print-button:hover {
            background: #2d3748;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        <span style="margin-right: 5px;">🖨</span> დაბეჭდვა / PDF
    </button>

    <div class="header">
        <div class="company-name">' . esc_html( $company_name ) . '</div>
        <div class="report-title">' . esc_html( $title ) . '</div>
        <div class="report-date">' . esc_html__( 'გენერირებულია:', 'wbim' ) . ' ' . esc_html( current_time( 'd/m/Y H:i' ) ) . '</div>
    </div>

    <table>
        <thead>
            <tr>';

        foreach ( $headers as $header ) {
            $html .= '<th>' . esc_html( $header ) . '</th>';
        }

        $html .= '</tr>
        </thead>
        <tbody>';

        foreach ( $rows as $row ) {
            $html .= '<tr>';
            foreach ( $row as $cell ) {
                $cell = wp_strip_all_tags( $cell );
                $cell = html_entity_decode( $cell, ENT_QUOTES, 'UTF-8' );
                $html .= '<td>' . esc_html( $cell ) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>
    </table>';

        if ( ! empty( $footer_text ) ) {
            $html .= '<div class="footer">' . esc_html( $footer_text ) . '</div>';
        }

        $html .= '
</body>
</html>';

        // Save file
        $result = file_put_contents( $filepath, $html );
        if ( ! $result ) {
            return new WP_Error( 'file_error', __( 'ფაილის შექმნა ვერ მოხერხდა.', 'wbim' ) );
        }

        // Schedule cleanup
        self::schedule_cleanup();

        return self::$export_url . $filename;
    }

    /**
     * Schedule cleanup of old export files
     *
     * @return void
     */
    private static function schedule_cleanup() {
        if ( ! wp_next_scheduled( 'wbim_cleanup_exports' ) ) {
            wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'wbim_cleanup_exports' );
        }
    }

    /**
     * Cleanup old export files
     *
     * @return void
     */
    public static function cleanup_exports() {
        self::init_paths();

        $files = glob( self::$export_dir . '*' );
        $now = time();

        foreach ( $files as $file ) {
            // Skip index.php and .htaccess
            if ( basename( $file ) === 'index.php' || basename( $file ) === '.htaccess' ) {
                continue;
            }

            // Delete files older than 1 hour
            if ( is_file( $file ) && ( $now - filemtime( $file ) ) > HOUR_IN_SECONDS ) {
                unlink( $file );
            }
        }
    }

    /**
     * Stream download directly (for large files)
     *
     * @param string $content   File content.
     * @param string $filename  Filename.
     * @param string $mime_type MIME type.
     * @return void
     */
    public static function stream_download( $content, $filename, $mime_type = 'text/csv' ) {
        // Clean output buffer
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        // Set headers
        header( 'Content-Type: ' . $mime_type . '; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $content ) );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Expires: 0' );

        // Output content
        echo $content;
        exit;
    }
}

// Register cleanup hook
add_action( 'wbim_cleanup_exports', array( 'WBIM_Export', 'cleanup_exports' ) );
