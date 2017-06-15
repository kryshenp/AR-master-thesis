// vzorovy kod

<?php
/* Nastaveni pripojeni k databazi MySQL  */

$dbhost = "mysql6.000webhost.com"; /* jmeno meho serveru */
$dbdata = "a3183018_augment"; /* jmeno databaze */
$dbuser = "a3183018_augment"; /* jmeno uzivatele datbaze */
$dbpass = "dffdlove2012"; /* heslo k databazi */

/* pripojujeme se k serveru MySQL. */
$db = new PDO( "mysql:host=$dbhost; dbname=$dbdata", $dbuser,
$dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") );

// set the error reporting attribute to throw Exception .
$db->setAttribute( PDO::ATTR_ERRMODE , PDO::ERRMODE_EXCEPTION );



//Nasledujici kod precte retezev dotazu a vlozi parametry do pole s nazvem $value:
	// Vlozime potrebne nazvy parametru z GetPOI dotazu do pole s nazvem  $keys.
	$keys = array( "layerName", "lat", "lon", "radius" );
	// Inicializujeme prazdne asociativni pole.
	$value = array();
	try {
		// Nacteme hodnoty parametru pomovi  $_GET a vlozime je do pole $value s nazvem parametru jako key.
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



//Nyni je potreba pripravit nasi odpoved, ktera zobrazi POI uzivateli:

	// Vytvorime prazdne pole s nazvem response(odpoved)
	$response = array();
	// Priradime odpovidajici hodnoty k povinnym JSON klicum odpovedi (mandatory JSON response keys).

	$response["layer"] = $value["layerName"];
	// Pouzijeme Gethotspots() funkci POI, ktere spadaji do rozsahu vyhledavani (search range).
	$response["hotspots"] = Gethotspots( $db, $value );
	// v pripade, ze nenasel se zadny POI bod, vrati se vlastni chybova zprava.
		if ( empty( $response["hotspots"] ) ) {
			$response["errorCode"] = 20;
			$response["errorString"] = "No POI found. Please adjust the range.";
		}//if
	else {
		$response["errorCode"] = 0;
		$response["errorString"] = "ok";
	}//else

	function Gethotspots( $db, $value ) {

/* Vytvori SQL dotaz pro nacteni bodu POI, ktere spadaji do "radiusu" vraceneho z GetPOI dotazu. 
Vracene body POI budou roztridene na zaklade vzdalenosti (distance) a budou vybrane nejblizsi 50 bodu POI.
Vzdalenost se vypocte na zaklade Haversinove vety.
Poznamka: Tento zpusob vypoctu nevyhovuje pro dotazovati na velkou databazi.
*/

// ":lat1", ":lat2", ":long" a ":radius" jsou pojmenovane parametry na ktere 
// budou nahrazene realne hodnoty po provedeni prikazu.
// $sql je vracen jako PDO statement object.
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
		cos((:long - lon) * pi() / 180))
		) * 180 / pi()) * 60 * 1.1515 * 1.609344 * 1000) as distance
		FROM POI_Table
		HAVING distance < :radius
		ORDER BY distance ASC
		LIMIT 0, 50 " );

	// PDOStatement::bindParam() vaze jmenovane merkery parametru ke specifikovanym hodnotam parametru.

	$sql->bindParam( ':lat1', $value['lat'], PDO::PARAM_STR );
	$sql->bindParam( ':lat2', $value['lat'], PDO::PARAM_STR );
	$sql->bindParam( ':long', $value['lon'], PDO::PARAM_STR );
	$sql->bindParam( ':radius', $value['radius'], PDO::PARAM_INT );

	// Pouzijeme PDO::execute() pro provedeni pripraveneho prikazu  $sql.
	$sql->execute();
	
	// Iterator pro pole odezvy.
	$i = 0;

	// Pouzijeme fetchAll pro vraceni pole, obsahujiciho veskere zbyvajici radky v sade vysledku.
	// Pouzijeme PDO::FETCH_ASSOC pro nacteni vysledku $sql dotazu a vraceni kazdehor radku jako pole indexoovaneho 
	// nazvem sloupce
	$pois = $sql->fetchAll(PDO::FETCH_ASSOC);

	/* Zpracovani vysledku $pois  */	
	// pokud pole $pois je pradne, vrati prazdne pole.
	if ( empty($pois) ) {
		$response["hotspots"] = array ();
	}//if
	else {
	
		// Vlozi informace ke kazdemu POI do $response["hotspots"] pole.
		foreach ( $pois as $poi ) {

		// Neni-li pouzity, vrati prazdne pole akci.
			$poi["actions"] = array();
			
			// Skladuje integer hodnotu "lat" a "lon" s pouzitim preddefinovane funkce
			// ChangetoIntLoc.
			$poi["lat"] = ChangetoIntLoc( $poi["lat"] );
			$poi["lon"] = ChangetoIntLoc( $poi["lon"] );

			// Zmeni na Int s pouzitim funkce  ChangetoInt.
			$poi["type"] = ChangetoInt( $poi["type"] );
			$poi["dimension"] = ChangetoInt( $poi["dimension"] );

			// Zmeni na desetinne hodnoty pomoci funkce ChangetoFloat
			$poi["distance"] = ChangetoFloat( $poi["distance"] );
		
			// Vlozi poi do pole odpovedi.
			$response["hotspots"][$i] = $poi;
			$i++;
		}//foreach
	}//else
	return $response["hotspots"];
}//Gethotspots



// Konvertujeme desetinne hodnoty GPS sirky a delky na integer nasobenim na 1000000.
//
// Argumenty:
// value_Dec ; Desetinna sirka nebo vyska z GPS hodnoty.
//
// Vrati:
// int ; Integer hodnotu sirky a vysky.
//
function ChangetoIntLoc( $value_Dec ) {
	return $value_Dec * 1000000;
}//ChangetoIntLoc



// Zmeni string hodnotu na integer.
//
// Argumenty:
// string ; Hodnota string.
//
// Vrati:
// Int ; Je-li string prazdny, vrati NULL.
//

function ChangetoInt( $string ) {
	if ( strlen( trim( $string ) ) != 0 ) {
		return (int)$string;
	}
	else
		return NULL;
}//ChangetoInt



// Zmeni hodnotu na float
//
// Argumentu:
// string ; Hodnota string.
//
// Vrati:
// float ; Je-li string prazdny, vrati NULL.
//
function ChangetoFloat( $string ) {
	if ( strlen( trim( $string ) ) != 0 ) {
	return (float)$string;
	}
	else
		return NULL;
	}//ChangetoFloat

	// Vlozi JSON reprezentaci $response do $jsonresponse.
	$jsonresponse = json_encode( $response );
	
	// Deklaruje spravny typ obsahu do headeru HTTP odpovedi.
	header( "Content-type: application/json; charset=utf-8" );
	
	// Vytiskne JSON odpoved.
	echo $jsonresponse;
	
	/* Zavreme MySQL pripojeni.*/
	// Nastavime $db na NULL pro ukonceni pripojeni k databazi.
	$db=null;
?>
