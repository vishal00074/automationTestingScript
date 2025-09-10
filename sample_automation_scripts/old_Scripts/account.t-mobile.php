<?php //updated login code
// Server-Portal-ID: 6414 - Last modified: 20.01.2025 06:31:49 UTC - User: 1

public $baseUrl = "https://account.t-mobile.com";
public $loginUrl = "https://account.t-mobile.com";
public $homePageUrl = "https://my.t-mobile.com/home.html";
public $username_selector = 'input#usernameTextBox'; 
public $password_selector = 'input#passwordTextBox';
public $login_button_selector = 'form[name="passwordForm"]  button[id*="login-btn"]';
public $login_confirm_selector = '#logout-Ok, a[href="logoutUser"], a[href*="account/account-overview"], a#logout_desktop';
public $billingPageUrl = "https://my.t-mobile.com/billing/summary.html";
public $remember_me = "input[name=\"remember_me\"]";
public $submit_button_selector = "input[type='submit'],button#lp1-next-btn,button#lp2-login-btn";
public $dropdown_selector = "#img_DropDownIcon";
public $dropdown_item_selector = "#di_billCycleDropDown";
public $more_bill_selector = ".view-more-bills-btn";
public $login_tryout = 0;
public $isNoInvoice = true;
public $check_login_failed_selector = 'p#page-level-id > span';




/** 	
	* Entry Method thats called for a portal
	* @param Integer $count Number of times portal is retried.
	*/
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	$this->exts->loadCookiesFromFile();

	$this->exts->openUrl($this->loginUrl);
	sleep(5);
	$this->exts->capture_by_chromedevtool("Home-page-without-cookie");
	
	$isCookieLoginSuccess = false;
	if($this->checkLogin()) {
		$isCookieLoginSuccess = true;
	} else {
		$this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
		sleep(5);
	}

	if(!$isCookieLoginSuccess) {
		$this->exts->capture_by_chromedevtool("after-login-clicked");

		$this->exts->waitTillPresent($this->username_selector, 5);
		$this->fillForm(0);
		sleep(5);
		$this->checkFillTwoFactor();

		$tryTime = 0;
		while ($this->exts->urlContains('sap/v2/error') && $tryTime < 4) {
			sleep(1);
			$tryTime++;
			$this->exts->openUrl($this->loginUrl);
			sleep(5);
			$this->fillForm(0);
		}

		if ($this->exts->exists('[ng-disabled="passwordBannerController.errorFromServer"]')) {
			$this->exts->account_not_ready();
		} else if ($this->exts->exists('.verification-alert-message .icon-error, div.server-error-description span.alert-message')) { 
			$this->exts->loginFailure(1);	
		}
		
		if ($this->exts->exists('input[value="sms"]')) {
			$this->exts->click_by_xdotool('input[value="sms"]:not(:checked)');
			sleep(2);

			$this->exts->click_by_xdotool('button.continue-btn');
			sleep(15);

			if ($this->exts->exists('input[name="device"]')) {
				$el = $this->exts->getElements('input[name="device"]')[0];
				$el_value = $el->getAttribute('value');
				$this->exts->click_by_xdotool('input[value="' . $el_value . '"]:not(:checked)');
				sleep(2);

				$this->exts->moveToElementAndClick('button.continue-btn');
				sleep(15);
			}

			$this->checkFillTwoFactor();
		} else if ($this->exts->exists('input[value="security_question"]')) {
			$this->exts->click_by_xdotool('input[value="security_question"]:not(:checked)');
			sleep(2);

			$this->exts->click_by_xdotool('button.continue-btn');
			sleep(15);

			$this->checkFillSecurityQuestions();
		}

		if ($this->exts->exists('form#choose-method-page button[type="submit"]')) {
			$this->exts->click_by_xdotool('form#choose-method-page button[type="submit"]');
			sleep(5);
			$this->checkFillTwoFactor();
		}

		if($this->checkLogin()) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$this->exts->capture("LoginSuccess");
			$this->downloadInvoice();
			// Final, check no invoice
			if($this->isNoInvoice){
				$this->exts->no_invoice();
			}
			$this->exts->success();
		} else {
			if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), "The login information you provided is incorrect. Please try again.") !== false) {
				$this->exts->log("Wrong credential !!!!");
				$this->exts->loginFailure(1);
			}
			$this->exts->capture("LoginFailed");
			if ($this->exts->getElement('[ng-disabled="passwordBannerController.errorFromServer"]') !== null) {
				$this->exts->account_not_ready();
			} else if ($this->exts->getElement('.verification-alert-message .icon-error, div.server-error-description span.alert-message')!== null) { 
				$this->exts->loginFailure(1);	
			} else {
				$this->exts->loginFailure();	
			}
		}
	} else {
		$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
		$this->exts->capture("LoginSuccess");
		$this->downloadInvoice();
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	}
}

function fillForms($count){
	$this->exts->log("Begin fillForm ".$count);
	try {
		
		if( $this->exts->exists($this->username_selector) != null) {
			sleep(2);

			$this->exts->log("Enter Username");
			
			$this->exts->type_key_by_xdotool("Delete");
			$this->exts->moveToElementAndType($this->username_selector,$this->username);
			sleep(1);
			$this->exts->type_key_by_xdotool("Return");
			sleep(5);
			
			if ($this->exts->exists($this->username_selector)) {
				$this->exts->click_by_xdotool('a.langLink');
				sleep(5);

				$this->exts->log("Enter Username");

				$this->exts->type_key_by_xdotool("Delete");
				$this->exts->moveToElementAndType($this->username_selector,$this->username);
				sleep(1);

				$this->exts->capture('filled username');
				$this->exts->log('filled username');
				sleep(3);

				$this->exts->type_key_by_xdotool("Return");
				sleep(5);
			}

			if ($this->exts->exists('div.server-error-description span.alert-message')) {
				$this->exts->loginFailure(1);
			}

			$this->exts->log("Enter Password");
	
			sleep(1);
			$this->exts->type_key_by_xdotool("Delete");
			$this->exts->moveToElementAndType($this->password_selector,$this->password);
			sleep(1);

			$this->exts->capture('filled password');
			$this->exts->log('filled password');
			sleep(3);

			$this->exts->type_key_by_xdotool("Return");
			sleep(10);
		
		}
	} catch(\Exception $exception){
		$this->exts->log("Exception filling loginform ".$exception->getMessage());
	}
}

function fillForm($count)
{
	$this->exts->log("Begin fillForm " . $count);
	$this->exts->waitTillPresent($this->username_selector, 5);
	try {
		if ($this->exts->querySelector($this->username_selector) != null) {

			$this->exts->capture("1-pre-login");
			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType($this->username_selector, $this->username);
			sleep(2);

			if ($this->exts->exists($this->submit_button_selector)) {
				$this->exts->click_by_xdotool($this->submit_button_selector);
			}
			sleep(5);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType($this->password_selector, $this->password);
			
			$this->exts->capture("1-login-page-filled");
			sleep(5);
			if ($this->exts->exists($this->submit_button_selector)) {
				$this->exts->click_by_xdotool($this->submit_button_selector);
			}
		}
	} catch (\Exception $exception) {

		$this->exts->log("Exception filling loginform " . $exception->getMessage());
	}
}

// private function checkFillTwoFactor() {
// 	$two_factor_selector = 'input#confirmcode, input#code';
// 	$two_factor_message_selector = 'div.secondFactorSms_ui_body, h2.verify-security';
// 	$two_factor_submit_selector = 'button[ng-click*="secondFactorSmsVerificationController"][ng-click*="validateOneTimePin"], button.continue-action-btn';

// 	if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
// 		$this->exts->log("Two factor page found.");
// 		$this->exts->capture("2.1-two-factor");

// 		if($this->exts->getElement($two_factor_message_selector) != null){
// 			$this->exts->two_factor_notif_msg_en = "";
// 			for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
// 				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
// 			}
// 			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
// 			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
// 			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
// 		}
// 		if($this->exts->two_factor_attempts == 2) {
// 			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
// 			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
// 		}

// 		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
// 		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
// 			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
// 			$this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);
			
// 			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
// 			sleep(3);
// 			$this->exts->moveToElementAndClick('input#remember-this-device-input');
// 			sleep(1);
// 			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

// 			$this->exts->moveToElementAndClick($two_factor_submit_selector);
// 			sleep(15);

// 			if($this->exts->getElement($two_factor_selector) == null){
// 				$this->exts->log("Two factor solved");
// 			} else if ($this->exts->two_factor_attempts < 3) {
// 				$this->exts->two_factor_attempts++;
// 				$this->checkFillTwoFactor();
// 			} else {
// 				$this->exts->log("Two factor can not solved");
// 			}

// 		} else {
// 			$this->exts->log("Not received two factor code");
// 		}
// 	}
// }


private function checkFillTwoFactor(){
    $two_factor_selector = 'input#confirmcode, input#code';
	$two_factor_message_selector = 'div.secondFactorSms_ui_body, h2.verify-security';
	$two_factor_submit_selector = 'button[ng-click*="secondFactorSmsVerificationController"][ng-click*="validateOneTimePin"], button.continue-action-btn';
   

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        $this->exts->notification_uid = '';

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->getElement('div.private-checkbox.remember-device input') != null) {
                $this->exts->moveToElementAndClick('div.private-checkbox.remember-device input');
                sleep(2);
            }
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);
            if ($this->exts->exists('button[data-2fa-rememberme="true"]')) {
                $this->exts->moveToElementAndClick('button[data-2fa-rememberme="true"]');
                sleep(15);
            }
            if ($this->exts->getElement($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    } else if ($this->exts->getElement($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
        // $two_factor_message_selector = 'form[action*="/2fa/email/"] > p';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");


        $this->exts->two_factor_notif_msg_en = "";
        for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
        }
        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . 'Please input "OK" after Tap "Yes, it\'s me" on your mobile!';
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . 'Please input "OK" after Tap "Yes, it\'s me" on your mobile!';;
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->notification_uid = '';
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            sleep(15);
            if ($this->exts->exists('button[data-2fa-rememberme="true"]')) {
                $this->exts->moveToElementAndClick('button[data-2fa-rememberme="true"]');
                sleep(15);
            }
            if ($this->exts->getElement($two_factor_message_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                if ($this->exts->exists('[data-key="login.twoFactor.errors.hubspotAppDenied.button"]')) {
                    $this->exts->moveToElementAndClick('[data-key="login.twoFactor.errors.hubspotAppDenied.button"]');
                    sleep(3);
                }
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

private function checkFillSecurityQuestions() {
	$answer_els = $this->exts->getElements('div[ng-repeat*="securityQuestions"]');
	$two_factor_submit_selector = 'button[ng-click*="validateSecurityAnswers"]';
	$count_fill_answer = 0;
	foreach ($answer_els as $answer_el) {
		$two_factor_selector_el = $this->exts->getElement('input', $answer_el);
		if($two_factor_selector_el != null && $this->exts->two_factor_attempts < 3){
			$this->exts->log("Two factor page found.");
			$this->exts->capture("2.1-two-factor");

			if($this->exts->getElement('div.ui_body', $answer_el) != null){
				$this->exts->two_factor_notif_msg_en = "";
				for ($i=0; $i < count($this->exts->getElements('div.ui_body', $answer_el)); $i++) { 
					$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements('div.ui_body', $answer_el)[$i]->getText()."\n";
				}
				$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
				$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
				$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
			}
			if($this->exts->two_factor_attempts == 2) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
				$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
			}

			$two_factor_code = trim($this->exts->fetchTwoFactorCode());
			if(!empty($two_factor_code) && trim($two_factor_code) != '') {
				$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
				$two_factor_selector_el->sendKeys($two_factor_code);
				$count_fill_answer += 1;
			} else {
				$this->exts->log("Not received two factor code");
			}
		}
	}

	if (count($answer_els) == $count_fill_answer) {
		$this->exts->log("checkFillTwoFactor: Clicking submit button.");
		sleep(3);
		$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

		$this->exts->moveToElementAndClick($two_factor_submit_selector);
		sleep(15);

		if($this->exts->getElement('div[ng-repeat*="securityQuestions"] input') == null){
			$this->exts->log("Two factor solved");
		} else if ($this->exts->two_factor_attempts < 3) {
			$this->exts->two_factor_attempts++;
			$this->checkFillTwoFactor();
		} else {
			$this->exts->log("Two factor can not solved");
		}
	}
	
}

/**
	* Method to Check where user is logged in or not
	* return boolean true/false
	*/
function checkLogin() {
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;
	try {
		if($this->exts->exists($this->login_confirm_selector)&& !$this->exts->exists('input#confirmcode')) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$isLoggedIn = true;
			
		}
	} catch(\Exception $exception){
		$this->exts->log("Exception checking loggedin ".$exception);
	}
	return $isLoggedIn;
}

/**
	*method to download incoice
	*/

function downloadInvoice(){
	$this->exts->log("Begin downlaod invoice ");
	
	sleep(10);
	if($this->exts->exists('.ui_billing')){
		try{
			if( $this->exts->getElement(".ui_billing") != null ) {
				$this->exts->getElement(".ui_billing")->click();
				sleep(15);
			}
			if( $this->exts->getElement($this->dropdown_selector) != null ) {
				$this->exts->getElement($this->dropdown_selector)->click();
				sleep(15);
			}
			if( $this->exts->getElement($this->dropdown_item_selector) != null ) {
				$dropdown_items = $this->exts->getElement($this->dropdown_item_selector)->findElements(WebDriverBy::tagName('li'));
				$this->exts->log("No of Months:".count($dropdown_items));
				for($i = 1; $i <= count($dropdown_items); $i++){
					try{
						$this->isNoInvoice = false;
						$receiptDateInit =$this->exts->getElement($this->dropdown_item_selector)->findElements(WebDriverBy::tagName('li'))[$i-1]->getText();
						$receiptDate = substr($receiptDateInit,strpos($receiptDateInit,"-")+ 1);
						$this->exts->log($receiptDate);
						$parsed_date = $this->exts->parse_date($receiptDate);
						$this->exts->log("Parsed Date:".$parsed_date);
						$this->exts->webdriver->executeScript("document.getElementById('di_billCycleDropDown').getElementsByTagName('li')[".($i-1)."].click();");
						sleep(30);
						$download_button_selector = "#summaryBill";
						$downloaded_file = $this->exts->click_and_download($download_button_selector,"pdf",$receiptDateInit.'.pdf',"CSS",40);
						sleep(2);
						if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
							$this->exts->new_invoice('', $parsed_date, '', $downloaded_file);
							sleep(1);
						}
						sleep(15);
						$this->exts->webdriver->executeScript("document.getElementById('img_DropDownIcon').click();");	sleep(5);

					}catch(\Exception $exception){
						$this->exts->log("Exception downlaoding invoice ".$exception->getMessage());
					}
					
				}
				sleep(5);
			}
		}catch(\Exception $exception){
			$this->exts->log("Exception downlaoding invoice ".$exception->getMessage());
		}
	} else if($this->exts->exists('a[href*="billandpay"]')) {
		$this->exts->moveToElementAndClick('a[href*="billandpay"]');
		sleep(15);
		$this->exts->moveToElementAndClick('a.bb-historical-bills-modal-link');
		sleep(15);
		$rows = count($this->exts->getElements('div.bb-past-bill-row'));
		for ($i=0; $i < $rows; $i++) {
			$row = $this->exts->getElements('div.bb-past-bill-row')[$i];
			if($this->exts->getElement('.bb-download-past-summary-bill button.past-bill-link', $row) != null) {
				$this->isNoInvoice = false;
				$download_button = $this->exts->getElement('.bb-download-past-summary-bill button.past-bill-link', $row);
				$invoiceName = trim($this->exts->extract('.bb-charge-summary:not(.d-block) .bb-past-bills-cycle', $row, 'innerText'));
				$invoiceName = str_replace(' ', '-', str_replace(',', '', $invoiceName));
				$invoiceFileName = $invoiceName.'.pdf';
				$invoiceDate = trim($this->exts->extract('.bb-charge-summary:not(.d-block) .bb-past-bills-cycle', $row, 'innerText'));
				$invoiceAmount = trim($this->exts->extract('.bb-charge-summary:not(.d-block) .bb-past-bills-amount', $row, 'innerText'));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' USD';

				$this->exts->log('--------------------------');
				$this->exts->log('invoiceName: '.$invoiceName);
				$this->exts->log('invoiceDate: '.$invoiceDate);
				$this->exts->log('invoiceAmount: '.$invoiceAmount);
				$parsed_date = $this->exts->parse_date($invoiceDate, 'F d, Y','Y-m-d');
				$this->exts->log('Date parsed: '.$parsed_date);

				// Download invoice if it not exisited
				if($this->exts->invoice_exists($invoiceName)){
					$this->exts->log('Invoice existed '.$invoiceFileName);
				} else {
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
						$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				}
			}
		}
	} else if($this->exts->exists('#view_billing_details')){
		$this->exts->moveToElementAndClick('#view_billing_details');
		sleep(10);
		$this->downloadInvoiceTfb();
	}
}

private function downloadInvoiceTfb() {
	sleep(25);
	
	$this->exts->capture("4-invoices-page-tfb");
	$invoices = [];

	$rows = count($this->exts->getElements('div.statement_row'));
	for ($i=0; $i < $rows; $i++) {
		$row = $this->exts->getElements('div.statement_row')[$i];
		$tags = $this->exts->getElements('div[class*="col"]', $row);
		if(count($tags) >= 5 && $this->exts->getElement('div.dwnloadIcn img.export_img', $row) != null) {
			$this->isNoInvoice = false;
			$download_button = $this->exts->getElement('div.dwnloadIcn img.export_img', $row);
			$invoiceName = str_replace(' ', '', $tags[0]->getAttribute('innerText'));
			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = trim($tags[0]->getAttribute('innerText'));
			$invoiceDate = trim(end(explode('-', $invoiceDate)));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' USD';

			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);
			$parsed_date = $this->exts->parse_date($invoiceDate, 'M d, Y','Y-m-d');
			$this->exts->log('Date parsed: '.$parsed_date);

			// Download invoice if it not exisited
			if($this->exts->invoice_exists($invoiceName)){
				$this->exts->log('Invoice existed '.$invoiceFileName);
			} else {
				try{
					$this->exts->log('Click download button');
					$download_button->click();
				} catch(\Exception $exception){
					$this->exts->log('Click download button by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
				}
				sleep(5);
				$this->exts->moveToElementAndClick('button#downloadPDFbtn');
				sleep(5);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
			}

			
		}

	}
}