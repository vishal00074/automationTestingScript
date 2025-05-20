public $baseUrl = "https://app.sendgrid.com/login";
public $loginUrl = "https://app.sendgrid.com/login";
public $homePageUrl = "https://app.sendgrid.com/settings/billing";
public $username_selector = "input#usernameContainer-input-id, input#username";
public $password_selector = "input#passwordContainer-input-id, input#password";
public $submit_button_selector = "div.login-btn button,button[type='submit'][name='action']";
public $login_tryout = 0;

private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture("Home-page-without-cookie");
    
    if(!$this->checkLogin()) {
        $this->exts->capture("after-login-clicked");
        if($this->exts->exists('a.acceptAllButtonLower')){
            $this->exts->moveToElementAndClick('a.acceptAllButtonLower');
            sleep(5);
        }
        $this->fillForm(0);
        sleep(10); 
        if($this->exts->exists('div.security-checkup-continue-link a')){
            $this->exts->moveToElementAndClick('div.security-checkup-continue-link a');
            sleep(10);
        }
        if($this->exts->exists('a.acceptAllButtonLower')){
            $this->exts->moveToElementAndClick('a.acceptAllButtonLower');
            sleep(5);
        }
        $this->checkFillTwoFactor();
        sleep(5);
        
        if($this->exts->exists('div.setup-2fa-header-horizontal-progress h2') && strpos(strtolower($this->exts->extract('div.setup-2fa-header-horizontal-progress h2')), 'add two-factor authentication') !== false){
            $this->exts->log("Setup 2FA: " .$this->exts->extract('div.setup-2fa-header-horizontal-progress h2'));
            $this->exts->account_not_ready();
        }

        if($this->exts->exists('[data-qahook="setup2FARequiredEmailCheckpoint"]') && strpos(strtolower($this->exts->extract('[data-qahook="setup2FARequiredEmailCheckpoint"]')), 'secure your account with two-factor authentication') !== false){
            $this->exts->log("Setup 2FA: " .$this->exts->extract('[data-qahook="setup2FARequiredEmailCheckpoint"]'));
            $this->exts->account_not_ready();
        }

        $err_msg = $this->exts->extract('form.login-form .login-form-error, #login-error-alert-container');
        
        if ($err_msg != "" && $err_msg != null) {
            $this->exts->log($err_msg);
            $this->exts->loginFailure(1);
        }
        
        if($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }
        } elseif(strpos(strtolower($this->exts->extract('div#login-error-alert-container p')), 'Your username or password is invalid.') !== false){
            $this->exts->log("Your username or password is invalid.");
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        }else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
    }
}

private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->refresh();
            sleep(10);

            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}

private function check_solve_cloudflare_page() {
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if(
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) && 
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ){
        for ($waiting=0; $waiting < 10; $waiting++) {
            sleep(2);
            if($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])){
                sleep(3);
                break;
            }
        }
    }

    if($this->exts->exists($unsolved_cloudflare_input_xpath)){
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
    }
}

function fillForm($count){
    $this->exts->log("Begin fillForm ".$count);
     $this->exts->waitTillPresent($this->username_selector);
    if( $this->exts->exists($this->username_selector)) {
        sleep(2);
        $this->login_tryout = (int)$this->login_tryout + 1;
        $this->exts->capture("2-login-page");
        
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->check_solve_cloudflare_page();
        $this->exts->moveToElementAndClick('button[data-role="continue-btn"]');
        sleep(10);
        if( $this->exts->exists($this->password_selector)) {
            $this->exts->log("Enter cloudflare");
            $this->check_solve_cloudflare_page();
            sleep(5);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            
            sleep(8);
        }
    } else {
        $this->exts->capture("2-login-page-not-found");
    }
}

function checkLogin() {
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if($this->exts->getElement('li[data-logout="logout"] a') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
            
        }
    } catch(Exception $exception){
        $this->exts->log("Exception checking loggedin ".$exception);
    }
    return $isLoggedIn;
}

private function checkFillTwoFactor() {
    $two_factor_selector = 'input#authyTokenContainer-input-id';
    $two_factor_message_selector = 'p[role="codeSentText"]';
    $two_factor_submit_selector = 'form[role="validateForm"] [role="validateBtn"]';
    
    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        if($this->exts->getElement($two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
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
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
            
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);
            
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
    } else {
        $two_factor_selector = 'input#twoFactorCode,input#code';
        $two_factor_message_selector = '//p[contains(text(),"with two-factor authentication")],//h1[contains(text(),"Verify Your Identity")]';
        $two_factor_submit_selector = 'button[type="submit"][data-qahook="validate2FAContinueButton"],form div button[type="submit"][name="action"]';
        
        if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            
            if($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null){
                $this->exts->two_factor_notif_msg_en = "";
                for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) { 
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getText()."\n";
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
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
                
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                $this->exts->capture("2.2-two-factor-clicked-".$this->exts->two_factor_attempts);
                
                if($this->exts->getElement($two_factor_selector) == null){
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
        }
    }
}