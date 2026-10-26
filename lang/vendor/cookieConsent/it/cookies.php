<?php

return [
    'title' => 'Utilizziamo i cookie',
    'intro' => 'Questo sito web utilizza i cookie per migliorare l\'esperienza complessiva dell\'utente.',
    'link' => 'Dai un\'occhiata alla nostra <a href=":url">Cookie Policy</a> per maggiori informazioni.',

    'essentials' => 'Solo essenziali',
    'all' => 'Accetta tutti',
    'customize' => 'Personalizza',
    'manage' => 'Gestisci Cookie',
    'details' => [
        'more' => 'Più dettagli',
        'less' => 'Meno dettagli',
    ],
    'save' => 'Salva impostazioni',
    'cookie' => 'Cookie',
    'purpose' => 'Scopo',
    'duration' => 'Durata',
    'year' => 'Anno|Anni',
    'day' => 'Giorno|Giorni',
    'hour' => 'Ora|Ore',
    'minute' => 'Minuto|Minuti',

    'categories' => [
        'essentials' => [
            'title' => 'Cookie essenziali',
            'description' => 'Ci sono alcuni cookie che dobbiamo includere affinché determinate pagine web funzionino. Per questo motivo, non richiedono il tuo consenso.',
        ],
        'analytics' => [
            'title' => 'Cookie di analisi',
            'description' => 'Li utilizziamo per ricerche interne su come migliorare il servizio che offriamo a tutti i nostri utenti. Questi cookie valutano il modo in cui interagisci con il nostro sito web.',
        ],
        'optional' => [
            'title' => 'Cookie opzionali',
            'description' => 'Questi cookie abilitano funzionalità che potrebbero migliorare la tua esperienza utente, ma la loro assenza non influirà sulla tua capacità di navigare nel nostro sito web.',
        ],
    ],

    'defaults' => [
        'consent' => 'Utilizzato per memorizzare le preferenze di consenso ai cookie dell\'utente.',
        'session' => 'Utilizzato per identificare la sessione di navigazione dell\'utente.',
        'csrf' => 'Utilizzato per proteggere sia l\'utente che il nostro sito web dagli attacchi di tipo cross-site request forgery.',
        'remember' => 'Ti mantiene connesso su questo dispositivo. Viene impostato solo se selezioni "Ricordami" al login.',
        'sentry_replay' => 'Registra una riproduzione con testi oscurati dei momenti che precedono un errore, così possiamo correggere problemi di cui altrimenti non sapremmo nulla. Resta nel browser finché non chiudi la scheda, non è un cookie.',
        'appearance' => 'Ricorda la tua scelta tra tema chiaro e scuro.',
        'sidebar_state' => 'Ricorda se la barra laterale è aperta o chiusa nel tuo account e nell\'area di amministrazione.',
        'locale' => 'Ricorda la lingua che hai scelto. Resta nella memoria del browser, non è un cookie.',
        '_ga' => 'Cookie principale utilizzato da Google Analytics, consente al servizio di distinguere un visitatore dall\'altro.',
        '_ga_ID' => 'Utilizzato da Google Analytics per mantenere lo stato della sessione.',
        '_gid' => 'Utilizzato da Google Analytics per identificare l\'utente.',
        '_gat' => 'Utilizzato da Google Analytics per limitare la velocità delle richieste.',
    ],
];
