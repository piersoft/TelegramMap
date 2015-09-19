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
//include('settings_t.php');
include("Telegram.php");

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
		$reply = "Benvenuto. Per inviare una segnalazione, clicca Invia posizione dall'icona a forma di graffetta e aspetta 1 minuto. Quando ricevi la risposta automatica, puoi scrivere un testo descrittivo. Non sono ammesse foto o altri contenuti multimediali come Video o Suoni. In qualsiasi momento scrivendo /start ti ripeterò questo messaggio di benvenuto.";
		$content = array('chat_id' => $chat_id, 'text' => $reply);
		$telegram->sendMessage($content);
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
		//  $file_id=implode(",",array($response["message"]["photo"][0]["file_id"]));
$type=$response["message"]["video"]["file_id"];
$risposta="";
$file_name="";
$file_path="";
if ($type !=NULL) {
$file_id=$type;
	$text="video allegato";
	$risposta="ID dell'allegato:".$file_id;

}
else {
$file_id=$response["message"]["photo"][0]["file_id"];
	$file_path=$response["message"]["photo"][0]["file_path"];
if ($file_id !=NULL) {
$text="foto allegata";
$risposta="ID dell'allegato:".$file_id;
 }
}
$typed=$response["message"]["document"]["file_id"];

if ($typed !=NULL){
$file_id=$typed;
$file_name=$response["message"]["document"]["file_name"];
$text="documento: ".$file_name." allegato";
$risposta="ID dell'allegato:".$file_id;

}
$username=$response["message"]["from"]["username"];
$first_name=$response["message"]["from"]["first_name"];

			//inserisce la segnalazione nel DB delle segnalazioni georiferite
			$statement = "UPDATE ".DB_TABLE_GEO ." SET text='".$text."',file_id='". $file_id ."',filename='". $file_name ."',first_name='". $first_name ."',file_path='". $file_path ."',username='". $username ."' WHERE bot_request_message ='".$reply_to_msg['message_id']."'";
			print_r($reply_to_msg['message_id']);
			$db->exec($statement);
			$reply = "La segnalazione è stata Registrata. ".$risposta." Grazie! ";
			$content = array('chat_id' => $chat_id, 'text' => $reply);
			$telegram->sendMessage($content);
			$log=$today. ";information for maps recorded;" .$chat_id. "\n";

			exec('sqlite3 -header -csv db.sqlite "select * from segnalazioni;" > map_data.csv');


		}
		//comando errato
		else{

			 $reply = "Hai selezionato un comando non previsto";
			 $content = array('chat_id' => $chat_id, 'text' => $reply);
			 $telegram->sendMessage($content);
			 $log=$today. ";wrong command sent;" .$chat_id. "\n";

		 }

		//aggiorna tastiera
		//$this->create_keyboard($telegram,$chat_id);
		//log
		file_put_contents(LOG_FILE, $log, FILE_APPEND | LOCK_EX);

}


// Crea la tastiera
 function create_keyboard($telegram, $chat_id)
	{
			//
	}

//crea la tastiera per scegliere la zona temperatura
 function create_keyboard_temp($telegram, $chat_id)
	{
			//
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
			$content = array('chat_id' => $chat_id, 'text' => "[Cosa sta accadendo qui?]", 'reply_markup' =>$forcehide, 'reply_to_message_id' =>$bot_request_message_id);
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
