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

$museo_vicino = 0;

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
    if (strpos($text, "Cerca") === 0) {
 
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
            FROM musei
            ORDER BY distance ASC
            ");

            telegram_send_location($chat_id, $nearby[0][13], $nearby[0][14]);
            telegram_send_message($chat_id, 'Questa è la location a te più vicina', null);
            if ($nearby[0][11] != NULL)
                telegram_send_message($chat_id, "Museo di ".$nearby[0][11], null);
            else if ($nearby[0][20] > 0)
                telegram_send_message($chat_id, "Museo di "."arte", null);
            else if ($nearby[0][21] > 0)
                telegram_send_message($chat_id, "Museo di "."storia", null);
            else if ($nearby[0][22] > 0)
                telegram_send_message($chat_id, "Museo di "."altro tipo", null);
            else
                telegram_send_message($chat_id,'Il tipo di museo non è stato specificato', null);
        }

        //posizione non trovata
        else 
            telegram_send_message($chat_id, 'Devi mandare prima le tue coordinate', null);
    }

    //salva una nuova posizione
    else if (strpos($text, "Salva") === 0) {

        //estrae l'id dalla tabella 'current_position'
        $current = db_table_query("SELECT * FROM current_pos WHERE Id = $from_id");

        //se l'id utente trova corrispondenza nella tabella 'current_position' 
        if ($current[0][0] != 0) {
            
            //copia latitudine 
            $current_lat = db_scalar_query("SELECT Latitudine FROM current_pos WHERE Id = 
                                            $from_id");
            
            //copia longitudine
            $current_lng = db_scalar_query("SELECT Longitudine FROM current_pos WHERE Id = 
                                            $from_id");

            $opera_pos = db_table_query("SELECT *, 
            SQRT(POW($current_lat - Latitudine, 2) + POW($current_lng - Longitudine, 2)) 
            AS distance
            FROM musei
            ORDER BY distance ASC
            LIMIT 1");

            //se la posizione corrente rientra nell'intervallo di quella più vicina
            //allora non viene consentito l'inserimento
            if (($current_lat >= $opera_pos[0][13]-0.001 && $current_lat <= $opera_pos[0][13]+0.001) && 
                ($current_lng >= $opera_pos[0][14]-0.001 && $current_lng <= $opera_pos[0][14]+0.001))

                    //sono dentro l'area
                    telegram_send_message($chat_id, 'Questo museo è già stato inserito!', null);
            else {

                //fuori dall'area, posso salvare             
                $newID = uniqid();

                $id = hexdec( uniqid() );

                db_perform_action("INSERT INTO musei (Id, Longitudine, Latitudine)
                VALUES($id, $current_lng, $current_lat)");   

                telegram_send_message($chat_id, 'Una nuova posizione è stata inserita', null);
            }           
        }

        //se l'id utente non è stato trovato
        else
            telegram_send_message($chat_id, 'Devi inviare la tua posizione prima di poter salvare', null);
    }

    else if (strpos($text, "d'arte") === 15){

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
                FROM musei
                ORDER BY distance ASC
                LIMIT 1");  

                $museo_vicino = $nearby[0][0];

                //controlla di essere nel raggio (200m) di un opera
                if (($lat >= $nearby[0][13]+0.001 && $lat <= $nearby[0][13]-0.001) && 
                    ($lng >= $nearby[0][14]+0.001 && $lng <= $nearby[0][14]-0.001)) {

                        //se non è nel raggio
                        telegram_send_message($chat_id, 'Devi essere nel raggio di un opera per poter effettuare modifiche', null);
                }
                //se è nel raggio
                else {

                    if ($nearby[0][20] == 0 && $nearby[0][21] == 0 && $nearby[0][22] == 0)
                    {   
                        //aggiunge presenza di un dipinto nel db
                        db_perform_action("UPDATE musei SET Arte = 1 WHERE Id = $museo_vicino ");

                        telegram_send_message($chat_id, 'Hai aggiunto il dettaglio museo di arte', null);
                    }
                    else if ($nearby[0][20] == 1)                     
                        telegram_send_message($chat_id, 'Il dettaglio museo di arte è già stato inserito', null);
                    else
                    telegram_send_message($chat_id, 'Un dettaglio per questo museo opera è già presente...', null);
                }                
            }
            //posizione non trovata 
            else
                telegram_send_message($chat_id, 'Devi mandare prima le tue coordinate', null);
    }
    
    else if (strpos($text, "storico") === 15){

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
                FROM musei
                ORDER BY distance ASC
                LIMIT 1");  

                $museo_vicino = $nearby[0][5];

                //controlla di essere nel raggio (200m) di un opera
                if (($lat >= $nearby[0][13]+0.001 && $lat <= $nearby[0][13]-0.001) && 
                    ($lng >= $nearby[0][14]+0.001 && $lng <= $nearby[0][14]-0.001)) {

                        //se non è nel raggio
                        telegram_send_message($chat_id, 'Devi essere nel raggio di un opera per poter effettuare modifiche', null);
                }
                //se è nel raggio
                else {

                    if ($nearby[0][20] == 0 && $nearby[0][21] == 0 && $nearby[0][22] == 0)
                    {   
                        //aggiunge presenza di un dipinto nel db
                        db_perform_action("UPDATE musei SET Storia = 1 WHERE Id = $museo_vicino ");

                        telegram_send_message($chat_id, 'Hai aggiunto il dettaglio museo storico', null);
                    }
                    else if ($nearby[0][21] == 1)                     
                        telegram_send_message($chat_id, 'Il dettaglio museo storico è già stato inserito', null);
                    else
                        telegram_send_message($chat_id, 'Un dettaglio per questo museo è già presente...', null);
                }                
            }
            //posizione non trovata 
            else
                telegram_send_message($chat_id, 'Devi mandare prima le tue coordinate', null);
    }

    else if (strpos($text, "altro") === 9){
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
                FROM musei
                ORDER BY distance ASC
                LIMIT 1");  

                $museo_vicino = $nearby[0][0];

                //controlla di essere nel raggio (200m) di un opera
                if (($lat >= $nearby[0][13]+0.001 && $lat <= $nearby[0][13]-0.001) && 
                    ($lng >= $nearby[0][14]+0.001 && $lng <= $nearby[0][14]-0.001)) {

                        //se non è nel raggio
                        telegram_send_message($chat_id, 'Devi essere nel raggio di un opera per poter effettuare modifiche', null);
                }
                //se è nel raggio
                else {

                    if ($nearby[0][20] == 0 && $nearby[0][21] == 0 && $nearby[0][22] == 0)
                    {   
                        //aggiunge presenza di un dipinto nel db
                        db_perform_action("UPDATE musei SET Altro = 1 WHERE Id = $museo_vicino ");

                        telegram_send_message($chat_id, 'Hai aggiunto il dettaglio "Altro"', null);
                    }
                    else if ($nearby[0][22] == 1)                     
                        telegram_send_message($chat_id, 'Il dettaglio "Altro" è già stato inserito', null);
                    else
                    telegram_send_message($chat_id, 'Un dettaglio per questo museo è già presente...', null);
                }                
            }
            //posizione non trovata 
            else
                telegram_send_message($chat_id, 'Devi mandare prima le tue coordinate', null);
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

        //Estrae tutte le posizioni dal db, dove la colomuseo_vicinoa user_id assume
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
