<?php
/**
 * New Transfer View
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wbim-new-transfer-page">
    <h1><?php esc_html_e( 'ახალი გადატანა', 'wbim' ); ?></h1>

    <form id="wbim-new-transfer-form" class="wbim-transfer-form">
        <?php wp_nonce_field( 'wbim_admin_nonce', 'wbim_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="source_branch"><?php esc_html_e( 'წყარო ფილიალი', 'wbim' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="source_branch" id="source_branch" class="regular-text" required>
                        <option value=""><?php esc_html_e( 'აირჩიეთ ფილიალი', 'wbim' ); ?></option>
                        <?php foreach ( $branches as $branch ) : ?>
                            <option value="<?php echo esc_attr( $branch->id ); ?>">
                                <?php echo esc_html( $branch->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'ფილიალი საიდანაც გადაიტანება მარაგი.', 'wbim' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="destination_branch"><?php esc_html_e( 'დანიშნულების ფილიალი', 'wbim' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="destination_branch" id="destination_branch" class="regular-text" required>
                        <option value=""><?php esc_html_e( 'აირჩიეთ ფილიალი', 'wbim' ); ?></option>
                        <?php foreach ( $branches as $branch ) : ?>
                            <option value="<?php echo esc_attr( $branch->id ); ?>">
                                <?php echo esc_html( $branch->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'ფილიალი სადაც გადაიტანება მარაგი.', 'wbim' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="notes"><?php esc_html_e( 'შენიშვნები', 'wbim' ); ?></label>
                </th>
                <td>
                    <textarea name="notes" id="notes" class="large-text" rows="4"></textarea>
                    <p class="description"><?php esc_html_e( 'დამატებითი ინფორმაცია გადატანის შესახებ.', 'wbim' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary" id="wbim-create-transfer">
                <?php esc_html_e( 'შექმნა და პროდუქტების დამატება', 'wbim' ); ?>
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers' ) ); ?>" class="button">
                <?php esc_html_e( 'გაუქმება', 'wbim' ); ?>
            </a>
        </p>
    </form>
</div>


<script type="text/javascript">
jQuery(document).ready(function($) {
    // Prevent selecting same branch for source and destination
    $('#source_branch, #destination_branch').on('change', function() {
        var sourceVal = $('#source_branch').val();
        var destVal = $('#destination_branch').val();

        // Enable all options first
        $('#source_branch option, #destination_branch option').prop('disabled', false);

        // Disable selected in opposite dropdown
        if (sourceVal) {
            $('#destination_branch option[value="' + sourceVal + '"]').prop('disabled', true);
        }
        if (destVal) {
            $('#source_branch option[value="' + destVal + '"]').prop('disabled', true);
        }
    });

    // Form submission
    $('#wbim-new-transfer-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#wbim-create-transfer');
        var sourceVal = $('#source_branch').val();
        var destVal = $('#destination_branch').val();

        if (!sourceVal || !destVal) {
            alert('<?php echo esc_js( __( 'აირჩიეთ წყარო და დანიშნულების ფილიალები.', 'wbim' ) ); ?>');
            return;
        }

        if (sourceVal === destVal) {
            alert('<?php echo esc_js( __( 'წყარო და დანიშნულება არ შეიძლება იყოს ერთი და იგივე.', 'wbim' ) ); ?>');
            return;
        }

        $submitBtn.prop('disabled', true).text('<?php echo esc_js( __( 'იქმნება...', 'wbim' ) ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_create_transfer',
                nonce: (typeof wbimAdmin !== 'undefined') ? wbimAdmin.nonce : $('#wbim_nonce').val(),
                source_branch: sourceVal,
                destination_branch: destVal,
                notes: $('#notes').val()
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    alert(response.data.message || '<?php echo esc_js( __( 'შეცდომა მოხდა.', 'wbim' ) ); ?>');
                    $submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'შექმნა და პროდუქტების დამატება', 'wbim' ) ); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js( __( 'სერვერის შეცდომა.', 'wbim' ) ); ?>');
                $submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'შექმნა და პროდუქტების დამატება', 'wbim' ) ); ?>');
            }
        });
    });
});
</script>
