<?php
/**
 * Stock Import View
 *
 * Displays the CSV import interface.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$template_url = add_query_arg(
    array(
        'action' => 'wbim_download_template',
        'nonce'  => wp_create_nonce( 'wbim_admin' ),
    ),
    admin_url( 'admin-ajax.php' )
);
?>

<div class="wrap wbim-stock-import">
    <h1><?php esc_html_e( 'მარაგის იმპორტი', 'wbim' ); ?></h1>

    <div class="wbim-import-container">
        <div class="wbim-import-main">
            <div class="wbim-form-box">
                <h3><?php esc_html_e( 'CSV ფაილის ატვირთვა', 'wbim' ); ?></h3>
                <div class="wbim-form-box-content">
                    <form id="wbim-import-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'wbim_admin', 'nonce' ); ?>

                        <div class="wbim-upload-area" id="wbim-upload-area">
                            <div class="wbim-upload-content">
                                <span class="dashicons dashicons-upload"></span>
                                <p><?php esc_html_e( 'გადაათრიეთ CSV ფაილი აქ ან', 'wbim' ); ?></p>
                                <label class="button" for="import_file">
                                    <?php esc_html_e( 'აირჩიეთ ფაილი', 'wbim' ); ?>
                                </label>
                                <input type="file" id="import_file" name="import_file" accept=".csv" style="display: none;" />
                            </div>
                            <div class="wbim-file-info" style="display: none;">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <span class="wbim-file-name"></span>
                                <button type="button" class="wbim-remove-file">&times;</button>
                            </div>
                        </div>

                        <div class="wbim-import-options">
                            <h4><?php esc_html_e( 'იმპორტის პარამეტრები', 'wbim' ); ?></h4>
                            <p>
                                <label>
                                    <input type="checkbox" name="update_existing" value="true" checked />
                                    <?php esc_html_e( 'არსებული მარაგის განახლება', 'wbim' ); ?>
                                </label>
                            </p>
                            <p class="description">
                                <?php esc_html_e( 'თუ ჩართულია, იმპორტი განაახლებს არსებულ მარაგს. თუ გამორთულია, არსებული ჩანაწერები გამოტოვდება.', 'wbim' ); ?>
                            </p>
                        </div>

                        <div class="wbim-import-actions">
                            <button type="submit" class="button button-primary" disabled>
                                <?php esc_html_e( 'იმპორტის დაწყება', 'wbim' ); ?>
                            </button>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-stock' ) ); ?>" class="button">
                                <?php esc_html_e( 'გაუქმება', 'wbim' ); ?>
                            </a>
                        </div>
                    </form>

                    <div id="wbim-import-progress" style="display: none;">
                        <div class="wbim-progress-bar">
                            <div class="wbim-progress-fill"></div>
                        </div>
                        <p class="wbim-progress-text"><?php esc_html_e( 'იმპორტი მიმდინარეობს...', 'wbim' ); ?></p>
                    </div>

                    <div id="wbim-import-results" style="display: none;">
                        <h4><?php esc_html_e( 'იმპორტის შედეგები', 'wbim' ); ?></h4>
                        <div class="wbim-results-summary"></div>
                        <div class="wbim-results-errors"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wbim-import-sidebar">
            <div class="wbim-form-box">
                <h3><?php esc_html_e( 'ინსტრუქცია', 'wbim' ); ?></h3>
                <div class="wbim-form-box-content">
                    <p><?php esc_html_e( 'CSV ფაილის ფორმატი:', 'wbim' ); ?></p>
                    <ol>
                        <li><strong>sku</strong> - <?php esc_html_e( 'პროდუქტის SKU (სავალდებულო)', 'wbim' ); ?></li>
                        <li><strong>branch_id</strong> - <?php esc_html_e( 'ფილიალის ID (სავალდებულო)', 'wbim' ); ?></li>
                        <li><strong>quantity</strong> - <?php esc_html_e( 'რაოდენობა (სავალდებულო)', 'wbim' ); ?></li>
                        <li><strong>low_stock_threshold</strong> - <?php esc_html_e( 'დაბალი მარაგის ზღვარი (არასავალდებულო)', 'wbim' ); ?></li>
                        <li><strong>shelf_location</strong> - <?php esc_html_e( 'თაროს ადგილმდებარეობა (არასავალდებულო)', 'wbim' ); ?></li>
                    </ol>
                    <p>
                        <a href="<?php echo esc_url( $template_url ); ?>" class="button">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            <?php esc_html_e( 'შაბლონის ჩამოტვირთვა', 'wbim' ); ?>
                        </a>
                    </p>
                </div>
            </div>

            <div class="wbim-form-box">
                <h3><?php esc_html_e( 'ფილიალების ID', 'wbim' ); ?></h3>
                <div class="wbim-form-box-content">
                    <?php
                    $branches = WBIM_Branch::get_active();
                    if ( ! empty( $branches ) ) :
                        ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'ID', 'wbim' ); ?></th>
                                    <th><?php esc_html_e( 'სახელი', 'wbim' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $branches as $branch ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $branch->id ); ?></code></td>
                                        <td><?php echo esc_html( $branch->name ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="description">
                            <?php esc_html_e( 'ფილიალები არ არის დამატებული.', 'wbim' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
