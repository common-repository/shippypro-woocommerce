<?php
/*
	Plugin Name: WooCommerce Real Time Shipping Rates
	Description: Obtain Real time shipping rates via the ShippyPro API.
	Version: 0.0.8
	Author: ShippyPro
	Author URI: https://www.shippypro.com
*/

//Dev Version: 2.6.1
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Required functions
if ( ! function_exists( 'shp_is_woocommerce_active' ) ) {
	require_once( 'shippypro-includes/shp-functions.php' );
}

// WC active check
if ( ! shp_is_woocommerce_active() ) {
	return;
}

/**
 * Plugin activation check
 */
function shp_shippypro_welcome_screen_activation_redirect(){
	set_transient('shp_shippypro_welcome_screen_activation_redirect', true, 30);
}

register_activation_hook( __FILE__, 'shp_shippypro_welcome_screen_activation_redirect' );

define("SHP_SHIPPYPRO_ID", "shp_shipping_shippypro");

/**
 * ShippyPro_WooCommerce class
 */
if(!class_exists('ShippyPro_WooCommerce')){
	class ShippyPro_WooCommerce {
		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'init' ) );
            add_action('admin_init', array($this,'shp_shippypro_welcome'));
            add_action('admin_menu', array($this,'shp_shippypro_welcome_screen'));
            add_action('admin_head', array($this,'shp_shippypro_welcome_screen_remove_menus'));
                
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'shp_plugin_action_links' ) );
			add_action( 'woocommerce_shipping_init', array( $this, 'shp_shipping_init') );
			add_filter( 'woocommerce_shipping_methods', array( $this, 'shp_shippypro_add_method') );

			//add_action( 'woocommerce_after_cart', array( $this, 'shp_add_accesspoint_div' ) );
			add_action( 'woocommerce_before_checkout_form', array( $this, 'shp_add_accesspoint_div' ) );
		}

		public function init() { }
        
        public function shp_shippypro_welcome()
        {
            if (!get_transient('shp_shippypro_welcome_screen_activation_redirect')) {
                 return;
            }
            delete_transient('shp_shippypro_welcome_screen_activation_redirect');
            wp_safe_redirect(add_query_arg(array('page' => 'ShippyPro-Welcome'), admin_url('index.php')));
        }
        
        public function shp_shippypro_welcome_screen()
        {
            add_dashboard_page('Welcome To ShippyPro', 'Welcome To ShippyPro', 'read', 'ShippyPro-Welcome', array($this,'shp_shippypro_screen_content'));
        }
        
        public function shp_shippypro_screen_content()
        {
            include 'includes/shp_shippypro_welcome.php';
        }
        
        public function shp_shippypro_welcome_screen_remove_menus()
        {
             remove_submenu_page('index.php', 'ShippyPro-Welcome');
        }

		/**
		 * Plugin page links
		 */
		public function shp_plugin_action_links( $links ) {
			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=shp_shipping_shippypro' ) . '">' . __( 'Settings', 'shippypro-woocommerce' ) . '</a>',
				'<a href="' . admin_url('index.php?page=ShippyPro-Welcome') . '" target="_blank">' . __( 'Get Started', 'shippypro-woocommerce' ) . '</a>'
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * shp_shippypro_init function.
		 *
		 * @access public
		 * @return void
		 */
		function shp_shipping_init() {
			include_once( 'includes/class-shp-shippypro.php' );
		}

		/**
		 * shp_shippypro_add_method function.
		 *
		 * @access public
		 * @param mixed $methods
		 * @return void
		 */
		function shp_shippypro_add_method( $methods ) {
			$methods[] = 'Shp_Shipping_ShippyPro';
			return $methods;
		}
	
		function shp_add_accesspoint_div() {

			$options = get_option("woocommerce_shp_shipping_shippypro_settings");

			$origin_postcode    = isset($options['origin_postcode'] ) ? $options['origin_postcode'] : '';
			$origin_country     = isset($options['origin_country']) ? $options['origin_country'] : '';
			$origin_city        = isset($options['origin_city']) ? $options['origin_city'] : '';			

			$accessPointsUrl = get_site_url() . "/wp-content/plugins/shippypro-woocommerce/get-access-points.php";

			$script = <<<EOT
		
		<style>
			.accesspointoption { position: relative; }
			.accesspointoption:hover { border: 1px solid #2B7CAC !important; }
			.accesspointoption.selected { border: 1px solid #2B7CAC !important; }
			.accesspointoption.selected:after {
				content: '✓';
				position: absolute;
				top: 0;
				right: 0;
				border-radius: 0px 0px 0px 10px;
				background-color: #2B7CAC;
				width: 30px;
				height: 30px;
				color: white;
				text-align: center;
				line-height: 25px;
			}
			#accesspointsContainer {
				display: none;
				width: 100%;
				margin-bottom: 20px;
			}
			#accesspoints {
				overflow-y: scroll;
				overflow-x: hidden;
				height: 335px;
				width: 29%;
				display: inline-block;
			}
			#map
			{
				width: 100%;
				height: 280px;
				margin-top: 10px;
			}
			#accesspointsmapcontainer
			{
				width: 70%;
				display: inline-block;
			}		
			#accesspointsrangeselect
			{
				width: 79%;
				display: inline-block;
			}
			#accesspointsrangeselectlabel
			{
				width: 20%;
				display: inline-block;
			}
			.choosetooltipbutton
			{
				width: 100%;
				margin-bottom: 10px;
			}

			@media only screen and (max-width: 768px) {
				#accesspointsmapcontainer,
				#accesspoints,
				#range,
				#accesspointsrangeselect,
				#accesspointsrangeselectlabel
				{
					width: 100% !important;
					text-align: center !important;
					display: block !important;
				}
				#accesspointsmapcontainer
				{
					margin-top: 10px;
				}
			}
		</style>
		
		<div id="accesspointsContainer">
			<div id="accesspoints"></div>
			<div id="accesspointsmapcontainer">
				<div style="text-align: right">
					<div id="accesspointsrangeselect">
						<select class="form-control" id="range">
							<option>2</option>
							<option>5</option>
							<option>10</option>
							<option>25</option>
							<option>50</option>
						</select>
					</div>
					<div id="accesspointsrangeselectlabel">
						<p style="line-height: 28px">Km</p>
					</div>
				</div>
				<div id="map"></div>
			</div>
		</div>
		
		<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCkm0O8jHQdOROM64FP2FoQc0SmOaSAVEI&libraries=places&callback=initMap" async defer></script>

		<script type="text/javascript">
		
			var map;
			var geocoder;
			var infoWindow;
			var markers = [];
			var mainPos;

			var gMapsLoaded = false;

			var accessPoints = [];
			var accessPointsCarriers = ["UPS (Access Point)", "SDA (Punto Poste)", "SDA (Punto Poste Locker)", "SDA (Casella Postale)", "SDA (Fermo Posta)"];
			var currentCarrier = "";

			var contentString = '';

			var city = "";
			var postcode = "";
			var country = "";
			
			function initInfoWindow() {
				infoWindow = new google.maps.InfoWindow();
			}			
			
			// Sets the map on all markers in the array.
			function setMapOnAll(map) {
				for (var i = 0; i < markers.length; i++) {
				  markers[i].setMap(map);
				}
			}

			// Removes the markers from the map, but keeps them in the array.
			function clearMarkers() {
				setMapOnAll(null);
				
				markers = [];
			}
			
			function getMarkerIcon(url)
			{
				return {
					  url: url,
					  scaledSize: new google.maps.Size(25, 25), // scaled size
				};
			}
			
			// Adds a marker to the map and push to the array.
			function addMarker(location, accessPoint) {
				var marker = new google.maps.Marker({
				  position: location,
				  map: map,
				  accessPoint: accessPoint,
				  icon: getMarkerIcon('https://www.shippypro.com/sites/all/themes/shippypro_theme/assets_pannello/images/packageblack.png')
				});
				
				marker.addListener('click', function() {
					infoWindow.setContent(formatAccessPoint(this.accessPoint, true));
					infoWindow.open(map, marker);
				});
				
				markers.push(marker);
			}
			
			function getLocation() {
				geocoder.geocode({
					'address': city + ", " + postcode + " " + country
				}, function(results, status) {
					if (status == google.maps.GeocoderStatus.OK) {
						mainPos = results[0];
								
						gMapsLoaded = true;
						
						setCenter();
						showMainPositionMarker();						
						getMapAddressInfo();
					}
				});
			}
			
			function setCenter()
			{
				infoWindow.close();
				
				var position = new google.maps.LatLng(mainPos.geometry.location.lat(), mainPos.geometry.location.lng());
				
				map.setCenter(position);
			}
			
			function showMainPositionMarker() {
				new google.maps.Marker({
					position: new google.maps.LatLng(mainPos.geometry.location.lat(), mainPos.geometry.location.lng()),
					map: map
				});
			}
			
			function initMap() {	
				markers = [];
				geocoder = new google.maps.Geocoder();
				
				map = new google.maps.Map(document.getElementById('map'), {
					zoom: 14,
					mapTypeControl: false
				});
				
				initInfoWindow();
				
				getLocation();
			}
			
			function getMapAddressInfo()
			{
				var city = mainPos.address_components.find(x => x.types[0] == "locality" || x.types[0] == "postal_town" || x.types[0] == "administrative_area_level_3").short_name;
				var country = mainPos.address_components.find(x => x.types[0] == "country").short_name;
				var zip = mainPos.address_components.find(x => x.types[0] == "postal_code").short_name;

				loadAccessPoints(city, country, zip);
			}
			
			function loadAccessPoints(city, country, zip)
			{
				clearMarkers();
				jQuery("#accesspoints").empty();
				jQuery("#accesspoints").css("background", "url(https://www.shippypro.com/sites/all/themes/shippypro_theme/assets_pannello/images/loaders/4.gif) no-repeat center");

				jQuery.get("{$accessPointsUrl}?city=" + city + "&country=" + country + "&zip=" + zip + "&max_distance=" + jQuery("#range").val() + "&carrier=" + currentCarrier, function(res) {
					var resp = JSON.parse(res);	
					
					jQuery("#accesspoints").css("background", "");

					accessPoints = resp;

					jQuery.each(accessPoints, function(ind, accessPoint) {
						addMarker(new google.maps.LatLng(parseFloat(accessPoint.Latitude), parseFloat(accessPoint.Longitude)), accessPoint);
						
						jQuery("#accesspoints").append(formatAccessPoint(accessPoint));
					});
				});
			}
			
			function formatAccessPoint(accessPoint, marker = false)
			{
				return '<div ' + ((!marker) ? 'class="accesspointoption" style="width: 100%; border: 1px solid lightgray; padding: 10px; margin-top: 5px"' : '') + '>' +
							'<input type="button" class="choosetooltipbutton" name="accessPointID" access-point-id="' + accessPoint.AccessPointID + '" value="Choose"><br>' +
							'<b>' + accessPoint.Description + '</b><br>' +
							((accessPoint.Distance != "") ? 'Distance: ' + accessPoint.Distance + '<br><br>' : '') +
                    		((accessPoint.Hours != "") ? 'Hours: ' + accessPoint.Hours + '<br><br>' : '') +
							'Name: ' + accessPoint.Name + '<br>' +
							'Address: ' + accessPoint.Address + '<br>' +
							'City: ' + accessPoint.City + '<br>' +
							'Zip: ' + accessPoint.Zip +
						'</div>';
			}
		
			function showAccessPointsDiv()
			{
				jQuery("#accesspointsContainer").show();

				jQuery('html, body').animate({
					scrollTop: jQuery('#accesspoints').offset().top - 100
				}, 500);
			}

			function hideAccessPointsDiv(canResetFields)
			{
				if (canResetFields)
				{						
					jQuery("#billing_first_name").val(original_billing_first_name);
					jQuery("#billing_last_name").val(original_billing_last_name);
					jQuery("#billing_address_1").val(original_billing_address_1);
					jQuery("#billing_city").val(original_billing_city);
					jQuery("#billing_postcode").val(original_billing_postcode);
				}

				jQuery("#accesspointsContainer").hide();
				jQuery(".accesspointoption").removeClass("selected");
			}

			var original_billing_first_name = "";
			var original_billing_last_name = "";
			var original_billing_address_1 = "";
			var original_billing_city = "";
			var original_billing_postcode = "";
			var skipSaving = false;

			var delayKeyUp = (function(){
				var timer = 0;
				return function(callback, ms){
					clearTimeout (timer);
					timer = setTimeout(callback, ms);
				};
			})();		
			
			function saveOriginalAddress()
			{
				if (skipSaving) {
					skipSaving = false;

					return;
				}

				delayKeyUp(function(){
					original_billing_first_name = jQuery("#billing_first_name").val();
					original_billing_last_name = jQuery("#billing_last_name").val();
					original_billing_address_1 = jQuery("#billing_address_1").val();
					original_billing_city = city = jQuery("#billing_city").val();
					original_billing_postcode = postcode = jQuery("#billing_postcode").val();
					country = jQuery("#billing_country").val();

					initMap();
				}, 1000);
			}

			jQuery(document).on('change keyup', 'input[name^=billing]', function() { saveOriginalAddress(); });

			function findCarrierNameByRow(row)
			{
				return row.text().split(":")[0].split("-")[0].trim();
			}

			function checkSelectedShippingOption(canResetFields = false)
			{
				var selectedAccessPoint = false;

				jQuery("label[for^=shipping_method_]").each(function() {
					var carrierName = findCarrierNameByRow(jQuery(this));
					
					if (jQuery.inArray(carrierName, accessPointsCarriers) != -1)
					{
						if (jQuery(this).prev().attr("checked") == "checked") {
							currentCarrier = carrierName;

							selectedAccessPoint = true;
							return false;
						}
					}
				});

				if (selectedAccessPoint) {
					showAccessPointsDiv();
					if (gMapsLoaded) getMapAddressInfo();
				}
				else hideAccessPointsDiv(canResetFields);
			};

			function initShippyProModule()
			{
				saveOriginalAddress();

				checkSelectedShippingOption();

				jQuery(document).on('updated_checkout', function() {
					checkSelectedShippingOption();
				});
				
				jQuery(document).on('change', 'input[name^=shipping_method]', function() {
					checkSelectedShippingOption(true);
				});
				
				jQuery(document).on('change', '#range', function() {
					setRange(jQuery(this).val());
				});
				
				jQuery(document).on('click', 'input[name=accessPointID]', function() {
					selectAccessPoint(jQuery(this).attr("access-point-id"));
				});
			}
			
			document.addEventListener('DOMContentLoaded', function() {
				initShippyProModule();
			}, true);
			
			function selectAccessPoint(accessPointID)
			{
				infoWindow.close();

				var accessPointInfo = accessPoints.find(x => x.AccessPointID == accessPointID);

				skipSaving = true;
				jQuery("#billing_first_name").val(original_billing_first_name + " " + original_billing_last_name);
				jQuery("#billing_last_name").val(" by " + accessPointInfo.Name);
				jQuery("#billing_address_1").val(accessPointInfo.Address);
				jQuery("#billing_city").val(accessPointInfo.City);
				jQuery("#billing_postcode").val(accessPointInfo.Zip);

				jQuery(".accesspointoption").removeClass("selected");
				
				jQuery.each(markers, function(ind, marker) {	
					if (markers[ind].getIcon() != 'https://www.shippypro.com/sites/all/themes/shippypro_theme/assets_pannello/images/packageblack.png' && marker.accessPoint.AccessPointID != accessPointID)
						markers[ind].setIcon(getMarkerIcon("https://www.shippypro.com/sites/all/themes/shippypro_theme/assets_pannello/images/packageblack.png"));
					else if (marker.accessPoint.AccessPointID == accessPointID)
					{
						markers[ind].setIcon(getMarkerIcon("https://www.shippypro.com/sites/all/themes/shippypro_theme/assets_pannello/images/packagered.png"));
						map.panTo(markers[ind].getPosition());
					}
				});
				
				var elemToSelect = jQuery("input[name=accessPointID][access-point-id=" + accessPointID + "]");
					
				elemToSelect.parent().addClass("selected");
				
				jQuery('#accesspoints').stop().animate({scrollTop: jQuery('#accesspoints').scrollTop() + (elemToSelect.offset().top - jQuery('#accesspoints').offset().top - 40)}, 1000);
			}
			
			function setRange(range)
			{
				setCenter();
				
				var newZoom = 14;
					
				if (range == 5) newZoom = 12;
				if (range == 10) newZoom = 11;
				if (range == 25) newZoom = 10;
				if (range == 50) newZoom = 9;
				
				map.setZoom(newZoom);
				
				getMapAddressInfo();
			}
			
		</script>
EOT;
			$html = $script;
			
			echo $html;
		}
	}

	new ShippyPro_WooCommerce();
}
