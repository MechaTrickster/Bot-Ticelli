<?php
/*
 * Telegram Bot Sample
 * ===================
 * UWiClab, University of Urbino
 * ===================
 * Basic message processing functionality,
 * used by both pull and push scripts.
 *
 * Put your custom bot intelligence here!
 */

// This file assumes to be included by pull.php or
// hook.php right after receiving a new message.
// It also assumes that the message data is stored
// inside a $message variable.

// Message object structure: {
//     "message_id": 123,
//     "from": {
//       "id": 123456789,
//       "first_name": "First",
//       "last_name": "Last",
//       "username": "FirstLast"
//     },
//     "chat": {
//       "id": 123456789,
//       "first_name": "First",
//       "last_name": "Last",
//       "username": "FirstLast",
//       "type": "private"
//     },
//     "date": 1460036220,
//     "text": "Text"
//   }
$message_id = $message['message_id'];
$chat_id = $message['chat']['id'];
$from_id = $message['from']['id'];

$nn = 0;

//se viene inviata la posizione
if (isset($message['location'])) {
    $lat = $message['location']['latitude'];
    $lng = $message['location']['longitude'];

    //inserisce i dati nella tabella 'current_position'
    db_perform_action("REPLACE INTO current_pos VALUES($chat_id, $lat,
    $lng)");

    echo "Utente $from_id in $lat,$lng" . PHP_EOL;
}

//se viene inviato del testo
else if (isset($message['text'])) {

    $text = $message['text'];

    //cerca la posizione più vicina
    if (strpos($text, "cerca") === 0) {
 
        //estrapola la posizione dell'utente
        $pos = db_table_query("SELECT * FROM current_pos WHERE Id = $from_id");

        //se l'utente ha segnalato la sua posizione
        if (count($pos) >= 1) {
            
            //copia le coordinate
            $lat = $pos[0][1];
            $lng = $pos[0][2];

            //estrae la locazione piu' vicina all'utente corrente
            $nearby = db_table_query("SELECT *, 
            SQRT(POW($lat - Latitudine, 2) + POW($lng - Longitudine, 2)) 
            AS distance
            FROM  beniCulturali`
            ORDER BY distance ASC
            LIMIT 1");

            telegram_send_location($chat_id, $nearby[0][44], $nearby[0][45]);
            telegram_send_message($chat_id, 'Questa è la location a te più vicina', null);
        }

        //posizione non trovata
        else 
            telegram_send_message($chat_id, 'Devi mandare prima le tue coordinate', null);
    }

    //salva una nuova posizione
    else if (strpos($text, "salva") === 0) {
        
        $save_id = 0;

        //estrae l'id dalla tabella 'current_position'
        $save_id = db_scalar_query("SELECT Id FROM current_pos WHERE Id = 
        $from_id");

        //se l'id utente trova corrispondenza nella tabella 'current_position' 
        if ($save_id != 0) {
            
            //copia latitudine 
            $save_lat = db_scalar_query("SELECT Latitudine FROM current_pos");
            
            //copia longitudine
            $save_lng = db_scalar_query("SELECT Longitudine FROM current_pos");

            //***VALIDAZIONE INSERIMENTO ***/
            //cerca la posizione più vicina a quella corrente
            $prova = db_table_query("SELECT *, 
            SQRT(POW($save_lat - Latitudine, 2) + POW($save_lng - Longitudine, 2)) 
            AS distance
            FROM  beniCulturali`
            ORDER BY distance ASC
            LIMIT 1");

            //se la posizione corrente rientra nell'intervallo di quella più vicina
            //allora non viene consentito l'inserimento
            if (($save_lat >= $prova[0][44]-0.001 && $save_lat <= $prova[0][44]+0.001) && 
                ($save_lng >= $prova[0][45]-0.001 && $save_lng <= $prova[0][45]+0.001))

                    //sono dentro l'area
                    telegram_send_message($chat_id, 'Questa posizione è già stata inserita !', null);
            else {

                //fuori dall'area, posso salvare 
            
                $newID = uniqid();

                $id = hexdec( uniqid() );

                db_perform_action("INSERT INTO beniCulturali (Id, Longitudine, Latitudine)
                VALUES($id, $save_lng, $save_lat)");   

                telegram_send_message($chat_id, 'Una nuova posizione è stata inserita', null);
            }           
        }

        //se l'id utente non è stato trovato
        else
            telegram_send_message($chat_id, 'Devi inviare la tua posizione prima di poter salvare', null);
    }

    else if (strpos($text, "dipinto") === 9){

            //estrapola la posizione dell'utente
            $pos = db_table_query("SELECT * FROM current_pos WHERE Id = 
            $from_id");
        
            //se l'utente ha segnalato la sua posizione
            if (count($pos) >= 1) {
                    
                //copia le coordinate
                $lat = $pos[0][1];
                $lng = $pos[0][2];
        
                //estrae la locazione piu' vicina all'utente corrente
                $nearby = db_table_query("SELECT *, 
                SQRT(POW($lat - Latitudine, 2) + POW($lng - Longitudine, 2)) 
                AS distance
                FROM  beniCulturali`
                ORDER BY distance ASC
                LIMIT 1");  

                $nn = $nearby[0][5];

                //controlla di essere nel raggio (200m) di un opera
                if (($lat >= $nearby[0][44]+0.001 && $lat <= $nearby[0][44]-0.001) && 
                    ($lng >= $nearby[0][45]+0.001 && $lng <= $nearby[0][45]-0.001)) {

                        //se non è nel raggio
                        telegram_send_message($chat_id, 'Devi essere vicino a un opera per effettuare modifiche', null);
                }
                //se è nel raggio
                else {

                    //seleziona l'opera in questione
                    $idOpera = db_table_query("SELECT Id FROM beniCulturali WHERE Latitudine BETWEEN
                                  $lat - 0.001 AND $lat + 0.001 AND Longitudine BETWEEN
                                  $lng - 0.001 AND $lng + 0.001 LIMIT 1");
              
              $nearby[0] = 1;
              telegram_send_message($chat_id, "ID = $nn", null);

                    //validazione
                   // $val = db_table_query("SELECT Gpl FROM beniCulturali WHERE Id = $bene");



                   /* $val = db_table_query("SELECT CASE
                                                WHEN Gpl = 'true' AND Id = $bene
                                                    THEN 1
                                                WHEN Gpl = 'false' AND Id = $bene
                                                    THEN 0
                                            END
                                            FROM beniCulturali");    */

                 /*   if($val[0][46] == 'true' || $tipo != null)
                        telegram_send_message($chat_id, 'Un dipinto è già stato inserito', null);    
                    
                    else if($val[0][46] == 'false') {
                        db_perform_action("UPDATE beniCulturali SET Dipinto = true WHERE Id = $bene");
                    
                        telegram_send_message($chat_id, 'Hai aggiunto un nuovo dipinto', null);
                    }

                    else
                    telegram_send_message($chat_id, 'Problemi presenti nel DB', null);  */
                }                
            }
            //posizione non trovata 
            else
                telegram_send_message($chat_id, 'Devi mandare prima le tue coordinate', null);
    }
    
    else if (strpos($text, "scultura") === 9){


    }

    else if (strpos($text, "altro") === 9){


    }

    else
        telegram_send_message($chat_id, 'Non conosco questo comando', null);

/*  if (strpos($text, "/start") === 0) {
        //Start command

       // telegram_send_message($chat_id, 'Ciao! Il sondaggio di oggi è: quant\'è blu il cielo? Vota col comando /vote seguito da un numero da 1 a 5', null);

        echo 'Received /start command!' . PHP_EOL;
    }
    else if (strpos($text, "/vote") === 0) {
        //Vote command        
        echo 'Received /start command!' . PHP_EOL;

        $voto = intval( substr($text, 6) );

        //Estrae tutte le posizioni dal db, dove la colonna user_id assume
        //l'id dell'utente che sta conversando
        $pos = db_table_query("SELECT * FROM current_position WHERE user_id = 
        $from_id");

        //Se la posizione è almeno una
        if(count($pos) >= 1) {
            //Posizione trovata
            $lat = $pos[0][1];
            $lng = $pos[0][2];

            db_perform_action("REPLACE INTO votes VALUES($from_id, '$voto', 
            NOW(), $lat, $lng)");

            telegram_send_message($chat_id, "Voto registrato!");
        }
        else {
            //Psizione non trovata
            telegram_send_message($chat_id, "Inviami la tua posizione prima
            di votare!");
        }

        
    }

    else if (strpos($text, "/results") === 0) {
        $media = db_scalar_query("SELECT AVG(vote) FROM votes");

        $voti = db_table_query("SELECT vote, COUNT(*) FROM votes GROUP BY vote");

        $risposta = "Voti: ";
        foreach($voti as $voto => $conteggio) {
            $risposta .= $conteggio[0] . "(" . $conteggio[1] . ")";         
        }

        telegram_send_message($chat_id, "$risposta la media dei voti è $media", null);
    }
    else {
        echo "Received message: $text" . PHP_EOL;

        // Do something else...
    }   */
}
else {
    telegram_send_message($chat_id, 'Sorry, I understand only text messages at the moment!');
}
?>
