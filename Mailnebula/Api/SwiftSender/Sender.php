<?php

namespace Mailnebula\Api\SwiftSender;


class Sender {
    public function sender($adresats, $subject, $body, $attachments = null) {
        $mail_user = '';
        $mail_pass = '';

        if($attachments == null) {
            $transport = \Swift_SmtpTransport::newInstance('ssl://smtp.gmail.com', 465)
                ->setUsername($mail_user)
                ->setPassword($mail_pass);
            $mailer = \Swift_Mailer::newInstance($transport);
            $message = \Swift_Message::newInstance()
                ->setFrom(array('mailnebulatest@gmail.com' => 'Mailnebula'))
                ->setTo($adresats)
                ->setSubject($subject)
                ->setBody($body);
            return $mailer->send($message);
        } else {
            $transport = \Swift_SmtpTransport::newInstance('ssl://smtp.gmail.com', 465)
                ->setUsername($mail_user)
                ->setPassword($mail_pass);
            $mailer = \Swift_Mailer::newInstance($transport);
            $message = \Swift_Message::newInstance()
                ->setFrom(array('mailnebulatest@gmail.com' => 'Mailnebula'))
                ->setTo($adresats)
                ->setSubject($subject)
                ->setBody($body);
            foreach($attachments as $attachment) {
                $message->attach(\Swift_Attachment::fromPath($attachment));
            }
            return $mailer->send($message);
        }

    }
}

//class GetLink
//{
//    public function getFilenebulaLink ($path) {
//        $reg_expr = '/((http|https|ftp):\/\/.*?filenebula\.[^\s]+)/i';
//
//        $handle = fopen($path, 'r');
//
//        $text = fread($handle, filesize($path));
//
//        fclose($handle);
//
//        preg_match_all($reg_expr,$text,$result);
//
//        echo '<br/><strong>Result:</strong> <pre>'.var_export($result[1],true).'</pre>';
//    }
//
//    public function getAllLinks ($path) {
//        $reg_exp = '/((http|https|ftp):\/\/[^\s]+)/i';
//
//        $handle = fopen($path, 'r');
//
//        $text = fread($handle, filesize($path));
//
//        fclose($handle);
//
//        preg_match_all($reg_exp,$text,$result);
//
//        echo '<br/><strong>Result:</strong> <pre>'.var_export($result[1],true).'</pre>';
//    }
//}
