<?php
// Created by Xuan Wang
// Layar Technical Support
// Email: xuan@layar.com
// Website: http://layar.com


/*** Custom functions ***/


// Convert a decimal GPS latitude or longitude value to an integer by multiplying by 1000000.
// 
// Arguments:
//   value_Dec ; The decimal latitude or longitude GPS value.
//
// Returns:
//   int ; The integer value of the latitude or longitude.
//
function ChangetoIntLoc( $value_Dec ) {

  return $value_Dec * 1000000;
  
}//ChangetoIntLoc

// Change a string value to integer. 
//
// Arguments:
//   string ; A string value.
// 
// Returns:
//   Int ; If the string is empty, return NULL.
//
function ChangetoInt( $string ) {

  if ( strlen( trim( $string ) ) != 0 ) {
  
    return (int)$string;
  }
  else 
  	return NULL;
}//ChangetoInt

// Change a string value to float
//
// Arguments:
//   string ; A string value.
// 
// Returns:
//   float ; If the string is empty, return NULL.
//
function ChangetoFloat( $string ) {

  if ( strlen( trim( $string ) ) != 0 ) {
  
    return (float)$string;
  }
  else 
  	return NULL;
}//ChangetoFloat

// Put received POIs into an associative array. The returned values are assigned to $reponse["hotspots"].
//
// Arguments:
//   db ; The handler of the database.
//   value ; An array which contains all the needed parameters retrieved from GetPOI request. 
//
// Returns:
//   array ; An array of received POIs.
//
function Gethotspots( $db, $value ) {

/* Create the SQL query to retrieve POIs within the "radius" returned from GetPOI request. 
       Returned POIs are sorted by distance and the first 50 POIs are selected. 
	   The distance is caculated based on the Haversine formula. 
	   Note: this way of calculation is not scalable for querying large database.
*/
	
  // Use PDO::prepare() to prepare SQL statement. 
  // This statement is used due to security reasons and will help prevent general SQL injection attacks.
  // ":lat1", ":lat2", ":long" and ":radius" are named parameter markers for which real values 
  // will be substituted when the statement is executed. 
  // $sql is returned as a PDO statement object. 
  $sql = $db->prepare( "
  			SELECT id,
  			       attribution, 
  			       title, 
  			       lat,
  			       lon,
  			       imageURL,
  			       line4, 
  			       line3,
  			       line2,
  			       type,
  			       dimension,
  			       (((acos(sin((:lat1 * pi() / 180)) * sin((lat * pi() / 180)) +
                  	   cos((:lat2 * pi() / 180)) * cos((lat * pi() / 180)) * 
                       cos((:long  - lon) * pi() / 180))
                      ) * 180 / pi()) * 60 * 1.1515 * 1.609344 * 1000) as distance
    		FROM POI_Table
    		HAVING distance < :radius
    		ORDER BY distance ASC
    		LIMIT 0, 100 " );

  // PDOStatement::bindParam() binds the named parameter markers to the specified parameter values. 
  $sql->bindParam( ':lat1', $value['lat'], PDO::PARAM_STR );
  $sql->bindParam( ':lat2', $value['lat'], PDO::PARAM_STR );
  $sql->bindParam( ':long', $value['lon'], PDO::PARAM_STR );
  $sql->bindParam( ':radius', $value['radius'], PDO::PARAM_INT );
	
  // Use PDO::execute() to execute the prepared statement $sql. 
  $sql->execute();
	
  // Iterator for the response array.
  $i = 0; 
  
  // Use fetchAll to return an array containing all of the remaining rows in the result set.
  // Use PDO::FETCH_ASSOC to fetch $sql query results and return each row as an array indexed by column name.
  $pois = $sql->fetchAll(PDO::FETCH_ASSOC);
 
  /* Process the $pois result */
  
  // if $pois array is empty, return empty array. 
  if ( empty($pois) ) {
  	
  	$response["hotspots"] = array ();
	
  }//if 
  else { 
  	
  	// Put each POI information into $response["hotspots"] array.
 	foreach ( $pois as $poi ) {
		
		// If not used, return an empty actions array. 
		$poi["actions"] = array();
		
    	// Store the integer value of "lat" and "lon" using predefined function ChangetoIntLoc.
    	$poi["lat"] = ChangetoIntLoc( $poi["lat"] );
    	$poi["lon"] = ChangetoIntLoc( $poi["lon"] );
    
   	 	// Change to Int with function ChangetoInt.
    	$poi["type"] = ChangetoInt( $poi["type"] );
    	$poi["dimension"] = ChangetoInt( $poi["dimension"] );
    
    	// Change to demical value with function ChangetoFloat
    	$poi["distance"] = ChangetoFloat( $poi["distance"] );
	
    	// Put the poi into the response array.
    	$response["hotspots"][$i] = $poi;
    	$i++;
  	}//foreach
  
  }//else
  
  return $response["hotspots"];
}//Gethotspots

/*** Main entry point ***/


/* Pre-define connection to the MySQL database, please specify these fields.*/
$dbhost = "localhost";
$dbdata = "nomenclator";
$dbuser = "layar";
$dbpass = "b4rcelon4";

/* Put parameters from GetPOI request into an associative array named $value */

// Put needed parameter names from GetPOI request in an array called $keys. 
$keys = array( "layerName", "lat", "lon", "radius" );

// Initialize an empty associative array.
$value = array(); 
try {
  // Retrieve parameter values using $_GET and put them in $value array with parameter name as key. 
  foreach( $keys as $key ) {
  
    if ( isset($_GET[$key]) )
      $value[$key] = $_GET[$key]; 
    else 
      throw new Exception($key ." parameter is not passed in GetPOI request.");
  }//foreach
}//try
catch(Exception $e) {
  echo 'Message: ' .$e->getMessage();
}//catch
 
try {
	/* Connect to MySQL server. We use PDO which is a PHP extension to formalise database connection.
	   For more information regarding PDO, please see http://php.net/manual/en/book.pdo.php. 
	*/
	
	// Connect to predefined MySQl database.  
	$db = new PDO( "mysql:host=$dbhost; dbname=$dbdata", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND =>  "SET NAMES utf8") );
	
	// set the error reporting attribute to Exception.
	$db->setAttribute( PDO::ATTR_ERRMODE , PDO::ERRMODE_EXCEPTION );
	
	/* Construct the response into an associative array.*/
	
	// Create an empty array named response.
	$response = array();
	
	// Assign cooresponding values to mandatory JSON response keys.
	$response["layer"] = $value["layerName"];
	
	// Use Gethotspots() function to retrieve POIs with in the search range.  
	$response["hotspots"] = Gethotspots( $db, $value );

	// if there is no POI found, return a custom error message.
	if ( empty( $response["hotspots"] ) ) {
		$response["errorCode"] = 20;
 		$response["errorString"] = "No POI found. Please adjust the range.";
	}//if
	else {
  		$response["errorCode"] = 0;
  		$response["errorString"] = "ok";
	}//else

	/* All data is in $response, print it into JSON format.*/
	
	// Put the JSON representation of $response into $jsonresponse.
	$jsonresponse = json_encode( $response );
	
	// Declare the correct content type in HTTP response header.
	header( "Content-type: application/json; charset=utf-8" );
	
	// Print out Json response.
	echo $jsonresponse;

	/* Close the MySQL connection.*/
	
	// Set $db to NULL to close the database connection.
	$db=null;
}
catch( PDOException $e )
    {
    echo $e->getMessage();
    }

?>
