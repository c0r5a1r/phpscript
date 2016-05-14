<?php

namespace Mailnebula\Api\Imap;


class ImapConnection
{
    private $stream;

    public function __construct($hostname, $port, $type, $security, $username, $password)
    {
        $stream = imap_open('{'.$hostname.':'.$port.'/'.$type.'/'.$security.'}', $username, $password);

        if (false === $stream) {
            throw new \Exception('Connect failed: '.imap_last_error());
        } else {
            $this->stream = $stream;
        }
    }

    public function getResource()
    {
        return !empty($this->stream) ? $this->stream : null;
    }
}