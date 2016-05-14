<?php

namespace Mailnebula\Api\Database;


interface IProxy
{
    const DSN = 'mysql:host=localhost;dbname=Mailnebula';
    const USERNAME = 'root';
    const USERPASSWORD = 'ju6iCohv';

    public function connect();
}
