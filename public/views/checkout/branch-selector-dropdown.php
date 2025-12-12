<?php
/**
 * Branch Selector - Dropdown Template
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

<div class="wbim-branch-selector wbim-branch-selector--dropdown" id="wbim-branch-selector">
    <h3><?php esc_html_e( 'აირჩიეთ ფილიალი', 'wbim' ); ?></h3>

    <p class="form-row form-row-wide" id="wbim_branch_id_field">
        <label for="wbim_branch_id">
            <?php esc_html_e( 'ფილიალი', 'wbim' ); ?>
            <?php if ( $required ) : ?>
                <abbr class="required" title="<?php esc_attr_e( 'სავალდებულო', 'wbim' ); ?>">*</abbr>
            <?php endif; ?>
        </label>

        <select name="wbim_branch_id" id="wbim_branch_id" class="wbim-branch-select" <?php echo $required ? 'required' : ''; ?>>
            <option value=""><?php esc_html_e( '-- აირჩიეთ ფილიალი --', 'wbim' ); ?></option>
            <?php foreach ( $branches as $branch ) : ?>
                <option
                    value="<?php echo esc_attr( $branch['id'] ); ?>"
                    <?php selected( $selected_branch, $branch['id'] ); ?>
                    <?php echo ! $branch['can_fulfill'] ? 'class="wbim-branch-unavailable"' : ''; ?>
                    data-can-fulfill="<?php echo esc_attr( $branch['can_fulfill'] ? '1' : '0' ); ?>"
                    data-address="<?php echo esc_attr( $branch['address'] ); ?>"
                    data-phone="<?php echo esc_attr( $branch['phone'] ); ?>"
                    <?php if ( isset( $branch['distance'] ) ) : ?>
                        data-distance="<?php echo esc_attr( round( $branch['distance'], 1 ) ); ?>"
                    <?php endif; ?>
                >
                    <?php
                    echo esc_html( $branch['name'] );

                    if ( isset( $branch['distance'] ) ) {
                        echo ' (' . esc_html( round( $branch['distance'], 1 ) ) . ' კმ)';
                    }

                    if ( ! $branch['can_fulfill'] ) {
                        echo ' - ' . esc_html__( 'არასაკმარისი მარაგი', 'wbim' );
                    }
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <div class="wbim-branch-info" id="wbim-branch-info" style="display: none;">
        <div class="wbim-branch-address"></div>
        <div class="wbim-branch-phone"></div>
    </div>

    <?php if ( $show_stock ) : ?>
    <div class="wbim-branch-stock-info" id="wbim-branch-stock-info" style="display: none;">
        <h4><?php esc_html_e( 'მარაგის სტატუსი', 'wbim' ); ?></h4>
        <div class="wbim-stock-list"></div>
    </div>
    <?php endif; ?>

    <div class="wbim-branch-warning" id="wbim-branch-warning" style="display: none;">
        <span class="wbim-warning-icon">⚠</span>
        <span class="wbim-warning-text"><?php esc_html_e( 'არჩეულ ფილიალში ზოგიერთ პროდუქტზე არასაკმარისი მარაგია.', 'wbim' ); ?></span>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var $selector = $('#wbim_branch_id');
    var $info = $('#wbim-branch-info');
    var $warning = $('#wbim-branch-warning');
    var $stockInfo = $('#wbim-branch-stock-info');

    $selector.on('change', function() {
        var $selected = $(this).find('option:selected');
        var branchId = $(this).val();

        if (!branchId) {
            $info.hide();
            $warning.hide();
            $stockInfo.hide();
            return;
        }

        // Show branch info
        var address = $selected.data('address');
        var phone = $selected.data('phone');
        var canFulfill = $selected.data('can-fulfill');

        if (address || phone) {
            $info.find('.wbim-branch-address').html(address ? '<strong><?php esc_html_e( 'მისამართი:', 'wbim' ); ?></strong> ' + address : '');
            $info.find('.wbim-branch-phone').html(phone ? '<strong><?php esc_html_e( 'ტელეფონი:', 'wbim' ); ?></strong> ' + phone : '');
            $info.show();
        } else {
            $info.hide();
        }

        // Show warning if can't fulfill
        if (!canFulfill) {
            $warning.show();
        } else {
            $warning.hide();
        }

        <?php if ( $show_stock ) : ?>
        // Load stock info via AJAX
        $.ajax({
            url: wbim_checkout.ajax_url,
            type: 'POST',
            data: {
                action: 'wbim_get_branch_stock',
                nonce: wbim_checkout.nonce,
                branch_id: branchId
            },
            success: function(response) {
                if (response.success && response.data.stock_info) {
                    var html = '<ul>';
                    $.each(response.data.stock_info, function(key, item) {
                        var statusClass = item.sufficient ? 'wbim-stock-ok' : 'wbim-stock-low';
                        html += '<li class="' + statusClass + '">';
                        html += '<?php esc_html_e( 'საჭირო:', 'wbim' ); ?> ' + item.needed + ', ';
                        html += '<?php esc_html_e( 'ხელმისაწვდომი:', 'wbim' ); ?> ' + item.available;
                        html += '</li>';
                    });
                    html += '</ul>';
                    $stockInfo.find('.wbim-stock-list').html(html);
                    $stockInfo.show();
                }
            }
        });
        <?php endif; ?>
    });

    // Trigger change on load if branch is selected
    if ($selector.val()) {
        $selector.trigger('change');
    }
});
</script>
