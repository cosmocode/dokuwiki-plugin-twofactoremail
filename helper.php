<?php
// Load the Twofactor_Auth_Module Class
require_once(dirname(__FILE__).'/../twofactor/authmod.php');

class helper_plugin_twofactoremail extends Twofactor_Auth_Module {
	/** 
	 * If the user has a valid email address in their profile, then this can be used.
	 */
    public function canUse($user = null){
		global $USERINFO;
		return ($this->attribute->exists("twofactoremail", "verified", $user) && $this->attribute->exists("twofactoremail", "email", $success, $user) == $USERINFO['mail'] && $this->getConf('enable') === 1);
	}
	
	/**
	 * This module can not provide authentication functionality at the main login screen.
	 */
    public function canAuthLogin() {
		return false;
	}
		
	/**
	 * This user will need to verify their email.
	 */
    public function renderProfileForm(){
		$elements = array();
			// If email has not been verified, then do so here.
			if (!$this->attribute->exists("twofactoremail", "verified")) {
				// Render the HTML to prompt for the verification/activation OTP.
				$elements[] = '<span>'.$this->getLang('verifynotice').'</span>';				
				$elements[] = form_makeTextField('email_verify', '', $this->getLang('verifymodule'), '', 'block', array('size'=>'50', 'autocomplete'=>'off'));
				$elements[] = form_makeCheckboxField('email_send', '1', $this->getLang('resendcode'),'','block');
			}			
			else {
				// Render the element to remove email.
				$elements[] = form_makeCheckboxField('email_disable', '1', $this->getLang('killmodule'), '', 'block');
			}
		return $elements;
	}

	/**
	 * Process any user configuration.
	 */	
    public function processProfileForm(){
		global $INPUT, $USERINFO;
		if ($INPUT->bool('email_disable', false)) {
			// Delete the verified setting.
			$this->attribute->del("twofactoremail", "verified");
			return true;
		}
		$otp = $INPUT->str('email_verify', '');
		if ($otp) { // The user will use email.
			$checkResult = $this->processLogin($otp);
			// If the code works, then flag this account to use email.
			if ($checkResult == false) {
				return 'failed';
			}
			else {
				$this->attribute->set("twofactoremail", "verified", true);
				return 'verified';
			}					
		}							
		
		$changed = null;
		$email = $INPUT->str('email', $USERINFO['mail']);
		if ($email != $this->attribute->get("twofactoremail","email")) {
			if ($this->attribute->set("twofactoremail","email", $email)== false) {
				msg("TwoFactor: Error setting email.", -1);
			}
			// Delete the verification for the email if it was changed.
			$this->attribute->del("twofactoremail", "verified");
			$changed = true;
		}
		
		// If the data changed and we have everything needed to use this module, send an otp.
		if ($changed && $this->attribute->exists("twofactoremail", "email")) {
			$changed = 'otp';
		}
		return $changed;
	}	
	
	/**
	 * This module can send messages.
	 */
	public function canTransmitMessage(){
		return true;
	}
	
	/**
	 * Transmit the message via email to the address on file.
	 * As a special case, configure the mail settings to send only via text.
	 */
	public function transmitMessage($message, $force = false){		
		if (!$this->canUse()  && !$force) { return false; }
		$to = $this->attribute->get("twofactoremail", "email");
		// Create the email object.
		$mail = new Mailer();
		$subject = $conf['title'].' login verification';
		$mail->to($to);
		$mail->subject($subject);
		$mail->setText($message);			
		$result = $mail->send();
		// This is here only for debugging for me for now.  My windows box can't send out emails :P
		#if (!result) { msg($message, 0); return true;}
		return $result;
		}
	
	/**
	 * 	This module uses the default authentication.
	 */
    //public function processLogin($code);
}