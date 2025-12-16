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

// Get branches for dropdown
$branches = WBIM_Branch::get_active();
?>

<div class="wrap wbim-stock-import">
    <h1><?php esc_html_e( 'მარაგის იმპორტი', 'wbim' ); ?></h1>

    <div class="wbim-import-container">
        <div class="wbim-import-main">
            <div class="wbim-form-box">
                <h3><?php esc_html_e( 'ფაილის ატვირთვა', 'wbim' ); ?></h3>
                <div class="wbim-form-box-content">
                    <form id="wbim-import-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'wbim_admin', 'nonce' ); ?>

                        <div class="wbim-branch-selector">
                            <h4><?php esc_html_e( 'აირჩიეთ ფილიალი', 'wbim' ); ?></h4>
                            <select name="branch_id" id="import_branch_id" class="wbim-branch-select" required>
                                <option value=""><?php esc_html_e( '-- აირჩიეთ ფილიალი --', 'wbim' ); ?></option>
                                <?php foreach ( $branches as $branch ) : ?>
                                    <option value="<?php echo esc_attr( $branch->id ); ?>">
                                        <?php echo esc_html( $branch->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'ფაილში არსებული მარაგი იმპორტირდება ამ ფილიალში.', 'wbim' ); ?>
                            </p>
                        </div>

                        <div class="wbim-upload-area" id="wbim-upload-area">
                            <div class="wbim-upload-content">
                                <span class="dashicons dashicons-upload"></span>
                                <p><?php esc_html_e( 'გადაათრიეთ CSV ან JSON ფაილი აქ ან', 'wbim' ); ?></p>
                                <label class="button" for="import_file">
                                    <?php esc_html_e( 'აირჩიეთ ფაილი', 'wbim' ); ?>
                                </label>
                                <input type="file" id="import_file" name="import_file" accept=".csv,.json" style="display: none;" />
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
                            <p style="margin-top: 10px;">
                                <label>
                                    <input type="checkbox" name="distribute_to_variations" value="true" />
                                    <?php esc_html_e( 'ვარიაბელურ პროდუქტებზე მარაგის განაწილება', 'wbim' ); ?>
                                </label>
                            </p>
                            <p class="description">
                                <?php esc_html_e( 'თუ ჩართულია და SKU ვარიაბელურ პროდუქტს ეკუთვნის, მარაგი თანაბრად განაწილდება ყველა ვარიაციაზე. თუ გამორთულია, ეს პროდუქტები გამოტოვდება.', 'wbim' ); ?>
                            </p>
                            <p style="margin-top: 10px;">
                                <label>
                                    <input type="checkbox" name="mark_missing_out_of_stock" value="true" />
                                    <?php esc_html_e( 'ფაილში არარსებული პროდუქტების "არ არის მარაგში" მონიშვნა', 'wbim' ); ?>
                                </label>
                            </p>
                            <p class="description">
                                <?php esc_html_e( 'თუ ჩართულია, ფაილში არარსებული ყველა პროდუქტი ამ ფილიალში მოინიშნება როგორც "არ არის მარაგში" (რაოდენობა: 0). ვარიაბელური პროდუქტის შემთხვევაში, თუ მშობელი SKU ფაილშია, მისი ვარიაციები არ შეიცვლება.', 'wbim' ); ?>
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
                    <h4><?php esc_html_e( 'JSON ფაილის ფორმატი:', 'wbim' ); ?></h4>
                    <p class="description"><?php esc_html_e( 'JSON ფაილი უნდა შეიცავდეს მასივს ობიექტებით:', 'wbim' ); ?></p>
                    <ul>
                        <li><strong>Column2</strong> - <?php esc_html_e( 'პროდუქტის SKU', 'wbim' ); ?></li>
                        <li><strong>Column5</strong> - <?php esc_html_e( 'რაოდენობა', 'wbim' ); ?></li>
                    </ul>
                    <p class="description"><?php esc_html_e( 'ან სტანდარტული ფორმატი:', 'wbim' ); ?></p>
                    <ul>
                        <li><strong>sku</strong> - <?php esc_html_e( 'პროდუქტის SKU', 'wbim' ); ?></li>
                        <li><strong>quantity</strong> - <?php esc_html_e( 'რაოდენობა', 'wbim' ); ?></li>
                    </ul>

                    <hr style="margin: 15px 0;">

                    <h4><?php esc_html_e( 'CSV ფაილის ფორმატი:', 'wbim' ); ?></h4>
                    <ul>
                        <li><strong>sku</strong> - <?php esc_html_e( 'პროდუქტის SKU (სავალდებულო)', 'wbim' ); ?></li>
                        <li><strong>quantity</strong> - <?php esc_html_e( 'რაოდენობა (სავალდებულო)', 'wbim' ); ?></li>
                        <li><strong>low_stock_threshold</strong> - <?php esc_html_e( 'დაბალი მარაგის ზღვარი (არასავალდებულო)', 'wbim' ); ?></li>
                        <li><strong>shelf_location</strong> - <?php esc_html_e( 'თაროს ადგილმდებარეობა (არასავალდებულო)', 'wbim' ); ?></li>
                    </ul>
                    <p>
                        <a href="<?php echo esc_url( $template_url ); ?>" class="button">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            <?php esc_html_e( 'შაბლონის ჩამოტვირთვა', 'wbim' ); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
