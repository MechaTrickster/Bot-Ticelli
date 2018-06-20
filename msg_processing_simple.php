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

$museo_vicino = 0;   //contiene il museo piu' vicino alla propria posizione
$i;                  //utilizzata per scorrere i musei
$token;              //token dell'utente

//validazione

//estrae l'utente in questione dalla tabella utenti
$utente = db_table_query("SELECT * FROM utenti WHERE Id = $from_id");

//controlla se l'utente in questione è già noto
if ($utente == NULL )
{
    //utente non conosciuto

    //niente token quindi viene generato    
    $token = hexdec( uniqid() );

    telegram_send_message($chat_id, 'Salute visitatore, il Bot-Ticelli ti augura un caldo benvenuto! ', null);
    
    telegram_send_message($chat_id, 'Il tuo codice autorizzativo è: '.$token, null);

    telegram_send_message($chat_id, 'Il Bot-Ticelli ricorda che occorre inviare il proprio codice autorizzativo prima di poter effettuare modifiche', null);

    //salva il token nel db
    db_perform_action("INSERT INTO utenti (Id, Token, Autenticato)
                       VALUES($from_id, $token, 0)");
}
else
{
    //utente conosciuto, presente nel db

    //inserisce la data odierna
    db_perform_action("UPDATE utenti SET Data = CURRENT_DATE WHERE Id = $from_id");
    
    $token = $utente[0][1];

    //db_perform_action("UPDATE utenti SET Autenticato = 1 WHERE Id = $from_id ");
    
    if ($utente[0][2] < 1)
    {
        //se l'user non è autenticato
    }

    else
    {
        //user già autenticato può fare modifiche
        
        //autenticazione valida solo per il giorno corrente
        if ($utente[0][3] != date('Y-m-d'))
        {
            db_perform_action("UPDATE utenti SET Autenticato = 0 WHERE Id = $from_id"); 
        }
    }
}

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

            //se cerca il museo successivo
            if (strpos($text, "il prossimo museo") === 6)
            {
                $i = db_scalar_query("SELECT count FROM current_pos WHERE Id = $from_id");                
                $i++;
                db_perform_action("UPDATE current_pos SET count = $i WHERE Id = $from_id");                          
            }
            else
            {
                $i = 0;
                db_perform_action("UPDATE current_pos SET count = 0 WHERE Id = $from_id");
            }

                

            telegram_send_location($chat_id, $nearby[$i][13], $nearby[$i][14]);
            telegram_send_message($chat_id, 'Questo è il luogo a te più vicino', null);
            if ($nearby[$i][11] != NULL)
                telegram_send_message($chat_id, "Museo di ".$nearby[$i][11], null);
            else if ($nearby[$i][20] > 0)
                telegram_send_message($chat_id, "Museo di "."arte", null);
            else if ($nearby[$i][21] > 0)
                telegram_send_message($chat_id, "Museo di "."storia", null);
            else if ($nearby[$i][22] > 0)
                telegram_send_message($chat_id, "Museo di "."altro tipo", null);
            else
                telegram_send_message($chat_id,'Il tipo di museo non è stato specificato', null);
        }

        //posizione non trovata
        else 
            telegram_send_message($chat_id, 'Devi mandare prima le tue coordinate', null);
    }

    else if ((strpos($text, "Salva") === 0) && ($utente[0][2] < 1) )
        telegram_send_message($chat_id, 'Mi dispiace ma ho prima bisogno del tuo codice!', null);

    //salva una nuova posizione
    else if (strpos($text, "Salva") === 0) {

        //estrae l'id dalla tabella 'current_position'
        $current = db_table_query("SELECT * FROM current_pos WHERE Id = $from_id");

        //se l'id utente trova corrispondenza nella tabella 'current_position'
        //allora l'utente ha inviato la sua posizione
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
    else if ((strpos($text, "d'arte") === 15) && ($utente[0][2] < 1) )
        telegram_send_message($chat_id, 'Mi dispiace ma ho prima bisogno del tuo codice!', null);

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
                        telegram_send_message($chat_id, 'Devi essere nel raggio di un museo per poter effettuare modifiche', null);
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
    else if ((strpos($text, "storico") === 15) && ($utente[0][2] < 1) )
        telegram_send_message($chat_id, 'Mi dispiace ma ho prima bisogno del tuo codice!', null);
    
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
                        telegram_send_message($chat_id, 'Devi essere nel raggio di un museo per poter effettuare modifiche', null);
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
    else if ((strpos($text, "altro") === 9) && ($utente[0][2] < 1) )
        telegram_send_message($chat_id, 'Mi dispiace ma ho prima bisogno del tuo codice!', null);

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

    else if ((strpos($text, "Reinvio del token") === 0))
    {
            telegram_send_message($chat_id, 'Hai bisogno del tuo codice autorizzativo?', null);
            telegram_send_message($chat_id, 'Ci pensa il Bot-Ticelli', null);
            telegram_send_message($chat_id, 'Ed eccolo lì: '.$token, null);
    }

    else if (is_numeric($message['text']))
    {
        telegram_send_message($chat_id, 'Controllo subito questo token...', null);

        if(strcmp($message['text'], $token) == 0)
        {
            if ($utente[0][2] != 1)
            {
                telegram_send_message($chat_id, 'Autenticazione avvenuta con successo', null);  
                db_perform_action("UPDATE utenti SET Autenticato = 1 WHERE Id = $from_id ");
            }
            else
                telegram_send_message($chat_id, 'Ma... quante volte vuoi autenticarti!?', null);
        }
        else
            telegram_send_message($chat_id, 'Attenzione autenticazione fallita, il codice inserito non corrisponde', null);   
    }
    else
        telegram_send_message($chat_id, 'Non conosco questo comando', null);

}
else {
    telegram_send_message($chat_id, 'Scusa ma il Bot-Ticelli non riesce a comprendere');
}
?>
