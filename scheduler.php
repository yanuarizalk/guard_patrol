<?php
    require __DIR__ . '/config.php';

    $q = $db['conn'] -> prepare('SELECT value FROM settings WHERE name = "Q_CHECKIN_METHOD"');
    if (!$q -> execute()) {
        die('Query error: '.$db['conn'] -> errorInfo());
    }
    if ($q -> rowCount() < 1) die('Nothing to do here');
    $res = $q -> fetch(PDO::FETCH_ASSOC);

    $q = $db['conn'] -> prepare('UPDATE settings SET value = :val WHERE name = "CHECKIN_METHOD"');
    if (!$q -> execute([':val' => $res['value']])) {
        die('Query error: '.$db['conn'] -> errorInfo());
    }

    $q = $db['conn'] -> prepare('DELETE FROM settings WHERE name = "Q_CHECKIN_METHOD"');
    if (!$q -> execute([':val' => $res['value']])) {
        die('Query error: '.$db['conn'] -> errorInfo());
    }
    echo 'Settings have been updated!';
?>