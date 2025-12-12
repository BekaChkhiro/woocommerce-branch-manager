<?php
/**
 * Transfers List View
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wbim-transfers-page">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'მარაგის გადატანები', 'wbim' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'ახალი გადატანა', 'wbim' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php
    // Display notices
    if ( isset( $_GET['message'] ) ) {
        $message_type = sanitize_key( $_GET['message'] );
        $messages = array(
            'created'   => __( 'გადატანა წარმატებით შეიქმნა.', 'wbim' ),
            'updated'   => __( 'გადატანა განახლდა.', 'wbim' ),
            'deleted'   => __( 'გადატანა წაიშალა.', 'wbim' ),
            'completed' => __( 'გადატანა დასრულდა.', 'wbim' ),
        );
        if ( isset( $messages[ $message_type ] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $message_type ] ) . '</p></div>';
        }
    }
    ?>

    <!-- Filters -->
    <div class="wbim-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wbim-transfers">

            <select name="status">
                <option value=""><?php esc_html_e( 'ყველა სტატუსი', 'wbim' ); ?></option>
                <?php foreach ( $statuses as $status_key => $status_label ) : ?>
                    <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status, $status_key ); ?>>
                        <?php echo esc_html( $status_label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="branch">
                <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
                <?php foreach ( $branches as $branch ) : ?>
                    <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $branch_id, $branch->id ); ?>>
                        <?php echo esc_html( $branch->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button"><?php esc_html_e( 'გაფილტვრა', 'wbim' ); ?></button>

            <?php if ( $status || $branch_id ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers' ) ); ?>" class="button">
                    <?php esc_html_e( 'გასუფთავება', 'wbim' ); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Status summary -->
    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers' ) ); ?>"
               class="<?php echo ! $status ? 'current' : ''; ?>">
                <?php esc_html_e( 'ყველა', 'wbim' ); ?>
                <span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
            </a>
        </li>
        <?php foreach ( $statuses as $status_key => $status_label ) : ?>
            <?php
            $status_count = WBIM_Transfer::get_count( array( 'status' => $status_key ) );
            if ( $status_count > 0 ) :
            ?>
            | <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&status=' . $status_key ) ); ?>"
                   class="<?php echo $status === $status_key ? 'current' : ''; ?>">
                    <?php echo esc_html( $status_label ); ?>
                    <span class="count">(<?php echo esc_html( $status_count ); ?>)</span>
                </a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <!-- Transfers table -->
    <table class="wp-list-table widefat fixed striped wbim-transfers-table">
        <thead>
            <tr>
                <th class="column-number"><?php esc_html_e( 'ნომერი', 'wbim' ); ?></th>
                <th class="column-source"><?php esc_html_e( 'წყარო', 'wbim' ); ?></th>
                <th class="column-destination"><?php esc_html_e( 'დანიშნულება', 'wbim' ); ?></th>
                <th class="column-items"><?php esc_html_e( 'პროდუქტები', 'wbim' ); ?></th>
                <th class="column-status"><?php esc_html_e( 'სტატუსი', 'wbim' ); ?></th>
                <th class="column-created"><?php esc_html_e( 'შექმნილია', 'wbim' ); ?></th>
                <th class="column-actions"><?php esc_html_e( 'მოქმედებები', 'wbim' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $transfers ) ) : ?>
                <tr>
                    <td colspan="7" class="wbim-no-items">
                        <?php esc_html_e( 'გადატანები არ მოიძებნა.', 'wbim' ); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ( $transfers as $transfer ) : ?>
                    <?php
                    $item_count = WBIM_Transfer_Item::get_count( $transfer->id );
                    $total_qty = WBIM_Transfer_Item::get_total_quantity( $transfer->id );
                    $status_class = 'wbim-status-' . $transfer->status;
                    ?>
                    <tr>
                        <td class="column-number">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=edit&id=' . $transfer->id ) ); ?>">
                                <strong><?php echo esc_html( $transfer->transfer_number ); ?></strong>
                            </a>
                        </td>
                        <td class="column-source">
                            <?php echo esc_html( $transfer->source_branch_name ); ?>
                        </td>
                        <td class="column-destination">
                            <?php echo esc_html( $transfer->destination_branch_name ); ?>
                        </td>
                        <td class="column-items">
                            <?php
                            printf(
                                /* translators: 1: Item count, 2: Total quantity */
                                esc_html__( '%1$d პროდუქტი (%2$d ერთეული)', 'wbim' ),
                                $item_count,
                                $total_qty
                            );
                            ?>
                        </td>
                        <td class="column-status">
                            <span class="wbim-status <?php echo esc_attr( $status_class ); ?>">
                                <?php echo esc_html( WBIM_Transfer::get_status_label( $transfer->status ) ); ?>
                            </span>
                        </td>
                        <td class="column-created">
                            <?php
                            echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $transfer->created_at ) ) );
                            if ( $transfer->created_by_name ) {
                                echo '<br><small>' . esc_html( $transfer->created_by_name ) . '</small>';
                            }
                            ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=edit&id=' . $transfer->id ) ); ?>" class="button button-small">
                                <?php echo $transfer->status === WBIM_Transfer::STATUS_DRAFT ? esc_html__( 'რედაქტირება', 'wbim' ) : esc_html__( 'ნახვა', 'wbim' ); ?>
                            </a>
                            <?php if ( $transfer->status !== WBIM_Transfer::STATUS_DRAFT && ! empty( $transfer->status ) ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=pdf&id=' . $transfer->id ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'PDF გადმოწერა', 'wbim' ); ?>">
                                    <span class="dashicons dashicons-pdf" style="vertical-align: middle;"></span>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php
                    printf(
                        /* translators: %s: Number of items */
                        esc_html( _n( '%s ჩანაწერი', '%s ჩანაწერი', $total_count, 'wbim' ) ),
                        number_format_i18n( $total_count )
                    );
                    ?>
                </span>
                <span class="pagination-links">
                    <?php
                    $base_url = admin_url( 'admin.php?page=wbim-transfers' );
                    if ( $status ) {
                        $base_url .= '&status=' . $status;
                    }
                    if ( $branch_id ) {
                        $base_url .= '&branch=' . $branch_id;
                    }

                    // First page
                    if ( $paged > 1 ) {
                        echo '<a class="first-page button" href="' . esc_url( $base_url ) . '"><span class="screen-reader-text">' . esc_html__( 'პირველი გვერდი', 'wbim' ) . '</span><span aria-hidden="true">&laquo;</span></a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
                    }

                    // Previous page
                    if ( $paged > 1 ) {
                        echo '<a class="prev-page button" href="' . esc_url( $base_url . '&paged=' . ( $paged - 1 ) ) . '"><span class="screen-reader-text">' . esc_html__( 'წინა გვერდი', 'wbim' ) . '</span><span aria-hidden="true">&lsaquo;</span></a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
                    }

                    // Page indicator
                    echo '<span class="paging-input">';
                    echo '<span class="tablenav-paging-text">' . $paged . ' / ' . $total_pages . '</span>';
                    echo '</span>';

                    // Next page
                    if ( $paged < $total_pages ) {
                        echo '<a class="next-page button" href="' . esc_url( $base_url . '&paged=' . ( $paged + 1 ) ) . '"><span class="screen-reader-text">' . esc_html__( 'შემდეგი გვერდი', 'wbim' ) . '</span><span aria-hidden="true">&rsaquo;</span></a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
                    }

                    // Last page
                    if ( $paged < $total_pages ) {
                        echo '<a class="last-page button" href="' . esc_url( $base_url . '&paged=' . $total_pages ) . '"><span class="screen-reader-text">' . esc_html__( 'ბოლო გვერდი', 'wbim' ) . '</span><span aria-hidden="true">&raquo;</span></a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

