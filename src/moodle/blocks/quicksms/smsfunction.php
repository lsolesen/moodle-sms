<?php
/**
 * smsfunction.php - function to send the actual sms.
 *
 * PHP version 4
 *
 * Works with Moodle 1.9
 *
 * @author Lars Olesen <lars@legestue.net>
 * @version @@VERSION@@
 * @package quicksms
 */
   function sms_to_user($send_to_phone, $USER, $message)
   {  /*
        set_include_path('c:/Users/Lars Olesen/workspace/ilib/Ilib_Services_CPSMS/src/' . PATH_SEPARATOR . get_include_path());
        // todo put this down in the code, so only one call is made to the service
        require_once 'Ilib/Services/CPSMS.php';
        $sms = new Ilib_Services_CPSMS(USERNAME, PASSWORD);
        $sms->setMessage($message);
        $sms->addRecipient($send_to_phone);
        $sms->send($send_to_phone);
      */
       return true;
   }