<?php
return [
    'title' => 'Utilizamos cookies',
    'intro' => 'Este sitio utiliza cookies para mejorar su experiencia de usuario.',
    'link' => 'Consulte nuestra <a href=":url">política de cookies</a> para obtener más información.',

    'essentials' => 'Solo esenciales',
    'all' => 'Aceptar todas',
    'customize' => 'Personalizar',
    'manage' => 'Administrar cookies',
    'details' => [
        'more' => 'Más información',
        'less' => 'Menos información',
    ],
    'save' => 'Guardar configuración',
    'cookie' => 'Cookie',
    'purpose' => 'Finalidad',
    'duration' => 'Duración',
    'year' => 'Año|Años',
    'day' => 'Día|Días',
    'hour' => 'Hora|Horas',
    'minute' => 'Minuto|Minutos',

    'categories' => [
        'essentials' => [
            'title' => 'Cookies de funcionamiento',
            'description' => 'Necesitamos usar ciertas cookies para que ciertas páginas web funcionen. Por eso no requieren tu consentimiento.',
        ],
        'analytics' => [
            'title' => 'Cookies analíticas',
            'description' => 'Utilizamos estas cookies con fines de investigación interna sobre como podemos mejorar el servicio que brindamos a nuestros usuarios. Estas cookies se utilizan para evaluar como interactúa con nuestro sitio web como un usuario anónimo (los datos recopilados no lo identifican personalmente).',
        ],
        'optional' => [
            'title' => 'Cookies opcionales',
            'description' => 'Estas cookies habilitan funciones que pueden mejorar su experiencia de usuario, pero su ausencia no afecta su capacidad para navegar por nuestro sitio web',
        ],
    ],

    'defaults' => [
        'consent' => 'Se utiliza para almacenar las preferencias de consentimiento de cookies del usuario.',
        'session' => 'Se utiliza para identificar la sesión de navegación del usuario.',
        'csrf' => 'Se utiliza para asegurar tanto al usuario como a nuestro sitio web contra ataques de falsificación de solicitudes entre sitios.',
        'remember' => 'Mantiene tu sesión iniciada en este dispositivo. Solo se crea si marcas "Recordarme" al iniciar sesión.',
        'sentry_replay' => 'Graba una reproducción con los textos ocultos de los momentos alrededor de un error, para poder corregir fallos que de otro modo no conoceríamos. Se queda en tu navegador hasta que cierres la pestaña, no es una cookie.',
        'appearance' => 'Recuerda tu elección de tema claro u oscuro.',
        'sidebar_state' => 'Recuerda si la barra lateral está abierta o cerrada en tu cuenta y en el área de administración.',
        'locale' => 'Recuerda el idioma que elegiste. Se guarda en el almacenamiento del navegador, no es una cookie.',
        '_ga' => 'Cookie principal utilizada por Google Analytics, permite distinguir a un visitante de otro.',
        '_ga_ID' => 'Utilizada por Google Analytics para persistir el estado de la sesión.',
        '_gid' => 'Utilizada por Google Analytics para identificar al usuario.',
        '_gat' => 'Utilizada por Google Analytics para limitar la tasa de solicitudes.',
    ]
];
