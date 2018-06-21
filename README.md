# Corso di Piattaforme Digitali per la Gestione del Territorio

## Sessione Estiva A.A. 2017/2018

# Progetto: "Bot-Ticelli"

## Studenti:
#### *Alessandro Commodaro [MAT. 274065]      GitHub: MechaTrickster*
#### *Daniele Commodaro [MAT. 267250]         GitHub: pankake*

# Introduzione
Il "Bot-Ticelli" è un'applicazione sviluppata per piattaforma Telegram, il cui scopo è quello di assistere un utente viaggiatore che si pone come obbiettivo quello di riscoprire i principali edifici artstici, storici e culturali distribuiti su tutto il territorio nazionale. Le sue funzionalità prevedono la ricerca dell'esposizione più vicina, viene permessa l'aggiunta di nuovi luoghi di interesse nel caso questi non fossero già presenti, aggiungerne alcuni dettagli, ed infine una sorta di itinerario che per tutta la durata del viaggio assiste il viaggiatore mostrandogli il percorso più indicato da seguire. In questo modo è possibile sfruttare una base di dati preesistente inserita attraverso un dataset in formato .csv, la quale grazie al contributo dell'utente viene arricchita a patto che egli abbia eseguito il processo autenticativo previsto.

# Struttura
Tramite Telegram, l'utente può interagire con il "Bot-Ticelli" inviando la propria posizione, la quale viene salvata nel Data Base così da poter essere utilizzata per trovare il museo più vicino ad essa o per salvarne uno nuovo. 

```//se viene inviata la posizione
if (isset($message['location']))
{
    $lat = $message['location']['latitude'];
    $lng = $message['location']['longitude'];

    //inserisce i dati nella tabella 'current_position'
    db_perform_action("REPLACE INTO current_pos VALUES($chat_id, $lat,
    $lng)");

    echo "Utente $from_id in $lat,$lng" . PHP_EOL;
}
```

L'invio delle proprie coordinate viene eseguito sfruttando la funzionalità di invio della posizione già presente in telegram, tutte le altre funzionalità vengono rese disponibili attraverso l'utilizzo di pulsanti generati attraversi il client "Postman". Accorpare i vari comandi in pulsanti permette una comprensione immediata di quello che il "Bot-Ticelli" è realmente in grado di fare, oltre a questo chiaramente si evita di dover immettere manualmente l'intera stringa che compone il comando. 

## Funzionalità

### /Cerca:

Con questa opzione si chiede al bot di visualizzare il museo più vicino, il quale risponde mostrando una piccola mappa che riporta il luogo d'interesse e la sua tipologia, o, nel caso non fosse stata inviata, la richiesta della posizione all'utente. La galleria viene scelta dal Data Base grazie ad una query sql che prevede l'utilizzo del "Teorema di Pitagora" per calcolare le distanze tra la posizione dell'utente e quella dei musei. In base a queste, le mostre vengono poi ordinate dalla più vicina e viene selezionata quella interessata, cioè la prima. 

### /Cerca il prossimo museo:

Questa opzione serve per chiedere al bot di visualizzare il museo successivo a quello già visitato, mostrato col comando **/Cerca**, uno per volta, in ordine di distanza, basandosi sulla prima posizione registrata. Lo scorrimento delle gallerie avviene tramite una variabile contatore adibita all'indicizzazione dei record, contenuta all'interno del Data Base e legata all'utente. Nel caso in cui venga usato questo comando, la variabile viene incrementata scorrendo di un posto la lista ordinata di musei provenienti dalla query introdotta nel passaggio precedente. Ad ogni nuovo luogo visualizzato, il contatore del Data Base viene aggiornato, finchè l'utente non utilizzerà il comando base */Cerca*, da cui segue l'azzeramento della variabile, e quindi del contatore. La posizione dell'utente all'interno della base di dati rimane sempre quella di partenza, ma in questo modo è possibile visualizzare tutte le mostre nelle vicinanze.

```
//cerca la posizione più vicina
    if (strpos($text, "Cerca") === 0) 
    {
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
```
    

### /Salva:
Questo è il comando per chiedere al bot di salvare nel Data Base la posizione inviata dall'utente al fine di registrare un nuovo museo non presente. Nel caso in cui l'edificio sia già stato inserito, il bot risponderà che è già presente, questo controllo validativo viene effettuato al fine di evitare di immettere più di una volta lo stesso edificio. Per farlo si controlla che la posizione effettiva dell'utente rientri o meno in un area di dimensione decisa staticamente (200m circa) . Il nuovo museo inserito verrà trattato come ogni altro, quindi potrebbe venire segnalato ad un altro utente vicino, in seguito al comando **/Cerca**.

```
//salva una nuova posizione
    else if (strpos($text, "Salva") === 0) 
    {
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
```

### /Aggiungi museo d'arte/Aggingi museo storico/Aggiungi altro museo:

All'interno della base di dati ogni museo è dotato di 3 campi riportanti un intero, 0 o 1, il cui scopo è di segnalarne la tipologia, cioè se si tratta di una mostra artistica, storica o di altra materia. Ogni nuovo museo avrà tutti i campi sullo 0 e lo scopo di questo comando è di impostare ad 1 il campo che l'utente ha scelto per descrivere la tipologia della galleria in esame. Ogni museo può avere un solo campo su 1, quindi nelle circostanze in cui un utente usi il comando su un edificio già provvisto del dettaglio, il bot risponde che questo è già presente. In circostanze normali, in cui la tipologia non sia definita, il bot verificherà nuovamente se la posizione corrente dell'utente rientri nel raggio di una mostra, così da poter verificare che l'utente sia effettivamente nelle sue vicinanze. Al termine dell'operazione il bot confermerà l'avvenuta operazione ed il nuovo valore inserito mostrerà la tipologia del museo a qualunque utente esegua il comando **/Cerca**.

```
else if (strpos($text, "d'arte") === 15)
{
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
    
    else if (strpos($text, "storico") === 15)
    {
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

    else if (strpos($text, "altro") === 9)
    {
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
                    ($lng >= $nearby[0][14]+0.001 && $lng <= $nearby[0][14]-0.001)) 
                    {
                        //se non è nel raggio
                        telegram_send_message($chat_id, 'Devi essere nel raggio di un opera per poter effettuare modifiche', null);
                }
                //se è nel raggio
                else 
                {
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
```

## Validazione
Per il processo validativo si è optato per un'implementazione interamente in linguaggio php. Si precisa che per validazione si intende l'atto di autenticarsi al "Bot-Ticelli", al fine di poter modificare la base di dati già esistente. Tale processo non coinvolgerà le funzioni di ricerca, bensì deve essere limitato a quelle operazioni che implicano delle modifiche. Per gestire la funzionalità viene istanziata un'apposita tebella per memorizzare gli utenti conosciuti dal bot; lo user viene riconosciuto sulla base del suo ID Telegram, quando il "Bot-Ticelli" incontra un nuovo utente procederà con la creazione e l'invio di un token, il quale viene memorizzato nella tabella ed associato al relativo ID Telegram. Nel caso di un utente conosciuto, il "Bot-Ticelli" attenderà semplicemente la ricezione del token, prima di abilitarlo ad apportare modifiche.

```
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
    $token = $utente[0][1]
}
```
Nella tabella deve essere presente un attributo *Autenticazione*, contenente uno 0 o un 1, per rilevare che lo user sia stato convalidato o meno, ed un campo *Data* il quale permette di rilasciare (annullare) l'autenticazione al termine di ogni giornata. Vengono quindi confrontate la data presente nel Data Base con quella odierna e, se queste non dovessero coincidere, la richiesta andrà ripetuta.
La possibilità di apportare modifiche viene quindi limitata alla giornata in cui viene richiesta. 

```
//autenticazione valida solo per il giorno corrente
if ($utente[0][3] != date('Y-m-d'))
{
db_perform_action("UPDATE utenti SET Autenticato = 0 WHERE Id = $from_id"); 
}
```

Segue la verifica del campo *Autenticazione* ogni qualvolta si desideri modificare la base di dati.

```
...

else if ((strpos($text, "Salva") === 0) && ($utente[0][2] < 1) )
{

...

}

...

else if ((strpos($text, "d'arte") === 15) && ($utente[0][2] < 1) )
{

...

}

...

else if ((strpos($text, "storico") === 15) && ($utente[0][2] < 1) )
{

...

}

...

else if ((strpos($text, "altro") === 9) && ($utente[0][2] < 1) )
{

...

}
```

Per concludere, si è tenuto conto che per l'utente medio risulta essere molto facile smarrire una password (anche se reperibile scorrendo la chat a ritroso), per questo motivo non si è potuto fare a meno di aggiungere una funzionalità per il reinvio del token.

```
else if ((strpos($text, "Reinvio del token") === 0))
{
        telegram_send_message($chat_id, 'Hai bisogno del tuo codice autorizzativo?', null);
        telegram_send_message($chat_id, 'Ci pensa il Bot-Ticelli', null);
        telegram_send_message($chat_id, 'Ed eccolo lì: '.$token, null);
}
```

Basterà restituire in chat il token al "Bot-Ticelli" per essere autenticati.

# Conclusione
I vantaggi derivanti dall'utilizzo del "Bot-Ticelli" sono molteplici, dall'esaltazione del nostro patrimonio artistico, storico e culturale, all'utilizzo di un dataset che non solo evita di essere compromesso ma che può essere bensì arricchito semplicemente grazie all'utilizzo dell'applicazione da parte di ogni singolo user. Di più, grazie alla funzionalità che mette in mostra il luogo di interesse più vicino, escludendo quelli già visitati, viene stabilito una specie di itinerario che guida ed assiste il visitatore e al tempo stesso crea un percorso di viaggio ottimale, riducendo così gli spostamenti inutili, con tutti i vantaggi che ne conseguono (riduzione del traffico, delle emissioni, ecc...). Possiamo quindi constatare che il "Bot-Ticelli", oltre ad essere dotato di un certo charme, risulta anche essere un più che valido ed affidabile assistente di viaggio.
