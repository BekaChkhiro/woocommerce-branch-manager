<?php
/**
 * Low Stock Alert Email Template
 *
 * @package WBIM
 * @since 1.0.0
 *
 * @var WC_Product $product Product object.
 * @var object     $branch  Branch object.
 * @var object     $stock   Stock object.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2 style="color: #dc3545; margin-top: 0;">
    <?php esc_html_e( 'დაბალი მარაგის გაფრთხილება', 'wbim' ); ?>
</h2>

<p style="margin-bottom: 20px;">
    <?php
    printf(
        /* translators: 1: Product name, 2: Branch name */
        esc_html__( 'პროდუქტი "%1$s" ფილიალში "%2$s" დაბალ მარაგზეა და საჭიროებს შევსებას.', 'wbim' ),
        '<strong>' . esc_html( $product->get_name() ) . '</strong>',
        '<strong>' . esc_html( $branch->name ) . '</strong>'
    );
    ?>
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9; width: 150px;">
            <strong><?php esc_html_e( 'პროდუქტი:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <?php echo esc_html( $product->get_name() ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9;">
            <strong><?php esc_html_e( 'SKU:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <?php echo esc_html( $product->get_sku() ?: '-' ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9;">
            <strong><?php esc_html_e( 'ფილიალი:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <?php echo esc_html( $branch->name ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #fff3cd;">
            <strong><?php esc_html_e( 'მიმდინარე მარაგი:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #fff3cd;">
            <span style="font-size: 18px; font-weight: bold; color: #dc3545;">
                <?php echo esc_html( $stock->quantity ); ?>
            </span>
            <?php esc_html_e( 'ერთეული', 'wbim' ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px; border: 1px solid #e5e5e5; background: #f9f9f9;">
            <strong><?php esc_html_e( 'დაბალი მარაგის ზღვარი:', 'wbim' ); ?></strong>
        </td>
        <td style="padding: 12px; border: 1px solid #e5e5e5;">
            <?php echo esc_html( $stock->low_stock_threshold ); ?> <?php esc_html_e( 'ერთეული', 'wbim' ); ?>
        </td>
    </tr>
</table>

<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 30px; border-radius: 4px;">
    <strong style="color: #721c24;"><?php esc_html_e( 'რეკომენდაცია:', 'wbim' ); ?></strong>
    <p style="margin: 10px 0 0; color: #721c24;">
        <?php esc_html_e( 'გთხოვთ განიხილოთ მარაგის შევსება ან სხვა ფილიალიდან გადატანა მარაგის დეფიციტის თავიდან ასაცილებლად.', 'wbim' ); ?>
    </p>
</div>

<p style="margin-top: 30px;">
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-stock' ) ); ?>"
       style="display: inline-block; padding: 12px 25px; background: #7f54b3; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 500;">
        <?php esc_html_e( 'მარაგის მართვა', 'wbim' ); ?>
    </a>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=new' ) ); ?>"
       style="display: inline-block; padding: 12px 25px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 500; margin-left: 10px;">
        <?php esc_html_e( 'ახალი გადატანა', 'wbim' ); ?>
    </a>
</p>
