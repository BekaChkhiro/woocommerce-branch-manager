<?php
/**
 * Branch Selector - Radio Buttons Template
 *
 * @package WBIM
 * @since 1.0.0
 *
 * @var array  $branches          Available branches.
 * @var int    $selected_branch   Selected branch ID.
 * @var array  $customer_location Customer location.
 * @var bool   $show_stock        Whether to show stock info.
 * @var bool   $required          Whether selection is required.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $branches ) ) {
    return;
}
?>

<div class="wbim-branch-selector wbim-branch-selector--radio" id="wbim-branch-selector">
    <h3><?php esc_html_e( 'აირჩიეთ ფილიალი', 'wbim' ); ?></h3>

    <div class="wbim-branch-list">
        <?php foreach ( $branches as $branch ) : ?>
            <?php
            $is_selected = $selected_branch == $branch['id'];
            $branch_class = 'wbim-branch-option';
            if ( ! $branch['can_fulfill'] ) {
                $branch_class .= ' wbim-branch-unavailable';
            }
            if ( $is_selected ) {
                $branch_class .= ' wbim-branch-selected';
            }
            ?>
            <div class="<?php echo esc_attr( $branch_class ); ?>" data-branch-id="<?php echo esc_attr( $branch['id'] ); ?>">
                <label class="wbim-branch-label">
                    <input
                        type="radio"
                        name="wbim_branch_id"
                        value="<?php echo esc_attr( $branch['id'] ); ?>"
                        <?php checked( $is_selected ); ?>
                        <?php echo $required ? 'required' : ''; ?>
                        data-can-fulfill="<?php echo esc_attr( $branch['can_fulfill'] ? '1' : '0' ); ?>"
                    />

                    <span class="wbim-branch-content">
                        <span class="wbim-branch-name">
                            <?php echo esc_html( $branch['name'] ); ?>
                            <?php if ( ! $branch['can_fulfill'] ) : ?>
                                <span class="wbim-unavailable-badge"><?php esc_html_e( 'არასაკმარისი მარაგი', 'wbim' ); ?></span>
                            <?php endif; ?>
                        </span>

                        <?php if ( $branch['address'] ) : ?>
                            <span class="wbim-branch-address">
                                <svg class="wbim-icon" viewBox="0 0 24 24" width="14" height="14">
                                    <path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                <?php echo esc_html( $branch['address'] ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( $branch['phone'] ) : ?>
                            <span class="wbim-branch-phone">
                                <svg class="wbim-icon" viewBox="0 0 24 24" width="14" height="14">
                                    <path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                                </svg>
                                <?php echo esc_html( $branch['phone'] ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( isset( $branch['distance'] ) ) : ?>
                            <span class="wbim-branch-distance">
                                <svg class="wbim-icon" viewBox="0 0 24 24" width="14" height="14">
                                    <path fill="currentColor" d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v3H8v-4c0-.55.45-1 1-1h5V7.5l3.5 3.5-3.5 3.5z"/>
                                </svg>
                                <?php echo esc_html( round( $branch['distance'], 1 ) ); ?> <?php esc_html_e( 'კმ', 'wbim' ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( $show_stock && ! empty( $branch['stock_info'] ) ) : ?>
                            <span class="wbim-branch-stock">
                                <?php
                                $all_sufficient = true;
                                foreach ( $branch['stock_info'] as $item_stock ) {
                                    if ( ! $item_stock['sufficient'] ) {
                                        $all_sufficient = false;
                                        break;
                                    }
                                }
                                if ( $all_sufficient ) : ?>
                                    <span class="wbim-stock-status wbim-stock-ok">
                                        <svg class="wbim-icon" viewBox="0 0 24 24" width="14" height="14">
                                            <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                        </svg>
                                        <?php esc_html_e( 'მარაგი ხელმისაწვდომია', 'wbim' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="wbim-stock-status wbim-stock-low">
                                        <svg class="wbim-icon" viewBox="0 0 24 24" width="14" height="14">
                                            <path fill="currentColor" d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
                                        </svg>
                                        <?php esc_html_e( 'ზოგიერთ პროდუქტზე არასაკმარისი მარაგი', 'wbim' ); ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ( $required ) : ?>
    <p class="wbim-required-note">
        <small><?php esc_html_e( '* ფილიალის არჩევა სავალდებულოა', 'wbim' ); ?></small>
    </p>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.wbim-branch-option input[type="radio"]').on('change', function() {
        var $option = $(this).closest('.wbim-branch-option');

        // Update selected class
        $('.wbim-branch-option').removeClass('wbim-branch-selected');
        $option.addClass('wbim-branch-selected');
    });
});
</script>
