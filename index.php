<?php
require_once 'vendor/autoload.php';

use Mailnebula\Api\Imap\ImapConnection;
use Mailnebula\Api\Imap\ImapMessage;
use Mailnebula\Api\File\File;
use Mailnebula\Api\Database\Proxy;
use Mailnebula\Api\Database\Check;
use Mailnebula\Api\File\UnArchiver;
use Mailnebula\Api\ApiFilenebula;

/**
 *
 * @CLASS ImapConnection
 *
 * W puste miejsce odpowiednio:
 *
 * mail
 * hasło
 *
 */
$connection = new ImapConnection('Imap.gmail.com', 993, 'Imap', 'ssl', '', '');

$imap_resource = new ImapMessage($connection);
$quantity = $imap_resource->msgCount();

$path = 'mails';
File::createMessageFolder($path);


$connection = new Proxy();
$database = new Check($connection);

for ($id=1; $id<=$quantity; $id++) {
    $uid = $imap_resource->getMessageUid($id); // zwykłe ID

    //SPRAWDZENIE CZY UID ISTNIEJE JUZ W BAZIE - JESLI TAK - TWORZENIE NIE JEST WYKONYWANE
    if (true === $database->checkUId($uid)) {
        $header = $imap_resource->getHeader($id); // zwykłe ID
        $imap_resource->setStructure($uid);
        $subject = File::removeSlashes($header->subject);

        // TWORZENIE KATALOGU WIADOMOSCI
        $directory_path = $path . '/' . $uid . ' ' . $subject;
        //$directory_path = preg_replace('/[$^&*+=\';|":<>?~\\\\]/','',$directory_path);
        File::createMessageFolder($directory_path, $header->update);

        // TWORZENIE WIADOMOSCI + ZAPIS DO PLIKU
        $body = $imap_resource->getMessage($imap_resource->getStream(), $uid);

        // POBRANIE ZAŁĄCZNIKA
        $attachments = $imap_resource->getAttachment($id); // zwykłe ID

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if ($attachment['is_attachment'] === true) {

                    $directory = $directory_path . '/data';
                    $attachment_path = $directory . '/' . File::removeSlashes($attachment['filename']);
//                    $attachment_path = preg_replace('/[$^&*+=\';|":<>?~\\\\]/','',$attachment_path);

                    if ($attachment['inline']) {
                        $img_path = 'data/' . File::removeSlashes($attachment['filename']);
                        $body->content = $imap_resource->changeImgSrcHtml($body->content, $img_path);
                    }

                    File::createMessageFolder($directory, $header->update);

                    File::write($attachment_path, $attachment['attachment']);

                    UnArchiver::extract($attachment_path, $directory, $attachment['subtype']);

                    /*if (file_exists($directory.'/__MACOSX')) {
                        chmod($directory.'/__MACOSX', 0777);
                        File::rRmdir($directory.'/__MACOSX');
                    }*/
                }
            }
        }

        if (!empty($body)) {
            File::createMessageFile($directory_path, $subject, $body, $header->update);
        }

        $database->setUid($uid);
    }
}

$imap_resource->close();


/**
 *
 *
 *      API
 *
 *
 */

/**
 *
 *
 *      Dane do konta usera filenebuli
 *
 *
 */
$user = "";
$pass = "";

$api = new ApiFilenebula();

$access_token = $api->auth($user,$pass);
//$welcomes = $api->welcomes($access_token);

$dir_path = 'mails/';

$dirs = File::dirPath($dir_path);
$files = File::filesPath($dir_path);

$mkdirs = array();
$upload_files = array();
$server_files = array();


/**
 * linki
 */
//$public = array();
//foreach ($files_hash as $file=>$hash){
//    $public[] = $api->getPublicLink($file, $access_token);
//}
/**------*/
/**
 * upload
 */
foreach($files as $file) {
    $server_files[$file] = md5_file($file);
}

$files_on_filenebula = $api->checkFiles($access_token);
$files_difference = $api->fileDiff($server_files, $files_on_filenebula);


//var_dump($files_on_filenebula);
//var_dump($files_difference);

if(is_array($files_difference)) {
    foreach ($dirs as $dir) {
        $dir = preg_replace('/[\s]/','%20',$dir);

        $mkdirs[] = ApiFilenebula::mkdir($dir,$access_token);
    }

    foreach ($files_difference as $file=>$hash) {
        $upload_files[] = ApiFilenebula::upload($file, $access_token);
    }
    echo "<strong>NEW MAILS HAS BEEN UPDATED<strong/>" . "<br/>";
} else {
    echo "<strong>NO UPDATE NEEDED<strong/>" . "<br/>";
}
/**------*/
/**
 * download from filenebula
 */
if(!is_dir('sent/')){
    mkdir('sent/', 0777, true);
}
if(is_dir('outbox/')){


    $out_files_hash_neb = $api->checkFilesOutboxHash($access_token);

//    var_dump($out_files_hash_neb);
    foreach ($out_files_hash_neb as $file=>$size){
        $explode_files = explode('/', $file);
        unset($explode_files[0]);
        $sent_file = implode('/', $explode_files);
        $sent_file = 'sent/' . $sent_file;
        echo $sent_file;
        if(file_exists($sent_file)){
            echo ' ------> ' . ' mail juz wysłany wcześniej ' . '<br/>';
        } else {
            $s = explode('/', $file);
            $link = ' ';
            $links = array();
            $out_files = $api->checkFilesOutboxSubj($access_token, $s[1]);
//            var_dump($out_files);
            foreach($out_files as $filez=>$sizez){

                if ($sizez > 44000){
                    $link = $api->getPublicLink($filez, $access_token);
                    $f = explode('/', $filez);
                    $body_file = 'outbox/' . $f[1] . '/body.txt';
                    $links[$link] = $body_file;
                } else {
                    $api->download($filez,$access_token);
                }
            }
            foreach($links as $link=>$body_file){
                file_put_contents($body_file, "\r\n" . $link, FILE_APPEND | LOCK_EX);
            }
            echo ' ----> ' . 'downloaded' . '<br/>';
        }
    }

} else {
    $out_files = $api->checkFilesOutbox($access_token);
    $link = ' ';
    $links = array();
    foreach($out_files as $file=>$size){

        if ($size > 44000){
            $link = $api->getPublicLink($file, $access_token);
            $f = explode('/', $file);
            $body_file = 'outbox/' . $f[1] . '/body.txt';
            $links[$link] = $body_file;
        } else {
            $api->download($file,$access_token);
        }
    }
    foreach($links as $link=>$body_file){
        file_put_contents($body_file, "\r\n" . $link, FILE_APPEND | LOCK_EX);
    }
}
/**----*/

/**
 * sending with swiftmailer
 */

$outbox = File::filesPath('outbox/');
foreach ($outbox as $file) {
    if (basename($file) === 'adresat.txt') {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $adresats = array();
        foreach ($lines as $line) {
            $adresats[$line] = '';
        }
    }
}

$directory = 'outbox/';
$scanned_directory = array_diff(scandir($directory, 1), array('..', '.'));
$swift_sender = new \Mailnebula\Api\SwiftSender\Sender();
foreach($scanned_directory as $subject) {
    if (is_dir($directory . $subject)) {
        $adresats = array();
        $adresats_file = $directory . $subject . '/adresat.txt';
        $lines = file($adresats_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $adresats[$line] = '';
        }
        $body_file = $directory . $subject . '/body.txt';
        $handle = fopen($body_file, "r");
        $body = fread($handle, filesize($body_file));
        fclose($handle);
        $scan_attach = array_diff(scandir($directory . $subject . '/attachments/', 1), array('..', '.'));
        $attachments = array();
        foreach($scan_attach as $file) {
            $attachments[] = $directory . $subject . '/attachments/' . $file;
        }
        $sender = $swift_sender->sender($adresats,$subject, $body, $attachments);

    }
    unset($adresats);
}
/**
 * wyslane maily do foldera sent
 */
$dir = "outbox/";
$dirNew = "sent";
if (is_dir($dir)) {
    if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            if ($file==".") continue;
            if ($file=="..")continue;
            //if ($file=="index.php") continue; for example if you have index.php in the folder
            if (rename($dir.'/'.$file,$dirNew.'/'.$file))
            {
                echo " Files Copyed Successfully";
                echo ": $dirNew/$file";
            }
            else {echo "File Not Copy";}
        }
        closedir($dh);
    }
}




