<?php
    define("DB_HOST", "localhost");
    define("DB_NAME", "tyfounte_sgc");
    define("DB_USER", "tyfounte_user");
    define("DB_PASS", "Abcd*1234");
    define("DB_PORT", 3306);

    $db['conn'] = new PDO("mysql:dbname=".DB_NAME.";host=".DB_HOST.";port=".DB_PORT.";", DB_USER, DB_PASS);

?>