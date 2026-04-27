<?php
    date_default_timezone_set('Europe/Athens');

    header('Content-Type: application/json; charset=utf-8');

    $lastUpdateFile = __DIR__ . '/last_toc_update.txt';
    $updateHours = [11, 23];

    $now = time();

    // FORCE UPDATE 

    $force = false;

    if (php_sapi_name() === 'cli') {
        foreach ($argv ?? [] as $a) {
            if (strpos($a, 'force') !== false) {
                $force = true;
            }
        }
    } else {
        if (!empty($_GET['force']) && in_array($_GET['force'], ['1','true','yes'])) {
            $force = true;
        }
    }

    // READ LAST UPDATE
    $lastUpdate = 0;

    if (file_exists($lastUpdateFile)) {
        $content = trim(file_get_contents($lastUpdateFile));

        if ($content !== '') {
            $ts = strtotime($content);
            if ($ts !== false) {
                $lastUpdate = $ts;
            }
        }
    }
    // CHECK IF UPDATE IS NEEDED
    $shouldUpdate = false;

    if ($force || $lastUpdate === 0) {
        $shouldUpdate = true;
    } else {

        $lastDay = strtotime(date('Y-m-d', $lastUpdate));
        $currentDay = strtotime(date('Y-m-d'));

        $daysDiff = (int)(($currentDay - $lastDay) / 86400);

        for ($d = 0; $d <= $daysDiff; $d++) {

            $date = date('Y-m-d', strtotime("+$d day", $lastDay));

            foreach ($updateHours as $h) {

                $slot = strtotime("$date $h:00:00");

                if ($slot > $lastUpdate && $slot <= $now) {
                    $shouldUpdate = true;
                    break 2;
                }
            }
        }
    }

    // EXECUTE UPDATE IF NEEDED

    $updateExecuted = false;
    $error = null;

    if ($shouldUpdate) {

        try {

            include 'cache_update.php';
            $updateExecuted = true;

            file_put_contents(
                $lastUpdateFile,
                date('Y-m-d H:i:s', $now)
            );

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    echo json_encode([
        "shouldUpdate"   => $shouldUpdate,
        "updateExecuted" => $updateExecuted,
        "lastUpdate"     => $lastUpdate ? date('Y-m-d H:i:s', $lastUpdate) : null,
        "currentTime"    => date('Y-m-d H:i:s', $now),
        "force"          => $force,
        "error"          => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>