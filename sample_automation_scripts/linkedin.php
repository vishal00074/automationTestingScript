<?php
// Server-Portal-ID: 331 - Last modified: 10.12.2024 14:55:53 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.linkedin.com';
public $loginUrl = 'https://www.linkedin.com/login?fromSignIn=true&trk=guest_homepage-basic_nav-header-signin';
public $username_selector = 'input[name="session_key"][type="text"]';
public $password_selector = 'input[name="session_password"]';
public $submit_login_selector = 'button[data-litms-control-urn="login-submit"]';
public $check_login_failed_selector = '#error-for-password a[href*="/request-password-reset"], #error-for-username:not(.hidden), #error-for-password:not(.hidden)';
public $check_login_success_selector = 'a[href*="/logout"], li#profile-nav-item button#nav-settings__dropdown-trigger, #notifications-nav-item a, .global-nav__me .artdeco-dropdown__trigger';

public $isNoInvoice = true;
public $restrictPages = 3;
public $login_with_google = 0;
public $company_detail = '';
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)$this->exts->config_array["login_with_google"] : $this->login_with_google;
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->company_detail = isset($this->exts->config_array["company_detail"]) ? trim($this->exts->config_array["company_detail"]) : '';
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(15);
    if($this->exts->exists('[type="COOKIE_CONSENT"] [action-type="ACCEPT"]')){
        $this->exts->moveToElementAndClick('[type="COOKIE_CONSENT"] [action-type="ACCEPT"]');
        sleep(3);
    }
    $this->exts->capture('1-init-page');
    
    
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        if($this->login_with_google == 1){
            $this->exts->openUrl('https://www.linkedin.com/checkpoint/lg/sign-in-another-account');
            sleep(15);
            if($this->exts->exists('button#sign-in-with-google-button')){
                $this->exts->moveToElementAndClick('button#sign-in-with-google-button');
            } else {
                $this->exts->moveToElementAndClick('div.alternate-signin__btn--google');
            }
            sleep(5);
            $this->loginGoogleIfRequired();
        } else {
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            $this->checkFillRecaptcha();
            $this->checkFillRecaptcha();
            if($this->exts->exists('div[class*="member-profile__details"]')){
                $this->exts->log('****************CLICK ACCOUNT LOGIN WITH COOKIE*****************');
                $this->exts->moveToElementAndClick('div[class*="member-profile__details"]');
                sleep(10);
            }
            if($this->exts->exists('[type="COOKIE_CONSENT"] [action-type="ACCEPT"]')){
                $this->exts->moveToElementAndClick('[type="COOKIE_CONSENT"] [action-type="ACCEPT"]');
                sleep(3);
            }
            $this->checkFillLogin();
            sleep(10);

            

            if($this->checkFillRecaptcha() && $this->exts->exists('iframe.iframe--authentication')){
                $this->switchToFrame('iframe.iframe--authentication');
                if(!$this->exts->exists($this->check_login_failed_selector)){
                    // If after login submit, recaptcha showed.
                    // Solve it and check fill login again
                    $this->checkFillLogin();
                }
            }
            
            $this->checkTwoFactorAuth(1);
            
            
            // Some time this site showed a form to confirm email and phone number, just simple click DONE
            if($this->exts->exists('.cp-manage-account .cp-challenge #password-prompt-wrapper button')){
                $this->exts->moveToElementAndClick('.cp-manage-account .cp-challenge #password-prompt-wrapper button');
                sleep(10);
            } else if($this->exts->urlContainsAny(['/check/add', '/check/manage']) && $this->exts->exists('.cp-challenge .cp-actions button[class*="secondary-action"][type="button"]')){
                // Click SKIP button
                $this->exts->moveToElementAndClick('.cp-challenge .cp-actions button[class*="secondary-action"][type="button"]');
                sleep(10);
            }
            if($this->exts->exists('[role="main"] form#remember-me-prompt__form-primary [data-cie-control-urn="checkpoint_remember_me_save_info_yes"]')){
                $this->exts->moveToElementAndClick('[role="main"] form#remember-me-prompt__form-primary [data-cie-control-urn="checkpoint_remember_me_save_info_yes"]');
                sleep(10);
            }
            if($this->exts->urlContainsAny(['linkedin.com/check/bounced-email']) && $this->exts->exists('.cp-bounced-email button.secondary-action-new')){
                // Click SKIP button
                $this->exts->moveToElementAndClick('.cp-bounced-email button.secondary-action-new');
                sleep(10);
            }
        }
    }
    
    // then check user logged in or not
    if($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        if($this->exts->exists('[type="COOKIE_CONSENT"] [action-type="ACCEPT"]')){
            $this->exts->moveToElementAndClick('[type="COOKIE_CONSENT"] [action-type="ACCEPT"]');
            sleep(3);
        }
        $this->exts->capture("3-login-success");
        
        
        // Open invoices url and download invoice
        $this->exts->openUrl('https://www.linkedin.com/payments/purchasehistory?trk=');
        sleep(10);

        $this->exts->execute_javascript('let selectBox = document.querySelector("select#customDateOption-purchaseHistoryForm");
                                            selectBox.value = "custom";
                                            selectBox.dispatchEvent(new Event("change"));');

        $this->processInvoices();
        $this->exts->openUrl('https://www.linkedin.com/manage/purchases-payments/transactions');
        sleep(10);
        $this->processInvoicestransactions();
        // Final, check no invoice
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed: ' . $this->exts->getUrl());
        if($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if($this->exts->exists('[name="pageKey"][content="d_checkpoint_lg_accountRestricted"]')){
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin() {
    if($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");
        if($this->exts->exists($this->username_selector)){
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(4);
        }
        
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(4);
        
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);

      
    
        
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        if($funcaptcha_displayed){
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        }

        if($funcaptcha_displayed){
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
        }
        $this->cookiePopup();
        
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function cookiePopup()
{
    if($this->exts->exists('[type="COOKIE_CONSENT"] [action-type="ACCEPT"]')){
        $this->exts->log('COOKIE CONSENT Page found');
        $this->exts->moveToElementAndClick('[type="COOKIE_CONSENT"] [action-type="ACCEPT"]');
        sleep(3);
    }
}

private function checkAndSolveFunCaptcha() {
    $this->exts->log("Begin solving fun captcha" . $count);
    $input = "input[name='fc-token']";
    $formInput=  'input[name="captchaUserResponseToken"]';
    $url = $this->exts->getUrl();
    if ($this->exts->exists("iframe#captcha-internal")){
        $this->switchToFrame("iframe#captcha-internal");
        if ($this->exts->exists("iframe#arkoseframe")){
            $this->switchToFrame("iframe#arkoseframe");
            sleep(1);
        }
        if ($this->exts->exists('iframe[data-e2e="enforcement-frame"]')){
            $this->switchToFrame('iframe[data-e2e="enforcement-frame"]');
            sleep(1);
        }
        if ($this->exts->exists('#fc-iframe-wrap')){
            $this->switchToFrame('#fc-iframe-wrap');
            sleep(1);
        }

        if ($this->exts->exists('#CaptchaFrame')){
            $this->switchToFrame('#CaptchaFrame');
            // Click button to show images challenge
            if($this->exts->exists('#home_children_button')){
                $this->exts->moveToElementAndClick('#home_children_button');
                sleep(7);
            }
        }
        $this->exts->switchToDefault();
        sleep(1);
    }
    if($this->exts->exists('iframe.iframe--authentication')){
        $this->switchToFrame('iframe.iframe--authentication');
    }
    if ($this->exts->exists('iframe#captcha-internal')) {
        $this->switchToFrame('iframe#captcha-internal');
        sleep(3);
        if ($this->exts->exists('iframe#arkoseframe')){
            $this->switchToFrame('iframe#arkoseframe');
        }
        if ($this->exts->exists('iframe[data-e2e="enforcement-frame"]')){
            $this->switchToFrame('iframe[data-e2e="enforcement-frame"]');
            sleep(1);
        }
        sleep(3);
        if($this->exts->exists($input)){
            $value = $this->exts->getElement($input)->getAttribute("value");
            $this->exts->log("value " . $value);
            $params = explode("|", $value);
            
            $pkKey = null;
            $surl = null;
            
            foreach ($params as $i => $param) {
                if (strpos($param, "pk=") === 0)
                    $pkKey = explode("=", $param)[1];
                else if (strpos($param, "surl=") === 0)
                    $surl = explode("=", $param)[1];
            }
            
            $this->exts->log("Found value pk-key " . $pkKey . " and surl " . $surl);
            $token = $this->exts->processFunCaptcha(null, null, $pkKey, 'https://linkedin-api.arkoselabs.com', $url, false);
            if($token == null){
                $token = $this->exts->processFunCaptcha(null, null, $pkKey, 'https://linkedin-api.arkoselabs.com', $url, false);
            }
            $this->exts->switchToDefault();
            sleep(1);
            if($this->exts->exists('iframe.iframe--authentication')){
                $this->switchToFrame('iframe.iframe--authentication');
            }
            $this->exts->moveToElementAndType($formInput, $token);
            $this->exts->execute_javascript('document.querySelector("form#captcha-challenge").submit();');
            sleep(15);
            return true;
        }
    } else {
        $this->exts->log("No captcha found, continue process");
    }
    $this->exts->switchToDefault();
    return false;
}

private function processFunCaptchaByClicking() {
    $this->exts->log("Checking Funcaptcha");
    $language_code = $this->exts->extract('[data-locale]', null, "data-locale");
    // if($this->exts->exists('div.funcaptcha-modal:not([class*="hidden"])')) {
    // 	$this->exts->capture("funcaptcha");
    // 	// Captcha located in multi layer frame, go inside
    // 	if ($this->exts->exists('.funcaptcha-frame')){
    // 		$this->switchToFrame('.funcaptcha-frame');
    // 	}
    if ($this->exts->exists('iframe.iframe--authentication')) {
        $this->switchToFrame('iframe.iframe--authentication');
        sleep(1);
    }
    if ($this->exts->exists("iframe#captcha-internal")){
        $this->switchToFrame("iframe#captcha-internal");
        if ($this->exts->exists("iframe#arkoseframe")){
            $this->switchToFrame("iframe#arkoseframe");
            sleep(1);
        }
        if ($this->exts->exists('iframe[data-e2e="enforcement-frame"]')){
            $this->switchToFrame('iframe[data-e2e="enforcement-frame"]');
            sleep(1);
        }else{
            $this->exts->log("funcaptcha without content - reload iframe");
            $this->exts->switchToDefault();
            if ($this->exts->exists('iframe.iframe--authentication')) {
                $this->switchToFrame('iframe.iframe--authentication');
                sleep(1);
            }
            if ($this->exts->exists("iframe#captcha-internal")){
                $this->switchToFrame("iframe#captcha-internal");
            }
            // reload frame to get content
            $javascript_expression = '
                var captcha_iframe = document.querySelector("iframe#arkoseframe");
                captcha_iframe.src = captcha_iframe.src;
            ';
            $devTools->execute(
                'Runtime.evaluate',
                ['expression' => $javascript_expression]
            );
            sleep(10);
            $this->exts->switchToDefault();
            if ($this->exts->exists('iframe.iframe--authentication')) {
                $this->switchToFrame('iframe.iframe--authentication');
                sleep(1);
            }
            if ($this->exts->exists("iframe#captcha-internal")){
                $this->switchToFrame("iframe#captcha-internal");
            }
            if ($this->exts->exists("iframe#arkoseframe")){
                $this->switchToFrame("iframe#arkoseframe");
                sleep(1);
            }
            if ($this->exts->exists('iframe[data-e2e="enforcement-frame"]')){
                $this->switchToFrame('iframe[data-e2e="enforcement-frame"]');
                sleep(1);
            }
        }
        if ($this->exts->exists('#fc-iframe-wrap')){
            $this->switchToFrame('#fc-iframe-wrap');
            sleep(1);
        }

        if ($this->exts->exists('#CaptchaFrame')){
            $this->switchToFrame('#CaptchaFrame');
            // Click button to show images challenge
            if($this->exts->exists('#home_children_button')){
                $this->exts->moveToElementAndClick('#home_children_button');
                sleep(2);
            } else if($this->exts->exists('#wrong_children_button')){
                $this->exts->moveToElementAndClick('#wrong_children_button');
                sleep(5);
                $this->exts->switchToDefault();
                if ($this->exts->exists('iframe.iframe--authentication')) {
                    $this->switchToFrame('iframe.iframe--authentication');
                    sleep(1);
                }
                if ($this->exts->exists("iframe#captcha-internal")){
                    $this->switchToFrame("iframe#captcha-internal");
                    if ($this->exts->exists("iframe#arkoseframe")){
                        $this->switchToFrame("iframe#arkoseframe");
                        sleep(1);
                    }
                    if ($this->exts->exists('#fc-iframe-wrap')){
                        $this->switchToFrame('#fc-iframe-wrap');
                    }
                    $this->exts->moveToElementAndClick('a.reloadBtn');
                    sleep(8);
                }
            }
            $captcha_instruction = 'Pick the image that is the correct way up';
            $this->exts->log('language_code: '.$language_code.' Instruction: '. $captcha_instruction);
            $this->exts->switchToDefault();
            if ($this->exts->exists('iframe.iframe--authentication')) {
                $this->switchToFrame('iframe.iframe--authentication');
                sleep(1);
            }
            // if ($this->exts->exists("iframe#captcha-internal")){
            // 	$this->switchToFrame("iframe#captcha-internal");
            // }
            // if ($this->exts->exists("iframe#arkoseframe")){
            // 	$this->switchToFrame("iframe#arkoseframe");
            // 	sleep(1);
            // }

            $captcha_wraper_selector = 'iframe#captcha-internal';
            // $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=true);// use $language_code and $captcha_instruction if they changed captcha content
            // if($coordinates == ''){
            //     $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=true);
            // }
            // if($coordinates != ''){
            //     $wraper = $this->exts->getElement($captcha_wraper_selector);
            //     $actions = $this->exts->webdriver->action();
            //     $this->exts->log('Clicking X/Y: '.$coordinates[0]['x'].'/'.$coordinates[0]['y']);
            //     $actions->moveToElement($wraper, intval($coordinates[0]['x']), intval($coordinates[0]['y']))->click()->perform();
            //     sleep(3);
            // }
        }
        $this->exts->switchToDefault();
        return true;
    }
    $this->exts->switchToDefault();
    return false;
}
private function checkFillRecaptcha($count=1) {
    $this->exts->log(__FUNCTION__);
    $this->switchToFrame('iframe#captcha-internal');
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        
        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
        
        if($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__."::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i=0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');
            
            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->execute_javascript('
                if(document.querySelector("[data-callback]") != null){
                    return document.querySelector("[data-callback]").getAttribute("data-callback");
                }

                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                return found ? "___grecaptcha_cfg.clients[0]." + result : null;
            ');
            $this->exts->log('Callback function: '.$gcallbackFunction);
            if($gcallbackFunction != null){
                $this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
                sleep(10);
            }
        } else {
            // Only call this if recaptcha service expired.
            if($count < 5){
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
        return true;
    } else {
        $this->exts->log(__FUNCTION__.'::Not found reCaptcha');
        return false;
    }
}
private function checkLogin(){
    $this->exts->capture('check-login');
    return $this->exts->exists($this->check_login_success_selector);
}
private function checkTwoFactorAuth($count = 1) {
    
    $this->exts->log(__FUNCTION__ . ' Begin ' . $count);
    $is_recaptcha = $this->exts->getElement('div#nocaptcha iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]');
    
    
    $isCodeVerificationFrm = $this->exts->getElement('form[name="ATOPinChallengeForm"]');
    $isCodeVerificationFrmV1 = $this->exts->getElement('form[name="TwoStepVerificationForm"] input#verification-code');
    $isCodeVerificationFrmV2 = $this->exts->getElement('form#email-pin-challenge input[name="pin"]');
    $isCodeVerificationFrmV3 = $this->exts->getElement('input#input__phone_verification_pin');
    
    
    if($is_recaptcha != null && stripos($this->exts->getUrl(), '/uas/consumer-captcha-v2?challengeId') !== false) {
        $this->exts->log( __FUNCTION__ . " Found recaptcha");
        $this->exts->loginFailure();
    } else if($isCodeVerificationFrm != null && stripos($this->exts->getUrl(), '/uas/consumer-email-challenge') !== false) {
        
        $two_factor_input_selector = "input[name='PinVerificationForm_pinParam']";
        $two_factor_msg = '';
        $submit_button_selector = 'input#btn-primary[type="submit"]';
        $ele = $this->exts->getElement('form[name="ATOPinChallengeForm"] h2');
        if($ele != null) {
            $two_factor_msg = $ele->getText();
        }
        $this->handleTwoFactorCode($two_factor_input_selector, $two_factor_msg, $submit_button_selector);
    } else if($isCodeVerificationFrmV1 != null && stripos($this->exts->getUrl(), 'consumer-two-step') !== false) {
        $two_factor_input_selector = "input[name='PinVerificationForm_pinParam']";
        $two_factor_msg = '';
        $submit_button_selector = 'form[name="TwoStepVerificationForm"] input#btn-primary';
        $ele = $this->exts->getElement('div#main h2');
        if($ele != null) {
            $two_factor_msg = $ele->getText();
        }
        $this->handleTwoFactorCode($two_factor_input_selector, $two_factor_msg, $submit_button_selector);
    } else if($isCodeVerificationFrmV2 != null && stripos($this->exts->getUrl(), 'checkpoint') !== false) {
        $two_factor_input_selector = "form#email-pin-challenge input[name='pin']";
        $two_factor_msg = '';
        $submit_button_selector = 'form#email-pin-challenge button#email-pin-submit-button';
        $ele = $this->exts->getElement('form#email-pin-challenge h2.form__subtitle');
        if($ele != null) {
            $two_factor_msg = $ele->getText();
        }
        $this->handleTwoFactorCode($two_factor_input_selector, $two_factor_msg, $submit_button_selector);
    } else if($isCodeVerificationFrmV3 != null && stripos($this->exts->getUrl(), 'checkpoint') !== false) {
        $two_factor_input_selector = "form#two-step-challenge input[name='pin']";
        $two_factor_msg = '';
        $submit_button_selector = 'form#two-step-challenge button#two-step-submit-button';
        $ele = $this->exts->getElement('div.app__content h1.content__header');
        if($ele != null) {
            $two_factor_msg = $ele->getText();
        }
        $this->handleTwoFactorCode($two_factor_input_selector, $two_factor_msg, $submit_button_selector);
    } else if($this->exts->getElement('//p[contains(text(),"notification to your signed in devices") or contains(text(),"Wir haben eine Mitteilung an Ihre eingeloggten")]', null, 'xpath') != null){
        $two_factor_message_selector = '//p[contains(text(),"notification to your signed in devices") or contains(text(),"Wir haben eine Mitteilung an Ihre eingeloggten")]';
        $this->exts->two_factor_notif_msg_en = "";
            
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector, null, 'xpath')[0]->getText();
        
        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            sleep(15);
            if($this->exts->getElement($two_factor_message_selector, null, 'xpath') == null){
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkTwoFactorAuth($two_factor_message_selector);
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    } else {
        $feed_button = $this->exts->getElement('div.not-found__main-cta a[href*="linkedin.com/feed/"]');
        if($feed_button != null) {
            $this->exts->moveToElementAndClick('div.not-found__main-cta a[href*="linkedin.com/feed/"]');
            sleep(5);
        }
        
        if($this->exts->getElement("form.country-code-list-form input[name=\"phoneNumber\"]") != null && $this->exts->getElement("div.cp-actions button.secondary-action") != null) {
            $this->exts->moveToElementAndClick("div.cp-actions button.secondary-action");
        }
    }
}
private function handleTwoFactorCode($two_factor_input_selector, $two_factor_msg, $submit_button_selector) {
    
    $this->exts->log(__FUNCTION__ . ' Begin ' . ', ' . $two_factor_msg);
    $this->exts->two_factor_notif_msg_en = $two_factor_msg;
    $this->exts->two_factor_notif_msg_de = $two_factor_msg;
    
    if($this->exts->two_factor_attempts == 2) {
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
    }
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if(trim($two_factor_code) != "" && !empty($two_factor_code)) {
        try {
            $this->exts->log(__FUNCTION__ . "::Entering two_factor_code.");
            $this->exts->getElement($two_factor_input_selector)->sendKeys($two_factor_code);
            
            $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
            $this->exts->moveToElementAndClick($submit_button_selector);
            sleep(10);
            
            if($this->exts->getElement($two_factor_input_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->handleTwoFactorCode($two_factor_input_selector, $two_factor_msg, $submit_button_selector);
            } else {
                if($this->exts->getElement("form.country-code-list-form input[name=\"phoneNumber\"]") != null && $this->exts->getElement("div.cp-actions button.secondary-action") != null) {
                    $this->exts->moveToElementAndClick("div.cp-actions button.secondary-action");
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
        }
    }
}

    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]';
    public $google_submit_username_selector = '#identifierNext';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #passwordNext button';
    public $google_solved_rejected_browser = false;
    private function loginGoogleIfRequired()
    {
        if ($this->exts->urlContains('google.')) {
            $this->checkFillGoogleLogin();
            sleep(10);
            $this->check_solve_rejected_browser();

            if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null) {
                $this->exts->loginFailure(1);
            }

            if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
                sleep(10);
            }
            // Click next if confirm form showed
            $this->exts->click_by_xdotool('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
            $this->checkGoogleTwoFactorMethod();
            sleep(10);
            if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
                $this->exts->click_by_xdotool('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->exts->exists('#tos_form input#accept')) {
                $this->exts->click_by_xdotool('#tos_form input#accept');
                sleep(10);
            }
            if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->click_by_xdotool('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->click_by_xdotool('.action-button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->click_by_xdotool('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->click_by_xdotool('input[name="later"]');
                sleep(7);
            }
            if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->click_by_xdotool('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->click_by_xdotool('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }
            if ($this->exts->urlContains('gds.google.com/web/chip')) {
                $this->exts->click_by_xdotool('[role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
                sleep(10);
            }


            $this->exts->log('URL before back to main tab: ' . $this->exts->getUrl());
            $this->exts->capture("google-login-before-back-maintab");
            if (
                $this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null
            ) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required google login.');
            $this->exts->capture("3-no-google-required");
        }
    }
    private function checkFillGoogleLogin()
    {
        if ($this->exts->exists('[data-view-id*="signInChooserView"] li [data-identifier]')) {
            $this->exts->click_by_xdotool('[data-view-id*="signInChooserView"] li [data-identifier]');
            sleep(10);
        } else if ($this->exts->exists('form li [role="link"][data-identifier]')) {
            $this->exts->click_by_xdotool('form li [role="link"][data-identifier]');
            sleep(10);
        }
        if ($this->exts->exists('form [data-profileindex]')) {
            $this->exts->click_by_xdotool('form [data-profileindex]');
            sleep(5);
        }
        $this->exts->capture("2-google-login-page");
        if ($this->exts->querySelector($this->google_username_selector) != null) {
            // $this->fake_user_agent();
            // $this->exts->refresh();
            // sleep(5);

            $this->exts->log("Enter Google Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(7);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }

            // Which account do you want to use?
            if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->querySelector($this->google_password_selector) != null) {
            $this->exts->log("Enter Google Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            }

            $this->exts->capture("2-google-password-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->capture("2-login-google-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(10);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                    $this->exts->capture("2-login-google-pageandcaptcha-filled");
                    $this->exts->click_by_xdotool($this->google_submit_password_selector);
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::google Password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function check_solve_rejected_browser()
    {
        $this->exts->log(__FUNCTION__);
        $root_user_agent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12.6; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }

        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
    }
    private function overwrite_user_agent($user_agent_string = 'DN')
    {
        $userAgentScript = "
            (function() {
                if ('userAgentData' in navigator) {
                    navigator.userAgentData.getHighEntropyValues({}).then(() => {
                        Object.defineProperty(navigator, 'userAgent', { 
                            value: '{$user_agent_string}', 
                            configurable: true 
                        });
                    });
                } else {
                    Object.defineProperty(navigator, 'userAgent', { 
                        value: '{$user_agent_string}', 
                        configurable: true 
                    });
                }
            })();
        ";
        $this->exts->execute_javascript($userAgentScript);
    }

    private function checkFillLogin_undetected_mode($root_user_agent = '')
    {
        if ($this->exts->exists('form [data-profileindex]')) {
            $this->exts->click_by_xdotool('form [data-profileindex]');
            sleep(5);
        } else if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
            $this->exts->capture("2-google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
            if (!empty($root_user_agent)) {
                $this->overwrite_user_agent('DN'); // using DN (DONT KNOW) user agent, last solution
            }
            $this->exts->type_key_by_xdotool("F5");
            sleep(5);
            $current_useragent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

            $this->exts->log('current_useragent: ' . $current_useragent);
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->google_username_selector);
            $this->exts->click_by_xdotool($this->google_username_selector);
            $this->exts->type_key_by_xdotool("ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);
            $this->exts->capture_by_chromedevtool("2-google-username-filled");
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(7);
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
                if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->click_by_xdotool($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->click_by_xdotool($this->google_submit_username_selector);
                    sleep(5);
                }
            }

            if (!empty($root_user_agent)) { // If using DN user agent, we must revert back to root user agent before continue
                $this->overwrite_user_agent($root_user_agent);
                if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(3);
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(3);
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(6);
                    $this->exts->capture_by_chromedevtool("2-google-login-reverted-UA");
                }
            }

            // Which account do you want to use?
            if ($this->exts->check_exist_by_chromedevtool('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->check_exist_by_chromedevtool('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            }

            $this->exts->capture("2-google-password-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->exts->exists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->capture("2-lgoogle-ogin-pageandcaptcha-filled");
                    $this->exts->click_by_xdotool($this->google_submit_password_selector);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function checkGoogleTwoFactorMethod()
    {
        // Currently we met many two factor methods
        // - Confirm email account for account recovery
        // - Confirm telephone number for account recovery
        // - Call to your assigned phone number
        // - confirm sms code
        // - Solve the notification has sent to smart phone
        // - Use security key usb
        // - Use your phone or tablet to get a security code (EVEN IF IT'S OFFLINE)
        $this->exts->log(__FUNCTION__);
        sleep(5);
        $this->exts->capture("2.0-before-check-two-factor");
        // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
        if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
            $this->exts->click_by_xdotool('#assistActionId');
            sleep(5);
        } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
            if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
                $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
                sleep(5);
            }
        } else if ($this->exts->urlContains('/sk/webauthn') || $this->exts->urlContains('/challenge/pk')) {
            // CURRENTLY THIS CASE CAN NOT BE SOLVED
            $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get clean'");
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get -y update'");
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get install -y xdotool'");
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
            sleep(3);
            $this->exts->capture("2.0-cancel-security-usb");
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->click_by_xdotool('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
        } else if ($this->exts->exists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
        } else if ($this->exts->urlContains('/challenge/') && !$this->exts->urlContains('/challenge/pwd') && !$this->exts->urlContains('/challenge/totp')) { // totp is authenticator app code method
            // if this is not password form AND this is two factor form BUT it is not Authenticator app code method, back to selection list anyway in order to choose Authenticator app method if available
            $supporting_languages = [
                "Try another way",
                "Andere Option w",
                "Essayer une autre m",
                "Probeer het op een andere manier",
                "Probar otra manera",
                "Prova un altro metodo"
            ];
            $back_button_xpath = '//*[contains(text(), "Try another way") or contains(text(), "Andere Option w") or contains(text(), "Essayer une autre m")';
            $back_button_xpath = $back_button_xpath . ' or contains(text(), "Probeer het op een andere manier") or contains(text(), "Probar otra manera") or contains(text(), "Prova un altro metodo")';
            $back_button_xpath = $back_button_xpath . ']/..';
            $back_button = $this->exts->getElement($back_button_xpath, null, 'xpath');
            if ($back_button != null) {
                try {
                    $this->exts->log(__FUNCTION__ . ' back to method list to find Authenticator app.');
                    $this->exts->execute_javascript("arguments[0].click();", [$back_button]);
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript("arguments[0].click()", [$back_button]);
                }
            }
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            $this->exts->capture("2.1-2FA-method-list");

            // Updated 03-2023 since we setup sub-system to get authenticator code without request to end-user. So from now, We priority for code from Authenticator app top 1, sms code or email code 2st, then other methods
            if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND TOP 1 method type = 6 is get code from Google Authenticator
                $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->click_by_xdotool('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->click_by_xdotool('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="1"]:not([data-challengeunavailable="true"])')) {
                // Select enter your passowrd, if only option is passkey
                $this->exts->click_by_xdotool('li [data-challengetype="1"]:not([data-challengeunavailable="true"])');
                sleep(3);
                $this->checkFillGoogleLogin();
                sleep(3);
                $this->checkGoogleTwoFactorMethod();
            } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])') && (isset($this->security_phone_number) && $this->security_phone_number != '')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->click_by_xdotool('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="10"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            }
            else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="12"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->click_by_xdotool('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
            sleep(10);
        }

        // STEP 2: (Optional)
        if ($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
            // If methos is recovery email, send 2FA to ask for email
            $this->exts->two_factor_attempts = 2;
            $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                $this->exts->type_key_by_xdotool('Return');
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
            // If methos confirm recovery phone number, send 2FA to ask
            $this->exts->two_factor_attempts = 3;
            $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool('Return');
                sleep(5);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('input#phoneNumberId')) {
            // Enter a phone number to receive an SMS with a confirmation code.
            $this->exts->two_factor_attempts = 3;
            $input_selector = 'input#phoneNumberId';
            $message_selector = '[data-view-id] form section > div > div > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool('Return');
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->querySelectorAll('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext';
            $this->exts->two_factor_attempts = 3;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionIdk
        } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
        }
    }
    private function fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            if ($this->exts->two_factor_attempts > 1) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
        }

        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->querySelector($input_selector) != null) {
                if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                    $two_factor_code = end(explode('G-', $two_factor_code));
                }
                if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                    $two_factor_code = end(explode('g-', $two_factor_code));
                }
                $this->exts->log("fillTwoFactor: Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, '');
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(2);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log("fillTwoFactor: Clicking submit button.");
                    $this->exts->click_by_xdotool($submit_selector);
                } else if ($submit_by_enter) {
                    $this->exts->type_key_by_xdotool('Return');
                }
                sleep(10);
                $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else {
                    if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->notification_uid = '';
                        $this->exts->two_factor_attempts++;
                        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
                            // if(strpos(strtoupper($this->exts->extract('div:last-child[style*="visibility: visible;"] [role="button"]')), 'CODE') !== false){
                            $this->exts->click_by_xdotool('[aria-relevant="additions"] + [style*="visibility: visible;"] [role="button"]');
                            sleep(2);
                            $this->exts->capture("2.2-two-factor-resend-code-" . $this->exts->two_factor_attempts);
                            // }
                        }

                        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
                    } else {
                        $this->exts->log("Two factor can not solved");
                    }
                }
            } else {
                $this->exts->log("Not found two factor input");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
    // -------------------- GOOGLE login END

public $invoices = array();
private function processInvoices($paging_count=1) {
    sleep(10);
    $this->exts->capture("4-invoices-page-".$paging_count);
    
    
    $rows = $this->exts->getElements('table > tbody > tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 4 && $this->exts->getElement('button.view-receipt[data-order-id]', $row) != null) {
            $invoiceName = $this->exts->getElement('button.view-receipt[data-order-id]', $row)->getAttribute('data-order-id');
            $invoiceUrl = 'https://www.linkedin.com/payments/receipt/' . $invoiceName;
            $invoiceDate = trim($tags[0]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';
            
            array_push($this->invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>$invoiceDate,
                'invoiceAmount'=>$invoiceAmount,
                'invoiceUrl'=>$invoiceUrl
            ));
            $this->isNoInvoice = false;
        }
    }
    
    if($this->restrictPages == 0 && $paging_count < 20 && $this->exts->getElement('a#next-order-history') != null){
        $paging_count++;
        $this->exts->moveToElementAndClick('a#next-order-history');
        sleep(5);
        $this->processInvoices($paging_count);
    }else if($this->restrictPages > 0 && $paging_count < 4 && $this->exts->getElement('a#next-order-history') != null){
        $paging_count++;
        $this->exts->moveToElementAndClick('a#next-order-history');
        sleep(5);
        $this->processInvoices($paging_count);
    } else {
        $this->downloadInvoices();
    }
}
public function downloadInvoices() {
    // Download all invoices

    $this->exts->log('Invoices found: '.count($this->invoices));
    foreach ($this->invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: '.$invoice['invoiceName']);
        $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
        
        $invoiceFileName = $invoice['invoiceName'].'.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
        $this->exts->log('Date parsed: '.$invoice['invoiceDate']);
        
        if($this->exts->invoice_exists($invoice['invoiceName'])){
            $this->exts->log('Invoice EXISTS');
            continue;
        }
        $this->exts->openUrl($invoice['invoiceUrl']);
        sleep(3);
        if($this->company_detail != ''){
            if($this->exts->exists('.company-billing-info-updater button.add-billing-details-btn:not(.hidden)')){
                $this->exts->moveToElementAndClick('.company-billing-info-updater button.add-billing-details-btn');
                sleep(3);
                $edit_area = $this->exts->getElement('.company-billing-info-updater #company-billing-info');
                $this->exts->execute_javascript("arguments[0].innerHTML=''", [$edit_area]);
                $this->exts->moveToElementAndClick('.company-billing-info-updater #company-billing-info');
                $this->exts->webdriver->getKeyboard()->releaseKey($this->company_detail);
                sleep(1);
                $this->exts->moveToElementAndClick('.company-billing-info-updater button.company-button[type="submit"]');
            } else if($this->exts->exists('.company-billing-info-updater a.edit:not(.hidden)')){
                $this->exts->moveToElementAndClick('.company-billing-info-updater a.edit:not(.hidden)');
                sleep(3);
                $edit_area = $this->exts->getElement('.company-billing-info-updater #company-billing-info');
                $this->exts->execute_javascript("arguments[0].innerHTML=''", [$edit_area]);
                $this->exts->moveToElementAndClick('.company-billing-info-updater #company-billing-info');
                $this->exts->webdriver->getKeyboard()->releaseKey($this->company_detail);
                sleep(1);
                $this->exts->moveToElementAndClick('.company-billing-info-updater button.company-button[type="submit"]');
            }
            sleep(3);
        }
        $downloaded_file = $this->exts->download_current($invoiceFileName);
        if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        }
    }
}

private function processInvoicestransactions() {
    sleep(25);

    $this->exts->capture("4-invoices-pagetransactions");
    $invoices = [];

    $rows = $this->exts->getElements('div.invoices-table__list  [role="row"]');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('[role="cell"]', $row);
        if(count($tags) >= 7 && $this->exts->getElement('button[id*="menu-trigger"]', $tags[6]) != null) {
            $invoice_menu_action = $this->exts->getElement('button[id*="menu-trigger"]', $tags[6]);
            try{
                $this->exts->log('Click download button');
                $invoice_menu_action->click();
            } catch(\Exception $exception){
                $this->exts->log('Click download button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$invoice_menu_action]);
            }
            sleep(2);
            if($this->exts->exists('a[download*="LNKD_INVOICE"]')){
                $invoiceUrl = $this->exts->getElement('a[download*="LNKD_INVOICE"]')->getAttribute("href");
                $invoiceName = trim($tags[3]->getAttribute('innerText'));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName'=>$invoiceName,
                    'invoiceDate'=>$invoiceDate,
                    'invoiceAmount'=>$invoiceAmount,
                    'invoiceUrl'=>$invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }
    }

    // Download all invoices
    $this->exts->log('Invoices found: '.count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: '.$invoice['invoiceName']);
        $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

        $invoiceFileName = $invoice['invoiceName'].'.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
        $this->exts->log('Date parsed: '.$invoice['invoiceDate']);
        
        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        }
    }
}


public function switchToFrame($query_string)
{
    $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
    $frame = null;
    if (is_string($query_string)) {
        $frame = $this->exts->queryElement($query_string);
    }

    if ($frame != null) {
        $frame_context = $this->exts->get_frame_excutable_context($frame);
        if ($frame_context != null) {
            $this->exts->current_context = $frame_context;
            return true;
        }
    } else {
        $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
    }

    return false;
}