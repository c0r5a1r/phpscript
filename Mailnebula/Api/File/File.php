<?php

namespace Mailnebula\Api\File;


class File
{
    /**
     * @param $path
     * @param null $date
     * @throws \Exception
     *
     * Foldery tworzone przez pętle, czy osobne wywołania funkcji?
     * CHMOD - argument, czy stały
     */
    public static function createMessageFolder($path, $date=null)
    {
        if ($date !== null) {
            self::checkDate($date);
        }

        $path = self::checkPath($path);

        if (!empty($path)) {


            $diretory_path = array_filter(explode('/', trim($path, '/')));
            $quantity = count($diretory_path);

            $full_path = '';

            if ($quantity <= 5) {

                $i = 0;
                foreach ($diretory_path as $element) {
                    $full_path .= $element;

                    if (!file_exists($full_path)) {
                        $oldmask = umask(0);
                        mkdir($full_path, 0777);

                        /*if ($date !== null) {
                            touch($full_path, $date, $date);
                        } else {
                            touch($full_path);
                        }*/

                        ($date !== null) ? touch($full_path, $date, $date) : touch($full_path);

                        umask($oldmask);
                    }
                    $i++;

                    if ($i < $quantity) {
                        $full_path .= '/';
                    }
                }
            } else {
                throw new \Exception('The directory path is to long. Too many folders you wanted to create');
            }
        } else {
            throw new \Exception('Bad directory path.');
        }
    }

    /**
     * @param $path
     * @param $subject
     * @param $body
     * @param null $date
     *
     * walidacja ścieżki
     * co z wiadomością?
     */
    public static function createMessageFile($path, $subject, $body, $date=null)
    {
        $subject = filter_var($subject, FILTER_SANITIZE_STRING);
        $date = self::checkDate($date);

        if (self::checkPath($path) && !empty($subject)) {
//            $subject = preg_replace('/[$^&*+=\';|":<>?~\\\\]/','',$subject);
            $message_path = $path.'/'.$subject.'.html';
            if (file_exists($path) && !file_exists($message_path)) {

                touch($message_path, $date, $date);
                chmod($message_path, 0777);

                if (!empty($message_path) && !empty($body->content)) {
                    file_put_contents($message_path, $body->content);
                }
            }
        }
    }

    public static function rRmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);

            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") {
                        self::rRmdir($dir."/".$object);
                    } else {
                        unlink($dir."/".$object);
                    }
                }
            }

            reset($objects);
            self::rRmdir($dir);
        }
    }

    public static function dirPath ($dir_path) {
        $dirs = $files = array();

        $directory = new \RecursiveDirectoryIterator($dir_path, \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST) as $path ) {
            $path->isDir() ? $dirs[] = $path->__toString() : $files[] = realpath($path->__toString());
        }

        return $dirs;

    }

    public static function filesPath ($dir_path) {
        $dirs = $files = array();

        $directory = new \RecursiveDirectoryIterator($dir_path, \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST) as $path ) {
            $path->isDir() ? $dirs[] = $path->__toString() : $files[] = $path->__toString();
        }

        return $files;

    }

    private static function checkPath($path)
    {
        $path = filter_var($path, FILTER_SANITIZE_STRING);
        return $path;
    }

    private static function checkDate($date)
    {
        preg_match('#(^[0-9]{10})#', $date, $date_f);
        return !empty($date_f[0]) ? $date_f[0] : 0;
    }

    public static function removeSlashes(&$string)
    {
        $string = preg_replace('/[$^&*+=\';|":<>?~\\\\]/','',$string);
        $string = str_replace('/', '_', $string);
        return $string;
    }

    public static function write($path, $data)
    {
        if (!file_exists($path)) { // && isNotReference) {
            file_put_contents($path, $data);

            chmod($path, 0777);
        }
    }
}
