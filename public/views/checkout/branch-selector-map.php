<?php
/**
 * Branch Selector - Map Template
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

$settings = get_option( 'wbim_settings', array() );
$google_maps_api_key = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';

// Prepare branches data for JavaScript
$branches_json = array();
foreach ( $branches as $branch ) {
    if ( $branch['latitude'] && $branch['longitude'] ) {
        $branches_json[] = array(
            'id'          => $branch['id'],
            'name'        => $branch['name'],
            'address'     => $branch['address'],
            'phone'       => $branch['phone'],
            'lat'         => floatval( $branch['latitude'] ),
            'lng'         => floatval( $branch['longitude'] ),
            'canFulfill'  => $branch['can_fulfill'],
            'distance'    => isset( $branch['distance'] ) ? round( $branch['distance'], 1 ) : null,
        );
    }
}

// Default center (Tbilisi, Georgia)
$default_center = array(
    'lat' => 41.7151,
    'lng' => 44.8271,
);

if ( $customer_location ) {
    $default_center = array(
        'lat' => $customer_location['lat'],
        'lng' => $customer_location['lng'],
    );
} elseif ( ! empty( $branches_json ) ) {
    $default_center = array(
        'lat' => $branches_json[0]['lat'],
        'lng' => $branches_json[0]['lng'],
    );
}
?>

<div class="wbim-branch-selector wbim-branch-selector--map" id="wbim-branch-selector">
    <h3><?php esc_html_e( 'აირჩიეთ ფილიალი', 'wbim' ); ?></h3>

    <?php if ( empty( $google_maps_api_key ) ) : ?>
        <div class="wbim-map-error">
            <p><?php esc_html_e( 'რუკის ჩვენებისთვის საჭიროა Google Maps API გასაღების კონფიგურაცია.', 'wbim' ); ?></p>
            <?php
            // Fallback to dropdown
            include WBIM_PLUGIN_DIR . 'public/views/checkout/branch-selector-dropdown.php';
            ?>
        </div>
    <?php else : ?>
        <div class="wbim-map-container">
            <div id="wbim-branch-map" class="wbim-map"></div>

            <div class="wbim-map-sidebar">
                <div class="wbim-branch-list">
                    <?php foreach ( $branches as $branch ) : ?>
                        <?php if ( $branch['latitude'] && $branch['longitude'] ) : ?>
                            <?php
                            $is_selected = $selected_branch == $branch['id'];
                            $branch_class = 'wbim-map-branch-item';
                            if ( ! $branch['can_fulfill'] ) {
                                $branch_class .= ' wbim-branch-unavailable';
                            }
                            if ( $is_selected ) {
                                $branch_class .= ' wbim-branch-selected';
                            }
                            ?>
                            <div class="<?php echo esc_attr( $branch_class ); ?>" data-branch-id="<?php echo esc_attr( $branch['id'] ); ?>">
                                <label>
                                    <input
                                        type="radio"
                                        name="wbim_branch_id"
                                        value="<?php echo esc_attr( $branch['id'] ); ?>"
                                        <?php checked( $is_selected ); ?>
                                        <?php echo $required ? 'required' : ''; ?>
                                    />
                                    <span class="wbim-branch-name"><?php echo esc_html( $branch['name'] ); ?></span>
                                    <?php if ( isset( $branch['distance'] ) ) : ?>
                                        <span class="wbim-branch-distance"><?php echo esc_html( round( $branch['distance'], 1 ) ); ?> კმ</span>
                                    <?php endif; ?>
                                    <?php if ( ! $branch['can_fulfill'] ) : ?>
                                        <span class="wbim-unavailable-badge"><?php esc_html_e( 'არასაკმარისი მარაგი', 'wbim' ); ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <input type="hidden" name="wbim_branch_id_hidden" id="wbim_branch_id_hidden" value="<?php echo esc_attr( $selected_branch ); ?>" />

        <script type="text/javascript">
        var wbimMapBranches = <?php echo wp_json_encode( $branches_json ); ?>;
        var wbimMapCenter = <?php echo wp_json_encode( $default_center ); ?>;
        var wbimSelectedBranch = <?php echo absint( $selected_branch ); ?>;
        </script>

        <script async defer
            src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr( $google_maps_api_key ); ?>&callback=wbimInitMap">
        </script>

        <script type="text/javascript">
        var wbimMap;
        var wbimMarkers = [];

        function wbimInitMap() {
            var mapElement = document.getElementById('wbim-branch-map');
            if (!mapElement) return;

            wbimMap = new google.maps.Map(mapElement, {
                center: wbimMapCenter,
                zoom: 12,
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            });

            var bounds = new google.maps.LatLngBounds();
            var infoWindow = new google.maps.InfoWindow();

            wbimMapBranches.forEach(function(branch) {
                var position = { lat: branch.lat, lng: branch.lng };
                var markerColor = branch.canFulfill ? '#4CAF50' : '#FF5722';

                var marker = new google.maps.Marker({
                    position: position,
                    map: wbimMap,
                    title: branch.name,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 10,
                        fillColor: markerColor,
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 2
                    }
                });

                marker.branchId = branch.id;
                wbimMarkers.push(marker);
                bounds.extend(position);

                var infoContent = '<div class="wbim-map-info">' +
                    '<strong>' + branch.name + '</strong>';

                if (branch.address) {
                    infoContent += '<br>' + branch.address;
                }
                if (branch.phone) {
                    infoContent += '<br>' + branch.phone;
                }
                if (branch.distance !== null) {
                    infoContent += '<br><?php esc_html_e( 'მანძილი:', 'wbim' ); ?> ' + branch.distance + ' კმ';
                }
                if (!branch.canFulfill) {
                    infoContent += '<br><span style="color: #FF5722;"><?php esc_html_e( 'არასაკმარისი მარაგი', 'wbim' ); ?></span>';
                }

                infoContent += '<br><button type="button" class="wbim-map-select-btn" onclick="wbimSelectBranch(' + branch.id + ')"><?php esc_html_e( 'არჩევა', 'wbim' ); ?></button>';
                infoContent += '</div>';

                marker.addListener('click', function() {
                    infoWindow.setContent(infoContent);
                    infoWindow.open(wbimMap, marker);
                });

                // Highlight selected marker
                if (branch.id === wbimSelectedBranch) {
                    marker.setAnimation(google.maps.Animation.BOUNCE);
                    setTimeout(function() {
                        marker.setAnimation(null);
                    }, 2000);
                }
            });

            if (wbimMapBranches.length > 1) {
                wbimMap.fitBounds(bounds);
            }
        }

        function wbimSelectBranch(branchId) {
            // Update hidden input
            document.getElementById('wbim_branch_id_hidden').value = branchId;

            // Update radio button
            var radio = document.querySelector('input[name="wbim_branch_id"][value="' + branchId + '"]');
            if (radio) {
                radio.checked = true;
            }

            // Update sidebar selection
            var items = document.querySelectorAll('.wbim-map-branch-item');
            items.forEach(function(item) {
                item.classList.remove('wbim-branch-selected');
                if (item.dataset.branchId == branchId) {
                    item.classList.add('wbim-branch-selected');
                }
            });

            // Update marker animations
            wbimMarkers.forEach(function(marker) {
                if (marker.branchId === branchId) {
                    marker.setAnimation(google.maps.Animation.BOUNCE);
                    setTimeout(function() {
                        marker.setAnimation(null);
                    }, 1500);
                } else {
                    marker.setAnimation(null);
                }
            });
        }

        // Handle sidebar clicks
        jQuery(document).ready(function($) {
            $('.wbim-map-branch-item').on('click', function() {
                var branchId = parseInt($(this).data('branch-id'));
                wbimSelectBranch(branchId);

                // Pan to marker
                var marker = wbimMarkers.find(function(m) {
                    return m.branchId === branchId;
                });
                if (marker && wbimMap) {
                    wbimMap.panTo(marker.getPosition());
                    wbimMap.setZoom(14);
                }
            });
        });
        </script>
    <?php endif; ?>
</div>
