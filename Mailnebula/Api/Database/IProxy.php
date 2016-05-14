<?php

namespace Mailnebula\Api\Database;


interface IProxy
{
    const DSN = 'mysql:host=localhost;dbname=Mailnebula';
    const USERNAME = 'root';
    const USERPASSWORD = '';

    public function connect();
}
