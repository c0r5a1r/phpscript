<?php

namespace Mailnebula\Api\Database;


class Proxy implements IProxy
{
    private static $dsn = IProxy::DSN;
    private static $userName = IProxy::USERNAME;
    private static $userPassword = IProxy::USERPASSWORD;
    private static $dbConnection;

    public function connect()
    {
        try {
            self::$dbConnection = new \PDO(self::$dsn, self::$userName, self::$userPassword);
            self::$dbConnection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return self::$dbConnection;
        } catch (\PDOException $e) {
            echo 'Wystapił błąd: ' . $e->getMessage();
            return false;
        }
    }
}