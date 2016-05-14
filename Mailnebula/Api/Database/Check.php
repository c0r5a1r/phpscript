<?php
/**
 * Created by PhpStorm.
 * User: szubi
 * Date: 10.04.2016
 * Time: 11:21
 */

namespace Mailnebula\Api\Database;


class Check
{
    private $handle;
    private $ids;
    private $how_many_ids;

    public function __construct(Proxy $handle)
    {
        $this->handle = $handle->connect();
        $this->ids = [];

        $this->setUIds();
    }

    private function setUIds()
    {
        try {
            $stmt = $this->handle->query('select mail_id from mails_id');

            while ($row = $stmt->fetch()) {
                $this->ids[] = (int)$row['mail_id'];
            }

            $stmt->closeCursor();

        } catch (\PDOException $e) {
            echo $e->getMessage();
        }

        $this->how_many_ids = count($this->ids);
    }

    public function checkUId($uid)
    {
        $check = true;

        for ($i=0; $i<$this->how_many_ids; $i++) {
            if ($uid === $this->ids[$i]) {
                $check = false;
                break;
            }
        }

        return $check;
    }

    public function setUid($uid)
    {
        try {
            $stmt = $this->handle->prepare('INSERT INTO `mails_id` ( `mail_id` ) VALUES( :id )');
            $stmt->bindValue(':id', $uid, \PDO::PARAM_INT);

            $stmt->execute();
        } catch (\PDOException $e) {
            echo $e->getMessage();
        }
    }
}
