<?php
/**
 * Branch Stock Display Template
 *
 * Displays branch stock information on product page.
 *
 * @package WBIM
 * @since 1.0.0
 *
 * @var int   $product_id    Product ID.
 * @var int   $variation_id  Variation ID.
 * @var array $branches      Active branches.
 * @var bool  $show_quantity Whether to show exact quantity.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $branches ) ) {
    return;
}
?>

<div class="wbim-branch-stock-container" data-product-id="<?php echo esc_attr( $product_id ); ?>">
    <h4 class="wbim-stock-title"><?php esc_html_e( 'ფილიალების მარაგი', 'wbim' ); ?></h4>

    <div class="wbim-branch-stock-list">
        <?php foreach ( $branches as $branch ) : ?>
            <?php
            $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
            $quantity = $stock ? (int) $stock->quantity : 0;
            $stock_status = $stock && isset( $stock->stock_status ) ? $stock->stock_status : 'instock';
            $low_threshold = $stock ? $stock->low_stock_threshold : 0;

            // Map stock status to CSS class and display text
            $status_classes = array(
                'instock'    => 'wbim-stock-in',
                'low'        => 'wbim-stock-low',
                'outofstock' => 'wbim-stock-out',
                'preorder'   => 'wbim-stock-preorder',
            );

            $status_texts = array(
                'instock'    => __( 'მარაგშია', 'wbim' ),
                'low'        => __( 'მცირე რაოდენობა', 'wbim' ),
                'outofstock' => __( 'არ არის მარაგში', 'wbim' ),
                'preorder'   => __( 'წინასწარი შეკვეთით', 'wbim' ),
            );

            $status_class = isset( $status_classes[ $stock_status ] ) ? $status_classes[ $stock_status ] : 'wbim-stock-out';
            $status_text = isset( $status_texts[ $stock_status ] ) ? $status_texts[ $stock_status ] : __( 'არ არის მარაგში', 'wbim' );

            // Show quantity only for instock and low statuses
            $can_show_quantity = in_array( $stock_status, array( 'instock', 'low' ), true );
            ?>
            <div class="wbim-branch-stock-item <?php echo esc_attr( $status_class ); ?>">
                <span class="wbim-branch-name"><?php echo esc_html( $branch->name ); ?></span>
                <span class="wbim-stock-status">
                    <?php if ( $show_quantity && $can_show_quantity && $quantity > 0 ) : ?>
                        <?php echo esc_html( $status_text ); ?>
                        <span class="wbim-stock-quantity">(<?php echo esc_html( $quantity ); ?> <?php esc_html_e( 'ცალი', 'wbim' ); ?>)</span>
                    <?php else : ?>
                        <?php echo esc_html( $status_text ); ?>
                    <?php endif; ?>
                </span>
                <span class="wbim-stock-indicator"></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Show additional branch info if configured
    $settings = get_option( 'wbim_settings', array() );
    if ( ! empty( $settings['show_branch_contact'] ) && 'yes' === $settings['show_branch_contact'] ) :
    ?>
    <div class="wbim-branch-contact-toggle">
        <button type="button" class="wbim-toggle-btn" id="wbim-show-branches">
            <?php esc_html_e( 'ფილიალების კონტაქტი', 'wbim' ); ?>
            <svg class="wbim-toggle-icon" viewBox="0 0 24 24" width="16" height="16">
                <path fill="currentColor" d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
            </svg>
        </button>
        <div class="wbim-branch-contacts" style="display: none;">
            <?php foreach ( $branches as $branch ) : ?>
                <div class="wbim-branch-contact-item">
                    <strong><?php echo esc_html( $branch->name ); ?></strong>
                    <?php if ( $branch->address ) : ?>
                        <div class="wbim-contact-address">
                            <svg viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <?php echo esc_html( $branch->address ); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ( $branch->phone ) : ?>
                        <div class="wbim-contact-phone">
                            <svg viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                            </svg>
                            <a href="tel:<?php echo esc_attr( $branch->phone ); ?>"><?php echo esc_html( $branch->phone ); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#wbim-show-branches').on('click', function() {
            var $contacts = $(this).siblings('.wbim-branch-contacts');
            var $icon = $(this).find('.wbim-toggle-icon');

            $contacts.slideToggle(200);
            $icon.toggleClass('wbim-rotated');
        });
    });
    </script>
    <?php endif; ?>
</div>
