<?php
/**
 * Notifications Class
 *
 * Handles email notifications for transfers and stock alerts.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notifications class
 *
 * @since 1.0.0
 */
class WBIM_Notifications {

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
        // Transfer notifications
        add_action( 'wbim_transfer_status_changed', array( $this, 'send_transfer_status_notification' ), 10, 3 );
        add_action( 'wbim_transfer_created', array( $this, 'send_transfer_created_notification' ), 10, 1 );

        // Low stock notifications
        add_action( 'wbim_low_stock_alert', array( $this, 'send_low_stock_notification' ), 10, 3 );

        // Order allocation notifications
        add_action( 'wbim_order_allocated', array( $this, 'send_order_allocation_notification' ), 10, 3 );
    }

    /**
     * Send transfer status change notification
     *
     * @param int    $transfer_id Transfer ID.
     * @param string $old_status  Old status.
     * @param string $new_status  New status.
     */
    public function send_transfer_status_notification( $transfer_id, $old_status, $new_status ) {
        $settings = get_option( 'wbim_settings', array() );

        // Check if notifications are enabled
        if ( empty( $settings['enable_transfer_notifications'] ) || 'yes' !== $settings['enable_transfer_notifications'] ) {
            return;
        }

        $transfer = WBIM_Transfer::get_by_id( $transfer_id );
        if ( ! $transfer ) {
            return;
        }

        // Determine notification type and recipients based on status
        switch ( $new_status ) {
            case WBIM_Transfer::STATUS_PENDING:
                // Notify destination branch about incoming transfer
                $this->send_transfer_pending_notification( $transfer );
                break;

            case WBIM_Transfer::STATUS_IN_TRANSIT:
                // Notify destination branch that transfer is on the way
                $this->send_transfer_in_transit_notification( $transfer );
                break;

            case WBIM_Transfer::STATUS_COMPLETED:
                // Notify source branch that transfer was completed
                $this->send_transfer_completed_notification( $transfer );
                break;

            case WBIM_Transfer::STATUS_CANCELLED:
                // Notify both branches about cancellation
                $this->send_transfer_cancelled_notification( $transfer );
                break;
        }
    }

    /**
     * Send notification for new transfer created
     *
     * @param int $transfer_id Transfer ID.
     */
    public function send_transfer_created_notification( $transfer_id ) {
        // Created transfers are drafts, no need to notify
    }

    /**
     * Send pending transfer notification to destination branch
     *
     * @param object $transfer Transfer object.
     */
    private function send_transfer_pending_notification( $transfer ) {
        $recipients = WBIM_User_Roles::get_notification_recipients( $transfer->destination_branch_id, 'transfer' );

        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Transfer number */
            __( 'ახალი გადატანის მოთხოვნა - #%s', 'wbim' ),
            $transfer->transfer_number
        );

        $items = WBIM_Transfer_Item::get_by_transfer( $transfer->id );

        ob_start();
        $this->load_email_template( 'transfer-pending', array(
            'transfer' => $transfer,
            'items'    => $items,
        ) );
        $message = ob_get_clean();

        $this->send_email( $recipients, $subject, $message );
    }

    /**
     * Send in-transit notification to destination branch
     *
     * @param object $transfer Transfer object.
     */
    private function send_transfer_in_transit_notification( $transfer ) {
        $recipients = WBIM_User_Roles::get_notification_recipients( $transfer->destination_branch_id, 'transfer' );

        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Transfer number */
            __( 'გადატანა გზაშია - #%s', 'wbim' ),
            $transfer->transfer_number
        );

        $items = WBIM_Transfer_Item::get_by_transfer( $transfer->id );

        ob_start();
        $this->load_email_template( 'transfer-in-transit', array(
            'transfer' => $transfer,
            'items'    => $items,
        ) );
        $message = ob_get_clean();

        $this->send_email( $recipients, $subject, $message );
    }

    /**
     * Send completed transfer notification to source branch
     *
     * @param object $transfer Transfer object.
     */
    private function send_transfer_completed_notification( $transfer ) {
        $recipients = WBIM_User_Roles::get_notification_recipients( $transfer->source_branch_id, 'transfer' );

        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Transfer number */
            __( 'გადატანა დასრულდა - #%s', 'wbim' ),
            $transfer->transfer_number
        );

        $items = WBIM_Transfer_Item::get_by_transfer( $transfer->id );

        ob_start();
        $this->load_email_template( 'transfer-completed', array(
            'transfer' => $transfer,
            'items'    => $items,
        ) );
        $message = ob_get_clean();

        $this->send_email( $recipients, $subject, $message );
    }

    /**
     * Send cancelled transfer notification to both branches
     *
     * @param object $transfer Transfer object.
     */
    private function send_transfer_cancelled_notification( $transfer ) {
        // Get recipients from both branches
        $source_recipients = WBIM_User_Roles::get_notification_recipients( $transfer->source_branch_id, 'transfer' );
        $dest_recipients = WBIM_User_Roles::get_notification_recipients( $transfer->destination_branch_id, 'transfer' );
        $recipients = array_unique( array_merge( $source_recipients, $dest_recipients ) );

        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Transfer number */
            __( 'გადატანა გაუქმდა - #%s', 'wbim' ),
            $transfer->transfer_number
        );

        $items = WBIM_Transfer_Item::get_by_transfer( $transfer->id );

        ob_start();
        $this->load_email_template( 'transfer-cancelled', array(
            'transfer' => $transfer,
            'items'    => $items,
        ) );
        $message = ob_get_clean();

        $this->send_email( $recipients, $subject, $message );
    }

    /**
     * Send low stock notification
     *
     * @param int    $product_id Product ID.
     * @param int    $branch_id  Branch ID.
     * @param object $stock      Stock object.
     */
    public function send_low_stock_notification( $product_id, $branch_id, $stock ) {
        $settings = get_option( 'wbim_settings', array() );

        // Check if low stock notifications are enabled
        if ( empty( $settings['enable_low_stock_notifications'] ) || 'yes' !== $settings['enable_low_stock_notifications'] ) {
            return;
        }

        $recipients = WBIM_User_Roles::get_notification_recipients( $branch_id, 'low_stock' );

        if ( empty( $recipients ) ) {
            return;
        }

        $product = wc_get_product( $product_id );
        $branch = WBIM_Branch::get_by_id( $branch_id );

        if ( ! $product || ! $branch ) {
            return;
        }

        $subject = sprintf(
            /* translators: 1: Product name, 2: Branch name */
            __( 'დაბალი მარაგი: %1$s (%2$s)', 'wbim' ),
            $product->get_name(),
            $branch->name
        );

        ob_start();
        $this->load_email_template( 'low-stock', array(
            'product'  => $product,
            'branch'   => $branch,
            'stock'    => $stock,
        ) );
        $message = ob_get_clean();

        $this->send_email( $recipients, $subject, $message );
    }

    /**
     * Send order allocation notification
     *
     * @param int   $order_id   Order ID.
     * @param int   $branch_id  Branch ID.
     * @param array $allocation Allocation data.
     */
    public function send_order_allocation_notification( $order_id, $branch_id, $allocation ) {
        $settings = get_option( 'wbim_settings', array() );

        // Check if order notifications are enabled
        if ( empty( $settings['enable_order_notifications'] ) || 'yes' !== $settings['enable_order_notifications'] ) {
            return;
        }

        $recipients = WBIM_User_Roles::get_notification_recipients( $branch_id, 'order' );

        if ( empty( $recipients ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        $branch = WBIM_Branch::get_by_id( $branch_id );

        if ( ! $order || ! $branch ) {
            return;
        }

        $subject = sprintf(
            /* translators: 1: Order number, 2: Branch name */
            __( 'ახალი შეკვეთა #%1$s - %2$s', 'wbim' ),
            $order->get_order_number(),
            $branch->name
        );

        ob_start();
        $this->load_email_template( 'order-allocation', array(
            'order'      => $order,
            'branch'     => $branch,
            'allocation' => $allocation,
        ) );
        $message = ob_get_clean();

        $this->send_email( $recipients, $subject, $message );
    }

    /**
     * Send email
     *
     * @param array  $recipients Email addresses.
     * @param string $subject    Email subject.
     * @param string $message    Email message.
     * @return bool
     */
    private function send_email( $recipients, $subject, $message ) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        );

        // Wrap message in email template
        $message = $this->wrap_email_template( $message, $subject );

        return wp_mail( $recipients, $subject, $message, $headers );
    }

    /**
     * Wrap message in email template
     *
     * @param string $message Email message.
     * @param string $subject Email subject.
     * @return string
     */
    private function wrap_email_template( $message, $subject ) {
        $site_name = get_bloginfo( 'name' );
        $site_url = get_bloginfo( 'url' );

        $header_color = get_option( 'woocommerce_email_header_color', '#7f54b3' );

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>' . esc_html( $subject ) . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f7f7f7;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f7f7f7; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border: 1px solid #e5e5e5;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: ' . esc_attr( $header_color ) . '; padding: 20px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-family: Arial, sans-serif; font-size: 24px;">
                                ' . esc_html( $site_name ) . '
                            </h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333333;">
                            ' . $message . '
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f7f7f7; padding: 20px; text-align: center; font-family: Arial, sans-serif; font-size: 12px; color: #999999; border-top: 1px solid #e5e5e5;">
                            <p style="margin: 0;">
                                ' . esc_html( $site_name ) . ' - <a href="' . esc_url( $site_url ) . '" style="color: ' . esc_attr( $header_color ) . ';">' . esc_html( $site_url ) . '</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        return $html;
    }

    /**
     * Load email template
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     */
    private function load_email_template( $template, $data = array() ) {
        extract( $data );

        $template_path = WBIM_PLUGIN_DIR . 'templates/emails/' . $template . '.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback: generate simple template
            $this->generate_fallback_template( $template, $data );
        }
    }

    /**
     * Generate fallback email template
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     */
    private function generate_fallback_template( $template, $data ) {
        switch ( $template ) {
            case 'transfer-pending':
            case 'transfer-in-transit':
            case 'transfer-completed':
            case 'transfer-cancelled':
                $this->generate_transfer_template( $template, $data );
                break;

            case 'low-stock':
                $this->generate_low_stock_template( $data );
                break;

            case 'order-allocation':
                $this->generate_order_allocation_template( $data );
                break;
        }
    }

    /**
     * Generate transfer email template
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     */
    private function generate_transfer_template( $template, $data ) {
        $transfer = $data['transfer'];
        $items = $data['items'];

        $status_messages = array(
            'transfer-pending'    => __( 'ახალი გადატანის მოთხოვნა მოელის თქვენს ფილიალში.', 'wbim' ),
            'transfer-in-transit' => __( 'გადატანა უკვე გზაშია თქვენს ფილიალში.', 'wbim' ),
            'transfer-completed'  => __( 'გადატანა წარმატებით დასრულდა.', 'wbim' ),
            'transfer-cancelled'  => __( 'გადატანა გაუქმდა.', 'wbim' ),
        );

        ?>
        <h2 style="color: #333; margin-top: 0;">
            <?php
            printf(
                /* translators: %s: Transfer number */
                esc_html__( 'გადატანა #%s', 'wbim' ),
                esc_html( $transfer->transfer_number )
            );
            ?>
        </h2>

        <p><?php echo esc_html( $status_messages[ $template ] ); ?></p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9; width: 150px;">
                    <strong><?php esc_html_e( 'წყარო ფილიალი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $transfer->source_branch_name ); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'დანიშნულება:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $transfer->destination_branch_name ); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'სტატუსი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( WBIM_Transfer::get_status_label( $transfer->status ) ); ?>
                </td>
            </tr>
        </table>

        <h3 style="margin-top: 30px;"><?php esc_html_e( 'პროდუქტები', 'wbim' ); ?></h3>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9; text-align: left;">
                        <?php esc_html_e( 'პროდუქტი', 'wbim' ); ?>
                    </th>
                    <th style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9; text-align: left;">
                        <?php esc_html_e( 'SKU', 'wbim' ); ?>
                    </th>
                    <th style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9; text-align: center; width: 80px;">
                        <?php esc_html_e( 'რაოდენობა', 'wbim' ); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e5e5e5;">
                            <?php echo esc_html( $item->product_name ); ?>
                        </td>
                        <td style="padding: 10px; border: 1px solid #e5e5e5;">
                            <?php echo esc_html( $item->sku ?: '-' ); ?>
                        </td>
                        <td style="padding: 10px; border: 1px solid #e5e5e5; text-align: center;">
                            <?php echo esc_html( $item->quantity ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 30px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=edit&id=' . $transfer->id ) ); ?>"
               style="display: inline-block; padding: 10px 20px; background: #7f54b3; color: #fff; text-decoration: none; border-radius: 3px;">
                <?php esc_html_e( 'გადატანის ნახვა', 'wbim' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Generate low stock email template
     *
     * @param array $data Template data.
     */
    private function generate_low_stock_template( $data ) {
        $product = $data['product'];
        $branch = $data['branch'];
        $stock = $data['stock'];

        ?>
        <h2 style="color: #dc3232; margin-top: 0;">
            <?php esc_html_e( 'დაბალი მარაგის გაფრთხილება', 'wbim' ); ?>
        </h2>

        <p>
            <?php
            printf(
                /* translators: 1: Product name, 2: Branch name */
                esc_html__( 'პროდუქტი "%1$s" ფილიალში "%2$s" დაბალ მარაგზეა.', 'wbim' ),
                esc_html( $product->get_name() ),
                esc_html( $branch->name )
            );
            ?>
        </p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9; width: 150px;">
                    <strong><?php esc_html_e( 'პროდუქტი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $product->get_name() ); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'SKU:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $product->get_sku() ?: '-' ); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'ფილიალი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $branch->name ); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'მიმდინარე მარაგი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5; color: #dc3232;">
                    <strong><?php echo esc_html( $stock->quantity ); ?></strong>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'დაბალი მარაგის ზღვარი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $stock->low_stock_threshold ); ?>
                </td>
            </tr>
        </table>

        <p style="margin-top: 30px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-stock' ) ); ?>"
               style="display: inline-block; padding: 10px 20px; background: #7f54b3; color: #fff; text-decoration: none; border-radius: 3px;">
                <?php esc_html_e( 'მარაგის მართვა', 'wbim' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Generate order allocation email template
     *
     * @param array $data Template data.
     */
    private function generate_order_allocation_template( $data ) {
        $order = $data['order'];
        $branch = $data['branch'];
        $allocation = $data['allocation'];

        ?>
        <h2 style="color: #333; margin-top: 0;">
            <?php
            printf(
                /* translators: %s: Order number */
                esc_html__( 'ახალი შეკვეთა #%s', 'wbim' ),
                esc_html( $order->get_order_number() )
            );
            ?>
        </h2>

        <p>
            <?php
            printf(
                /* translators: %s: Branch name */
                esc_html__( 'ახალი შეკვეთა მიღებულია თქვენს ფილიალში: %s', 'wbim' ),
                esc_html( $branch->name )
            );
            ?>
        </p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9; width: 150px;">
                    <strong><?php esc_html_e( 'შეკვეთის ნომერი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    #<?php echo esc_html( $order->get_order_number() ); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'თარიღი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) ); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'მომხმარებელი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e5e5; background: #f9f9f9;">
                    <strong><?php esc_html_e( 'ჯამი:', 'wbim' ); ?></strong>
                </td>
                <td style="padding: 10px; border: 1px solid #e5e5e5;">
                    <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
                </td>
            </tr>
        </table>

        <p style="margin-top: 30px;">
            <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"
               style="display: inline-block; padding: 10px 20px; background: #7f54b3; color: #fff; text-decoration: none; border-radius: 3px;">
                <?php esc_html_e( 'შეკვეთის ნახვა', 'wbim' ); ?>
            </a>
        </p>
        <?php
    }
}
