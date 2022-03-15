<?php

use dokuwiki\Form\Form;
use dokuwiki\plugin\twofactor\Provider;

/**
 * Twofactor Provider that sends codes by email
 */
class action_plugin_twofactoremail extends Provider
{

    /** @inheritdoc */
    public function getLabel()
    {
        return $this->getLang('name');
    }

    /** @inheritdoc */
    public function isConfigured()
    {
        return $this->settings->get('verified');
    }

    /** @inheritdoc */
    public function renderProfileForm(Form $form)
    {
        $verified = $this->settings->get('verified');
        $secret = $this->settings->get('secret');

        if (!$secret) {
            $form->addHTML('<p>' . $this->getLang('intro') . '</p>');
            $form->addCheckbox('email_init', $this->getLang('init'));
        } elseif (!$verified) {
            $form->addHTML('<p>' . $this->getLang('verifynotice') . '</p>');
            $form->addTextInput('verify', $this->getLang('verifymodule'));
        } else {
            $form->addHTML('<p>' . $this->getLang('configured') . '</p>');
        }

        return $form;
    }

    /** @inheritdoc */
    public function handleProfileForm()
    {
        global $INPUT;

        if ($INPUT->has('email_init')) {
            $this->initSecret();
            $code = $this->generateCode();
            $ok = $this->transmitMessage($code);
            msg($ok, 1);
        } elseif ($INPUT->has('verify')) {
            if ($this->checkCode($INPUT->str('verify'))) {
                $this->settings->set('verified', true);
            } else {
                $this->settings->delete('secret');
            }
        }

    }

    /** @inheritdoc */
    public function transmitMessage($code)
    {
        global $USERINFO;
        $to = $USERINFO['mail'];
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
