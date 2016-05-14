<?php

namespace Mailnebula\Api\Imap;


interface ImapMessageInterface
{
    public function msgCount();

    public function getHeader($id);

    public function getMessageUid($id);

    public function setStructure($id);

    public function getMessage($stream, $uid);

    public function getAttachment($uid);

    public function close();

    public function decode($content, $encoding);
}