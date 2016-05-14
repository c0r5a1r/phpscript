<?php

namespace Mailnebula\Api\Imap;

use \Mailnebula\Api\File;


class ImapMessage implements ImapMessageInterface
{
    private $stream;
    private $structure;

    public function __construct(ImapConnection $stream)
    {
        $this->stream = $stream->getResource();
    }

    public function msgCount()
    {
        return imap_num_msg($this->stream);
    }

    public function getMessageUid($id)
    {
        return imap_uid($this->stream, $id);
    }

    public function setStructure($uid)
    {
        $structure = imap_fetchstructure($this->stream, $uid, SE_UID);
        if (false === $this->structure) {
            throw new \Exception('FetchStructure failed: ' . imap_last_error());
        } else {
            $this->structure = $structure;
        }
    }

    public function getHeader($id)
    {
        $header = imap_headerinfo($this->stream, $id, 80, 150);

        $header_array['update'] = $header->udate;
        $header_array['format_date'] = gmdate('d.m.Y',$header->udate);
        $header_array['from'] = $header->from[0]->mailbox.'@'.$header->from[0]->host;
        $header_array['subject'] = $this->decodeSubject($header->subject);

        return (object)$header_array;
    }

    public function decodeSubject($string, $charset = 'utf-8')
    {
        $return = '';
        $elements   = imap_mime_header_decode($string);
        for($i=0; $i<count($elements); $i++)
        {
            if($elements[$i]->charset == 'default')
            {
                $elements[$i]->charset = 'iso-8859-1';
            }
            $return .= iconv(strtoupper($elements[$i]->charset), $charset.'//IGNORE', $elements[$i]->text);
        }
        return $return;
    }

    public function decodeToUTF8($stringQP, $base = 'windows-1252')
    {
        $pairs = array(
            '?x-unknown?' => "?$base?"
        );
        $stringQP = strtr($stringQP, $pairs);
        return imap_utf8($stringQP);
    }

    private function flattenParts($messageParts, $flattenedParts = array(), $prefix = '', $index = 1, $fullPrefix = true) {

        foreach($messageParts as $part) {
            $flattenedParts[$prefix.$index] = $part;
            if(isset($part->parts)) {
                if($part->type == 2) {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix.$index.'.', 0, false);
                }
                elseif($fullPrefix) {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix.$index.'.');
                }
                else {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix);
                }
                unset($flattenedParts[$prefix.$index]->parts);
            }
            $index++;
        }

        return $flattenedParts;

    }


    // Wydajnościowo poprawić
    public function getAttachment($id)
    {
        $attachments = [];

        if (isset($this->structure->parts) && count($this->structure->parts)) {
            for ($i = 0; $i < count($this->structure->parts); $i++) {
                $attachments[$i] = [
                    'is_attachment' => false,
                    'subtype' => '',
                    'filename' => '',
                    'name' => '',
                    'attachment' => '',
                    'inline' => false
                ];

                if (isset($this->structure->dparameters)) {
                    foreach ($this->structure->dparameters as $object) {
                        if (strtolower($object->attribute) == 'filename') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['inline'] = true;
                            $attachments[$i]['filename'] = $this->decodeToUTF8($object->value);

                            //var_dump($attachments);
                        }
                    }
                }

                if ($this->structure->parts[$i]->ifdparameters) {
                    foreach ($this->structure->parts[$i]->dparameters as $object) {
                        if (strtolower($object->attribute) == 'filename') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['filename'] = $this->decodeToUTF8($object->value);
                        }
                    }
                }

                if ($this->structure->parts[$i]->ifparameters) {
                    foreach ($this->structure->parts[$i]->parameters as $object) {
                        if (strtolower($object->attribute) == 'name') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['name'] = $this->decodeToUTF8($object->value);
                        }
                    }
                }

                /**
                 * PRZEGLĄDNĄC JESZCZE
                 */
                if (isset($this->structure->parts[$i]->parts[$i]->disposition)) {
                    if (strtolower($this->structure->parts[$i]->parts[$i]->disposition) == 'inline') {
                        $attachments[$i]['inline'] = true;


                        //for ($j=0; $j<count($structure->parts[$i]->parts[$i]->dparameters); $j++) {
                        //Zera - mogą być dodatkowe elementy - sprawdzenie
                        //}
                        if (strtolower($this->structure->parts[$i]->parts[$i]->dparameters[0]->attribute) == 'filename') {
                            $attachments[$i]['filename'] = $this->structure->parts[$i]->parts[$i]->dparameters[0]->value;
                            $attachments[$i]['is_attachment'] = true;

                            if (empty($attachments[$i]['filename'])) {
                                if (strtolower($this->structure->parts[$i]->parts[$i]->parameters[0]->attribute) == 'name') {
                                    $attachments[$i]['name'] = $this->structure->parts[$i]->parts[$i]->parameters[0]->value;
                                }
                            }
                        }
                    }
                }

                if ($attachments[$i]['inline'] === true) {
                    $attachments[$i]['subtype'] = $this->structure->parts[$i]->parts[$i]->subtype;
                } else {
                    $attachments[$i]['subtype'] = $this->structure->parts[$i]->subtype;
                }

                if ($attachments[$i]['is_attachment']) {

                    if ($attachments[$i]['inline'] === true) {

                        $encoding = $this->structure->parts[$i]->parts[$i]->encoding;

                        if (empty($encoding)) {
                            $encoding = $this->structure->encoding;
                            echo $encoding.'<br>';
                        }

                        for ($index=2; $index<=2.3; $index += 0.1) {

                            $attachments[$i]['attachment'] = imap_fetchbody($this->stream, $id, $index);
                            $attachments[$i]['attachment'] = $this->decode($attachments[$i]['attachment'], $encoding);

                            if (!empty($attachments[$i]['attachment'])) {
                                break;
                            }
                        }

                    } else {
                        $attachments[$i]['attachment'] = imap_fetchbody($this->stream, $id, $i+1);
                        $attachments[$i]['attachment'] = $this->decode($attachments[$i]['attachment'], $this->structure->parts[$i]->encoding);
                    }

                }
            }
        }

//        var_dump($attachments);

        return $attachments;
    }

    public function getMessage($inbox, $uid)
    {
        $message = $this->getPart($inbox, $uid, 'TEXT/HTML', $this->structure);

        if (empty(trim($message)) === true)
        {
            $message = $this->getPart($inbox, $uid, 'TEXT/PLAIN', $this->structure);
            $is_html = false;
        } else {
            $is_html = true;
        }

        if ($message !== false) {
            return (object)array('is_html' => $is_html, 'content' => '<meta charset="UTF-8">'.$message);
        }

        return false;
    }

    private function getPart($inbox, $uid, $mime_type, $structure, $part_counter = null)
    {
        if($mime_type === $this->getMimeType($structure))
        {
            if($part_counter === null)
            {
                $part_counter = 1;
            }

            return $this->decode(imap_fetchbody($inbox, $uid, $part_counter, FT_UID | FT_PEEK), $structure->encoding);

        } else if($structure->type === 1) {

            foreach($structure -> parts as $index => $sub_struct)
            {
                $prefix = '';
                if($part_counter !== null)
                {
                    $prefix = $part_counter.'.';
                }

                $data = $this->getPart($inbox, $uid, $mime_type, $sub_struct, $prefix.(++$index));

                if($data !== false)
                {
                    return $data;
                }
            }
        }

        return false;
    }

    private function getMimeType($structure)
    {
        $mime_types = array('TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER');

        if(empty($structure -> subtype) === false)
        {
            return $mime_types[$structure -> type].'/'.$structure -> subtype;
        }

        return 'TEXT/PLAIN';
    }

    function decode($content, $encoding)
    {
        // imap_qprint() vs quoted_printable_decode()?

        switch($encoding)
        {
            // 8 bits
            case 1:
                return quoted_printable_decode(imap_8bit($content));
            // Binary
            case 2:
                return imap_binary($content);
            // Base64
            case 3:
                return imap_base64($content);
            // Quoted printable
            case 4:
                return quoted_printable_decode($content);
            // 7 bits, other and unknown
            case 0:
            case 5:
            default:
                return $content;
        }
    }

    public function close()
    {
        if (is_resource($this->stream)) {
            imap_close($this->stream);
        }
    }

    public function getStream()
    {
        return $this->stream;
    }

    public function changeImgSrcHtml($html_message, $new_src)
    {
        preg_match_all('/src="cid:(.*)"/Uims', $html_message, $matches);

        $search = [];
        $replace = [];

        foreach($matches[1] as $match) {

            $search[] = "src=\"cid:".$match."\"";
            $replace[] = "src=\"".$new_src."\"";
        }

        return $html_message = str_replace($search, $replace, $html_message);
    }
}
