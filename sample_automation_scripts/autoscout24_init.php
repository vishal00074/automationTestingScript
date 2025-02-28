public $baseUrl = "https://www.autoscout24.de/entry/auth?client_id=identity-v2&scope=openid+email+profile+offline_access&state=de-DE%235bc7b103ef634543b289f28ec303b91d&pkce_callback=https%3A%2F%2Fwww.autoscout24.de%2Fidentity%2Foauth%2Fcallback&code_challenge=SWKLArRHsUzOgwAPpnRGFdGKwx-Nh_PUpwAqETOz8UE&social_callback=https%3A%2F%2Fwww.autoscout24.de%2Fidentity%2Foauth%2Fsocial-callback&social_code_challenge=b1f3ebf5-a91d-4245-873f-890d5fd5d9fc&code_challenge_method=S256&response_type=code&redirect_uri=https%3A%2F%2Fwww.autoscout24.de%2Fidentity%2Foauth%2Fcallback";
public $loginUrl = "https://www.autoscout24.de/entry/auth?client_id=identity-v2&scope=openid+email+profile+offline_access&state=de-DE%235bc7b103ef634543b289f28ec303b91d&pkce_callback=https%3A%2F%2Fwww.autoscout24.de%2Fidentity%2Foauth%2Fcallback&code_challenge=SWKLArRHsUzOgwAPpnRGFdGKwx-Nh_PUpwAqETOz8UE&social_callback=https%3A%2F%2Fwww.autoscout24.de%2Fidentity%2Foauth%2Fsocial-callback&social_code_challenge=b1f3ebf5-a91d-4245-873f-890d5fd5d9fc&code_challenge_method=S256&response_type=code&redirect_uri=https%3A%2F%2Fwww.autoscout24.de%2Fidentity%2Foauth%2Fcallback";
public $username_selector = "input#email";
public $password_selector = "input#password";
public $submit_button_selector = "button[type='submit']";
public $check_login_success_selector = 'a[href*="profile/settings"]';
public $login_tryout = 0;
/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    if (!$this->checkLogin()) {
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->waitTillPresent("button._consent-accept_1lphq_114", 5);
        if ($this->exts->exists("button._consent-accept_1lphq_114")) {
            $this->exts->click_element("button._consent-accept_1lphq_114");
        }
        $this->fillForm(0);
        sleep(5);
        $this->checkfillForm();
        
    }
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
 
            $this->exts->triggerLoginSuccess();
        }
    }else{
        $invalidMessageSelector = "small.error-highlight";
        if ($this->exts->querySelector($invalidMessageSelector) != null) {
            $this->exts->log("Invalid username or password detected!");
            $this->exts->capture("login-failed-invalid-credentials");
            $this->exts->loginFailure(1);
        }elseif (stripos($this->exts->extract('div.error p b'), "Der Login war leider nicht erfolgreich.") !== false) {
            $this->exts->log("Unfortunately, the login was not successful.");
            $this->exts->capture("login-failed");
            $this->exts->loginFailure(1);
        } elseif (stripos($this->exts->extract('div.error p b'), "Unfortunately, the login was not successful.") !== false) {
            $this->exts->log("Unfortunately, the login was not successful.");
            $this->exts->capture("login-failed");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->log("Login failed due to unknown reasons.");
            $this->exts->loginFailure();
        }
    }
}

function checkfillForm($count = 0){
    $this->exts->log("Begin checkfillForm " . $count);

    $this->exts->waitTillPresent("input#email");

    if($this->exts->querySelector('input#email') != null){
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        if ($this->exts->exists('input[type="checkbox"][class="sc-input"]')) {
            $this->exts->click_element('input[type="checkbox"][class="sc-input"]');
            sleep(1);
        }

        $this->checkFillRecaptcha();

        $this->exts->click_by_xdotool($this->submit_button_selector);
        sleep(10);
    }

    if ($this->exts->querySelector('input#email') != null && $count < 3) {
        $count = $count + 1;
        $this->checkfillForm($count);
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->click_by_xdotool($this->submit_button_selector);
            sleep(5);
        }
        $this->checkFillRecaptcha();
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('input[type="checkbox"][class="sc-input"]')) {
                $this->exts->click_element('input[type="checkbox"][class="sc-input"]');
                sleep(1);
            }

            
            $this->exts->click_by_xdotool($this->submit_button_selector);
            sleep(5); 
        }

    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->openUrl('https://www.autoscout24.de/account');
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }



    if ($isLoggedIn) {

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }
    }

    return $isLoggedIn;
}
private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'div#captcha iframe[src*="/recaptcha/enterprise/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}

