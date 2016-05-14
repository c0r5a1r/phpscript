<?php

namespace Mailnebula\Api\File;

//use ZipArchive;

//require_once 'Rar/vendor/autoload.php';


class UnArchiver
{
    /*public function __construct()
    {

    }*/

    public static function extract($file_path, $extract_directory, $type)
    {
        if (file_exists($file_path)) {

            switch (strtolower($type)) {
                case 'zip':

                    $zip = new \ZipArchive();
                    $open = $zip->open($file_path);

                    if ($open === true) {

                        $zip->extractTo($extract_directory);
                        $zip->close();

                        unlink($file_path);
                    }

                    break;
                case 'rar':

                    break;
            }
        }
    }
}