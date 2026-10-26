<?php
return [
    'title' => 'Nous utilisons des cookies',
    'intro' => 'Ce site utilise des cookies pour améliorer votre expérience utilisateur.',
    'link' => 'Consultez notre <a href=":url">politique d\'utilisation des cookies</a> pour en savoir plus.',

    'essentials' => 'Cookies essentiels',
    'all' => 'Accepter tout',
    'customize' => 'Personnaliser',
    'manage' => 'Gérer les cookies',
    'details' => [
        'more' => 'Plus d\'informations',
        'less' => 'Moins d\'informations',
    ],
    'save' => 'Enregistrer',
    'cookie' => 'Cookie',
    'purpose' => 'But',
    'duration' => 'Durée',
    'year' => 'Année|Années',
    'day' => 'Jour|Jours',
    'hour' => 'Heure|Heures',
    'minute' => 'Minute|Minutes',

    'categories' => [
        'essentials' => [
            'title' => 'Cookies de fonctionnement',
            'description' => 'Nous devons utiliser certains cookies pour pouvoir faire fonctionner certaines pages web. C\'est la raison pour laquelle ils ne nécessitent pas votre consentement.',
        ],
        'analytics' => [
            'title' => 'Cookies analytiques',
            'description' => 'Nous utilisons ces cookies uniquement à des fins de recherche interne sur la manière dont nous pouvons améliorer le service que nous offrons à tous nos utilisateurs. Ces cookies permettent d\'évaluer la manière dont vous interagissez avec notre site web.',
        ],
        'optional' => [
            'title' => 'Cookies optionnels',
            'description' => 'Ces cookies activent des fonctionnalités susceptibles d\'améliorer votre expérience utilisateur, mais leur absence n\'a pas d\'incidence sur votre capacité à naviguer sur notre site Web.',
        ],
    ],

    'defaults' => [
        'consent' => 'Stockage des préférences de consentement aux cookies de l\'utilisateur.',
        'session' => 'Identification de la session de navigation de l\'utilisateur.',
        'csrf' => 'Protection de l\'utilisateur et notre site contre les attaques d\'usurpation d\'identité lors des requêtes.',
        'remember' => 'Vous garde connecté sur cet appareil. Défini uniquement si vous cochez « Se souvenir de moi » à la connexion.',
        'sentry_replay' => 'Enregistre une relecture aux textes masqués des instants autour d\'une erreur, afin de corriger des bugs dont nous n\'entendrions jamais parler. Reste dans votre navigateur jusqu\'à la fermeture de l\'onglet, ce n\'est pas un cookie.',
        'appearance' => 'Mémorise votre choix de thème clair ou sombre.',
        'sidebar_state' => 'Mémorise si la barre latérale est ouverte ou fermée dans votre compte et dans l\'espace d\'administration.',
        'locale' => 'Mémorise la langue choisie. Conservé dans le stockage du navigateur, ce n\'est pas un cookie.',
        '_ga' => 'Cookie principal utilisé par Google Analytics, permettant de distinguer un visiteur d\'un autre.',
        '_ga_ID' => 'Utilisé par Google Analytics pour conserver l\'état de la session.',
        '_gid' => 'Utilisé par Google Analytics pour identifier un visiteur.',
        '_gat' => 'Utilisé par Google Analytics pour limiter le taux de demande.',
    ],
];
