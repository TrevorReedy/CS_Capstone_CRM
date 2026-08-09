<?php
// General application settings.
//
// The HTML comment that used to sit above the opening <?php tag was emitted as
// output the moment anything included this file, which would have broken every
// header() and session_start() call in app/Core/bootstrap.php. Nothing includes
// it today, which is the only reason it never surfaced.
//
// Note that 'env' and 'debug' here are NOT what the application reads: bootstrap
// .php takes APP_DEBUG from the environment, and .env is the place to set it.
// These values are kept because config/ is where a reader looks for them.
return [
    'name'  => getenv('APP_NAME')  ?: 'Typhon Cath CRM',
    'env'   => getenv('APP_ENV')   ?: 'local',
    'debug' => getenv('APP_DEBUG') === 'true',
];
