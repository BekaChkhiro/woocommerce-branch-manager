<?php
/**
 * PDF Generator Class
 *
 * Generates PDF documents for transfers using HTML to PDF conversion.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PDF Generator class
 *
 * @since 1.0.0
 */
class WBIM_PDF_Generator {

    /**
     * Generate transfer PDF
     *
     * @param int $transfer_id Transfer ID.
     */
    public static function generate_transfer_pdf( $transfer_id ) {
        if ( ! $transfer_id ) {
            wp_die( __( 'გადატანის ID არ არის მითითებული.', 'wbim' ) );
        }

        $transfer = WBIM_Transfer::get_by_id( $transfer_id );

        if ( ! $transfer ) {
            wp_die( __( 'გადატანა ვერ მოიძებნა.', 'wbim' ) );
        }

        // Check permission
        if ( ! current_user_can( 'manage_woocommerce' ) && ! WBIM_Transfer::user_can_manage( $transfer_id ) ) {
            wp_die( __( 'არ გაქვთ უფლება.', 'wbim' ) );
        }

        $items = WBIM_Transfer_Item::get_by_transfer_with_products( $transfer_id );

        // Generate HTML
        $html = self::generate_transfer_html( $transfer, $items );

        // Check if TCPDF or Dompdf is available
        if ( class_exists( 'TCPDF' ) ) {
            self::generate_with_tcpdf( $html, $transfer );
        } elseif ( class_exists( 'Dompdf\\Dompdf' ) ) {
            self::generate_with_dompdf( $html, $transfer );
        } else {
            // Fallback: Display HTML for printing
            self::display_printable_html( $html, $transfer );
        }
    }

    /**
     * Generate transfer HTML
     *
     * @param object $transfer Transfer object.
     * @param array  $items    Transfer items.
     * @return string HTML content.
     */
    private static function generate_transfer_html( $transfer, $items ) {
        $site_name = get_bloginfo( 'name' );

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php printf( esc_html__( 'გადატანა #%s', 'wbim' ), esc_html( $transfer->transfer_number ) ); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        .header-left h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header-left p {
            color: #666;
        }
        .header-right {
            text-align: right;
        }
        .header-right .transfer-number {
            font-size: 18px;
            font-weight: bold;
        }
        .header-right .transfer-date {
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        .status-draft { background: #e0e0e0; color: #333; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_transit { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .info-section {
            display: flex;
            margin-bottom: 30px;
        }
        .info-box {
            flex: 1;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        .info-box:first-child {
            margin-right: 20px;
        }
        .info-box h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #666;
            text-transform: uppercase;
        }
        .info-box p {
            font-size: 16px;
            font-weight: bold;
        }
        .info-box .address {
            font-weight: normal;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .items-table th {
            background: #f5f5f5;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            color: #666;
        }
        .items-table td {
            font-size: 12px;
        }
        .items-table .col-number {
            width: 50px;
            text-align: center;
        }
        .items-table .col-sku {
            width: 100px;
        }
        .items-table .col-qty {
            width: 80px;
            text-align: center;
        }
        .items-table tfoot td {
            font-weight: bold;
            background: #f5f5f5;
        }

        .signature-section {
            display: flex;
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-box {
            flex: 1;
            padding: 20px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 10px;
        }
        .signature-label {
            font-size: 11px;
            color: #666;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }

        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background: #fffef0;
            border: 1px solid #ffe58f;
        }
        .notes-section h3 {
            font-size: 12px;
            margin-bottom: 5px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1><?php echo esc_html( $site_name ); ?></h1>
                <p><?php esc_html_e( 'მარაგის გადატანის დოკუმენტი', 'wbim' ); ?></p>
            </div>
            <div class="header-right">
                <div class="transfer-number">#<?php echo esc_html( $transfer->transfer_number ); ?></div>
                <div class="transfer-date"><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $transfer->created_at ) ) ); ?></div>
                <div class="status-badge status-<?php echo esc_attr( $transfer->status ); ?>">
                    <?php echo esc_html( WBIM_Transfer::get_status_label( $transfer->status ) ); ?>
                </div>
            </div>
        </div>

        <div class="info-section">
            <div class="info-box">
                <h3><?php esc_html_e( 'წყარო ფილიალი', 'wbim' ); ?></h3>
                <p><?php echo esc_html( $transfer->source_branch_name ); ?></p>
                <?php
                $source_branch = WBIM_Branch::get_by_id( $transfer->source_branch_id );
                if ( $source_branch && $source_branch->address ) :
                ?>
                    <p class="address"><?php echo esc_html( $source_branch->address ); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-box">
                <h3><?php esc_html_e( 'დანიშნულება', 'wbim' ); ?></h3>
                <p><?php echo esc_html( $transfer->destination_branch_name ); ?></p>
                <?php
                $dest_branch = WBIM_Branch::get_by_id( $transfer->destination_branch_id );
                if ( $dest_branch && $dest_branch->address ) :
                ?>
                    <p class="address"><?php echo esc_html( $dest_branch->address ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-number">#</th>
                    <th><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                    <th class="col-sku"><?php esc_html_e( 'SKU', 'wbim' ); ?></th>
                    <th class="col-qty"><?php esc_html_e( 'რაოდენობა', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total_qty = 0;
                $row_num = 1;
                foreach ( $items as $item ) :
                    $total_qty += $item->quantity;
                ?>
                <tr>
                    <td class="col-number"><?php echo esc_html( $row_num++ ); ?></td>
                    <td>
                        <?php echo esc_html( $item->product_name ); ?>
                        <?php if ( isset( $item->variation_attributes ) && ! empty( $item->variation_attributes ) ) : ?>
                            <br><small style="color: #666;"><?php echo esc_html( implode( ', ', $item->variation_attributes ) ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="col-sku"><?php echo esc_html( $item->sku ?: '-' ); ?></td>
                    <td class="col-qty"><?php echo esc_html( $item->quantity ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right;"><?php esc_html_e( 'ჯამი:', 'wbim' ); ?></td>
                    <td class="col-qty"><?php echo esc_html( $total_qty ); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php if ( $transfer->notes ) : ?>
        <div class="notes-section">
            <h3><?php esc_html_e( 'შენიშვნები:', 'wbim' ); ?></h3>
            <p><?php echo esc_html( $transfer->notes ); ?></p>
        </div>
        <?php endif; ?>

        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">
                    <span class="signature-label"><?php esc_html_e( 'გამგზავნი', 'wbim' ); ?></span>
                </div>
                <?php if ( $transfer->sent_by_name ) : ?>
                    <p style="margin-top: 5px; font-size: 11px;"><?php echo esc_html( $transfer->sent_by_name ); ?></p>
                <?php endif; ?>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    <span class="signature-label"><?php esc_html_e( 'მიმღები', 'wbim' ); ?></span>
                </div>
                <?php if ( $transfer->received_by_name ) : ?>
                    <p style="margin-top: 5px; font-size: 11px;"><?php echo esc_html( $transfer->received_by_name ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <p>
                <?php
                printf(
                    /* translators: 1: Date, 2: Site name */
                    esc_html__( 'დოკუმენტი გენერირებულია: %1$s | %2$s', 'wbim' ),
                    wp_date( 'd/m/Y H:i' ),
                    esc_html( $site_name )
                );
                ?>
            </p>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate PDF using TCPDF
     *
     * @param string $html     HTML content.
     * @param object $transfer Transfer object.
     */
    private static function generate_with_tcpdf( $html, $transfer ) {
        // Create new PDF document
        $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );

        // Set document information
        $pdf->SetCreator( get_bloginfo( 'name' ) );
        $pdf->SetAuthor( get_bloginfo( 'name' ) );
        $pdf->SetTitle( sprintf( __( 'გადატანა #%s', 'wbim' ), $transfer->transfer_number ) );

        // Remove default header/footer
        $pdf->setPrintHeader( false );
        $pdf->setPrintFooter( false );

        // Set margins
        $pdf->SetMargins( 15, 15, 15 );

        // Add a page
        $pdf->AddPage();

        // Write HTML content
        $pdf->writeHTML( $html, true, false, true, false, '' );

        // Output PDF
        $filename = 'transfer-' . $transfer->transfer_number . '.pdf';
        $pdf->Output( $filename, 'D' );
        exit;
    }

    /**
     * Generate PDF using Dompdf
     *
     * @param string $html     HTML content.
     * @param object $transfer Transfer object.
     */
    private static function generate_with_dompdf( $html, $transfer ) {
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        $filename = 'transfer-' . $transfer->transfer_number . '.pdf';
        $dompdf->stream( $filename, array( 'Attachment' => true ) );
        exit;
    }

    /**
     * Display printable HTML (fallback when no PDF library available)
     *
     * @param string $html     HTML content.
     * @param object $transfer Transfer object.
     */
    private static function display_printable_html( $html, $transfer ) {
        // Add print button
        $print_button = '
        <style>
            .print-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: #333;
                color: #fff;
                padding: 10px 20px;
                z-index: 1000;
            }
            .print-header button {
                background: #0073aa;
                color: #fff;
                border: none;
                padding: 10px 20px;
                cursor: pointer;
                font-size: 14px;
                margin-right: 10px;
            }
            .print-header button:hover {
                background: #005177;
            }
            @media print {
                .print-header {
                    display: none;
                }
            }
            body {
                padding-top: 60px;
            }
        </style>
        <div class="print-header">
            <button onclick="window.print();">' . esc_html__( 'ბეჭდვა', 'wbim' ) . '</button>
            <button onclick="window.close();">' . esc_html__( 'დახურვა', 'wbim' ) . '</button>
        </div>';

        // Insert print header after <body>
        $html = str_replace( '<body>', '<body>' . $print_button, $html );

        // Output HTML
        echo $html;
        exit;
    }
}
