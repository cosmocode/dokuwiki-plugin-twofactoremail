<?php

use dokuwiki\Form\Form;
use dokuwiki\plugin\twofactor\OtpField;
use dokuwiki\plugin\twofactor\Provider;

/**
 * Twofactor Provider that sends codes by email
 */
class action_plugin_twofactoremail extends Provider
{

    /** @inheritdoc */
    public function getLabel()
    {
        global $USERINFO;
        return $this->getLang('name') . ' ' . $USERINFO['mail'];
    }

    /** @inheritdoc */
    public function isConfigured()
    {
        return $this->settings->get('verified');
    }

    /** @inheritdoc */
    public function renderProfileForm(Form $form)
    {
        $form->addHTML('<p>' . $this->getLang('verifynotice') . '</p>');
        $form->addElement(new OtpField('verify'));
        return $form;
    }

    /** @inheritdoc */
    public function handleProfileForm()
    {
        global $INPUT;

        if($INPUT->bool('init')) {
            $this->initSecret();
            $code = $this->generateCode();
            $this->transmitMessage($code);
        }

        if ($INPUT->has('verify')) {
            if ($this->checkCode($INPUT->str('verify'))) {
                $this->settings->set('verified', true);
            } else {
                // send a new code
                $code = $this->generateCode();
                $this->transmitMessage($code);
            }
        }
    }

    /** @inheritdoc */
    public function transmitMessage($code)
    {
        $userinfo = $this->getUserData();
        $to = $userinfo['mail'] ?? '';
        if (!$to) throw new \Exception($this->getLang('codesentfail'));

        // Create the email object.
        $body = io_readFile($this->localFN('mail'));
        $mail = new Mailer();
        $mail->to($to);
        $mail->subject($this->getLang('subject'));
        $mail->setBody($body, ['CODE' => $code]);
        $result = $mail->send();
        if (!$result) throw new \Exception($this->getLang('codesentfail'));

        return $this->getLang('codesent');
    }

}
