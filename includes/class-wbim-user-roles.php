<?php
/**
 * User Roles Class
 *
 * Handles custom user roles and capabilities for branch management.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * User Roles class
 *
 * @since 1.0.0
 */
class WBIM_User_Roles {

    /**
     * Branch Manager role name
     */
    const ROLE_BRANCH_MANAGER = 'wbim_branch_manager';

    /**
     * Custom capabilities
     */
    const CAP_VIEW_BRANCH_STOCK = 'wbim_view_branch_stock';
    const CAP_MANAGE_STOCK = 'wbim_manage_stock';
    const CAP_MANAGE_TRANSFERS = 'wbim_manage_transfers';
    const CAP_VIEW_REPORTS = 'wbim_view_reports';
    const CAP_MANAGE_BRANCHES = 'wbim_manage_branches';

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add user meta fields for branch assignment
        add_action( 'show_user_profile', array( $this, 'add_branch_assignment_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'add_branch_assignment_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_branch_assignment' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_branch_assignment' ) );

        // Filter admin menu based on capabilities
        add_action( 'admin_menu', array( $this, 'filter_admin_menu' ), 999 );

        // Add role column to users list
        add_filter( 'manage_users_columns', array( $this, 'add_branch_column' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'show_branch_column' ), 10, 3 );
    }

    /**
     * Create custom roles and capabilities
     * Called on plugin activation
     */
    public static function create_roles() {
        // Add Branch Manager role
        add_role(
            self::ROLE_BRANCH_MANAGER,
            __( 'ფილიალის მენეჯერი', 'wbim' ),
            array(
                'read'                     => true,
                'edit_posts'               => false,
                'delete_posts'             => false,
                'upload_files'             => true,
                self::CAP_VIEW_BRANCH_STOCK => true,
                self::CAP_MANAGE_STOCK     => true,
                self::CAP_MANAGE_TRANSFERS => true,
                self::CAP_VIEW_REPORTS     => true,
            )
        );

        // Add capabilities to administrator
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( self::CAP_VIEW_BRANCH_STOCK );
            $admin->add_cap( self::CAP_MANAGE_STOCK );
            $admin->add_cap( self::CAP_MANAGE_TRANSFERS );
            $admin->add_cap( self::CAP_VIEW_REPORTS );
            $admin->add_cap( self::CAP_MANAGE_BRANCHES );
        }

        // Add capabilities to shop manager
        $shop_manager = get_role( 'shop_manager' );
        if ( $shop_manager ) {
            $shop_manager->add_cap( self::CAP_VIEW_BRANCH_STOCK );
            $shop_manager->add_cap( self::CAP_MANAGE_STOCK );
            $shop_manager->add_cap( self::CAP_MANAGE_TRANSFERS );
            $shop_manager->add_cap( self::CAP_VIEW_REPORTS );
            $shop_manager->add_cap( self::CAP_MANAGE_BRANCHES );
        }
    }

    /**
     * Remove custom roles and capabilities
     * Called on plugin deactivation
     */
    public static function remove_roles() {
        // Remove Branch Manager role
        remove_role( self::ROLE_BRANCH_MANAGER );

        // Remove capabilities from administrator
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->remove_cap( self::CAP_VIEW_BRANCH_STOCK );
            $admin->remove_cap( self::CAP_MANAGE_STOCK );
            $admin->remove_cap( self::CAP_MANAGE_TRANSFERS );
            $admin->remove_cap( self::CAP_VIEW_REPORTS );
            $admin->remove_cap( self::CAP_MANAGE_BRANCHES );
        }

        // Remove capabilities from shop manager
        $shop_manager = get_role( 'shop_manager' );
        if ( $shop_manager ) {
            $shop_manager->remove_cap( self::CAP_VIEW_BRANCH_STOCK );
            $shop_manager->remove_cap( self::CAP_MANAGE_STOCK );
            $shop_manager->remove_cap( self::CAP_MANAGE_TRANSFERS );
            $shop_manager->remove_cap( self::CAP_VIEW_REPORTS );
            $shop_manager->remove_cap( self::CAP_MANAGE_BRANCHES );
        }
    }

    /**
     * Add branch assignment fields to user profile
     *
     * @param WP_User $user User object.
     */
    public function add_branch_assignment_fields( $user ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $branches = WBIM_Branch::get_active();
        $assigned_branches = get_user_meta( $user->ID, 'wbim_assigned_branches', true );
        if ( ! is_array( $assigned_branches ) ) {
            $assigned_branches = array();
        }

        ?>
        <h3><?php esc_html_e( 'ფილიალის მინიჭება', 'wbim' ); ?></h3>

        <table class="form-table">
            <tr>
                <th>
                    <label for="wbim_assigned_branches"><?php esc_html_e( 'მინიჭებული ფილიალები', 'wbim' ); ?></label>
                </th>
                <td>
                    <?php if ( ! empty( $branches ) ) : ?>
                        <fieldset>
                            <?php foreach ( $branches as $branch ) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="wbim_assigned_branches[]"
                                           value="<?php echo esc_attr( $branch->id ); ?>"
                                           <?php checked( in_array( $branch->id, $assigned_branches ) ); ?>>
                                    <?php echo esc_html( $branch->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">
                            <?php esc_html_e( 'ფილიალის მენეჯერებს მხოლოდ მინიჭებულ ფილიალებზე ექნებათ წვდომა.', 'wbim' ); ?>
                        </p>
                    <?php else : ?>
                        <p class="description">
                            <?php esc_html_e( 'ფილიალები არ არის შექმნილი.', 'wbim' ); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save branch assignment
     *
     * @param int $user_id User ID.
     */
    public function save_branch_assignment( $user_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( isset( $_POST['wbim_assigned_branches'] ) ) {
            $branches = array_map( 'absint', $_POST['wbim_assigned_branches'] );
            update_user_meta( $user_id, 'wbim_assigned_branches', $branches );
        } else {
            delete_user_meta( $user_id, 'wbim_assigned_branches' );
        }
    }

    /**
     * Filter admin menu based on capabilities
     */
    public function filter_admin_menu() {
        global $submenu;

        // Only filter for branch managers
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Check if user has any WBIM capabilities
        if ( ! current_user_can( self::CAP_VIEW_BRANCH_STOCK ) ) {
            return;
        }

        // Modify submenu items based on capabilities
        if ( isset( $submenu['wbim'] ) ) {
            foreach ( $submenu['wbim'] as $key => $item ) {
                $page_slug = $item[2];

                // Hide branches page for non-admins
                if ( $page_slug === 'wbim-branches' && ! current_user_can( self::CAP_MANAGE_BRANCHES ) ) {
                    unset( $submenu['wbim'][ $key ] );
                }

                // Hide stock page for users without stock permission
                if ( $page_slug === 'wbim-stock' && ! current_user_can( self::CAP_MANAGE_STOCK ) ) {
                    unset( $submenu['wbim'][ $key ] );
                }

                // Hide transfers page for users without transfer permission
                if ( $page_slug === 'wbim-transfers' && ! current_user_can( self::CAP_MANAGE_TRANSFERS ) ) {
                    unset( $submenu['wbim'][ $key ] );
                }

                // Hide reports page for users without reports permission
                if ( $page_slug === 'wbim-reports' && ! current_user_can( self::CAP_VIEW_REPORTS ) ) {
                    unset( $submenu['wbim'][ $key ] );
                }
            }
        }
    }

    /**
     * Add branch column to users list
     *
     * @param array $columns Columns array.
     * @return array
     */
    public function add_branch_column( $columns ) {
        $columns['wbim_branches'] = __( 'ფილიალები', 'wbim' );
        return $columns;
    }

    /**
     * Show branch column content
     *
     * @param string $output      Column output.
     * @param string $column_name Column name.
     * @param int    $user_id     User ID.
     * @return string
     */
    public function show_branch_column( $output, $column_name, $user_id ) {
        if ( $column_name !== 'wbim_branches' ) {
            return $output;
        }

        $assigned_branches = get_user_meta( $user_id, 'wbim_assigned_branches', true );
        if ( empty( $assigned_branches ) || ! is_array( $assigned_branches ) ) {
            return '—';
        }

        $branch_names = array();
        foreach ( $assigned_branches as $branch_id ) {
            $branch = WBIM_Branch::get_by_id( $branch_id );
            if ( $branch ) {
                $branch_names[] = esc_html( $branch->name );
            }
        }

        return implode( ', ', $branch_names );
    }

    /**
     * Get user's assigned branches
     *
     * @param int $user_id User ID.
     * @return array Array of branch IDs.
     */
    public static function get_user_branches( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        // Admins and shop managers have access to all branches
        $user = get_userdata( $user_id );
        if ( $user && ( in_array( 'administrator', $user->roles ) || in_array( 'shop_manager', $user->roles ) ) ) {
            $branches = WBIM_Branch::get_active();
            return wp_list_pluck( $branches, 'id' );
        }

        $assigned_branches = get_user_meta( $user_id, 'wbim_assigned_branches', true );
        return is_array( $assigned_branches ) ? $assigned_branches : array();
    }

    /**
     * Check if user has access to a specific branch
     *
     * @param int $branch_id Branch ID.
     * @param int $user_id   User ID.
     * @return bool
     */
    public static function user_can_access_branch( $branch_id, $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        // Admins have access to all branches
        if ( user_can( $user_id, 'manage_woocommerce' ) ) {
            return true;
        }

        $user_branches = self::get_user_branches( $user_id );
        return in_array( $branch_id, $user_branches );
    }

    /**
     * Get all capabilities
     *
     * @return array
     */
    public static function get_all_capabilities() {
        return array(
            self::CAP_VIEW_BRANCH_STOCK => __( 'ფილიალის მარაგის ნახვა', 'wbim' ),
            self::CAP_MANAGE_STOCK      => __( 'მარაგის მართვა', 'wbim' ),
            self::CAP_MANAGE_TRANSFERS  => __( 'გადატანების მართვა', 'wbim' ),
            self::CAP_VIEW_REPORTS      => __( 'რეპორტების ნახვა', 'wbim' ),
            self::CAP_MANAGE_BRANCHES   => __( 'ფილიალების მართვა', 'wbim' ),
        );
    }

    /**
     * Get branch managers for a specific branch
     *
     * @param int $branch_id Branch ID.
     * @return array Array of user objects.
     */
    public static function get_branch_managers( $branch_id ) {
        $args = array(
            'role'       => self::ROLE_BRANCH_MANAGER,
            'meta_query' => array(
                array(
                    'key'     => 'wbim_assigned_branches',
                    'value'   => serialize( strval( $branch_id ) ),
                    'compare' => 'LIKE',
                ),
            ),
        );

        return get_users( $args );
    }

    /**
     * Get all branch managers
     *
     * @return array Array of user objects.
     */
    public static function get_all_branch_managers() {
        return get_users( array( 'role' => self::ROLE_BRANCH_MANAGER ) );
    }

    /**
     * Get users who can receive notifications for a branch
     *
     * @param int    $branch_id         Branch ID.
     * @param string $notification_type Notification type.
     * @return array Array of email addresses.
     */
    public static function get_notification_recipients( $branch_id, $notification_type = 'all' ) {
        $recipients = array();

        // Get admin email
        $admin_email = get_option( 'admin_email' );
        if ( $admin_email ) {
            $recipients[] = $admin_email;
        }

        // Get branch managers for this branch
        $managers = self::get_branch_managers( $branch_id );
        foreach ( $managers as $manager ) {
            if ( $manager->user_email && ! in_array( $manager->user_email, $recipients ) ) {
                $recipients[] = $manager->user_email;
            }
        }

        // Get shop managers
        $shop_managers = get_users( array( 'role' => 'shop_manager' ) );
        foreach ( $shop_managers as $manager ) {
            if ( $manager->user_email && ! in_array( $manager->user_email, $recipients ) ) {
                $recipients[] = $manager->user_email;
            }
        }

        return array_unique( $recipients );
    }
}
