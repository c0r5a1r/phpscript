<?php

namespace Mailnebula\Api;

class ApiFilenebula {
    public function auth ($user,$pass){
        $ch = curl_init();
        $url = "https://api-demo.filenebula.com/auth/?token";
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, 'grant_type=password&username='.$user.'&password='.$pass.'&client_id=cURLTest');
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER,0);


        $result = curl_exec($ch);
        curl_close($ch);

        $xml = simplexml_load_string($result) or die("Error: Cannot create object");

        $access_token = $xml -> access_token->__toString();


        return $access_token;
    }

    public function welcomes ($access_token){
        $baseUrl = "https://api-demo.filenebula.com/";

        $header[0] = "Authorization: Bearer " . $access_token;


        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$baseUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $welcome = curl_exec($ch);
        curl_close($ch);

        return $welcome;
    }

    public static function upload ($file, $access_token){
        $file_striped = preg_replace('/[\s]/','%20',$file);

        $filesUrl = "https://api-demo.filenebula.com/files/Private/" . $file_striped;

        $header[0] = "Authorization: Bearer " . $access_token;




        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $filesUrl);
        curl_setopt($ch, CURLOPT_UPLOAD, TRUE);
        curl_setopt($ch, CURLOPT_PUT, 1);

        $data = fopen($file, 'r');

        curl_setopt($ch, CURLOPT_INFILE, $data);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $upload_result = curl_exec($ch);
        curl_close($ch);

        fclose($data);

        return $upload_result;

    }

    public static function mkdir($dirpath, $access_token) {
        $filesUrl = "https://api-demo.filenebula.com/files/?mkdir";
        $page = "/files/?mkdir";

        $xml_data = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<request>
<mkdir>
<dir name="/Private/mails" />
</mkdir>
</request>
XML;


        $req = new \SimpleXMLElement($xml_data);
        $dir = $req->mkdir->addChild('dir');
        $dir->addAttribute('name','/Private/' . $dirpath);
        $xml_data = $req->asXML();


        $headers = array(
            "POST ".$page." HTTP/1.1",
            "Authorization: Bearer " . $access_token,
            "Content-type: application/xml",
            "Accept: application/xml",
            "Cache-Control: no-cache",
            "Content-length: ".strlen($xml_data),
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $filesUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);

        $data = curl_exec($ch);
        curl_close($ch);


        return $data;
    }

    public function download($file, $access_token){
        $file_striped = preg_replace('/[\s]/','%20',$file);
        $baseUrl = "https://api-demo.filenebula.com/files/Private/" . $file_striped;

        $header[0] = "Authorization: Bearer " . $access_token;

        $dir_path = dirname($file);
        $only_file = basename($file); //вибирання тільки шляху папки

        mkdir($dir_path, 0775, true);

        $dest = $dir_path .DIRECTORY_SEPARATOR. $only_file;

        $fp = fopen ($dest, 'w+');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);


        $down_status = curl_exec($ch);

        curl_close($ch);
        fclose($fp);

        return $down_status;

    }
    /**
     * @param $access_token
     * @return array with filenames as key and hash as value in mails/ on Filenebula.
     *
     */
    public function checkFiles($access_token){
        $baseUrl = "https://api-demo.filenebula.com/files";
        $header[0] = "Authorization: Bearer " . $access_token;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$baseUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $xml = new \SimpleXMLElement($result);
        $max = $xml->private->files->file->count();
        $names = array();
        for($i=0; $i<$max; $i++) {
            $atribute  = $xml->private->files->file[$i]->attributes();
            $names[$atribute['name']->__toString()] = $atribute['hash']->__toString();
        }
        $cut_files = array();
        foreach($names as $file=>$hash) {
            $fi = '';
            $f = explode('/', $file);
            $max = count($f);
            for ($i=0; $i<$max; $i++){
                if ($f[$i] === 'mails') {
                    $fi = implode('/', $f);
                } else {
                    unset($f[$i]);
                }
            }
            $cut_files[$fi] = $hash;
        }
        unset($cut_files['']);

        return $cut_files;
    }

    public function fileDiff($files_on_server, $files_on_filenebula){

        if ($files_on_server == $files_on_filenebula){
            return true;
        } else {
            $arr_diff = array_diff_assoc($files_on_server, $files_on_filenebula);
            return $arr_diff;
        }

    }

    public function checkFilesOutbox($access_token){
        $baseUrl = "https://api-demo.filenebula.com/files";
        $header[0] = "Authorization: Bearer " . $access_token;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$baseUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $xml = new \SimpleXMLElement($result);
        $max = $xml->private->files->file->count();
        $names = array();
        for($i=0; $i<$max; $i++) {
            $atribute  = $xml->private->files->file[$i]->attributes();
            $names[$atribute['name']->__toString()] = $atribute['size']->__toString();
        }
        $cut_files = array();
        foreach($names as $file=>$size) {
            $fi = '';
            $f = explode('/', $file);
            $max = count($f);
            for ($i=0; $i<$max; $i++){
                if ($f[$i] === 'outbox') {
                    $fi = implode('/', $f);
                } else {
                    unset($f[$i]);
                }
            }
            $cut_files[$fi] = $size;
        }
        unset($cut_files['']);

        return $cut_files;
    }

    public function getPublicLink ($path, $access_token){
        $filesUrl = "https://api-demo.filenebula.com/public";
        $page = "/public";

        $xml_data = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<request>
<generate>
<!--<public name="/Private/" type="link" />-->
<!--<public name="/Private/slawek" type="gallery" description="Taka przykładowa galeria katalogu!" />-->
</generate>
</request>
XML;


        $req = new \SimpleXMLElement($xml_data);
        $dir = $req->generate->addChild('public');
        $dir->addAttribute('name','/Private/' . $path);
        $dir->addAttribute('type','link');
        $xml_data = $req->asXML();


        $headers = array(
            "POST ".$page." HTTP/1.1",
            "Authorization: Bearer " . $access_token,
            "Content-type: application/xml",
            "Accept: application/xml",
            "Cache-Control: no-cache",
            "Content-length: ".strlen($xml_data),
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $filesUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);

        $data = curl_exec($ch);
        curl_close($ch);

        $xml = new \SimpleXMLElement($data);
        $attribute = $xml->success->public->attributes();
        $display_url = $attribute['display_url']->__toString();


        return $display_url;

    }

    public function checkFilesOutboxHash($access_token){
        $baseUrl = "https://api-demo.filenebula.com/files";
        $header[0] = "Authorization: Bearer " . $access_token;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$baseUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $xml = new \SimpleXMLElement($result);
        $max = $xml->private->files->file->count();
        $names = array();
        for($i=0; $i<$max; $i++) {
            $atribute  = $xml->private->files->file[$i]->attributes();
            $names[$atribute['name']->__toString()] = $atribute['hash']->__toString();
        }
        $cut_files = array();
        foreach($names as $file=>$hash) {
            $fi = '';
            $f = explode('/', $file);
            $max = count($f);
            for ($i=0; $i<$max; $i++){
                if ($f[$i] === 'outbox') {
                    $fi = implode('/', $f);
                } else {
                    unset($f[$i]);
                }
            }
            $cut_files[$fi] = $hash;
        }
        unset($cut_files['']);
        $cut_files2 = array();
        foreach ($cut_files as $file=>$hash){
//            $f = explode('/', $file);
            if(basename($file) === 'adresat.txt'){
                $cut_files2[$file] = $hash;
            } else {
                continue;
            }

        }

        return $cut_files2;
    }

    public function checkFilesOutboxSubj($access_token, $subject){
        $baseUrl = "https://api-demo.filenebula.com/files";
        $header[0] = "Authorization: Bearer " . $access_token;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$baseUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $xml = new \SimpleXMLElement($result);
        $max = $xml->private->files->file->count();
        $names = array();
        for($i=0; $i<$max; $i++) {
            $atribute  = $xml->private->files->file[$i]->attributes();
            $names[$atribute['name']->__toString()] = $atribute['size']->__toString();
        }
        $cut_files = array();
        foreach($names as $file=>$size) {
            $fi = '';
            $f = explode('/', $file);
            $max = count($f);
            for ($i=0; $i<$max; $i++){
                if ($f[$i] === 'outbox') {
                    $fi = implode('/', $f);
                } else {
                    unset($f[$i]);
                }
            }
            $cut_files[$fi] = $size;
        }
        unset($cut_files['']);
        $cut_files2 = array();
        foreach($cut_files as $file=>$size) {
            $sub = explode('/', $file);
            if($sub[1] === $subject){
                $cut_files2[$file] = $size;
            }
        }


        return $cut_files2;
    }
}


