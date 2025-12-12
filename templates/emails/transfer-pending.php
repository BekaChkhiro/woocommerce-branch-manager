<?php
/**
 * Transfer Pending Email Template
 *
 * @package WBIM
 * @since 1.0.0
 *
 * @var object $transfer Transfer object.
 * @var array  $items    Transfer items.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2 style="color: #333; margin-top: 0;">
    <?php
    printf(
        /* translators: %s: Transfer number */
        esc_html__( 'ახალი გადატანის მოთხოვნა #%s', 'wbim' ),
        esc_html( $transfer->transfer_number )
    );
    ?>
</h2>

<p style="margin-bottom: 20px;">
    <?php esc_html_e( 'თქვენს ფილიალში მოელის ახალი მარაგის გადატანა. გთხოვთ გადახედოთ დეტალებს ქვემოთ.', 'wbim' ); ?>
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9; width: 150px;">
            <strong><?php esc_html_e( 'გადატანის ნომერი:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            #<?php echo esc_html( $transfer->transfer_number ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9;">
            <strong><?php esc_html_e( 'წყარო ფილიალი:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <?php echo esc_html( $transfer->source_branch_name ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9;">
            <strong><?php esc_html_e( 'დანიშნულება:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <?php echo esc_html( $transfer->destination_branch_name ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9;">
            <strong><?php esc_html_e( 'სტატუსი:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 3px; font-weight: 500;">
                <?php echo esc_html( WBIM_Transfer::get_status_label( $transfer->status ) ); ?>
            </span>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9;">
            <strong><?php esc_html_e( 'თარიღი:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $transfer->created_at ) ) ); ?>
        </td>
    </tr>
    <?php if ( $transfer->created_by_name ) : ?>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9;">
            <strong><?php esc_html_e( 'შექმნილია:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <?php echo esc_html( $transfer->created_by_name ); ?>
        </td>
    </tr>
    <?php endif; ?>
</table>

<h3 style="margin: 30px 0 15px; color: #333;"><?php esc_html_e( 'პროდუქტები', 'wbim' ); ?></h3>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
    <thead>
        <tr>
            <th style="padding: 12px; border: 1px solid #e5e5e5; background: #f5f5f5; text-align: left;">
                <?php esc_html_e( 'პროდუქტი', 'wbim' ); ?>
            </th>
            <th style="padding: 12px; border: 1px solid #e5e5e5; background: #f5f5f5; text-align: left; width: 100px;">
                <?php esc_html_e( 'SKU', 'wbim' ); ?>
            </th>
            <th style="padding: 12px; border: 1px solid #e5e5e5; background: #f5f5f5; text-align: center; width: 100px;">
                <?php esc_html_e( 'რაოდენობა', 'wbim' ); ?>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php
        $total_qty = 0;
        foreach ( $items as $item ) :
            $total_qty += $item->quantity;
        ?>
            <tr>
                <td style="padding: 12px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $item->product_name ); ?>
                </td>
                <td style="padding: 12px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $item->sku ?: '-' ); ?>
                </td>
                <td style="padding: 12px; border: 1px solid #e5e5e5; text-align: center;">
                    <?php echo esc_html( $item->quantity ); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" style="padding: 12px; border: 1px solid #e5e5e5; background: #f5f5f5; text-align: right;">
                <strong><?php esc_html_e( 'ჯამი:', 'wbim' ); ?></strong>
            </td>
            <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f5f5f5; text-align: center;">
                <strong><?php echo esc_html( $total_qty ); ?></strong>
            </td>
        </tr>
    </tfoot>
</table>

<?php if ( $transfer->notes ) : ?>
<div style="background: #fffef0; border: 1px solid #ffe58f; padding: 15px; margin-bottom: 30px;">
    <strong><?php esc_html_e( 'შენიშვნები:', 'wbim' ); ?></strong>
    <p style="margin: 10px 0 0;"><?php echo esc_html( $transfer->notes ); ?></p>
</div>
<?php endif; ?>

<p style="margin-top: 30px;">
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=edit&id=' . $transfer->id ) ); ?>"
       style="display: inline-block; padding: 12px 25px; background: #7f54b3; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 500;">
        <?php esc_html_e( 'გადატანის ნახვა', 'wbim' ); ?>
    </a>
</p>
