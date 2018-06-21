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
if (isset($message['location'])) {
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
```
    

### /Salva:
Questo è il comando per chiedere al bot di salvare nel Data Base la posizione inviata dall'utente al fine di registrare un nuovo museo non presente. Nel caso in cui l'edificio sia già stato inserito, il bot risponderà che è già presente, questo controllo validativo viene effettuato al fine di evitare di immettere più di una volta lo stesso edificio. Per farlo si controlla che la posizione effettiva dell'utente rientri o meno in un area di dimensione decisa staticamente (200m circa) . Il nuovo museo inserito verrà trattato come ogni altro, quindi potrebbe venire segnalato ad un altro utente vicino, in seguito al comando **/Cerca**.

```
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
```

### /Aggiungi museo d'arte/Aggingi museo storico/Aggiungi altro museo:

All'interno della base di dati ogni museo è dotato di 3 campi riportanti un intero, 0 o 1, il cui scopo è di segnalarne la tipologia, cioè se si tratta di una mostra artistica, storica o di altra materia. Ogni nuovo museo avrà tutti i campi sullo 0 e lo scopo di questo comando è di impostare ad 1 il campo che l'utente ha scelto per descrivere la tipologia della galleria in esame. Ogni museo può avere un solo campo su 1, quindi nelle circostanze in cui un utente usi il comando su un edificio già provvisto del dettaglio, il bot risponde che questo è già presente. In circostanze normali, in cui la tipologia non sia definita, il bot verificherà nuovamente se la posizione corrente dell'utente rientri nel raggio di una mostra, così da poter verificare che l'utente sia effettivamente nelle sue vicinanze. Al termine dell'operazione il bot confermerà l'avvenuta operazione ed il nuovo valore inserito mostrerà la tipologia del museo a qualunque utente esegua il comando **/Cerca**.

```
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
```

## Validazione



# Conclusione
I vantaggi derivanti dall'utilizzo del "Bot-Ticelli" sono molteplici, dall'esaltazione del nostro patrimonio artistico, storico e culturale, all'utilizzo di un dataset che non solo evita di essere compromesso ma che può essere bensì arricchito semplicemente grazie all'utilizzo dell'applicazione da parte di ogni singolo user. Di più, grazie alla funzionalità che mette in mostra il luogo di interesse più vicino, escludendo quelli già visitati, viene stabilito una specie di itinerario che guida ed assiste il visitatore e al tempo stesso crea un percorso di viaggio ottimale, riducendo così gli spostamenti inutili, con tutti i vantaggi che ne conseguono (riduzione del traffico, delle emissioni, ecc...). Possiamo quindi constatare che il "Bot-Ticelli", oltre ad essere dotato di un certo charme, risulta anche essere un più che valido ed affidabile assistente di viaggio.






# Telegram Bot Sample

Simple Telegram bot backend template, written in PHP.
Works both in *pull* and in *push* mode.

You can use this code as a starting point for your own bot and add your own intelligence through custom PHP code, external services (perhaps an Alice AIML interpreter?), or anything else.

Have fun!

## Installation

You need:

* **PHP:** in order to run samples and make your bot work in *pull* mode.
* **A web server and a domain:** in order to serve requests by Telegram in *push* mode (Apache, Nginx, or anything similar, really).

First of all, create a new Telegram Bot, by chatting with the [BotFather](http://telegram.me/BotFather). Your bot will need a unique **nickname** and you will obtain a unique **token** in return.
This token is all you need to communicate with the bot through the Telegram API.

Edit the `config.php` file and set the `TELEGRAM_BOT_TOKEN` constant with your token.

## Interacting with your bot

### Receiving messages (pull)

Once the bot's token has been set, you can very easily fetch new messages (one by one) using the `pull.php` script.

```
php pull.php
```

This will retrieve new messages (if any) and print out the JSON data to standard output by default.

Notice that Telegram keeps a queue of received and delivered messages on its servers.
If no particular message is queried, Telegram may return the same message over and over.
In order to advance through the queue of messages to deliver, the `pull.php` script keeps track of the last update received—by storing the update's ID—and by performing a query for the *next* update in queue. (See line 20.)
The last update ID is stored in the `pull-last-update.txt` file.

Also notice that Telegram's `getUpdates` query can perform **[long polling](https://core.telegram.org/bots/api#getupdates)**, which stalls your request until an update is ready to be delivered (or until the request times out).
By default the `pull.php` will wait for as long as *60 seconds* for an update (see line 20).

In order to turn off this feature and switch to immediate pulling, set the third parameter at line 20 as follows:

```php
$content = telegram_get_updates(intval($last_update) + 1, 1, 0);
```

In this case the request will return right away and your script will terminate.
Try launching `php pull.php` with different settings, either sending a message to your bot *before* or *after* launching the script.

If the `pull.php` script is configured to use *long-polling*, it can also be launched in continuous polling, using the `continuous-poll.sh` shell script.
The script does nothing else except running the pull script over and over, thus effectively keeping your Telegram bot alive and working without interruptions, even if for some reason you cannot run your bot in *push* mode (see below).

### Sending messages

Once you receive a message from a user (notice that Telegram bot conversations always start with the user sending a `/start` command to the bot), you also receive a **chat identifier** (use the `chat_id` attribute of your received message object).
This identifier can be used to send messages back to the user.

In order to do so, use the following function:

```php
$response = telegram_send_message(CHAT_ID, "Hello user!", null);
```

Check out the script `send.php` for a complete example (you'll have to fill in an existing chat ID at line 14), that allows to send messages through a command line parameter:

```
php send.php "This is the text to send!"
```

Also, take a look to the `lib.php` file, which includes many other library functions you can use to send messages, photos, and locations through the Telegram API.

### Receiving messages (push)

Telegram bot conversations can also work in *push* mode: instead of making your code constantly fetch updates from Telegram server, it is the service itself that calls your code and activates your bot.
This simplifies your code and also allows you to eschew constant connections to the Telegram API.

However, you'll need the following additional things:

* A domain name: that is, your web server must answer to a public domain name (i.e., my-web-server.com). You can buy a domain name and an associated hosting plan very cheaply nowadays.
* A certificate, associated to the domain name above. This can be costly, but you can also look into projects like [Let's encrypt](https://letsencrypt.org).

If you satisfy both criteria, you can setup push delivery by running the `register-bot.sh` script:

```
chmod +x register-bot.sh
./register-bot.sh -t BOT-TOKEN -c /path/to/public/certificate.pem -s https://my-web-server.com/hook.php
```

Replace the `-t` parameter with your bot's actual token and the `-c` parameter with the path to your certificate (either a PEM or a CRT file).
The `-s` parameter should be an HTTPS URI pointing to the `hook.php` file on your web server.

Once the "web hook" has been registered, Telegram will automatically call your `hook.php` file whenever a message is received.

(You can turn off any registered web hook by running the `unregister-bot.sh` script and passing in the bot's token.)

## Message processing

Both the `pull.php` and the `hook.php` scripts receive messages from Telegram and process them by including the `msg_processing_simple.php` script.
The script assumes that a new message was received (stored as the `$message` variable) and includes some boilerplate code for you to fill out:

```php
// Assumes incoming $message object
$message_id = $message['message_id'];
$chat_id = $message['chat']['id'];
$message_text = $message['text'];

// TODO: put message processing logic here
```

Notice that the `$message_text` variable can be `null` if the message does not contain any text (for instance, if the user sent a location or an image instead of a text message).

You can easily create an "echo bot" (that simply responds by sending back the original text to the user) by adding this call to the library function:

```php
telegram_send_message($chat_id, $message_text);
```

Also, you can easily detect Telegram commands (which, by convention, start with a slash character, like `/start`) using `strpos`:

```php
if (strpos($text, "/start") === 0) {
    // Received a /start command
}
```

Take a look to the `msg_processing_simple.php` script for a general idea of how message processing looks like.
By adding logic you can start adding some kind of *intelligence* to your bot.

### Connecting with an AIML bot

An easy way to add some kind of natural language processing intelligence to your bot, is to make use of an AIML interpreter, like—for instance—[Program-O](http://www.program-o.com).
This open-source AIML interpreter also exposes a [public API](http://www.program-o.com/chatbotapi) that you can very easily hook up to your Telegram bot:

```php
// Send text by user to AIML bot
$handle = prepare_curl_api_request('http://api.program-o.com/v2/chatbot/', 'POST',
    array(
        'say' => $message_text,
        'bot_id' => 6,
        'format' => 'json',
        'convo_id' => 'uwiclab-bot-sample'
    ),
    null,
    array(
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    )
);
$response = perform_curl_request($handle);

// Response has the following JSON format:
// {
//     "convo_id" : "5uf2nupqmb",
//     "usersay": "MESSAGE FROM USER",
//     "botsay" : "Response by bot"
// }
$json_response = json_decode($response, true);
$bot_response = $json_response['botsay'];

// Send AIML bot response back to user
$response = telegram_send_message($chat_id, $bot_response, null);
```

In order to customize your bot's intelligence, you'll have to download Program-O, install it locally to your server (this software requires PHP and MySQL), and then hook it up to your Telegram bot web hook.
By providing one or more AIML files to the Program-O interpeter, you'll be able to have an *almost* natural conversation with your bot in no time.

Check out `sample-apis/program-o.php` for a stand-alone sample.

### Conversations and Program-O

When linking a Telegram bot and a Program-O bot to provide a, seemingly, intelligent conversation with your users, it is important for the Program-O bot to correctly identify the user it is talking to.

As you may have noticed, the Program-O API provides a `convo_id` parameter.
This parameter identifies the conversation to the Program-O bot and allows it to distinguish between different users and different chats.

In the example above we simply used *one* conversation ID for every incoming message (namely, “uwiclab-bot-sample”).
This, however, means that every user talking with our Program-O bot shares the same conversation and also the same memory.
Any information stored about the user thus applies to *every* user of your bot.

This can be easily fixed: just make sure to provide a different `convo_id` parameter for every Telegram conversation.
For instance, by altering the code above as follows:

```php
$handle = prepare_curl_api_request('http://api.program-o.com/v2/chatbot/', 'POST',
    array(
        'say' => $message_text,
        'bot_id' => 6,
        'format' => 'json',
        'convo_id' => "telegram-$chat_id"
    ),
    null,
    array(
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    )
);
```

In this way, a message on Telegram chat #123123123 will be forwarded to Program-O using the `telegram-123123123` conversation ID.
This keeps every Telegram chat well separated from the others.

## The bot's memory: using a database

As long as you let Program-O drive your Telegram bot, there actually is no need to take care of the bot's memory: the Program-O interpreter does all the work for you.

However, if you should choose to write your own bot logic in PHP, you'll need a persistent storage to keep information about your users and about the conversations they are having.
That is, you'll need a **database**.

Make sure you edit the `config.php` and provide the correct database connection credentials.

```php
define('DATABASE_HOST', 'localhost');
define('DATABASE_NAME', '');
define('DATABASE_USERNAME', '');
define('DATABASE_PASSWORD', '');
```

The constants above are used by the `lib_database.php` script, that provides several useful functions for using a database from your code.

Once correctly setup, launch the database setup script.
(This needs to be done only *once*.)

```
php setup.php
```

Your database will be updated with a `conversation` table, which allows your bot to keep track of the state of its conversations.
In particular, state is stored as a simple integer, matching the ID of the user talking to the bot.

Switch to the `msg_processing_conversation.php` script in your `pull.php` (or `hook.php`) file and check out how the conversational message processing works.

## Help!

Any questions?
Send us an e-mail or open an issue here on Github and we'll be glad to help.
