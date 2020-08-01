<?php


    // Include config, CCXT, normalizer libraries, exchange wrapper and command interpreter

    include('cfg/cfg.config.php');          // Main application config
    include('lib/lib.output.php');          // Output handler
    include('lib/lib.database.php');        // SQLite database backend
    include('lib/lib.config.php');          // Configuration tools
    include('lib/lib.cache.php');           // Cache handler
    include('lib/lib.classes.php');         // Class definitions
    include('lib/lib.common.php');          // Commonly used function library
    include('lib/lib.frostyapi.php');       // Communication to Frostybot central API (api.frostytrading.com)
    include('lib/lib.cron.php');            // Background task scheduler
    include('lib/lib.whitelist.php');       // Whitelist Manager for HTTP/HTTPS requests
    include('ccxt/ccxt.php');               // CCXT wrapper
    include('lib/lib.normalizer.php');      // Normalizer base class
    foreach(glob('lib/lib.normalizer.*.php') as $normalizer) {      // Exchange output normalizers
        include($normalizer);               
    }
    foreach(glob('lib/lib.ccxt.*.php') as $ccxt) {                  // CCXT exchange extensions.
        include($ccxt);               
    }
    include('lib/lib.exchange.php');        // Main exchange wrapper
    include('lib/lib.symbolmap.php');       // Symbol mapping manager
    include('lib/lib.notifications.php');   // Notifications manager
    include('lib/lib.unittests.php');       // Development test units
    include('lib/lib.command.php');         // Command interpreter

?>
