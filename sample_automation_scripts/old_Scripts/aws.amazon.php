<?php
// Server-Portal-ID: 440 - Last modified: 20.03.2024 08:04:21 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://console.aws.amazon.com/billing/home';

public $restrictPages = 3;
public $isNoInvoice = true;
public $account_key = '';
public $tax_invoice = '';
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	
	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : $this->restrictPages;
	
	$this->account_key = isset($this->exts->config_array["account_key"]) ? trim($this->exts->config_array["account_key"]) : '';
	if(!preg_match("/[^\-\d]/", $this->account_key) && preg_match("/[\d]/", $this->account_key)){
		// Updated special case, if user input account key with format dddd-dddd-dddd, remove "-" symbol
		$this->account_key = preg_replace("/[^\d]/", '', $this->account_key);
	}
	$this->tax_invoice = isset($this->exts->config_array["tax_invoice"]) ? trim($this->exts->config_array["tax_invoice"]) : '';
	
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page-1');
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page-2');
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page-3');
	
	// If user hase not logged in from cookie, do login
	if(!$this->isLoggedin()) {
		$this->exts->log('NOT logged via cookie');
		// $this->exts->clearCookies();
		if(stripos($this->account_key, "http") !== false) {
			$custom_url = $this->account_key;
			$this->exts->openUrl($custom_url);
			sleep(15);
			if ($this->exts->exists('[ng-app="IamLoginApp"] form#signin_form')) {
				$this->checkFillIAMlogin();
			} else {
				if($this->exts->exists('#root_account_signin, [data-da-campaign="consolesignout_logbackin_cta"] a.lb-btn-p-primary, , a[href*="header-signin"]')){
					$this->exts->moveToElementAndClick('#root_account_signin, [data-da-campaign="consolesignout_logbackin_cta"] a.lb-btn-p-primary, a[href*="header-signin"]');
					sleep(10);
				}
				$this->checkFillLogin();
			}
		} else {
			$this->exts->openUrl($this->baseUrl);
			sleep(15);
			if($this->exts->exists('#root_account_signin, a[href*="header-signin"]')){
				$this->exts->moveToElementAndClick('#root_account_signin, a[href*="header-signin"]');
				sleep(10);
			}
			$this->checkFillLogin();
		}
		
		sleep(10);
		if ($this->exts->exists('span#optional_verification_method_confirmation_skip_verification_link')) {
			$this->exts->moveToElementAndClick('span#optional_verification_method_confirmation_skip_verification_link');
			sleep(10);
		}
		$this->checkFillCaptcha();
		$this->checkFillCaptcha();
		
		$this->checkFillTwoFactor();
		
		$this->checkFillCaptcha();
		if ($this->exts->exists('#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
			$this->exts->moveToElementAndClick('#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
			sleep(15);
			$this->exts->capture('after-click-skip-phone-number');
		}

		if($this->exts->urlContains('signin.aws.amazon.com/setpassword') && $this->exts->exists('.awsui-modal-dialog #set_password_ok_button')){
			$this->exts->capture('1-creating-newpassword-warning');
			// a Warning, but it's able to skip and continue to home page
			$this->exts->openUrl($this->baseUrl);
		}
		if ($this->exts->exists('#passwordexpiration_form #password_expiration_container #continue_button')) {
			// It warning password is going to expired, Click if it have "Continue" option
			$this->exts->moveToElementAndClick('#passwordexpiration_form #password_expiration_container #continue_button');
			sleep(10);
		}
		sleep(20);
	}

	// then check user logged in or not
	if($this->isLoggedin()) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		
		if($this->exts->exists('[data-id="awsccc-cb-btn-accept"]')) {
			$this->exts->moveToElementAndClick('[data-id="awsccc-cb-btn-accept"]');
			sleep(1);
		}
		
		// Open invoices url and download invoice
		$this->exts->openUrl($this->baseUrl);
		sleep(10);
		if($this->exts->exists('[data-id="awsccc-cb-btn-accept"]')) {
			$this->exts->moveToElementAndClick('[data-id="awsccc-cb-btn-accept"]');
			sleep(1);
		}
		$this->exts->moveToElementAndClick('#billing-console-root li a[href*="#/bills"]');
		sleep(20);
		//Sometime even if you click if didn't open invoice page.
		if(!$this->exts->exists('#billSelectDate li.awsui-select-option[data-value], [data-testid="billing-period-dropdown"] button[aria-haspopup="true"]')) {
			$this->exts->update_process_lock();
			$this->exts->execute_javascript("document.querySelectorAll('#billing-console-root li a[href*=\"#/bills\"]')[0].click();", array());
			sleep(20);
			
			if(!$this->exts->exists('#billSelectDate li.awsui-select-option[data-value], [data-testid="billing-period-dropdown"] button[aria-haspopup="true"]')) {
				$currentMonth= date('m');
				$currentYear = date('Y');
				$billingPage = 'https://console.aws.amazon.com/billing/home?#/bills?year='.$currentYear.'&month='.$currentMonth;
				$this->exts->openUrl($billingPage);
			}
		}

		// 03 2023, they're using both old and new layout, so check and switch to matched function
		if($this->exts->exists('[data-testid="billing-period-dropdown"] button[aria-haspopup="true"]')){
			$this->processInvoices_2023();
		} else {
			$this->processInvoices();
		}

		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed '. $this->exts->getUrl());
		if ($this->exts->exists('form#changepassword_form input#oldpassword')) {
			$this->exts->capture('account-must-change_password');
			$this->exts->account_not_ready();
		} else if($this->exts->urlContains('/forgotpassword/reverification')){
			$this->exts->capture('account-must-change_password');
			$this->exts->account_not_ready();
		} else if(stripos($this->exts->extract('#message_error', null, 'innerText'), 'password is incorrect') !== false){
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}
private function checkFillLogin() {
	$this->exts->log(__FUNCTION__);
	$username_selector = 'input#resolving_input';
	$password_selector = '#login_container:not([style*="display:none"]) input#password, input[name="password"]';
	$this->exts->capture("2-login-page");
	
	if($this->exts->getElement($username_selector) != null) {
		sleep(2);
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($username_selector, $this->username);
		sleep(1);
		$this->exts->moveToElementAndClick('button#next_button');
		sleep(7);
		$this->checkFillCaptcha();
		$this->checkFillCaptcha();
		$this->checkFillCaptcha();
	}
	
	if ($this->exts->exists('[ng-app="IamLoginApp"] form#signin_form')) {
		$this->checkFillIAMlogin();
	} else if($this->exts->getElement($password_selector) != null) {
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($password_selector, $this->password);
		sleep(1);
		
		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick('button#signin_button, #signInSubmit-input');
		sleep(5);
		if($this->exts->allExists(['#ap_captcha_img img', 'input#ap_captcha_guess', $password_selector])){
			// Solve captcha if it display on password page
			$this->exts->processCaptcha('#ap_captcha_img img', 'input#ap_captcha_guess');
			sleep(3);
			$this->exts->log("Enter Password and Captcha");
			$this->exts->moveToElementAndType($password_selector, $this->password);
			sleep(1);
			
			$this->exts->capture("2-login-with-captcha-filled");
			$this->exts->moveToElementAndClick('button#signin_button, #signInSubmit-input');
			sleep(5);
			if($this->exts->allExists(['#ap_captcha_img img', 'input#ap_captcha_guess', $password_selector])){
				// Solve captcha if it display on password page
				$this->exts->moveToElementAndType('input#ap_captcha_guess', '');
				$this->exts->processCaptcha('#ap_captcha_img img', 'input#ap_captcha_guess');
				sleep(3);
				$this->exts->log("Enter Password and Captcha");
				$this->exts->moveToElementAndType($password_selector, $this->password);
				sleep(1);
				
				$this->exts->capture("2-login-with-captcha-filled");
				$this->exts->moveToElementAndClick('button#signin_button, #signInSubmit-input');
				sleep(5);
			}
		}
		
		// Solve captcha if it display in a single block page
		$this->checkFillCaptcha();
		$this->checkFillCaptcha();
		$this->checkFillCaptcha();
	} else  {
		$this->exts->log(__FUNCTION__.'::Password page not found');
		$this->exts->capture("2-password-page-not-found");
	}
}
private function checkFillIAMlogin() {
	$this->exts->log(__FUNCTION__);
	$accountkey_selector = 'input#account';
	$username_selector = 'input#username';
	$password_selector = 'input#password';
	$this->exts->capture("2-IAM-login-page");
	
	if($this->exts->getElement('input#account') != null) {
		// DON't call 2FA for account key as confirmation from leader.
		// If user don't input account key, call loginFailed confirm
		// if(trim($this->account_key) == ''){
		// 	$this->exts->two_factor_notif_msg_en = 'User ('.$this->username.') is IAM user. Please enter Account ID (12 digits) or account alias';
		// 	$this->exts->two_factor_notif_msg_de = 'Benutzer ('.$this->username.') ist IAM-Benutzer. Bitte geben Sie die Kontonummer(12 Ziffern) oder Konto-Alias ein';
		// 	$this->account_key = trim($this->exts->fetchTwoFactorCode());
		// 	if(!empty($this->account_key) && $this->account_key != '') {
		// 		$this->exts->log('ACCOUNT KEY from 2FA: ' . $this->account_key);
		// 	}
		// 	$this->exts->two_factor_attempts = 0;
		// }
		$this->exts->log("Enter IAM Acount Key: " . $this->account_key);
		$this->exts->moveToElementAndType($accountkey_selector, '');
		$this->exts->moveToElementAndClick($accountkey_selector);

		$this->exts->moveToElementAndType($accountkey_selector, $this->account_key);
		
		$this->exts->log("Enter IAM Username");
		$this->exts->moveToElementAndType($username_selector, $this->username);
		sleep(1);
		$this->exts->log("Enter IAM Password");
		$this->exts->moveToElementAndType($password_selector, $this->password);
		sleep(1);
		
		$this->exts->capture("2-IAM-login-page-filled");
		$this->exts->moveToElementAndClick('#signin_button');
		sleep(5);
	} else if ($this->exts->exists('input#resolving_input, input#ap_password')) {
		sleep(2);
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType('input#resolving_input', $this->username);
		sleep(1);
		$this->exts->moveToElementAndClick('button#next_button');
		sleep(7);
		
		$this->exts->log("Enter IAM Password");
		$this->exts->moveToElementAndType('input#ap_password', $this->password);
		sleep(1);
		
		$this->exts->capture("2-IAM-login-page-filled");
		$this->exts->moveToElementAndClick('input#signInSubmit-input');
		sleep(5);
	} else {
		$this->exts->log(__FUNCTION__.'::IAM login page not found');
		$this->exts->capture("2-IAMlogin-page-not-found");
	}
}
private function checkFillCaptcha() {
	$this->exts->log(__FUNCTION__);
	// This function solve captcha if captcha is in blocked page
	if($this->exts->exists('div#captcha_container:not([style*="none"]) img#captcha_image') && $this->exts->exists('input#captchaGuess')){
		$this->exts->moveToElementAndType('input#captchaGuess', '');
		$this->exts->processCaptcha('img#captcha_image', 'input#captchaGuess');
		sleep(3);
		$this->exts->moveToElementAndClick('button#submit_captcha');
		sleep(5);
	}
	if($this->exts->exists('input[name="cvf_captcha_input"]')){
		$this->exts->processCaptcha('div.cvf-captcha-img img', 'input[name="cvf_captcha_input"]');
		$this->exts->moveToElementAndClick('input[name="cvf_captcha_captcha_action"]');
		sleep(5);
	}
}
private function checkFillTwoFactor() {
	$this->exts->capture("2.0-two-factor-checking");
	$two_factor_selector = 'input[name="otpCode"], input[name="code"], input[name="tokenCode"], input#mfaCode, input#mfacode';
	$two_factor_message_selector = 'form#auth-mfa-form .a-box-inner > p, form[action="verify"]:not([class*="form-resend"]) .a-row.a-spacing-none, div#ap_signin_authentication_device_section_title h2, #ap_signin_authentication_device_info, #mfa_container span#mfa_display_text, #displayTextMessage p';
	$two_factor_submit_selector = 'input[name="mfaSubmit"], form[action="verify"]:not([class*="form-resend"]) input[type="submit"], input#signInSubmit-input, button#mfa_submit_button, a#submitMfa_button';
	if($this->exts->exists('.show-page #multi_mfa_container [ng-model="mfaOption"]')){
		$this->exts->capture("2.1-2fa-mutil-method");
		if($this->exts->exists('.show-page #multi_mfa_container [ng-model="mfaOption"][value="SWHW"]')){
			$this->exts->moveToElementAndClick('form[action="verify"] #continue');
			sleep(1);	
		} else if($this->exts->exists('.show-page #multi_mfa_container [ng-model="mfaOption"][value="SMS"]')){
			$this->exts->moveToElementAndClick('form[action="verify"] #continue');
			sleep(1);	
		}
		$this->exts->moveToElementAndClick('#remember_mfa_checkbox:not(:checked)');
		$this->exts->capture("2.1-2fa-method-selected");
		$this->exts->moveToElementAndClick('a#next_multi');
		sleep(5);
	}

	if($this->exts->exists('form[action="verify"] #continue')){
		// continue for 2FA
		$this->exts->moveToElementAndClick('form[action="verify"] #continue');
		sleep(5);
	} else if($this->exts->exists('#auth-select-device-form #auth-send-code')){
		$this->exts->capture("2.0-two-factor-methods-selection");
		$this->exts->moveToElementAndClick('#auth-select-device-form #auth-send-code');
		sleep(7);
	}

	if($this->exts->getElement($two_factor_selector) != null){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");
		
		if($this->exts->getElement($two_factor_message_selector) != null){
			$this->exts->two_factor_notif_msg_en = "";
			$total_tfmsg = count($this->exts->getElements($two_factor_message_selector));
			for ($i=0; $i < $total_tfmsg; $i++) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
			}

			if($this->exts->exists('input[name="tokenCode"]') && $this->exts->exists('#ap_signin_email_label, #ap_signin_email')){// add email adress for one particular case
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->extract('#ap_signin_email_label, #ap_signin_email');
			}

			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}
		$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		$this->exts->notification_uid = "";
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
			$this->exts->moveToElementAndClick('input[name="rememberDevice"]');
			sleep(1);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(10);
			$this->exts->capture("2.2-two-factor-submitted-".$this->exts->two_factor_attempts);

			if($this->exts->getElement($two_factor_selector) == null){
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->checkFillTwoFactor();
			} else {
				$this->exts->log("Two factor can not solved");
			}
			
		} else {
			$this->exts->log("Not received two factor code");
		}
	} else if($this->exts->exists('div#tiv-message') && $this->exts->exists('div#tiv-waiting')){
		// 2fa App mobile
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");
		$two_factor_message_selector_app = 'div#tiv-message';
		if($this->exts->getElement($two_factor_message_selector_app) != null){
			$this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector_app);
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" after done';
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . ' Bitte geben Sie "OK" ein, nachdem Sie fertig sind';
			
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}
		$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		$this->exts->notification_uid = "";
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			sleep(15);
			if($this->exts->getElement($two_factor_message_selector_app) == null){
				$this->exts->log("Two factor solved");
			} else {
				$this->exts->log("Two factor can not solved");
			}
		} else {
			$this->exts->log("Not received two factor code");
		}
	} else if($this->exts->exists('[name="transactionApprovalStatus"]')){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");
		$two_factor_message_selector_app = '.a-spacing-large .transaction-approval-word-break, #channelDetails, .transaction-approval-word-break, #channelDetailsWithImprovedLayout';
		if($this->exts->getElement($two_factor_message_selector_app) != null){
			$this->exts->two_factor_notif_msg_en = join(' ', $this->exts->getElementsAttribute($two_factor_message_selector_app, 'innerText'));
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . "\n>> Please input OK after done";
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>> Bitte geben Sie OK ein, nachdem Sie fertig sind";
			
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}
		$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		$this->exts->notification_uid = "";
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			sleep(15);
			if($this->exts->getElement($two_factor_message_selector_app) == null){
				$this->exts->log("Two factor solved");
			} else {
				$this->exts->log("Two factor can not solved");
			}
		} else {
			$this->exts->log("Not received two factor code");
		}
	} else if($this->exts->urlContains('/oauth')){
		sleep(90);
		if($this->exts->exists('u2f-mfa, #U2fKey')){
			$this->exts->no_permission();
		}
	} 
}

private function isLoggedin() {
	return $this->exts->exists('a#aws-console-logout, a[href*="#/bills"]');
}
private function processInvoices() {
	sleep(17);
	$this->exts->capture("4-invoices-old-layout");
	if($this->exts->exists('[data-id="awsccc-cb-btn-accept"]')) {
		$this->exts->moveToElementAndClick('[data-id="awsccc-cb-btn-accept"]');
		sleep(1);
	}
	// $months = $this->exts->getElementsAttribute('#billSelectDate li.awsui-select-option[data-value]', 'data-value');
	$months = $this->exts->getElements('#billSelectDate li.awsui-select-option[data-value]');
	for ($i=0; $i < count($months); $i++) {
		// foreach ($months as $month_timestamp) {
		if($this->exts->exists('#billSelectDate') && $this->exts->exists('#billSelectDate li.awsui-select-option[data-value]')){
			$this->exts->moveToElementAndClick('#billSelectDate');
			sleep(3);
			// $this->exts->moveToElementAndClick('#billSelectDate li.awsui-select-option[data-value="'.$month_timestamp.'"]');
			$months_click = $this->exts->getElements('#billSelectDate li.awsui-select-option[data-value]')[$i];
			try{
				$this->exts->log('Click month button');
				$months_click->click();
			} catch(\Exception $exception){
				$this->exts->log('Click month button by javascript');
				$this->exts->execute_javascript("arguments[0].click()", [$months_click]);
			}
			sleep(10);
			if($this->exts->exists('[ng-controller*="invoiceVm"].bill-summary awsui-expandable-section h3')) {
				$sections = $this->exts->getElements('[ng-controller*="invoiceVm"].bill-summary awsui-expandable-section h3');
			} else {
				$sections = $this->exts->getElements('[ng-controller*="invoiceVm"].bill-summary awsui-expandable-section');
			}
			foreach ($sections as $key => $section) {
				try{
					$this->exts->log('Expanding section');
					$section->click();
				} catch(\Exception $exception){
					$this->exts->execute_javascript("arguments[0].click()", [$section]);
				}
				sleep(2);
			}
			$month_timestamp = $this->exts->extract('#billSelectDate li.awsui-select-option-selected[data-value]', null, 'data-value');
			$this->exts->log('month_timestamp: '.$month_timestamp);
			$month_date = date('Y-m-d', (int)@$month_timestamp/1000);
			
			// By default, only download tax invoice and service invoice
			// If $this->exts->config_array["tax_invoice"] = 1 then download payment invoice as well (download all invoices);
			$row_selector = '[data-testid="marketplace-invoices-section"] [ng-repeat*="invoice in"], #tax-invoices-section [ng-repeat*="invoice in"]';
			if($this->tax_invoice == "1"){
				$row_selector = '[ng-repeat*="invoice in"]';
			}
			$rows = count($this->exts->getElements($row_selector));
			for ($j=0; $j < $rows; $j++) {
				$row = $this->exts->getElements($row_selector)[$j];
				if($this->exts->getElement('button.view-invoice', $row) != null) {
					$this->isNoInvoice = false;
					$download_button = $this->exts->getElement('button.view-invoice', $row);
					$invoiceName = trim($download_button->getAttribute('innerText'));
					$invoiceName = preg_replace("/[^\w]/", '-', trim($invoiceName));
					$invoiceFileName = $invoiceName.'.pdf';
					$invoiceDate = trim($this->exts->extract('.ng-binding.c-xxs-2', $row, 'innerText'));
					if($invoiceDate == '') $invoiceDate = $month_date;
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.currency', $row, 'innerText'))) . ' USD';
					
					$this->exts->log('--------------------------');
					$this->exts->log('invoiceName: '.$invoiceName);
					$this->exts->log('invoiceDate: '.$invoiceDate);
					$this->exts->log('invoiceAmount: '.$invoiceAmount);
					$parsed_date = $this->exts->parse_date($invoiceDate, 'Y-m-d','Y-m-d');
					$this->exts->log('Date parsed: '.$parsed_date);
					
					// Download invoice if it not exisited
					if($this->exts->invoice_exists($invoiceName)){
						$this->exts->log('Invoice existed '.$invoiceFileName);
						continue;
					}
					
					try{
						$this->exts->log('Click download button');
						$download_button->click();
					} catch(\Exception $exception){
						$this->exts->log('Click download button by javascript');
						$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
					}
					sleep(5);
					$this->exts->wait_and_check_download('pdf');
					$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
					
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				}
			}
		}
		if($i%5 == 0 && $this->isNoInvoice){
			// update this to avoid script closed by system
			$this->exts->update_process_lock();
		}
		
		//This is to avoid downloading everytime all the data
		if((int)$this->restrictPages > 0 && $i > 10) {
			break;
		}
	}
}
private function processInvoices_2023() {
	sleep(17);
	$this->exts->moveToElementAndClick('button[role="tab"][data-testid="invoices"]');
	sleep(10);
	$this->exts->capture("4-invoices-new-layout");
	if($this->exts->exists('[data-id="awsccc-cb-btn-accept"]')) {
		$this->exts->moveToElementAndClick('[data-id="awsccc-cb-btn-accept"]');
		sleep(1);
	} else if($this->exts->exists('#awsc-aperture-widget-form-modal-id button[class*="dismiss"]')) {
		$this->exts->moveToElementAndClick('#awsc-aperture-widget-form-modal-id button[class*="dismiss"]');
		sleep(2);
	}
	// Expand the months dropdown for capturing picture in order to check.
	$this->exts->moveToElementAndClick('[data-testid="billing-period-dropdown"] button[aria-haspopup="true"]');
	sleep(2);
	if($this->exts->exists('[data-testid="billing-period-dropdown"] ul[class*="awsui_options-list"] li:last-child [aria-haspopup="true"]')){
		$this->exts->moveToElementAndClick('[data-testid="billing-period-dropdown"] ul[class*="awsui_options-list"] li:last-child [aria-haspopup="true"]');
		sleep(2);
	}
	$this->exts->capture("4-checking-month-dropdown");

	$total_month_back = 5;
	if($this->restrictPages == 0){
		$total_month_back = 12*3;
	}
	for ($m=0; $m <= $total_month_back; $m++) {
		$selected_date = strtotime("-$m months");
		$selected_year = date('Y', $selected_date);
		$selected_month = date('m', $selected_date);
		$this->exts->log('FINDING invoice for: ' . date('F Y', $selected_date));

		// expand month dropdown if it closed
		if(!$this->exts->exists('[data-testid="billing-period-dropdown"] ul[class*="awsui_options-list"] li')){
			$this->exts->moveToElementAndClick('[data-testid="billing-period-dropdown"] button[aria-haspopup="true"]');
			sleep(2);
		}
		// Then finding the selected month
		$subtract_month = (int)$selected_month - 1;
		$amazon_month = str_pad($subtract_month, 2, "0",STR_PAD_LEFT);// amazon use 00 for January instead of 01, 01 is for Feb..
		$selected_month_selector = "[data-testid='billing-period-dropdown'] li[data-testid='$selected_year-$amazon_month']";
		$this->exts->log('selected_month_selector: '.$selected_month_selector);
		$selected_month_element = null;
		if($this->exts->exists($selected_month_selector)){// If found month, get it
			$selected_month_element =  $this->exts->getElement($selected_month_selector);
		} else if($this->exts->exists('[data-testid="billing-period-dropdown"] li[data-testid="year-'.$selected_year.'"]')) {// If month doesn't found, maybe it grouped by year
			$this->exts->moveToElementAndClick('[data-testid="billing-period-dropdown"] li[data-testid="year-'.$selected_year.'"]');
			sleep(2);
			if($this->exts->exists($selected_month_selector)){
				$selected_month_element =  $this->exts->getElement($selected_month_selector);
			}
		}

		if($selected_month_element == null){
			$this->exts->log('NOT found: ' . date('F Y', $selected_date));
			return;
		}

		try {
			$this->exts->log('Choose month from dropdown');
			$selected_month_element->click();
		} catch(\Exception $exception){
			$this->exts->log('Choose month by javascript');
			$this->exts->execute_javascript("arguments[0].click()", [$selected_month_element]);
		}
		sleep(10);
		$this->exts->moveToElementAndClick('button[role="tab"][data-testid="invoices"]');
		sleep(2);

		// By default, only download tax invoice and service invoice
		// If $this->exts->config_array["tax_invoice"] = 1 then download payment invoice as well (download all invoices);
		$row_selector = '[data-testid*="-invoiced-charges"] tbody tr, [data-testid*="tax-invoice"] tbody tr';
		if($this->tax_invoice == "1"){
			$row_selector = '[data-testid*="invoice"] tbody tr';
		}
		$rows = count($this->exts->getElements($row_selector));
		for ($j=0; $j < $rows; $j++) {
			$row = $this->exts->getElements($row_selector)[$j];
			$download_button = $this->exts->getElement('a[role="button"]', $row);
			if($download_button != null) {
				$this->isNoInvoice = false;
				$invoiceName = trim($download_button->getAttribute('innerText'));
				$invoiceName = preg_replace("/[^\w]/", '-', trim($invoiceName));
				$invoiceFileName = $invoiceName.'.pdf';
				$invoiceDate = '';
				
				$this->exts->log('--------------------------');
				$this->exts->log('invoiceName: '.$invoiceName);
				$this->exts->log('invoiceDate: '.$invoiceDate);
				$this->exts->log('invoiceAmount: ');
				
				// Download invoice if it not exisited
				if($this->exts->invoice_exists($invoiceName)){
					$this->exts->log('Invoice existed '.$invoiceFileName);
					continue;
				}
				
				try{
					$this->exts->log('Click download button');
					$download_button->click();
				} catch(\Exception $exception){
					$this->exts->log('Click download button by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
				}
				sleep(5);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
				
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
			}
		}
		

		// sometime it display survey popup while looping invoices, close it
		if($this->exts->exists('#awsc-aperture-widget-form-modal-id button[class*="dismiss"], [role="dialog"][data-testid*="-tooltip-popover"] button[type="submit"]')) {
			$this->exts->moveToElementAndClick('#awsc-aperture-widget-form-modal-id button[class*="dismiss"], [role="dialog"][data-testid*="-tooltip-popover"] button[type="submit"]');
			sleep(2);
		}

		if($m%5 == 0 && $this->isNoInvoice){
			// update this to avoid script closed by system
			$this->exts->update_process_lock();
		}
	}
}