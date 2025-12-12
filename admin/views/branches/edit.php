<?php
/**
 * Branch Edit/Add View
 *
 * Displays the form for creating or editing a branch.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Determine if editing or adding
$branch_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$is_edit = $branch_id > 0;

// Get branch data if editing
$branch = null;
if ( $is_edit ) {
    $branch = WBIM_Branch::get_by_id( $branch_id );
    if ( ! $branch ) {
        wp_die( esc_html__( 'Branch not found.', 'wbim' ) );
    }
}

// Page title
$page_title = $is_edit ? __( 'Edit Branch', 'wbim' ) : __( 'Add New Branch', 'wbim' );

// Get managers for dropdown
$managers = WBIM_Utils::get_managers();

// Default values
$defaults = array(
    'name'       => '',
    'address'    => '',
    'city'       => '',
    'phone'      => '',
    'email'      => '',
    'manager_id' => 0,
    'lat'        => '',
    'lng'        => '',
    'is_active'  => 1,
    'sort_order' => 0,
);

// Merge with branch data if editing
if ( $branch ) {
    $data = array(
        'name'       => $branch->name,
        'address'    => $branch->address,
        'city'       => $branch->city,
        'phone'      => $branch->phone,
        'email'      => $branch->email,
        'manager_id' => $branch->manager_id,
        'lat'        => $branch->lat,
        'lng'        => $branch->lng,
        'is_active'  => $branch->is_active,
        'sort_order' => $branch->sort_order,
    );
} else {
    $data = $defaults;
}
?>

<div class="wrap wbim-branch-edit">
    <h1><?php echo esc_html( $page_title ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wbim-branch-form">
        <input type="hidden" name="action" value="wbim_save_branch" />
        <input type="hidden" name="branch_id" value="<?php echo esc_attr( $branch_id ); ?>" />
        <?php wp_nonce_field( 'wbim_save_branch', 'wbim_branch_nonce' ); ?>

        <div class="wbim-form-container">
            <div class="wbim-form-main">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="name"><?php esc_html_e( 'სახელი', 'wbim' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="name"
                                       name="name"
                                       value="<?php echo esc_attr( $data['name'] ); ?>"
                                       class="regular-text"
                                       required />
                                <p class="description"><?php esc_html_e( 'Branch name displayed throughout the system.', 'wbim' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="address"><?php esc_html_e( 'მისამართი', 'wbim' ); ?></label>
                            </th>
                            <td>
                                <textarea id="address"
                                          name="address"
                                          rows="3"
                                          class="large-text"><?php echo esc_textarea( $data['address'] ); ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="city"><?php esc_html_e( 'ქალაქი', 'wbim' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="city"
                                       name="city"
                                       value="<?php echo esc_attr( $data['city'] ); ?>"
                                       class="regular-text" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="phone"><?php esc_html_e( 'ტელეფონი', 'wbim' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="phone"
                                       name="phone"
                                       value="<?php echo esc_attr( $data['phone'] ); ?>"
                                       class="regular-text" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="email"><?php esc_html_e( 'ელფოსტა', 'wbim' ); ?></label>
                            </th>
                            <td>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       value="<?php echo esc_attr( $data['email'] ); ?>"
                                       class="regular-text" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="manager_id"><?php esc_html_e( 'მენეჯერი', 'wbim' ); ?></label>
                            </th>
                            <td>
                                <select id="manager_id" name="manager_id" class="regular-text">
                                    <option value=""><?php esc_html_e( '— Select Manager —', 'wbim' ); ?></option>
                                    <?php foreach ( $managers as $manager ) : ?>
                                        <option value="<?php echo esc_attr( $manager->ID ); ?>" <?php selected( $data['manager_id'], $manager->ID ); ?>>
                                            <?php echo esc_html( $manager->display_name ); ?> (<?php echo esc_html( $manager->user_email ); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'Location', 'wbim' ); ?></label>
                            </th>
                            <td>
                                <div class="wbim-coordinates-fields">
                                    <div class="wbim-coord-field">
                                        <label for="lat"><?php esc_html_e( 'განედი', 'wbim' ); ?></label>
                                        <input type="text"
                                               id="lat"
                                               name="lat"
                                               value="<?php echo esc_attr( $data['lat'] ); ?>"
                                               class="small-text"
                                               readonly />
                                    </div>
                                    <div class="wbim-coord-field">
                                        <label for="lng"><?php esc_html_e( 'გრძედი', 'wbim' ); ?></label>
                                        <input type="text"
                                               id="lng"
                                               name="lng"
                                               value="<?php echo esc_attr( $data['lng'] ); ?>"
                                               class="small-text"
                                               readonly />
                                    </div>
                                    <button type="button" id="clear-coordinates" class="button">
                                        <?php esc_html_e( 'Clear', 'wbim' ); ?>
                                    </button>
                                </div>

                                <div id="wbim-map-container">
                                    <div id="wbim-map"></div>
                                    <p class="description"><?php esc_html_e( 'Click on the map to set the branch location.', 'wbim' ); ?></p>
                                </div>

                                <?php if ( empty( WBIM_Utils::get_setting( 'google_maps_api_key' ) ) ) : ?>
                                    <div class="wbim-map-notice notice notice-warning inline">
                                        <p>
                                            <?php
                                            printf(
                                                /* translators: %s: Settings page URL */
                                                esc_html__( 'To use the map picker, please configure your Google Maps API key in the %s.', 'wbim' ),
                                                '<a href="' . esc_url( admin_url( 'admin.php?page=wbim-settings' ) ) . '">' . esc_html__( 'settings', 'wbim' ) . '</a>'
                                            );
                                            ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sort_order"><?php esc_html_e( 'რიგითობა', 'wbim' ); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="sort_order"
                                       name="sort_order"
                                       value="<?php echo esc_attr( $data['sort_order'] ); ?>"
                                       class="small-text"
                                       min="0" />
                                <p class="description"><?php esc_html_e( 'Lower numbers appear first.', 'wbim' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="is_active"><?php esc_html_e( 'აქტიური', 'wbim' ); ?></label>
                            </th>
                            <td>
                                <label for="is_active">
                                    <input type="checkbox"
                                           id="is_active"
                                           name="is_active"
                                           value="1"
                                           <?php checked( $data['is_active'], 1 ); ?> />
                                    <?php esc_html_e( 'This branch is active and can receive stock', 'wbim' ); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="wbim-form-sidebar">
                <div class="wbim-form-box">
                    <h3><?php esc_html_e( 'Actions', 'wbim' ); ?></h3>
                    <div class="wbim-form-box-content">
                        <?php submit_button( $is_edit ? __( 'Update Branch', 'wbim' ) : __( 'Create Branch', 'wbim' ), 'primary', 'submit', false ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-branches' ) ); ?>" class="button">
                            <?php esc_html_e( 'Cancel', 'wbim' ); ?>
                        </a>
                    </div>
                </div>

                <?php if ( $is_edit ) : ?>
                <div class="wbim-form-box">
                    <h3><?php esc_html_e( 'Information', 'wbim' ); ?></h3>
                    <div class="wbim-form-box-content wbim-meta-info">
                        <p>
                            <strong><?php esc_html_e( 'Created:', 'wbim' ); ?></strong><br />
                            <?php echo esc_html( WBIM_Utils::format_date( $branch->created_at, 'd/m/Y H:i' ) ); ?>
                        </p>
                        <p>
                            <strong><?php esc_html_e( 'Last Updated:', 'wbim' ); ?></strong><br />
                            <?php echo esc_html( WBIM_Utils::format_date( $branch->updated_at, 'd/m/Y H:i' ) ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
