<?php // migrated and updated login code
// Server-Portal-ID: 69939 - Last modified: 30.10.2024 07:04:59 UTC - User: 15

/*Define constants used in script*/
public $baseUrl = "https://dashboard.chatfuel.com";
public $loginUrl = "https://dashboard.chatfuel.com";
public $username_selector = '[name="email"]';
public $password_selector = '[name="pass"]';
public $submit_btn = "#loginbutton";
public $logout_btn = 'a[ng-click="userCtrl.logout()"], .user span.name';
public $login_with_facebook = '0';


public $loginLinkPrim = "li.header-login a[href*=\"fiverr.com/login\"]";
// public $facebook_username_selector = "form#login_form input[name=\"email\"]";
// public $facebook_password_selector = "form#login_form input[name=\"pass\"]";
public $submit_button_selector = "form#login_form input[type=submit]";
public $alt_username_selector = "form input[name=\"email\"]";
public $alt_password_selector = "form input[name=\"pass\"]";
public $alt_submit_button_selector = "form button[name='login'][type=submit]";
public $continue_button_selector = "#continue";
public $logout_link = "#logoutMenu";
public $login_tryout = 0;
public $last_state = array();
public $current_state = array();
public $account_uids = "";

public $user_birthday = ""; // config variable


/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);

    $this->login_with_facebook = isset($this->exts->config_array["login_with_facebook"]) ? (int)trim($this->exts->config_array["login_with_facebook"]) : 0;

    // hardcoded assign value for testing engine
    // $this->login_with_facebook = '1';
	$isCookieLoaded = false;
	if($this->exts->loadCookiesFromFile()) {
		sleep(1);
		$isCookieLoaded = true;
	}
	
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	
	if ($isCookieLoaded) {
		$this->exts->capture("Home-page-with-cookie");
	} else {
		$this->exts->capture("Home-page-without-cookie");
	}
	
	if(!$this->checkLogin()) {
		
		if($this->login_with_facebook == '1') {
            $this->exts->log("Start login with facebook");
            if($this->exts->exists('button[data-testid="facebook-sign-button"]')){
                $this->exts->moveToElementAndClick('button[data-testid="facebook-sign-button"]');
                sleep(5);
            }
            

            $facebook_login_page = $this->exts->findTabMatchedUrl(['facebook']);
            if ($facebook_login_page != null) {

                $this->exts->switchToTab($facebook_login_page);

                $this->loginFacebookIfRequired();
            }else{
                $this->exts->log("facebook login not found"); 
            }
            if ($this->exts->exists('button[data-testid="facebook-sign-button"]')) {
                $this->exts->moveToElementAndClick('button[data-testid="facebook-sign-button"]');
                sleep(5);
            }

			
		} else {
			$this->exts->capture("after-login-clicked");
			if($this->exts->exists($username_selector)){
				$this->fillForm(0);
				$this->checkFillRecaptcha(0);
				sleep(3);
                $this->exts->waitTillPresent("form#platformDialogForm button[data-testid='nextBtn']");
				
				if($this->exts->exists("form#platformDialogForm button[data-testid='nextBtn']")) {
					$this->exts->log(__FUNCTION__ . " First Time FB Login For user ");
					$this->exts->click_by_xdotool("form#platformDialogForm button[data-testid='nextBtn']");
					//$this->exts->account_not_ready();
					//return;
				}
			} else if($this->exts->exists('button[data-testid="login__button"]')){
				$this->exts->moveToElementAndClick('button[data-testid="login__button"]');
				sleep(10);
               
				if($this->exts->urlContains('facebook')){
					$this->loginFacebookIfRequired();
				}
			}
		}
	}
	
	if($this->checkLogin()) {
		$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
		$this->exts->capture("LoginSuccess");
		$this->processAfterLogin(0);
		
	} else {
		$this->exts->capture("LoginFailed");
		$this->exts->loginFailure();
	}
}

/**
 * Method to fill login form
 * @param Integer $count Number of times portal is retried.
 */
function fillForm($count){
	$this->exts->log("Begin fillForm ".$count);
    $this->exts->waitTillPresent($this->username_selector);
	try {
		
		if($this->exts->exists($this->username_selector)) {
			sleep(2);
			$this->exts->capture("1-pre-login");
			
			$this->exts->log("Enter Username");
			$this->moveToElementAndType($this->username_selector, $this->username);
			
			$this->exts->log("Enter Password");
			$this->moveToElementAndType($this->password_selector, $this->password);
			
			$this->moveToElementAndClick($this->submit_btn);
			
		} else if($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]") ) {
			$this->checkFillRecaptcha(0);
			$this->fillForm($count+1);
		}
		
	} catch(\Exception $exception){
		$this->exts->log("Exception filling loginform ".$exception->getMessage());
	}
}

function checkFillRecaptcha($counter) {
	
	if($this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->exts->exists('textarea[name="g-recaptcha-response"]')) {
		
		if($this->exts->exists("div.g-recaptcha")) {
			$data_siteKey = trim($this->exts->getElement("div.g-recaptcha")->getAttribute("data-sitekey"));
		} else {
			$iframeUrl = $this->exts->getElement("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]")->getAttribute("src");
			$tempArr = explode("&k=", $iframeUrl);
			$tempArr = explode("&", $tempArr[count($tempArr)-1]);
			
			$data_siteKey = trim($tempArr[0]);
			$this->exts->log("iframe url  - " . $iframeUrl);
		}
		$this->exts->log("SiteKey - " . $data_siteKey);
		
		$isCaptchaSolved = $this->exts->processRecaptcha($this->loginUrl, $data_siteKey, false);
		$this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
		
		if($isCaptchaSolved) {
			$this->exts->log("isCaptchaSolved");
			$this->exts->execute_javascript(
				"document.querySelector(\"#g-recaptcha-response\").innerHTML = arguments[0];",
				array($this->exts->recaptcha_answer)
			);
			sleep(25);
			$func =  trim($this->exts->getElement("div.g-recaptcha")->getAttribute("data-callback"));
			$this->exts->execute_javascript(
				$func . "('". $this->exts->recaptcha_answer."');"
			);
			sleep(10);
			
		}
		sleep(20);
	}
	
	if($this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->exts->exists('textarea[name="g-recaptcha-response"]')) {
		$counter++;
		sleep(5);
		if($counter < 3) {
			$this->exts->log("Retry reCaptcha");
			$this->checkFillRecaptcha($counter);
		} else {
			$this->exts->capture("LoginFailed");
			$this->exts->loginFailure();
		}
	}
}



/**================================== FACEBOOK LOGIN =================================================**/
public $facebook_loginUrl = 'https://www.facebook.com';
public $facebook_username_selector = 'form#login_form input#email';
public $facebook_password_selector = 'form#login_form input#pass';
public $facebook_remember_me_selector = '';
public $facebook_submit_login_selector = 'form#login_form input[type="submit"], #logginbutton, #loginbutton';
public $check_login_failed_selector = 'div.uiContextualLayerPositioner[data-ownerid="email"], div#error_box, div.uiContextualLayerPositioner[data-ownerid="pass"], input.fileInputUpload';
public $facebook_check_login_success_selector = '#logoutMenu, a[href*="bookmarks"], a[href*="/logout/"], [ng-click="logout()"], a[href*="/account/overview/"], [ng-click="logout()';

/**
 * Entry Method thats identify and click element by element text
 * Because many website use generated html, It did not have good selector structure, indentify element by text is more reliable
 * This function support seaching element by multi language text or regular expression
 * @param String $selector Selector string of element.
 * @param String $multi_language_texts the text label of element that want to click, can input single label, or multi language array or regular expression. Exam: 'invoice', ['invoice', 'rechung'], '/invoice|rechung/i'
 * @param Element $parent_element parent element when we search element inside.
 * @param Bool $is_absolutely_matched tru if want seaching absolutely, false if want to seaching relatively.
 */

private function loginFacebookIfRequired(){
    if($this->exts->urlContains('facebook.')){
        $this->exts->log('Start login with facebook');
        // $this->exts->openUrl($this->facebook_loginUrl);
        sleep(5);

        if($this->exts->exists('div[role="button"][aria-label="Allow"]')){
            $this->exts->log('Click On allow button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Als"] > div"]');
             sleep(10);
        }

       if($this->exts->exists('div[role="button"][aria-label*="Als"] > div, div[role="button"][aria-label*="Continue"] > div')){
            $this->exts->log('Click On Continue button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Als"] > div, div[role="button"][aria-label*="Continue"] > div');
             sleep(5);
        }

        

        $this->checkFillFacebookLogin();
        sleep(10);
        // choose 2FA verification method
        if ($this->exts->exists('button#checkpointSubmitButton')){
            // confirm "choose a verification method"
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(1);
            
            //Approve your login on another computer - 14
            //Log in with your Google account - 35
            //Get a code sent to your email = Receive code by email - 37
            //Get code on the phone - 34
            // Choose send code to phone, if not available, choose send code to email, else choose first option.
            $facebook_verification_method = $this->exts->getElementByText('.uiInputLabelLabel', ['phone', 'telefon', 'telefoon'], null, false);
            if ($facebook_verification_method == null){
                $facebook_verification_method = $this->exts->getElementByText('.uiInputLabelLabel', ['email', 'e-mail', 'e-mailadres'], null, false);
            }
            if($facebook_verification_method != null ) {
                $this->exts->click_by_xdotool($facebook_verification_method);
            }
            else {
                // choose first option.
                $this->exts->moveToElementAndClick('.uiInputLabelLabel');
            }
            
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(2);
            // choose number/email to send code
            $this->exts->moveToElementAndClick('.uiInputLabelInput');
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(2);
            // fill code and continue: captcha_response
            $this->checkFillFacebookTwoFactor();
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(2);
            // fill code and continue: approvals_code
            $this->checkFillFacebookTwoFactor();
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(2);
        }

        //Click "Continue" if required
        $continue_btn = $this->exts->getElementByText('div[role="button"]', ['Continue', 'Fortsetzen', 'Continuer'], null, true);
        if ($continue_btn != null) {
            $this->exts->click_by_xdotool($continue_btn);
            sleep(3);
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Not required facebook login.');
        $this->exts->capture("3-no-facebook-required");
    }
}

private function checkFillFacebookLogin() {
    if($this->exts->querySelector($this->facebook_password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-facebook-login-page");
        
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->facebook_username_selector, $this->username);
        sleep(1);
        
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->facebook_password_selector, $this->password);
        sleep(1);
        
        if($this->facebook_remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->facebook_remember_me_selector);
        sleep(2);
        
        $this->exts->capture("2-facebook-login-page-filled");
        $this->exts->moveToElementAndClick($this->facebook_submit_login_selector);
        sleep(10);

        if($this->exts->exists('div[role="button"][aria-label*="Allow"]')){
             $this->exts->log('Click On allow button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Allow"]');
             sleep(10);
        }

        $checkpoint = $this->exts->urlContains('checkpoint');

        $two_step_verification = $this->exts->urlContains('two_step_verification');

        if ($checkpoint != null || $two_step_verification != null) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
            $this->exts->type_key_by_xdotool('Return');
            sleep(4);
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
            $this->exts->type_key_by_xdotool('Down');
            sleep(1);
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
            $this->exts->type_key_by_xdotool('Return');


            sleep(5);
            $this->checkFillFacebookTwoFactor();

        }

        


        if($this->exts->exists('div[role="button"][aria-label*="Als"], div[role="button"][aria-label*="Continue"]')){
            $this->exts->log('Click On Continue button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Als"], div[role="button"][aria-label*="Continue"]');
             sleep(10);
        }

        if($this->exts->exists('div[role="button"][aria-label*="Als"] > div, div[role="button"][aria-label*="Continue"] > div')){
            $this->exts->log('Click On Continue button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Als"] > div, div[role="button"][aria-label*="Continue"] > div');
             sleep(10);
        }



    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-facebook-login-page-not-found");
    }
}

private function checkFillFacebookTwoFactor() {
    $facebook_two_factor_selector = 'form[method="GET"] input, form.checkpoint[action*="/checkpoint"] input[name*="captcha_response"], form.checkpoint[action*="/checkpoint"] input[name*="approvals_code"], input#recovery_code_entry';
    $facebook_two_factor_message_selector = 'form.checkpoint[action*="/checkpoint"] div strong, form[action*="/recover/code"] h2.uiHeaderTitle"';
    $facebook_two_factor_submit_selector = 'button#checkpointSubmitButton, form[action*="/recover/code"] div.uiInterstitialBar button';
    
    if($this->exts->querySelector($facebook_two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Facebook two factor page found.");
        $this->exts->capture("2.1-facebook-two-factor");
        
        if($this->exts->querySelector($facebook_two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->querySelectorAll($facebook_two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->querySelectorAll($facebook_two_factor_message_selector)[$i]->getText()."\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Facebook Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
        for ($i=0; $i < 2 && empty($facebook_two_factor_code); $i++) {
            $this->exts->capture('Facebook-2FA-not-received'.$i);
            $this->exts->log("Facebook Not received two factor code");
            $this->exts->log("Facebook Requesting re-send two factor code.");
            $this->exts->capture('Facebook-Request-2FA-Again'.$i);
            // Request portal to resend two factor code
            if ($this->exts->exists('#checkpointBottomBar a[role="button"]')){
                $this->exts->moveToElementAndClick('#checkpointBottomBar a[role="button"]');
                sleep(2);
                // click "Didn't get a code href"
                if ($this->exts->getElement('//span[@id="fbLoginApprovalsThrobber"]/preceding-sibling::a', null, 'xpath') != null){
                    $fbLoginApprovalsThrobber = $this->exts->getElement('//span[@id="fbLoginApprovalsThrobber"]/preceding-sibling::a', null, 'xpath');
                    $this->exts->click_by_xdotool($fbLoginApprovalsThrobber);
                    sleep(2);
                    // close modal
                    $this->exts->moveToElementAndClick('a.layerCancel');
                }
            } elseif ($this->exts->exists('input.uiLinkButtonInput[name*="send_code"]')){
                $this->exts->moveToElementAndClick('input.uiLinkButtonInput[name*="send_code"]');
                sleep(2);
            } elseif ($this->exts->exists('a[href*="/recover/initiate"]')) {
                $this->exts->moveToElementAndClick('a[href*="/recover/initiate"]');
                sleep(2);
                // choose first email address to send code
                $this->exts->moveToElementAndClick('form[action*="/ajax/recover/initiate/"] table input');
                // click next/further
                $this->exts->moveToElementAndClick('div.uiInterstitialBar button');
                sleep(2);
            }
            // call API to get code
            $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
        }
        
        if(!empty($facebook_two_factor_code) && trim($facebook_two_factor_code) != '') {
            $this->exts->log("FacebookCheckFillTwoFactor: Entering facebook_two_factor_code.".$facebook_two_factor_code);
            $this->exts->moveToElementAndType($facebook_two_factor_selector, $facebook_two_factor_code);
            
            $this->exts->log("FacebookCheckFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->type_key_by_xdotool('Return');

            $this->exts->capture("2.2-facebook-two-factor-filled-".$this->exts->two_factor_attempts);
            
            $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
            sleep(3);
            $this->exts->capture('2.2-facebook-two-factor-submitted-'.$this->exts->two_factor_attempts);
            
            if($this->exts->querySelector($facebook_two_factor_selector) == null){
                $this->exts->log("Facebook two factor solved");
                // Save device
                if ($this->exts->exists('input[value*="save_device"]')){
                    $this->exts->moveToElementAndClick('input[value*="save_device"]');
                    $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
                }
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillFacebookTwoFactor();
                $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
            } else {
                $this->exts->log("Facebook two factor can not solved.");
            }
        } else{
            $this->exts->log("Facebook failed to fetch two factor code!!!");
        }
        sleep(3);
    }
}

private function isFacebookLoggedin() {
    return $this->exts->exists($this->facebook_check_login_success_selector);
}

private function processAfterFacebookLogin() {
    // then check user logged in or not
    if($this->isFacebookLoggedin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User facebook logged in');
        $this->exts->capture("3-facebook-login-success");
        
        // if(!empty($this->exts->config_array['allow_login_success_request'])) {
        // 	$this->exts->triggerLoginSuccess();
        // }
        // Do the rest of work below (e.g: download invoices...)
        $this->exts->openUrl($this->homePageUrl);
        sleep(10);
        if($this->exts->exists('button[id*="accept-btn"]')) {
            $this->exts->moveToElementAndClick('button[id*="accept-btn"]');
            sleep(2);
        }
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        
        if($this->exts->exists('.mh-message-bar button.mh-close')) {
            $this->exts->moveToElementAndClick('.mh-message-bar button.mh-close');
        }
        
        $this->processAfterLogin();
        
        if($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        
        $this->exts->success();
        
    } else {
        $this->exts->log(__FUNCTION__.'::Use facebook login failed');
        $mesg = strtolower($this->exts->extract('form.checkpoint > div', null, 'innerText'));
        if (strpos($mesg, 'dein konto') !== false && strpos($mesg, 'gesperrt') !== false) {
            $this->exts->account_not_ready();
        }
        if (strpos($mesg, 'account has been temporarily blocked') !== false) {
            $this->exts->account_not_ready();
        }
        if (strpos($this->exts->get_page_content(), 'Dein Konto wurde gesperrt') !== false) {
            $this->exts->account_not_ready();
        }
        if($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } elseif ($this->exts->urlContains('login/device-based/regular/login')){
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
/**==================================END FACEBOOK LOGIN =================================================**/












function checkTwoFactorAuth(){
	$this->exts->log(__FUNCTION__ . " :: Begin");
	$this->checkConsent();
	if($this->checkMultiFactorAuth()) {
		$app_code = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"approvals_code\"]");
		$captcha_response = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"captcha_response\"]");
		$birthday_captcha_day = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"birthday_captcha_day\"]");
		$contact_index = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"contact_index\"]");
		
		
		$checkpoint_url = $this->exts->urlContains("/checkpoint/");
		if($app_code != null || $captcha_response != null || $birthday_captcha_day != null ) {
			$this->processTwoFactorAuth();
		} else if($checkpoint_url) {
			
			if($this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointFooterButton[type=\"submit\"]") != null) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointFooterButton[type=\"submit\"]");
			}
			
			if($this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]") != null) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
			}
			
			sleep(10);
			$ele_one = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] div._5orv:nth-child(1) input[name=\"c\"][value=\"2\"]");
			$ele_two = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] div._5orv:nth-child(2) input[name=\"c\"][value=\"2\"]");
			if($ele_one != null) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] div._5orv:nth-child(1)");
				sleep(5);
			} else if($ele_two != null) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] div._5orv:nth-child(2)");
				sleep(5);
			}
			$this->exts->log(__FUNCTION__ . " :: checkpoint_url found : " . $this->exts->webdriver->getCurrentURL());
			$this->exts->capture("CaptchaCheckAfterClick");
			$ele_one = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"4\"]");
			$temp_one = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"14\"]");
			$temp_two = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"2\"]");
			$temp_three = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][checked=\"1\"]");
			
			if($ele_one != null) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"4\"]");
				// TODO add click with sendeys spacebar if above fails
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
				sleep(5);
				$ele_two = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"send_code\"]");
				$ele_three = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/block/\"] input[name=\"send_code\"]");
				if($ele_two != null) {
					$this->moveToElementAndClick("input[name=\"contact_index\"]", "form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
				} else if($ele_three != null) {
					$this->moveToElementAndClick("input[name=\"contact_index\"]", "form.checkpoint[action*=\"/checkpoint/block/\"] button#checkpointSubmitButton[type=\"submit\"]");
				}
				$this->processTwoFactorAuth();
			} else if($temp_one != null) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"14\"]");
				// TODO add click with sendeys spacebar if above fails
				sleep(1);
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
				sleep(5);
				$this->processTwoFactorAuth();
			} else if($temp_two != null) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"2\"]");
				// TODO add click with sendeys spacebar if above fails
				sleep(1);
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
				sleep(5);
				
			} else if($temp_three != null)  {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
				sleep(5);
				$this->processTwoFactorAuth();
			}else {
				$this->exts->log(__FUNCTION__ . " :: Login Failed 1 " . $this->exts->webdriver->getCurrentURL());
				$this->exts->capture("final-else-block-1");
				$this->exts->loginFailure();
			}
			
			sleep(5);
			$temp_three = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"captcha_response\"]");
			$temp_four = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"birthday_captcha_day\"]");
			if($temp_three != null || $temp_four != null) {
				$this->exts->log(__FUNCTION__ . " :: Found captcha screen finally-- ");
				$this->processTwoFactorAuth();
			}
			
		} else if($contact_index != null) {
			$this->moveToElementAndClick("input[name=\"contact_index\"]", "form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
			$this->processTwoFactorAuth();
		} else {
			$this->exts->log(__FUNCTION__ . " :: Login Failed 2 " . $this->exts->webdriver->getCurrentURL());
			$this->exts->capture("final-else-block-2");
			$this->exts->loginFailure();
		}
	} else if ($this->exts->getElement('#checkpointBottomBar button') != null) {
		$this->exts->log(__FUNCTION__ . " :: Choose a security check " . $this->exts->webdriver->getCurrentURL());
		$this->exts->capture("choose-a-security-check");
		$this->exts->moveToElementAndClick('#checkpointBottomBar button');
		$this->exts->checkTwoFactorAuth();
		
	} else if ( $this->exts->getElement('#error_box') != null) {
		$this->exts->log(__FUNCTION__ . " :: Login Failed(1) 3 " . $this->exts->webdriver->getCurrentURL());
		$this->exts->capture("final-else-block-3");
		$this->exts->loginFailure(1);
		
	} else {
		$this->exts->log(__FUNCTION__ . " :: Login Failed 4 " . $this->exts->webdriver->getCurrentURL());
		$this->exts->capture("final-else-block-4");
		$this->exts->loginFailure();
	}
	
	sleep(5);
	if($this->exts->exists("form button#checkpointSubmitButton[type='submit']")) {
        $this->exts->click_by_xdotool("form button#checkpointSubmitButton[type='submit']");
	}
	if($this->exts->exists("form button#checkpointSubmitButton[type='submit']")) {
        $this->exts->click_by_xdotool("form button#checkpointSubmitButton[type='submit']");
	}
	
	$this->exts->capture("Confirm-Page");
	
	$this->checkConsent();
	
	if($this->exts->getElement("div[data-testid=\"return_to_feed_button\"] button._271k._271m._1qjd") != null) {
		$this->exts->moveToElementAndClick("div[data-testid=\"return_to_feed_button\"] button._271k._271m._1qjd");
		sleep(5);
	}
	sleep(3);
	if($this->checkLogin()) {
		$this->processAfterLogin(0);
	} else {
		$this->exts->log(__FUNCTION__ . " :: Login Failed 4 " . $this->exts->webdriver->getCurrentURL());
		$this->exts->capture("final-" . __FUNCTION__);
		$this->exts->loginFailure();
	}
	
	$this->exts->log(__FUNCTION__ . " :: End");
}



function checkMultiFactorAuth() {
	$this->exts->log(__FUNCTION__ . " :: Begin");
	$isMultiFactorAuth = $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"]");
	if($isMultiFactorAuth != null && $isMultiFactorAuth->isDisplayed()) {
		return true;
	} else {
		return false;
	}
	$this->exts->log(__FUNCTION__ . " :: End");
}



function checkConsent() {
	
	$this->exts->log(__FUNCTION__ . " :: Begin");
	$consent_btn = $this->exts->getElement("div[data-testid=\"parent_approve_consent_button\"] button._271k._271m._1qjd");
	if($this->exts->urlContains("/consent/?") || $consent_btn != null) {
		$this->exts->capture("parent_approve_consent_button");
		$this->exts->moveToElementAndClick("div[data-testid=\"parent_approve_consent_button\"] button._271k._271m._1qjd");
		sleep(10);
	}
	
	if($this->exts->getElement("button[data-testid='return_to_feed_button']") != null) {
		$this->exts->capture("return-to-feed");
		$this->exts->moveToElementAndClick("button[data-testid='return_to_feed_button']");
		sleep(10);
	}
	
	$this->exts->log(__FUNCTION__ . " :: End");
}


function fillBirthdate($birthdate) {
	$this->exts->log(__FUNCTION__ . " Begin ");
	$arr = explode("-", trim($birthdate));
	if(stripos($birthdate, ".") !== false && count($arr) < 3) {
		$arr = explode(".", $birthdate);
	}
	if(count($arr) == 3) {
		$bDay = "document.getElementsByName('birthday_captcha_day')[0].value='".trim($arr[0])."'";
		$this->exts->executeSafeScript($bDay, array());
		
		$bMon = "document.getElementsByName('birthday_captcha_month')[0].value='".trim($arr[1])."'";
		$this->exts->executeSafeScript($bMon, array());
		
		$bYear = "document.getElementsByName('birthday_captcha_year')[0].value='".trim($arr[2])."'";
		$this->exts->executeSafeScript($bYear, array());
		
		sleep(2);
		$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
		sleep(10);
		return true;
	} else {
		$this->exts->log("Birthday length : " . count($arr));
		return false;
	}
}


function processTwoFactorAuth() {
	
	$this->exts->log(__FUNCTION__ . " :: Begin");
	$this->exts->capture("TwoFactorAuth");
	
	$this->exts->two_factor_notif_title_en = "Facebook - Code";
	$this->exts->two_factor_notif_title_de = "Facebook - Code";
	if($this->exts->two_factor_attempts == 2) {
		$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
		$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
	}
	
	if($this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"approvals_code\"]") != null) {
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		$this->exts->log(__FUNCTION__ . ":: 1 Received two_factor_code. " . $two_factor_code);
		if(trim($two_factor_code) != "" && !empty($two_factor_code)) {
			$this->exts->moveToElementAndType("input[name=\"approvals_code\"]", $two_factor_code);
			$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
			sleep(10);
			$save_device_selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"name_action_selected\"][value=\"save_device\"]";
			$selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"approvals_code\"]";
			if($this->exts->getElement($selector) != null && $this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->exts->notification_uid = "";
				$this->processTwoFactorAuth();
			} else if($this->exts->getElement($save_device_selector) != null && $this->exts->two_factor_attempts < 3) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
				sleep(5);
			}
		}
	} else if($this->exts->getElement("form#twofac[action*=\"/security/twofac/enter/?2fac_next=\"] input[name=\"approvals_code\"]") != null) {
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		$this->exts->log(__FUNCTION__ . ":: 1 Received two_factor_code. " . $two_factor_code);
		if(trim($two_factor_code) != "" && !empty($two_factor_code)) {
			$save_device_selector = "form#twofac[action*=\"/security/twofac/enter/?2fac_next=\"] input[name=\"save\"][value=\"yes\"]";
			$this->exts->moveToElementAndType("input[name=\"approvals_code\"]", $two_factor_code);
			if($this->exts->getElement($save_device_selector) != null) {
				$deviceSelector = $this->exts->getElement($save_device_selector);
                $deviceSelector->type_key_by_xdotool('Return');
			}
			$this->exts->moveToElementAndClick("form#twofac[action*=\"/security/twofac/enter/?2fac_next=\"] button[type=\"submit\"]");
			sleep(10);
			$selector = "form#twofac[action*=\"/security/twofac/enter/?2fac_next=\"] input[name=\"approvals_code\"]";
			if($this->exts->getElement($selector) != null && $this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->exts->notification_uid = "";
				$this->processTwoFactorAuth();
			} else if($this->exts->getElement($save_device_selector) != null && $this->exts->two_factor_attempts < 3) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
				sleep(5);
			}
		}
	} else if($this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"captcha_response\"]") != null) {
		$cpText = $this->exts->getElements("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j strong");
		if(count($cpText) > 0) {
			$msgTitle = trim($cpText[0]->getText());
			$this->exts->two_factor_notif_title_en = $msgTitle;
			$this->exts->two_factor_notif_title_de = $msgTitle;
		}
		$cpTextDesc = $this->exts->getElements("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j div");
		if(count($cpTextDesc) > 0) {
			$msgDesc = $cpTextDesc[0]->getText();
			$this->exts->two_factor_notif_msg_en = $msgDesc;
			$this->exts->two_factor_notif_msg_de = $msgDesc;
		}
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		$this->exts->log(__FUNCTION__ . ":: 2 Received two_factor_code : . " . $two_factor_code);
		if(trim($two_factor_code) != "" && !empty($two_factor_code)) {
			$this->exts->moveToElementAndType("input[name=\"captcha_response\"]", $two_factor_code);
			if($this->exts->getElement("input[name=\"captcha_response\"]") != null) {
			   $captchaResponse = $this->exts->getElement("input[name=\"captcha_response\"]");
               $captchaResponse->type_key_by_xdotool('Return');
				sleep(10);
			}
			
			$send_code_selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"send_code\"]";
			$selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"captcha_response\"]";
			if($this->exts->getElement($selector) != null && $this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->exts->notification_uid = "";
				$this->processTwoFactorAuth();
			} else if($this->exts->getElement($send_code_selector) != null && $this->exts->two_factor_attempts < 3) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
				sleep(5);
				$this->exts->two_factor_attempts++;
				$this->exts->notification_uid = "";
				$this->processTwoFactorAuth();
			} else if($this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]") != null) {
				$this->exts->moveToElementAndClick("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
			}
		}
	} else if($this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"birthday_captcha_day\"]") != null) {
		$cpText = $this->exts->getElements("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j strong");
		if(count($cpText) > 0) {
			$msgTitle = "Facebook - " . trim($cpText[0]->getText());
			$this->exts->two_factor_notif_title_en = $msgTitle;
			$this->exts->two_factor_notif_title_de = $msgTitle;
		}
		$cpTextDesc = $this->exts->getElements("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j div");
		if(count($cpTextDesc) > 0) {
			$msgDesc = $cpTextDesc[0]->getText();
			$this->exts->two_factor_notif_msg_en = $msgDesc  . "#br#" . "Date should be in format date-month-year (Example: 1-1-2004)";
			$this->exts->two_factor_notif_msg_de = $msgDesc . "#br#" . "Datum muss im Format Tag-Monat-Jahr sein (zB. 1-1-2004)";
		} else {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "#br#" . "Date should be in format date-month-year (Example: 1-1-2004)";
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . "#br#" . "Datum muss im Format Tag-Monat-Jahr sein (zB. 1-1-2004)";
		}
		
		$isRequestRequired = false;
		if(strlen(trim($this->user_birthday)) > 0) {
			if(!$this->fillBirthdate($this->user_birthday)) {
				$this->exts->log("Birthday length : " . count($this->user_birthday));
				$isRequestRequired = true;
			}
		}
		
		if($isRequestRequired) {
			$two_factor_code = trim($this->exts->fetchTwoFactorCode());
			$this->exts->log(__FUNCTION__ . ":: 3 Received two_factor_code : " . $two_factor_code);
			if(strlen(trim($two_factor_code)) > 0 && $this->fillBirthdate($two_factor_code)) {
				$selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"birthday_captcha_day\"]";
				if($this->exts->getElement($selector) != null && $this->exts->two_factor_attempts < 3) {
					$this->exts->two_factor_attempts++;
					$this->exts->notification_uid = "";
					$this->processTwoFactorAuth();
				}
			} else {
				$this->exts->log(__FUNCTION__ . " : Invalid birthdate received in 2FA, try again");
				$this->exts->capture("invalid-2fa");
			}
		}
	} else if($this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] div._2ph_ ul li._3-8x span") != null && $this->exts->getElement("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointFooterButton[type=\"submit\"]") != null) {
		$cpText = $this->exts->getElements("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j strong");
		if(count($cpText) > 0) {
			$msgTitle = "Facebook - " . trim($cpText[0]->getText());
			$this->exts->two_factor_notif_title_en = $msgTitle;
			$this->exts->two_factor_notif_title_de = $msgTitle;
		}
		$cpTextDesc = $this->exts->getElements("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j div");
		if(count($cpTextDesc) > 0) {
			$msgDesc = $cpTextDesc[0]->getText();
			$this->exts->two_factor_notif_msg_en = $msgDesc  . "#br#" . "Please enter \"OK\" here below afterwards.";
			$this->exts->two_factor_notif_msg_de = $msgDesc . "#br#" . "Gebe danach hier unten \"OK\" ein";
		} else {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "#br#" . "Please enter \"OK\" here below afterwards.";
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . "#br#" . "Gebe danach hier unten \"OK\" ein)";
		}
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		$this->exts->log(__FUNCTION__ . ":: 4 Received two_factor_code : . " . $two_factor_code);
		if(!$this->checkLogin() && $this->exts->two_factor_attempts < 3) {
			$this->exts->two_factor_attempts++;
			$this->exts->notification_uid = "";
			$this->processTwoFactorAuth();
		}
	} else {
		$this->exts->log(__FUNCTION__ . " :: Need --TWO_FACTOR_REQUIRED-- handling here ");
		$this->exts->capture("need_two_factor_handling");
	}
	
	$this->exts->log(__FUNCTION__ . " :: End");
}


/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
function checkLogin() {
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;
	try {
		if($this->exts->exists($this->logout_btn)) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$isLoggedIn = true;
		}
	} catch(\Exception $exception){
		$this->exts->log("Exception checking loggedin ".$exception);
	}
	
	return $isLoggedIn;
}

function processAfterLogin() {
	sleep(5);
	// close any pop up
	$this->exts->moveToElementAndClick('.intercom-modal .intercom-post-close');
	$this->exts->log("Begin processAfterLogin ");
	
	$accounts = $this->exts->getElements(".dashboard-tiles_spacing .test-bot-list-item");
	$total_accounts = count($accounts);
	for($i=0; $i<$total_accounts; $i++) {
        $this->exts->click_by_xdotool($accounts[$i]);
		
		sleep(15);
		$this->exts->moveToElementAndClick('a[href*=settings]');
		sleep(20);
		$this->exts->moveToElementAndClick('a[ng-click="userCtrl.showPaymentsHistoryModal()"]');
		sleep(10);
		$this->downloadInvoice();
		sleep(10);
		$this->exts->openUrl($this->baseUrl);
		sleep(20);
		$accounts = $this->exts->getElements(".dashboard-tiles_spacing .test-bot-list-item");
	}
}

function downloadInvoice(){
	$this->exts->log("Begin download invoice ");
	sleep(5);
	try{
		if($this->exts->exists(".payments__list:not(.payments__list--settings) > li")) {
			$this->exts->capture("2-download-invoice");
			$invoices = array();
			
			$receipts = $this->exts->getElements(".payments__list:not(.payments__list--settings) > li");
			$idx = 0;
			foreach ($receipts as $receipt) {
				$idx++;
				
				try {
					$receiptDate = $receipt->getElement(".payments__item__text")->getText();
					
				} catch(\Exception $exception){
					$receiptDate = null;
				}
				
				if ($receiptDate !== null) {
					
					$receiptDate = trim(explode("\n", $receiptDate)[0]);
					
					$receiptUrl = ".payments__list:not(.payments__list--settings) > li:nth-child(".$idx.") .download_receipt_shape";
					$receiptAmount = $receipt->getElements(".payments__item__text")[1]->getText() . ' USD';
					$receiptAmount = str_replace('$', '', $receiptAmount);
					
					$receiptName = str_replace(' ', '_', $receiptDate);
					$receiptFileName = $receiptName . '.html';
					$this->exts->log($receiptDate);
					$this->exts->log($receiptFileName);
					
					$this->exts->log($receiptUrl);
					$parsed_date = $this->exts->parse_date($receiptDate, 'F d','Y-m-d');
					$this->exts->log($parsed_date);
					
					$invoice = array(
						'receiptName' => $receiptName,
						'parsed_date' => $parsed_date,
						'receiptAmount' => $receiptAmount,
						'receiptFileName' => $receiptFileName,
						'receiptUrl' => $receiptUrl,
					);
					
					array_push($invoices, $invoice);
				}
			}
			
			foreach ($invoices as $invoice) {
				$this->exts->moveToElementAndClick($invoice['receiptUrl']);
				sleep(5);
				$downloaded_file = $this->exts->find_saved_file('html', $invoice['receiptFileName']);
				sleep(2);
				if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
					$this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'] , $invoice['receiptAmount'], $downloaded_file);
					sleep(2);
				}
			};
		} else {
			// Maybe this site changed, get invoice via api
			$invoices = [];
			$invoices = $this->exts->execute_javascript('
		var data = []
		try{
			var token =localStorage["token"];
			var xhr = new XMLHttpRequest();
			xhr.open("POST", "https://dashboard.chatfuel.com/graphql", false);
			xhr.setRequestHeader("bearer", token); xhr.setRequestHeader("content-type", "application/json");
			var param = [{
				operationName: "GET_PAYMENTS_QUERY",
				query: "query GET_PAYMENTS_QUERY {me {id paymentsHistory {id adminName date amount activeUsers last4CardDigits page { id picture title __typename} __typename } __typename }}",
				variables: {}
			}];
			xhr.send(JSON.stringify(param));

			if(xhr.responseText.replace(/\s+/g, "").indexOf(\'"paymentsHistory":[\') > -1){
				var invoices = JSON.parse(xhr.responseText)[0].data.me.paymentsHistory;
				for(var i = 0; i < invoices.length; i++){
					var inv = invoices[i];
					data.push({
						invoiceName: inv.id,
						invoiceDate: (new Date(Number(inv.date))).toISOString().split("T")[0],
						invoiceAmount: inv.amount + " USD",
						url: ""
					});
				}
			}
		} catch(ex){
			console.log(ex);
		}
		return data;
	');
			
			// Download all invoices
			$this->exts->log('Invoices: '.count($invoices));
			$count = 1;
			$totalFiles = count($invoices);
			
			foreach ($invoices as $invoice) {
				$invoiceFileName = $invoice['invoiceName'].'.pdf';
				
				$this->exts->log('date before parse: '.$invoice['invoiceDate']);
				
				$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Y-m-d','Y-m-d');
				$this->exts->log('invoiceName: '.$invoice['invoiceName']);
				$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
				$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
				$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
				
				// Download invoice if it not exisited
				if($this->exts->invoice_exists($invoice['invoiceName'])){
					$this->exts->log('Invoice existed '.$invoiceFileName);
				} else {
					$this->exts->log('Downloading invoice '.$count.'/'.$totalFiles);
					$this->exts->execute_javascript('
				document.write("");
				try{
					var token =localStorage["token"];
					var xhr = new XMLHttpRequest();
					xhr.open("POST", "https://dashboard.chatfuel.com/graphql", false);
					xhr.setRequestHeader("bearer", token);
					xhr.setRequestHeader("content-type", "application/json");
					var param = [{
						operationName: "GET_INVOICE",
						query: "query GET_INVOICE($id: String){invoice(id: $id) {id html __typename}}",
						variables: {id: "'.$invoice['invoiceName'].'"}
					}];
					xhr.send(JSON.stringify(param));
					if(xhr.responseText.replace(/\s+/g, "").indexOf(\'"html":"\') > -1){
						document.write(JSON.parse(xhr.responseText)[0].data.invoice.html);
					}
				} catch(ex){
					console.log(ex);
				}
				document.close();
			');
					
					$downloaded_file = $this->exts->download_current($invoiceFileName, 2);
					
					sleep(2);
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
						sleep(1);
						$count++;
					} else {
						$this->exts->log('Timeout when download '.$invoiceFileName);
					}
				}
			}
		}
	} catch(\Exception $exception){
		$this->exts->log("Exception downloading invoice ".$exception->getMessage());
	}
}