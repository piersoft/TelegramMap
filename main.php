<?php
/**
* Telegram Bot example for mapping
* @author Matteo Tempestini
Funzionamento
- invio location
- invio segnalazione come risposta
- memorizzazione dati nel DB SQLITE
- export in file CSV e MAPPING

Accertarsi che db.sqlite abbia questi campi TEXT: lat,lng,user,username,text,bot_request_message,time,file_id,filename,first_name,file_path

*/

include("Telegram.php");
include("QueryLocation.php");

class mainloop{

function start($telegram,$update)
{

	date_default_timezone_set('Europe/Rome');
	$today = date("Y-m-d H:i:s");

	// Instances the class
	$db = new PDO(DB_NAME);

	/* If you need to manually take some parameters
	*  $result = $telegram->getData();
	*  $text = $result["message"] ["text"];
	*  $chat_id = $result["message"] ["chat"]["id"];
	*/


	$text = $update["message"] ["text"];
	$chat_id = $update["message"] ["chat"]["id"];
	$user_id=$update["message"]["from"]["id"];
	$location=$update["message"]["location"];
	$reply_to_msg=$update["message"]["reply_to_message"];

	$this->shell($telegram, $db,$text,$chat_id,$user_id,$location,$reply_to_msg);
	$db = NULL;

}

//gestisce l'interfaccia utente
 function shell($telegram,$db,$text,$chat_id,$user_id,$location,$reply_to_msg)
{
	date_default_timezone_set('Europe/Rome');
	$today = date("Y-m-d H:i:s");

	if ($text == "/start") {
		$reply = "Benvenuto. Per inviare una segnalazione, clicca Invia posizione dall'icona a forma di graffetta e aspetta 1 minuto. Quando ricevi la risposta automatica, puoi scrivere un testo descrittivo o allegare un contenuto video foto audio ect. In qualsiasi momento scrivendo /start ti ripeterò questo messaggio di benvenuto. Declino ogni responsabilità dall'uso improprio di questo strumento e dei contenuti degli utenti. Tutte le info sono sui server Telegram, mentre in un database locale c'è traccia dei links degli allegati da te inviati";
		$content = array('chat_id' => $chat_id, 'text' => $reply);
		$telegram->sendMessage($content);

		$forcehide=$telegram->buildKeyBoardHide(true);
		$content = array('chat_id' => $chat_id, 'text' => "", 'reply_markup' =>$forcehide, 'reply_to_message_id' =>$bot_request_message_id);
		$bot_request_message=$telegram->sendMessage($content);
		$log=$today. ";new chat started;" .$chat_id. "\n";
		}

		//gestione segnalazioni georiferite
		elseif($location!=null)
		{

			$this->location_manager($db,$telegram,$user_id,$chat_id,$location);
			exit;

		}
		elseif($reply_to_msg!=null)
		{

			$response=$telegram->getData();

$type=$response["message"]["video"]["file_id"];
$text =$response["message"]["text"];
$risposta="";
$file_name="";
$file_path="";
$file_name="";

if ($type !=NULL) {
  $file_id=$type;
$text="video allegato";
	$risposta="ID dell'allegato:".$file_id;
}

$file_id=$response["message"]["photo"][0]["file_id"];

if ($file_id !=NULL) {

$telegramtk=TELEGRAM_BOT; // inserire il token
$rawData = file_get_contents("https://api.telegram.org/bot".$telegramtk."/getFile?file_id=".$file_id);
$obj=json_decode($rawData, true);
$file_path=$obj["result"]["file_path"];
$caption=$response["message"]["caption"];
if ($caption != NULL) $text=$caption;
$risposta="ID dell'allegato: ".$file_id;

}
$typed=$response["message"]["document"]["file_id"];

if ($typed !=NULL){
$file_id=$typed;
$file_name=$response["message"]["document"]["file_name"];
$text="documento: ".$file_name." allegato";
$risposta="ID dell'allegato:".$file_id;

}

$typev=$response["message"]["voice"]["file_id"];
if ($typev !=NULL){
	$file_id=$typev;
  $text="audio allegato";
	$risposta="ID dell'allegato:".$file_id;

}
$csv_path=dirname(__FILE__).'/./map_data.csv';
$db_path=dirname(__FILE__).'/./db.sqlite';

$username=$response["message"]["from"]["username"];
$first_name=$response["message"]["from"]["first_name"];

$db1 = new SQLite3($db_path);
$q = "SELECT lat,lng FROM ".DB_TABLE_GEO ." WHERE bot_request_message='".$reply_to_msg['message_id']."'";
$result=	$db1->query($q);
$row = array();
$i=0;

while($res = $result->fetchArray(SQLITE3_ASSOC)){

						if(!isset($res['lat'])) continue;

						 $row[$i]['lat'] = $res['lat'];
						 $row[$i]['lng'] = $res['lng'];
						 $i++;
				 }

		//inserisce la segnalazione nel DB delle segnalazioni georiferite
			$statement = "UPDATE ".DB_TABLE_GEO ." SET text='".$text."',file_id='". $file_id ."',filename='". $file_name ."',first_name='". $first_name ."',file_path='". $file_path ."',username='". $username ."' WHERE bot_request_message ='".$reply_to_msg['message_id']."'";
			print_r($reply_to_msg['message_id']);
			$db->exec($statement);
	//		$this->create_keyboard_temp($telegram,$chat_id);

if ($text=="benzine" || $text=="farmacie" || $text=="musei")
{
	$tag="amenity=pharmacy";
if ($text=="musei") $tag="tourism=museum";
if ($text=="benzine") $tag="amenity=fuel";

	      $lon=$row[0]['lng'];
				$lat=$row[0]['lat'];
	//prelevo dati da OSM sulla base della mia posizione
					$osm_data=give_osm_data($lat,$lon,$tag	);

					//rispondo inviando i dati di Openstreetmap
					$osm_data_dec = simplexml_load_string($osm_data);

					//per ogni nodo prelevo coordinate e nome
					foreach ($osm_data_dec->node as $osm_element) {

						$nome="";
						foreach ($osm_element->tag as $key) {
//print_r($key);
							if ($key['k']=='name' || $key['k']=='wheelchair' || $key['k']=='phone' || $key['k']=='addr:street' )
							{
							if ($key['k']=='wheelchair')
									{
											$valore=utf8_encode($key['v'])."\n";
											$valore=str_replace("yes","si",$valore);
											$valore=str_replace("limited","con limitazioni",$valore);
											$nome .="Accessibile da disabili: ".$valore;
									}
							if ($key['k']=='phone')	$nome  .="Telefono: ".utf8_encode($key['v'])."\n";
							if ($key['k']=='addr:street')	$nome .="Indirizzo: ".utf8_encode($key['v'])."\n";
							if ($key['k']=='name')	$nome  .="Nome: ".utf8_encode($key['v'])."\n";

							}

						}
						//gestione musei senza il tag nome
						if($nome=="")
						{
							//	$nome=utf8_encode("Luogo non presente o identificato su Openstreetmap");
							//	$content = array('chat_id' => $chat_id, 'text' =>$nome);
							//	$telegram->sendMessage($content);
						}
						$content = array('chat_id' => $chat_id, 'text' =>$nome);
						$telegram->sendMessage($content);
						$reply = "Puoi visualizzarlo su :\nhttp://www.openstreetmap.org/?mlat=".$osm_element['lat']."&mlon=".$osm_element['lon']."#map=19/".$osm_element['lat']."/".$osm_element['lon'];
						$content = array('chat_id' => $chat_id, 'text' => $reply);
						$telegram->sendMessage($content);
					 }

					//crediti dei dati
					if((bool)$osm_data_dec->node)
					{
						$content = array('chat_id' => $chat_id, 'text' => utf8_encode("Questi sono i luoghi vicini a te entro 5km \n(dati forniti tramite OpenStreetMap. Licenza ODbL (c) OpenStreetMap contributors)"));
						$bot_request_message=$telegram->sendMessage($content);
					}else
					{
						$content = array('chat_id' => $chat_id, 'text' => utf8_encode("Non ci sono sono luoghi vicini, mi spiace! Se ne conosci uno nelle vicinanze mappalo su www.openstreetmap.org"));
						$bot_request_message=$telegram->sendMessage($content);
					}
}else{


			$reply = "La segnalazione è stata Registrata.\n".$risposta."Grazie!";
			$reply .= "Puoi visualizzarla su :\nhttp://umap.openstreetmap.fr/it/map/segnalazioni-con-piersoftbot_52968#19/".$row[0]['lat']."/".$row[0]['lng'];
			$content = array('chat_id' => $chat_id, 'text' => $reply);
			$telegram->sendMessage($content);
			$log=$today. ";information for maps recorded;" .$chat_id. "\n";

			exec(' sqlite3 -header -csv '.$db_path.' "select * from segnalazioni;" > '.$csv_path. ' ');
}

		}
		//comando errato
		else{

			 $reply = "Hai selezionato un comando non previsto. Ricordati che devi prima inviare la tua posizione";
			 $content = array('chat_id' => $chat_id, 'text' => $reply);
			 $telegram->sendMessage($content);

			 $log=$today. ";wrong command sent;" .$chat_id. "\n";

		 }

		//aggiorna tastiera
		$this->create_keyboard($telegram,$chat_id);
		//log
		file_put_contents(LOG_FILE, $log, FILE_APPEND | LOCK_EX);

}


// Crea la tastiera
 function create_keyboard($telegram, $chat_id)
	{
		$forcehide=$telegram->buildKeyBoardHide(true);
		$content = array('chat_id' => $chat_id, 'text' => "", 'reply_markup' =>$forcehide, 'reply_to_message_id' =>$bot_request_message_id);
		$bot_request_message=$telegram->sendMessage($content);
	}

//crea la tastiera per scegliere la zona temperatura
 function create_keyboard_temp($telegram, $chat_id)
	{
		$option = array(["farmacie","musei"]);
		$keyb = $telegram->buildKeyBoard($option, $onetime=true);
		$content = array('chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => "[Seleziona i luoghi di interesse o invia una segnalazione. Aggiornamento risposte ogni minuto]");
		$telegram->sendMessage($content);
	}



function location_manager($db,$telegram,$user_id,$chat_id,$location)
	{

			$lng=$location["longitude"];
			$lat=$location["latitude"];

			//rispondo
			$response=$telegram->getData();

			$bot_request_message_id=$response["message"]["message_id"];
			$time=$response["message"]["date"]; //registro nel DB anche il tempo unix

			$h = "2";// Hour for time zone goes here e.g. +7 or -4, just remove the + or -
			$hm = $h * 60;
			$ms = $hm * 60;
			$timec=gmdate("Y-m-d\TH:i:s\Z", $time+($ms));
			$timec=str_replace("T"," ",$timec);
			$timec=str_replace("Z"," ",$timec);
			//nascondo la tastiera e forzo l'utente a darmi una risposta
			$forcehide=$telegram->buildForceReply(true);

			//chiedo cosa sta accadendo nel luogo
			$content = array('chat_id' => $chat_id, 'text' => "[Cosa vuoi comunicarmi di questo posto? oppure, in via sperimentale, scrivi:\n\nfarmacie\no\nmusei\no\nbenzine (tutto minuscolo).\n\nTi indicherò quelle più vicine alla tua posizione nell'arco di 5km]", 'reply_markup' =>$forcehide, 'reply_to_message_id' =>$bot_request_message_id);
			$bot_request_message=$telegram->sendMessage($content);

			//memorizzare nel DB
			$obj=json_decode($bot_request_message);
			$id=$obj->result;
			$id=$id->message_id;

			//print_r($id);
			$statement = "INSERT INTO ". DB_TABLE_GEO. " (lat,lng,user,username,text,bot_request_message,time,file_id,file_path,filename,first_name) VALUES ('" . $lat . "','" . $lng . "','" . $user_id . "',' ',' ','". $id ."','". $timec ."',' ',' ',' ',' ')";
						$db->exec($statement);
	}


}

?>
