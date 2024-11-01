<?php
/**
 * Shp_Shipping_ShippyPro class.
 *
 * @extends WC_Shipping_Method
 */
class Shp_Shipping_ShippyPro extends WC_Shipping_Method {

	private $apiendpoint = 'https://www.shippypro.com/api';

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->id                 = SHP_SHIPPYPRO_ID;
		$this->method_title       = __( 'ShippyPro Live Shipping Rates', 'shippypro-woocommerce' );
		$this->method_description = __( 'The <strong>ShippyPro</strong> extension obtains rates dynamically from the ShippyPro API during cart/checkout.', 'shippypro-woocommerce' );
		
		$this->init();
	}

	/**
	 * Output a message or error
	 * @param  string $message
	 * @param  string $type
	 */
    public function debug( $message, $type = 'notice' ) {
        // Hard coding to 'notice' as recently noticed 'error' is breaking with wc_add_notice.
        $type = 'notice';
    	if ( $this->debug ) {
    		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
    			wc_add_notice( $message, $type );
    		} else {
    			global $woocommerce;
    			$woocommerce->add_message( $message );
    		}
		}
    }

    /**
     * init function.
     *
     * @access public
     * @return void
     */
    private function init() {
		global $woocommerce;
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->enabled            = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : $this->enabled;
		$this->title              = isset( $this->settings['title'] ) ? $this->settings['title'] : $this->method_title;
		$this->apikey             = isset( $this->settings['apikey'] ) ? $this->settings['apikey'] : '';
		$this->debug              = isset( $this->settings['debug'] ) && $this->settings['debug'] == 'yes' ? true : false;
		$this->origin_postcode    = isset( $this->settings['origin_postcode'] ) ? $this->settings['origin_postcode'] : '';
		$this->origin_state       = isset( $this->settings['origin_state'] ) ? $this->settings['origin_state'] : '';
		$this->origin_addressline = isset($this->settings['origin_addressline']) ? $this->settings['origin_addressline'] : '';
		$this->origin_city        = isset($this->settings['origin_city']) ? $this->settings['origin_city'] : '';
		$this->origin_country     = isset($this->settings['origin_country']) ? $this->settings['origin_country'] : '';
		$this->markup     = isset($this->settings['markup']) ? $this->settings['markup'] : '';
		$this->markup_type     = isset($this->settings['markup_type']) ? $this->settings['markup_type'] : '';
		$this->offer_freeshipping_over     = isset($this->settings['offer_freeshipping_over']) ? $this->settings['offer_freeshipping_over'] : '';
		$this->display_dropoff_locations     = isset($this->settings['display_dropoff_locations']) ? $this->settings['display_dropoff_locations'] : '';

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'clear_transients' ) );
	}

	/**
	 * environment_check function.
	 *
	 * @access public
	 * @return void
	 */
	private function environment_check() {
		global $woocommerce;

		$error_message = '';

		// Check for API Key
		if ($this->apikey == '' && $this->enabled == 'yes') {
			$error_message .= '<p>' . __( 'ShippyPro Live Shipping Rates are enabled, but API Key has not been set.', 'shippypro-woocommerce-shipping' ) .'</p>';
		}
        
        if (( $this->origin_addressline == '' || $this->origin_city == '' || $this->origin_postcode == '' || $this->origin_state == '' || $this->origin_country == '') && $this->enabled == 'yes') {
			$error_message .= '<p>' . __( 'ShippyPro Live Shipping Rates are enabled, but the origin address has not been set.', 'shippypro-woocommerce-shipping' ) .'</p>';
		}

		if ( ! $error_message == '' ) {
			echo '<div class="error">';
			echo $error_message;
			echo '</div>';
		}
	}
    
    /**
	 * admin_options function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options() {
		// Check users environment supports this method
		$this->environment_check();

		parent::admin_options();

		include(__DIR__ . "/style.html");
		echo '<a href="' . admin_url('index.php?page=ShippyPro-Welcome') . '" style="font-size: 18px">' . __( 'View the module guide', 'shippypro-woocommerce' ) . '</a>';
	}
    
	/**
	 * clear_transients function.
	 *
	 * @access public
	 * @return void
	 */
	public function clear_transients() {
		global $wpdb;

		$wpdb->query( "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_ups_quote_%') OR `option_name` LIKE ('_transient_timeout_ups_quote_%')" );
	}

    /**
     * init_form_fields function.
     *
     * @access public
     * @return void
     */
    public function init_form_fields() {
	    global $woocommerce;
        
    	$this->form_fields  = array(
			'enabled'                => array(
				'title'              => __( 'Realtime Rates', 'shippypro-woocommerce-shipping' ),
				'type'               => 'checkbox',
				'label'              => __( 'Enable', 'shippypro-woocommerce-shipping' ),
				'default'            => 'no',
                'description'        => __( 'Enable realtime rates on Cart/Checkout page.', 'shippypro-woocommerce-shipping' ),
                'desc_tip'           => true
			),
			'title'                  => array(
				'title'              => __( 'Carrier Method Title', 'shippypro-woocommerce-shipping' ),
				'type'               => 'text',
				'description'        => __( 'This controls the title which the user sees during checkout.', 'shippypro-woocommerce-shipping' ),
				'default'            => __( 'ShippyPro Live Shipping Rates', 'shippypro-woocommerce-shipping' ),
                'desc_tip'           => true
			),
		    'apikey'            => array(
				'title'           => __( 'ShippyPro API Key', 'shippypro-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Obtained from ShippyPro after getting an account.', 'shippypro-woocommerce-shipping' ),
				'default'         => '',
                'desc_tip'        => true
		    ),
            'origin_addressline'     => array(
				'title'           => __( 'Origin Address', 'shippypro-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Ship From Address.', 'shippypro-woocommerce-shipping' ),
				'default'         => '',
                'desc_tip'        => true
		    ),
		    'origin_city'      	  => array(
				'title'           => __( 'Origin City', 'shippypro-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Origin City (Ship From City)', 'shippypro-woocommerce-shipping' ),
				'default'         => '',
                'desc_tip'        => true
		    ),
            'origin_state'        => array(
				'title'           => __( 'Origin State / Province', 'shippypro-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Specify shipper state/province code.', 'shippypro-woocommerce-shipping' ),
				'default'         => '',
                'desc_tip'        => true
		    ),
		    'origin_postcode'     => array(
				'title'           => __( 'Origin Postcode', 'shippypro-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Ship From Zip/postcode.', 'shippypro-woocommerce-shipping' ),
				'default'         => '',
                'desc_tip'        => true
		    ),
			'origin_country'     => array(
				'title'           => __( 'Origin Country', 'shippypro-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Ship From Country.', 'shippypro-woocommerce-shipping' ),
				'default'         => '',
                'desc_tip'        => true
		    ),
			'markup'     => array(
				'title'           => __( 'Markup', 'shippypro-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Add a markup to the real price and show the marked up price.', 'shippypro-woocommerce-shipping' ),
				'default'         => 0,
                'desc_tip'        => true
		    ),
			'markup_type'     => array(
				'title'           => __( 'Markup Type', 'shippypro-woocommerce-shipping' ),
				'type'            => 'select',
				'options'     => array(
					1 => __('%'),
					2 => __('Fixed Value')
				),
				'default'         => 1,
                'desc_tip'        => true
		    ),
			'offer_freeshipping_over'     => array(
				'title'           => __( 'Offer Free Shipping for Orders Over ' . get_woocommerce_currency(), 'shippypro-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'The cheapest shipping service will be proposed for free for orders over the selected threshold.', 'shippypro-woocommerce-shipping' ),
				'default'         => 0,
                'desc_tip'        => true
		    ),
		    'display_dropoff_locations'                  => array(
				'title'              => __( 'Display Dropff Locations', 'shippypro-woocommerce-shipping' ),
				'label'              => __( 'Enable', 'shippypro-woocommerce-shipping' ),
				'type'               => 'checkbox',
				'default'            => 'yes',
				'description'        => __( 'Display dropoff locations on the map.', 'shippypro-woocommerce-shipping' ),
                'desc_tip'           => true
			),
		    'debug'                  => array(
				'title'              => __( 'Debug Mode', 'shippypro-woocommerce-shipping' ),
				'label'              => __( 'Enable', 'shippypro-woocommerce-shipping' ),
				'type'               => 'checkbox',
				'default'            => 'no',
				'description'        => __( 'Enable debug mode to show debugging information on your cart/checkout.', 'shippypro-woocommerce-shipping' ),
                'desc_tip'           => true
			)
        );   
    }   

    /**
     * calculate_shipping function.
     *
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping($package = array()) {
    	global $woocommerce;
        		
		$items = $woocommerce->cart->get_cart();
		
		$totalheight = 0;
		$totalwidth = 0;
		$totallength = 0;
		$totalweight = 0;
		
        foreach($items as $item => $values) {
			$_product =  wc_get_product( $values['data']->get_id());
			if (empty($_product->get_dimensions())) continue;
			$totalheight += $_product->get_height();
			$totalwidth += $_product->get_width();
			$totallength += $_product->get_length();			
			$totalweight += ($_product->get_weight() * $values['quantity']);
		}
		
		if ($totalheight == 0)
			$totalheight = 1;
		if ($totalwidth == 0)
			$totalwidth = 1;
		if ($totallength == 0)
			$totallength = 1;
		if ($totalweight == 0)
			$totalweight = 1;
        
        $country = $package["destination"]["country"];
        $state = $package["destination"]["state"];
        $city = $package["destination"]["city"];
        $postcode = $package["destination"]["postcode"];
        $address = $package["destination"]["address"];
		$address2 = $package["destination"]["address2"];
		
		if (empty($country) || empty($city) || empty($postcode)) return;
        
        $arr = array(
			"Method" => "GetRates",
			"Params" => 
			array(
				"to_address" => array(
					"name" => "John Doe",
					"company" => "",
					"street1" => $address,
					"street2" => "",
					"city" => $city,
					"state" => $state,
					"zip" => $postcode,
					"country" => $country,
					"phone" => "5551231234",
					"email" => ""
				),
				"from_address" => array(
					"name" => "John Doe",
					"company" => "",
					"street1" => $this->origin_addressline,
					"street2" => "",
					"city" => $this->origin_city,
					"state" => $this->origin_state,
					"zip" => $this->origin_postcode,
					"country" => $this->origin_country,
					"phone" => "123",
					"email" => ""
				),
				"parcels" => array(
					array("length" => $totallength, "width" => $totalwidth, "height" => $totalheight, "weight" => $totalweight)
				),
				"CashOnDelivery" => 0,
				"CashOnDeliveryCurrency" => "EUR",
				"Insurance" => 0,
				"InsuranceCurrency" => "EUR",
				"ContentDescription" => "SAMPLE",
				"TotalValue" => $woocommerce->cart->cart_contents_total . " EUR"
			)
		);
        
        $this->debug(json_encode($arr));
        
		$data = json_encode($arr);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data))
		);                
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);    
		curl_setopt($curl, CURLOPT_USERPWD, $this->apikey);
		curl_setopt($curl, CURLOPT_URL, "https://www.shippypro.com/api");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$json = curl_exec($curl);          
		curl_close($curl);
		$rates = json_decode($json);
        
        $this->debug($json);
        
        if ($rates !== null)
        {
			$addUpsAccessPoint = false;
			$addUpsAccessPointPrice = 0;
			$addSDADeliveryPoints = false;
			$addSDADeliveryPointsPrice = 0;

			$ratesToAdd = [];

            foreach ($rates->Rates as $rateIndex => $rate) {
				$rateCost = $rate->rate;

				if ($this->offer_freeshipping_over != 0 && $this->offer_freeshipping_over < $woocommerce->cart->cart_contents_total && $rateIndex == 0)
				{
					$rateCost = 0;
				}
				else
				{
					if ($this->markup_type == 1) {
						$ricarico = 1 + ($this->markup / 100);
						$rateCost = round($rateCost * $ricarico, 2);
					} else {
						$rateCost = $rateCost + $this->markup;
					}
				}

                $rateInfo = array(
                    'label' => $rate->carrier . " (" . $rate->service . ")" . (($rateCost == 0) ? ' - Free shipping' : ''),
                    'cost' => $rateCost,
                    'taxes' => '',
                    'calc_tax' => 'per_order'
				);

				$ratesToAdd[] = $rateInfo;

				if ($rate->carrier == "UPS" && !$addUpsAccessPoint && $this->display_dropoff_locations == 'yes') {
					$addUpsAccessPoint = true;
					$addUpsAccessPointPrice = $rateCost;
				}

				if (($rate->carrier == "SDA" || $rate->carrier == "POSTEITALIANE") && !$addSDADeliveryPoints && $this->display_dropoff_locations == 'yes') {
					$addSDADeliveryPoints = true;
					$addSDADeliveryPointsPrice = $rateCost;
				}
			}

			if ($addUpsAccessPoint) {
				$rateInfo["label"] = "UPS (Access Point)";
				$rateInfo["cost"] = $addUpsAccessPointPrice;
				$ratesToAdd[] = $rateInfo;
			}

			if ($addSDADeliveryPoints) {
				$sdaDeliveryPointsTypes = [
					"Punto Poste",
					"Punto Poste Locker",
					"Casella Postale",
					"Fermo Posta"
				];			
	
				foreach ($sdaDeliveryPointsTypes as $sdaDeliveryPointsType)
				{											
					$rateInfo["label"] = "SDA (" . $sdaDeliveryPointsType . ")";
					$rateInfo["cost"] = $addSDADeliveryPointsPrice;
					$ratesToAdd[] = $rateInfo;
				}		
			}

			usort($ratesToAdd, function($a, $b) {
				return $a["cost"] > $b["cost"] ? 1 : 0;
			});	
			
			foreach ($ratesToAdd as $index => $rateInfo)
			{
				$rateInfo["id"] = $index + 1;

				$this->add_rate($rateInfo);
			}
        }
	}
}
