<?php
// Do not allow remote execution
$arr = array_diff_assoc($_SERVER, $_ENV);
if (isset($arr["DOCUMENT_ROOT"]) && ($arr["DOCUMENT_ROOT"] != "") ) {
    header("HTTP/1.1 400");
    header("Content-type: text/plain; charset=UTF-8");
    echo "400 Not executable\r\n";
    exit(1);
}

if (!camp_db_table_exists($CC_CONFIG['prefTable'])) {
    echo " * Creating database table ".$CC_CONFIG['prefTable']."...";
    $CC_DBC->createSequence($CC_CONFIG['prefTable']."_id_seq");
    $sql = "CREATE TABLE ".$CC_CONFIG['prefTable']." (
        id int not null,
        subjid int REFERENCES ".$CC_CONFIG['subjTable']." ON DELETE CASCADE,
        keystr varchar(255),
        valstr text
    )";
    camp_install_query($sql, false);

    $sql = "CREATE UNIQUE INDEX ".$CC_CONFIG['prefTable']."_id_idx
        ON ".$CC_CONFIG['prefTable']." (id)";
    camp_install_query($sql, false);

    $sql = "CREATE UNIQUE INDEX ".$CC_CONFIG['prefTable']."_subj_key_idx
        ON ".$CC_CONFIG['prefTable']." (subjid, keystr)";
    camp_install_query($sql, false);

    $sql = "CREATE INDEX ".$CC_CONFIG['prefTable']."_subjid_idx
        ON ".$CC_CONFIG['prefTable']." (subjid)";
    camp_install_query($sql);

    echo " * Inserting starting data into table ".$CC_CONFIG['prefTable']."...";
    $stPrefGr = Subjects::GetSubjId($CC_CONFIG['StationPrefsGr']);
    Prefs::Insert($stPrefGr, 'stationName', "Radio Station 1");
    $genres = file_get_contents( dirname(__FILE__).'/../genres.xml');
    Prefs::Insert($stPrefGr, 'genres', $genres);
    echo "done.\n";
} else {
    echo " * Skipping: database table already exists: ".$CC_CONFIG['prefTable']."\n";
}

//------------------------------------------------------------------------
// Install storage directories
//------------------------------------------------------------------------
foreach (array('storageDir', 'bufferDir', 'transDir', 'accessDir', 'pearPath', 'cronDir') as $d) {
    $test = file_exists($CC_CONFIG[$d]);
    if ( $test === FALSE ) {
        @mkdir($CC_CONFIG[$d], 02775);
        if (file_exists($CC_CONFIG[$d])) {
            $rp = realpath($CC_CONFIG[$d]);
            echo " * Directory $rp created\n";
        } else {
            echo " * Failed creating {$CC_CONFIG[$d]}\n";
            exit(1);
        }
    } elseif (is_writable($CC_CONFIG[$d])) {
        $rp = realpath($CC_CONFIG[$d]);
        echo " * Skipping directory already exists: $rp\n";
    } else {
        $rp = realpath($CC_CONFIG[$d]);
        echo " * ERROR. Directory already exists, but is not writable: $rp\n";
        exit(1);
    }
    $CC_CONFIG[$d] = $rp;
}

//------------------------------------------------------------------------
// Storage directory writability test
//------------------------------------------------------------------------

echo " * Testing writability of ".$CC_CONFIG['storageDir']."...";
if (!($fp = @fopen($CC_CONFIG['storageDir']."/_writeTest", 'w'))) {
    echo "\nPlease make directory {$CC_CONFIG['storageDir']} writeable by your webserver".
        "\nand run install again\n\n";
    exit(1);
} else {
    fclose($fp);
    unlink($CC_CONFIG['storageDir']."/_writeTest");
}
echo "done.\n";

//------------------------------------------------------------------------
// Install Cron job
//------------------------------------------------------------------------
require_once(dirname(__FILE__).'/../cron/Cron.php');

$cron = new Cron();
$access = $cron->openCrontab('write');
if ($access != 'write') {
    do {
       $r = $cron->forceWriteable();
    } while ($r);
}
foreach ($cron->ct->getByType(CRON_CMD) as $line) {
    if (preg_match('/transportCron\.php/', $line['command'])) {
        $cron->closeCrontab();
        echo " * Storage cron job already exists.\n";
        exit;
    }
}
echo " * Adding transport cron job...";
$cron->ct->addCron('*/2', '*', '*', '*', '*', realpath("{$CC_CONFIG['cronDir']}/transportCron.php"));
$cron->closeCrontab();
echo "done.\n";

?>
