<?php

//----------------Initial Setup

// Set timezone
date_default_timezone_set('Australia/Adelaide');

// Originally based on descarte's TTN Webhook starter code
$ver  = "2023-07-22 v1.0";

ini_set("error_reporting", E_ALL);




//---------------Get and Process Data


// Get the incoming information from the raw input
$data = file_get_contents("php://input");
if ($data == "") { // So we can check the script is where we expect via a browser
	die("Keystone.Pipeline.XL version: ".$ver);
}

$json = json_decode($data, true);

// Save a raw copy of the message for debugging
// $pathNFile = date('YmdHis')."-".uniqid().".txt"; 
// file_put_contents($pathNFile, $data);

// Get selected values from the JSON
$end_device_ids = $json['end_device_ids'];
	$device_id = $end_device_ids['device_id'];
	$application_id = $end_device_ids['application_ids']['application_id'];
// Transform native ISO 8601 UTC timestamp to MySQL datetime format (and local timezone)
	$received_at_PRE = $json['received_at'];
	$received_at = date('Y-m-d H:i:s', strtotime($received_at_PRE));

$uplink_message = $json['uplink_message'];
	$CO2 = $uplink_message['decoded_payload']['CO2'];
	$LAT = $uplink_message['decoded_payload']['True_LAT'];
	$LON = $uplink_message['decoded_payload']['LONG'];
	$RH = $uplink_message['decoded_payload']['RH'];
	$SEA = $uplink_message['decoded_payload']['SEA'];	
	$CTIME = $uplink_message['decoded_payload']['TIME'];
	$SATS = $uplink_message['decoded_payload']['SATS'];


// Manual temperature calibration adjustment for individual applications
if ($application_id == "example") {
	$TEMP_PRE = $uplink_message['decoded_payload']['TEMP'];
	$TEMP = floatval($TEMP_PRE - 0.75); // make adjustment to temperature here
	}else{ 
	$TEMP = $uplink_message['decoded_payload']['TEMP'];
}


// Pad data with trailing zeroes when needed
// $LAT_PAD = sprintf("%.6f", $LAT);
// $LON_PAD = sprintf("%.6f", $LON);
$SEA_PAD = sprintf("%.1f", $SEA);
$TEMP_PAD = sprintf("%.2f", $TEMP);
$RH_PAD = sprintf("%.2f", $RH);
$CTIME_PAD = sprintf("%06d", $CTIME);


// If no GPS data has come through convert to null otherwise pad with leading or trailing zeroes where needed
if ($CTIME == 0) {
	$CTIME_PAD = null;
	}else{
	$CTIME_PAD = sprintf("%06d", $CTIME);
}

if ($LAT == 0) {
	$LAT_PAD = null;
	}else{
	$LAT_PAD = sprintf("%.6f", $LAT);
}

if ($LON == 0) {
	$LON_PAD = null;
	}else{
	$LON_PAD = sprintf("%.6f", $LON);
}

if ($SEA == 0) {
	$SEA_PAD = null;
	}else{
	$SEA_PAD = sprintf("%.1f", $SEA);
}




//---------------Collate and output the data to a local JSON file


// Write device data to txt
$file = $application_id.".txt";

// Create the array
$array = array(
	'Application ID' => $application_id,
	'Time Received' => $received_at,
	'Satellite Time UTC' => $CTIME_PAD,
	'Satellites Connected' => $SATS,
	'Latitude' => $LAT_PAD,
	'Longitude' => $LON_PAD,
	'Height Above Sea Level' => $SEA_PAD,
	'Temperature' => $TEMP_PAD,
	'Relative Humidity' => $RH_PAD,
	'CO2 Level' => $CO2
);

// Encode in to JSON
$jsonData = json_encode($array);

// Append new data to the file
file_put_contents($file, $jsonData, FILE_APPEND | LOCK_EX);




//---------------Collate and output the data to a local SQL database


// Write data to the SQL database
$servername = "localhost:3306";
$username = "username";
$password = "password";
$dbname = "dbname";

try {
  // Establish the connection
  $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
  
  // set the PDO error mode to exception
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Insert the data in to the correct table
  $sql = "INSERT INTO tablename (received_at, device_id, application_id, CTIME, LAT, LON, SEA, TEMP, RH, CO2, SATS, discovery_data_id)
  VALUES ('$received_at', '$device_id', '$application_id', '$CTIME_PAD', '$LAT_PAD', '$LON_PAD', '$SEA_PAD', '$TEMP_PAD', '$RH_PAD', '$CO2', '$SATS', NULL)"; 
  
  // Use exec() because no results are returned
  $conn->exec($sql);
  echo "New record created successfully";
} catch(PDOException $e) {
  echo $sql . "<br>" . $e->getMessage();
}

$conn = null;

?>