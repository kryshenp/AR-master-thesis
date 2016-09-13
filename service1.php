<?php
// Autor: Bc. Pavlo Kryshenyk
// Diplomova prace: Vizualizace prostorovych dat v prostredi augmentovane reality 
// Univerzita Karlova v Praze
// Prirodovedecka fakulta
// Katedra aplikovane geoinformatiky a kartografie
// Email: kryshenykpavlo@gmail.com
// Praha 2016


// Author: Bc. Pavlo Kryshenyk
// Master thesis: Augmented reality visualization of Spatial Data
// Charles University in Prague
// Faculty of Science
// Department of Applied Geionformatics and Cartography 
// Email: kryshenykpavlo@gmail.com
// Prague 2016


// soubor config.inc.php obsahuje informace o databazi.
require_once('config.inc.php');

// Funkce vklada potrebne parametry getPOI dotazu a jejich hodnoty
// do asociativniho pole 
// Argumenty:
//  array ; Pole s potrebnymi paramentry, ktere se pouzivaji v dotazu getPOI 
// Vraci:
//  array ; Asociativni pole obsahujici parametry dotazu a jejich hodnoty.
function getRequestParams($keys) {

  $paramsArray = array();
  try {
    // Nacte hodnot parametru s pouzitim $_GET a vlozi je do polen $value s nazvem parametru jako klic. 
    foreach( $keys as $key ) {
      if ( isset($_GET[$key]) )
        $paramsArray[$key] = $_GET[$key]; 
      else 
        throw new Exception($key .' parameter is not passed in GetPOI request.');
    }
    return $paramsArray;
  }
  catch(Exception $e) {
    echo 'Message: ' .$e->getMessage();
  }
}//getRequestParams 

// Pripojeni k databazi, konfguracni informace jsou ulozeny v souboru config.inc.php 
function connectDb() {
  try {
    $dbconn = 'mysql:host=' . DBHOST . ';dbname=' . DBDATA ; 
    $db = new PDO($dbconn , DBUSER , DBPASS , array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
    // pro vyjimky se nastavi error mode 
    $db->setAttribute(PDO::ATTR_ERRMODE , PDO::ERRMODE_EXCEPTION);
     return $db; 
  }// try
  catch(PDOException $e) {
    error_log('message:' . $e->getMessage());
  }// catch
}// connectDb

// Funkce pro zmenu formatu hodnot ze string  na float
// Argumenty:
//   string ; Hodnota string .
// Vraci:
//   float ; Pokud je retezec prazdny vrati  NULL.
//
function changetoFloat($string) {
  if (strlen(trim($string)) != 0) 
    return (float)$string;
  return NULL;
}//changetoFloat

// Funkce pro zmenu formatu hodnot ze string na integer. 
// Argumenty:
//   string ; Hodnota string .
// Vraci:
//   Int ; Pokud je retezec prazdny vrati  NULL.
//
function changetoInt($string) {
  if (strlen(trim($string)) != 0) 
    return (int)$string;
  return NULL;
}//changetoInt

// Konvertace retezcu na pole.
// Arguments:
//  string ; Vstupni retezec
//  separater, string ; Hranicni retezec pro separaci vstupniho retezce
//
// Vraci:
//  array ; Pole retezcu. Jinak vrati prazdne pole. 
function changetoArray($string, $separator){
  $newArray = array();
  if($string) {
    if (substr_count($string,$separator)) {
      $newArray= array_map('trim' , explode($separator, $string));
        }//if
    else 
      $newArray[0] = trim($string);
  }
  return $newArray;
}//changetoArray

// Konvvertace hodnot TinyInt na booleanovske hodnoty TRUE nebo FALSE
// Argumenty: 
//  int  value_Tinyint ; Hodnota Tinyint (0 nebo 1) klice v databazi. 
// Vraci:
//   boolean ; Booleanovska hodnota, vraci 'TRUE', kdyz je hodnota Tinyint rovna 1. 
//     Vraci 'FALSE', kdyz je hodnota Tinyint rovna 0.
//
function changetoBool($value_Tinyint) {
  if (strlen(trim($value_Tinyint)) != 0) {
    if ($value_Tinyint == 0)
      return FALSE;
    else 
      return TRUE;
   }
  return NULL;
}//changetoBool

// Funkce dava akce pro kazdy POI do asociativniho pole.
//
// Argumenty:
//   db ; Handler pripojeni k databazi. 
//   poi ;  pole POI.
//
// Vraci:
//   array ; Asociativni pole akci vracenych pro kazdy konkrtni POI.
//   Ve vsech jinych pripadech vraci prazdne pole. 
// 
function getPoiActions($db , $poi) {
  // Definujeme prazdne pole $actionArray. 
  $actionArray = array();

  // V tabulce 'POIAction' jsou ulozeny akce, kazda akce obsahuje pole 'poiID', 
  // ktere znamena ID POI, ke kteremu akce patri. 
  // SQL prikaz vraci akce, ktere maji stejnou hodnotu atributu poiID jako id POI($poiID).
  $sql_actions = $db->prepare(' 
      SELECT label, 
             uri, 
             contentType,
             activityType,
             autoTriggerRange,
             autoTriggerOnly,
             params
      FROM POIAction
      WHERE poiID = :id '); 

  // Svaze pojmenovany marker parametru  ':id' se specifikovanou hodnotu paramtru '$poiID.                 
  $sql_actions->bindParam(':id', $poi['id'], PDO::PARAM_STR);
  // Pouzije se PDO::execute() rozhrani pro provedeni pripraveneho prokazu $sql_actions. 
  $sql_actions->execute();
  // Iterator pro pole$actionArray .
  $count = 0; 
  // Vyzvedne veskere akce pro poi . 
  $actions = $sql_actions->fetchAll(PDO::FETCH_ASSOC);

  /* Provedeni vysledku $actions  */
  // pokud pole $actions neni prazdne. 
  if ($actions) {
    // Vlozi indoemace o kazde akci do pole $actionArray.
    foreach ($actions as $action) { 
      // Zmeni 'activityType' na Integer.
      $action['activityType'] = changetoInt($action['activityType']);
      $action['autoTriggerRange'] = changetoInt($action['autoTriggerRange']);
      $action['autoTriggerOnly'] = changetoBool($action['autoTriggerOnly']);
      $action['params'] = changetoArray($action['params'] , ',');
      // Proradi kazdou akci do pole $actionArray. 
      $actionArray[$count] = $action;
      $count++; 
    }// foreach
  }//if
  return $actionArray;
}//getPoiActions

// Funkce dava nactene parametry pro kazdy POI do asociativniho pole

// Argumenty:
//   db ; handler pripojeni k databazi. 
//   objectID, integer ; object id spojeny s konkretnim POI.
// Vraci:
//   asociativni pole nebo NULL ; Pole obdrzenych paramtru vztazenych k objektu pro kazdy POI POI. Jinak vrati NULL 
// 
function getObject($db , $objectID) {
  // Pokud neni nalezen zadny objekt, vrati NULL. 
  $object = NULL;

  // V databazi byla vytvorena tabulka  'Object', kde jsou ulozeny parametry vztahujici se k objektu,
  // konkretne 'url', 'contentType', 'reducedURL' a 'size'. Tento SQL prikaz vraci objekt, ktry ma 
  // stejne id jako hodnota parametru $objectID pro kazdy konkreni POI. 
  $sql_object = $db->prepare(
    ' SELECT contentType,
             url, 
             reducedURL, 
             size 
      FROM Object
      WHERE id = :objectID 
      LIMIT 0,1 '); 

  // Svazuje pojmenovany marker parametru ':objectID' se specifikovanou hodnotu parametru $objectID.                 
  $sql_object->bindParam(':objectID', $objectID, PDO::PARAM_INT);
  // Pouziva rozhrani PDO::execute() pro provedeni pripraveneho prikazu $sql_object. 
  $sql_object->execute();
  // Nacte objekt poi. 
  $rawObject = $sql_object->fetch(PDO::FETCH_ASSOC);

  /* Realizace vysledku $rawObject  */
  // poku pole $rawObject neni prazdne  . 
  if ($rawObject) {
    // Zmeni format parametru 'size' na float. 
    $rawObject['size'] = changetoFloat($rawObject['size']);
    $object = $rawObject;
  }
  return $object;
}//getObject

// Funkce dava nactene parametry  transformaci pro kazdy POI do asociativniho pole.
// Vracene hodnoty budou prirazeny k $poi[transform].
//
// Argumenty:
//   db ; handler pripojeni k databazi. 
//   transformID , integer ; Id transformace spojene s kazdm konkrenim POI.
//
// Vraci: asociativni pole nebo  NULL; Pole obdrzzenych transformacu vztazenych
// ke kazdemu konkretnimu POI. Ve vsech jinych pripadech vrati, NULL. 
// 
function getTransform($db , $transformID) {
  // Pokud nenalezena zadna transformace, vrati NULL. 
  $transform = NULL;
  // V databazi byla vytvorena tabulka 'Transform', kde jsou ulozeny parametry 
  // vztahujici se k transformacim, konkretne jsou to  'rotate','translate' a 'scale'. 
  // 'transformID' je transformace, ktera se aplikuje na kazdy konkretni POI. 
  // SQL prikaz vracii transformaci, ktera ma stejne id jako hodnota $transformID pro kazdy POI. 
  $sql_transform = $db->prepare('
      SELECT rel, 
             angle, 
             rotate_x,
             rotate_y,
             rotate_z,
             translate_x,
             translate_y,
             translate_z,
             scale
      FROM Transform
      WHERE id = :transformID 
      LIMIT 0,1 '); 

  // Svaze pojmenovany marker parametru ':transformID' se specifikovanou 
  // hodnotou parametru $transformID                
  $sql_transform->bindParam(':transformID', $transformID, PDO::PARAM_INT);
  // S pouzitim rozhrani PDO::execute() provede se pripraveny prikaz $sql_transform. 
  $sql_transform->execute();
  // Nacteni transformaci poi. 
  $rawTransform = $sql_transform->fetch(PDO::FETCH_ASSOC);

  /* Realizace vysledku $rawTransform */
  // pokud pole $rawTransform neni prazdne 
  if ($rawTransform) {
    // Format hodnoty 'scale' bude zmenen na dekadicky.
    $transform['scale'] = changetoFloat($rawTransform['scale']);
    // organizace pole translate 
    $transform['translate']['x'] =changetoFloat($rawTransform['translate_x']);
    $transform['translate']['y'] = changetoFloat($rawTransform['translate_y']);
    $transform['translate']['z'] = changetoFloat($rawTransform['translate_z']);
    // organizace pole rotate 
    $transform['rotate']['axis']['x'] = changetoFloat($rawTransform['rotate_x']);
    $transform['rotate']['axis']['y'] = changetoFloat($rawTransform['rotate_y']);
    $transform['rotate']['axis']['z'] = changetoFloat($rawTransform['rotate_z']);
    $transform['rotate']['angle'] = changetoFloat($rawTransform['angle']);
    $transform['rotate']['rel'] = changetoBool($rawTransform['rel']);
  }//if 
    
  return $transform;
}//getTransform

// Funkce pripravuje hodnotu vyhledavani, ktera se pouzije v SQL prikazu. 
// Argumnery: 
//   searchbox ; hodnota parametru SEARCHBOX v dotazu GetPOI.
//
// Vraci:
//   searchbox_value ; Pokud parametr searchbox ma prazdny retezec, vrati
//   retezec, ktery je kombinaci cisel, pismen a mezer.
//   Ve vsech jinych pripadech vraci hodnotu parametru searchbox. 

function getSearchValue ($searchbox) {
  
  // pokud $searchbox existuje, pripravi hodnotu vyhledavani. 
  if (isset($searchbox)) {
  
    // inicializuje hodnotu vyhledavani tak, aby mohla byt jakymkoli retezcem 
    // obsahujicim cisle, pismena a mezery. 
    $searchbox_value = '[0-9a-zA-Z\s]*';
    
    // pokud $searchbox neni prazdnym retezcem vrari hodnotu $searchbox. 
    if (!empty($searchbox))
    $searchbox_value = $searchbox;
    
    return $searchbox_value;
  } //if
  else { // pokud vsak $searchbox ne existuje, vyhodi vyjimku. 
    throw new Exception("searchbox parameter is not passed in GetPOI request.");
  }//else

}// getSearchValue

// Funkce pripravuj hodnotu seznamu prepinacich tlacitek, ktera se pouzije v SQL prikazu. 
// V ramcich teto funkce konvertujeme vracenou hodnotu do jedne z ulozenych v databazi. 
// Argumenty:
// radiolist ; hodnota integer parametru RADIOLIST v dotazu GetPOI.
//
// Vraci:
// radio_value ; hodnotu,ktera muze byt pouzita pri konstruovani spravného SQL prikazu. 
function getRadioValue ($radiolist) {
  // pokud $radiolist existuje, pripravime radio_value.   
  if(isset($radiolist)) {
  
    $radio_value = '';
  // pokud $radiolist == 1, vrati se hodnota $radio_value ="Česká spořitelna"; 
  // pokud $radiolist == 2, vrati se hodnota $radio_value ="Komerční banka";
  // a tak dale az do hodnoty $radiolist == 12, kdy bude vraceno $radio_value ="vsechny banky";
  switch ($radiolist) {
    case '1':
    $radio_value = "Česká spořitelna" ;
    break;
    case '2': 
    $radio_value = "Komerční banka" ;
    break;
    case '3': 
    $radio_value = "ČSOB/Poštovní spořitelna" ;
    break;
    case '4': 
    $radio_value = "UniCredit Bank" ;
    break;
    case '5': 
    $radio_value = "GE Money Bank" ;
    break;
    case '6': 
    $radio_value = "Citibank" ;
    break;
    case '7': 
    $radio_value = "Raiffeisenbank" ;
    break;
    case '8': 
    $radio_value = "Fio Banka" ;
    break;
    case '9': 
    $radio_value = "Oberbank" ;
    break;
    case '10': 
    $radio_value = "AirBank" ;
    break;
    case '11': 
    $radio_value = "Sberbank" ;
    break;
    case '12': 
    $radio_value = "všechny banky" ;
    break;    
    default:
    throw new Exception("invalid radiolist value:" . $radiolist);
    } //switch
  
   return $radio_value;
  }//if
  else {
    throw new Exception("radiolist parameter is not passed in GetPOI request.");
  }//else
  
}// getRadioValue

// Funkce pripravuje hodnotu checkbox value ktera se pouzije pro SQL prikaz. 
// V teto funkci pridavame vsechna cisla v parametru $checkboxlist parameter.
// Pokud je $checkboxlist prazdnyy, vratime 0.
// Argument:
// checkboxlist ; hodnota parametru CHECKBOXLIST v dotazu GetPOI.
// Vraci:
// checkbox_value ; hodnotu checkbox, ktera muze byt pouzita pro konstruovani SQL prikazu. 

function getCheckboxValue ($checkboxlist) {

  // pokud $checkboxlist existuje, pripravi checkbox_value.   
  if(isset($checkboxlist)) {
  
    // Inicializuje vracenou hodnotu aby byla rovna 0 pokud je $checkboxlist prazdny. 
  $checkbox_value = 0;
  
  // Neni-li $checkboxlist prazdny, vrati hodnotu pridane hodnoty vsech cisel rozdelene  zavorkami','.
  if (!empty($checkboxlist)) {
  
    if (strstr($checkboxlist , ',')) {
    
      $checkbox_array = explode(',' , $checkboxlist);
      
      for( $i=0; $i<count($checkbox_array); $i++ )
        $checkbox_value+=$checkbox_array[$i]; 
        
    }//if
    else 
      $checkbox_value = $checkboxlist;
  }//if
  
  return $checkbox_value;
  } //if
  else {
    throw new Exception("checkboxlist parameter is not passed in GetPOI request.");
  }//else

}//getCheckboxValue

// Funkce getHotspots() vklada obdrzene body POI do asociativniho pole.
// Vracene hodnoty budou prirazeny do $reponse['hotspots'].
// Argumenty:
//   db ; Handler databaze.
//   value , array ; Pole ovsahujici veskere potrebne parametry ziskane z dotazu GetPOI 
// Vraci:
//   array ; Pole s obdrzenymi body POI.
//
function getHotspots( $db, $value ) {
  // Definujeme prazdna pole $hotspots, $distance a $atmType.
  $hotspots = array();
  $distance = array();
  $atmType = array();

/* Vytvorime dotaz SQL abychom obdrzeli body POI spadajici do polomeru ('radius') vraceneho z dotazu GetPOI. 
   Budou vybrany prvnich 50 bodu POI 50, ktere nejsou blizsi k uzivateli nez 20 metru.
   Vypocet vzdalenosti proviha na zaklade Haversine formula.
*/

/* Vytvorime SQL dotaz pro ziskani bodu POI, odpovidajicich kriterii nastaveni filtru, vracenych z dotazu GetPOI . 
   Vracene POIs jsou serazeny na zaklade vzdalenosti, vybiraji se pouze prvnich 50 bodu. 
   - Vzdalenost se pocita na zaklade Haversine formule. 
   - filtr zaskrtavacich policek, nalezne body POI u kterych hodnota vyberovy/vkladovy obsahuej hledanou hodnotu. 
     Je li checkbox prazdny vrati se vsechny body POI. 
   - filtr prepinacich tlacitek, nalezne body POI, kde je hodnota v sloupci "Radiolist" 
     rovna pripravene hodnote radiolis z funkceGetRadioValue. 
   - filtr checkbox, nalezne body poi, ktere nevraci 0 po porovnani z hodnotou ze sloupce "Checkbox"
     a pripravenou hodnotu zaskrtacaciho policka z funkce (GetCheckboxValue function) s pouzitim bitovych operaci. 
     http://en.wikipedia.org/wiki/Bitwise_operation. je-li parametr prazdny CHECKBOX, nevraci se zadne POI. 
*/
	
  // POuzijeme rozhrani PDO::prepare() pro pripravu SQL prikazu. Tento prikaz se pouziva z 
  // bezpecnostnich duvodu za ucelem zabraneni obecnych SQL utoku.
  // ':lat1', ':lat2', ':long' a ':radius' jsou pojmentovane markery parametru, pro ktere
  // jejich skutecne hodnoty budou nahrazene, kdyz bude proveden prikaz.
  // $sql je vracen jako objekt PDO prikazu. 
  $sql = $db->prepare( '
  			SELECT id,
               imageURL,
               atmType,
               title,
               description,
               footnote,
               lat,
               lon,
               (((acos(sin((:lat1 * pi() / 180)) * sin((lat * pi() / 180)) +
                  	  cos((:lat2 * pi() / 180)) * cos((lat * pi() / 180)) * 
                      cos((:long  - lon) * pi() / 180))
                      ) * 180 / pi()
               )* 60 * 1.1515 * 1.609344 * 1000
               ) as distance,
               objectID,
               transformID
  			  FROM POI
         WHERE poiType = "geo"
           AND description REGEXP :search
           AND Radiolist = :radiolist
           AND (Checkbox & :checkbox) != 0
        HAVING distance < :radius
           AND distance > 20
      ORDER BY distance ASC
         LIMIT 0, 50 ' );

  // PDO prikaz ::bindParam() svazuje pojmenovane markery parametru se specifikovnaymi hodnotami parametru. 
  $sql->bindParam( ':lat1', $value['lat'], PDO::PARAM_STR );
  $sql->bindParam( ':lat2', $value['lat'], PDO::PARAM_STR );
  $sql->bindParam( ':long', $value['lon'], PDO::PARAM_STR );
  $sql->bindParam( ':radius', $value['radius'], PDO::PARAM_INT );

  // Nastaveni parametru vlastnich filtru. Tri finkce Get mohou byt prizpusobene. 
  $sql->bindParam(':search', getSearchValue($value['SEARCHBOX']), PDO::PARAM_STR);
  $sql->bindParam(':radiolist', getRadioValue($value['RADIOLIST']), PDO::PARAM_STR);
  $sql->bindParam(':checkbox', getCheckboxValue($value['CHECKBOXLIST']), PDO::PARAM_INT);

  // S pouzitim rozhrani PDO::execute() provede se pripraveny prikaz $sql. 
  $sql->execute();
  // Iterator pro pole odpovedi.
  $i = 0; 
  // Pouzije se fetchAll pro vraceni vsech zbyvajicich radku v sade vysledku.
  // S pouzitim rozhrani PDO::FETCH_ASSOC nactou se vsechny vysledky $sql dotazu a vrati se kazda radka jako 
  // pole indexovane  podle nazvu sloupce.
  $rawPois = $sql->fetchAll(PDO::FETCH_ASSOC);
 
  /* Provede se vysledek $pois */
  // pokud neni pole $rawPois prazdne 
  if ($rawPois) {
    // Veskere informace k bodum POI budou vlozeny do pole $hotspots.
 	  foreach ( $rawPois as $rawPoi ) {
 	  $poi = array();
      $poi['distance'] = $rawPoi['distance'];
      $poi['id'] = $rawPoi['id'];
      $poi['atmType'] = $rawPoi['atmType'];
      $poi['imageURL'] = $rawPoi['imageURL'];
      // Ziskava se informace anchor objektu 
      $poi['anchor']['geolocation']['lat'] = changetoFloat($rawPoi['lat']);
      $poi['anchor']['geolocation']['lon'] = changetoFloat($rawPoi['lon']);
      // ziskavaju se informace o textovych objektech
      $poi['text']['title'] = $rawPoi['title'];
      $poi['text']['description'] = $rawPoi['description'];
      $poi['text']['footnote'] = $rawPoi['footnote'];
      $poi['text']['test']  = "test";
      //Funkce getPOiActions() vrati pole akci spojenych s konkretnim POI 
      $poi['actions'] = getPoiActions($db, $rawPoi);
      // Ziskava se informace o objektu
      // Funkce ObjectID() vrati hodnotu $ObjectID 
      $poi['object'] = getObject($db, ObjectID($rawPoi['distance'], $rawPoi['atmType']));
      // Ziskava se informace o transformacich
      // Funkce TransformID() vrati hodnotu $TransformID 
      $poi['transform'] = getTransform($db, transformID($rawPoi['distance'], $rawPoi['atmType']));

      // Vlozime poi do pole $hotspots.
      $hotspots[$i] = $poi;
      $distance[$i] = $poi['distance'];
      $atmType[$i] = $poi['atmType'];

      $i++;
    }//foreach
  }//if
  return $hotspots;

}//getHotspots


// funkce urcuje objekt k vizualizaci
function ObjectID($distance, $atmType) {
// definujeme pole s prahovymi hodnotami pro vzdalenost
  $disttreshold = array(1, 150, 500, 1000, 2000, 5000, 100000000);
// pro kazdy usek mezi prahovymi hodnotami 
  for ($i=0; $i < count($disttreshold); $i++) {
// je li hodnota $distance vetsi nebo rovna jiste prahove hodnote a mensi nez prahova hodnota vpravo 
    if ($distance >= $disttreshold[$i] && $distance < $disttreshold[$i + 1]) {
// vrati $ObjectID, ktery je roven 10 nasobeno hodnotou parametru $atmType + index prahove hodnoty + 1      
      return (10 * $atmType + ($i + 1));
    }
  }//for
}//ObjectID

// Funkce urcuje transformace objektu
function TransformID($distance, $atmType) {
// definujeme pole s prahovymi hodnotami pro vzdalenost
  $disttreshold = array(1, 75, 150, 325, 500, 750, 1000, 1500, 2000, 3500, 5000, 100000000);
// pro kazdy usek mezi prahovymi hodnotami 
  for ($i=0; $i < count($disttreshold); $i++) { 
// je li hodnota $distance vetsi nebo rovna jiste prahove hodnote a mensi nez prahova hodnota vpravo     
    if ($distance >= $disttreshold[$i] && $distance < $disttreshold[$i + 1]) {
// vrati $TransformID, indexu prahove hodnoty + 1      
      return ($i + 1);
    }//if
  }//for 
}//TranssformID


/*** Hlavni vstupni bod ***/

/* Vlozime parametry z dotazu GetPOI do asociativniho pole s nazvem $requestParams */
// Vlozime potrebna jmena parametru z dotazu GetPOI do pole $keys. 
$keys = array('layerName', 'lat', 'lon', 'radius', 'RADIOLIST', 'CHECKBOXLIST', 'SEARCHBOX');

// Inicializujeme prazdne asociativni pole.
$requestParams = array(); 
// Zavolame funkci getRequestParams()  
$requestParams = getRequestParams($keys);
// Spojime se se serverem MySQL. Pouzivame PDO rozhrani, ktere je extenzi PHP pro formalizaci spojeni z databazi.	
// Spojime s preddefinouvanou MySQl databazi.  
$db = connectDb(); 
	
/* Konstruovani odpovedi do asociativniho pole.*/
	
// Vytvorime prazdne pole $response.
$response = array();
	
// Priradime odpovidajici hodnoty k povinnym klicum JSON odpovedi cooresponding values to mandatory JSON response keys.
$response['layer'] = $requestParams['layerName'];
	
// Pouzijeme funkci Gethotspots() pro ziskani bodu POI uvnitr polomeru vyhledavani.  
$response['hotspots'] = getHotspots($db, $requestParams);

// pokud nebude nalezen zadny bod POI, vrati se nastavena chybova zprava.
if (!$response['hotspots'] ) {
	$response['errorCode'] = 20;
 	$response['errorString'] = 'Nenalezen žádný objekt. Nastavte prosím poloměr vyhledávání.';
}//if
else {
  $response['errorCode'] = 0;
  $response['errorString'] = 'ok';
}//else
   
	/* Veskera dara je ted v $response, vytiskneme je do formatu JSON.*/
	
	// Vlozime reprezentaci JSON odpovedi $response do $jsonresponse.
	$jsonresponse = json_encode( $response );
	
	// Deklarujeme spravny typ v zahlavi HTTP odpovedi.
	header( 'Content-type: application/json; charset=utf-8' );
	
	// Vytiskneme Json odpoved.
	echo $jsonresponse;

?>
		