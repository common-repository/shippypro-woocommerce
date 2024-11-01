<?php
	include_once('../../../wp-load.php');
			
	function ApiRequest($params)
	{
		$options = get_option("woocommerce_shp_shipping_shippypro_settings");

		$data = json_encode($params);    
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data))
		);                
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);    
		curl_setopt($curl, CURLOPT_USERPWD, $options['apikey']);
		curl_setopt($curl, CURLOPT_URL, "https://www.shippypro.com/api");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$json = curl_exec($curl);          
		curl_close($curl);
		
		return json_decode($json);
	}

	$carrier = $_GET["carrier"];

	$carrierType = explode(" ", $carrier, 2)[0];
	$carrierService = str_replace(["(", ")"], "", explode(" ", $carrier, 2)[1]);

	$city = $_GET["city"];
	$country = $_GET["country"];
	$zip = $_GET["zip"];
	$max_distance = $_GET["max_distance"];

	if ($carrierType == "UPS")
	{            
		$request = array(
			"Method" => "GetUPSAccessPoints",
			"Params" => 
			array(
				"city" => $city,
				"country" => $country,
				"zip" => $zip,
				"max_distance" => $max_distance,
			)
		);
	}
	else if ($carrierType == "SDA" || $carrierType == "POSTEITALIANE")
	{
		$deliveryPointsTypeCode = "";
		
		if ($carrierService == "Punto Poste") $deliveryPointsTypeCode = "RTZ";
		if ($carrierService == "Punto Poste Locker") $deliveryPointsTypeCode = "APT";
		if ($carrierService == "Casella Postale") $deliveryPointsTypeCode = "CPT";
		if ($carrierService == "Fermo Posta") $deliveryPointsTypeCode = "FMP"; 

		$request = array(
			"Method" => "GetSDADeliveryPoints",
			"Params" => 
			array(
				"zip" => $zip,
				"deliveryPointsTypeCode" => $deliveryPointsTypeCode
			)
		);
	}

	$response = ApiRequest($request);

	$accessPoints = array();

	if (isset($response->AccessPoints))
	{	
		foreach ($response->AccessPoints as $accessPoint)
		{
			$accessPoints[] = array(
				"Latitude" => $accessPoint->Geocode->Latitude,
				"Longitude" => $accessPoint->Geocode->Longitude,
				"AccessPointID" => $accessPoint->AccessPointInformation->PublicAccessPointID,
				"Description" => $accessPoint->LocationAttribute->OptionCode->Description,
				"Distance" => $accessPoint->Distance->Value . " " . $accessPoint->Distance->UnitOfMeasurement->Code,
				"Hours" => $accessPoint->StandardHoursOfOperation,
				"Name" => $accessPoint->AddressKeyFormat->ConsigneeName,
				"Address" => $accessPoint->AddressKeyFormat->AddressLine,
				"City" => $accessPoint->AddressKeyFormat->PoliticalDivision2,
				"Zip" => $accessPoint->AddressKeyFormat->PostcodePrimaryLow
			);
		}	
	}

	if (isset($response->DeliveryPoints))
	{	
		foreach ($response->DeliveryPoints as $deliveryPoint)
		{
			$accessPoints[] = array(
				"Latitude" => trim($deliveryPoint->ygradi),
				"Longitude" => trim($deliveryPoint->xgradi),
				"AccessPointID" => trim($deliveryPoint->codiceUfficio),
				"Description" => trim($deliveryPoint->descrizioneUfficio),
				"Distance" => "",
				"Hours" => trim($deliveryPoint->oraApLun . " " . $deliveryPoint->oraChLun),
				"Name" => trim($deliveryPoint->descrizioneUfficio),
				"Address" => trim($deliveryPoint->indirizzo),
				"City" => trim($deliveryPoint->localita),
				"Zip" => trim($deliveryPoint->cap)
			);
		}	
	}

	echo json_encode($accessPoints);
?>