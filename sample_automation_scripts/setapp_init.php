public $baseUrl = "https://my.setapp.com/login";
public $loginUrl = "https://my.setapp.com/login";
public $restrictPages = 3;
public $login_tryout = 0;
public $billingPageUrl = "https://my.setapp.com/account";
public $username_selector = "input[name='email']";
public $password_selector = "input[name='password']";
public $submit_button_selector = 'button[type="submit"]';
public $logout_selector = ".log-out-link']";
public $bill_selector = "a[href='/payment-history']";

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */


private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    sleep(2);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    $this->exts->loadCookiesFromFile();

    $this->exts->openUrl($this->billingPageUrl);
    sleep(15);
    if ($this->checkLogin()) {
        $isCookieLoginSuccess = true;
    }


    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");
        $this->exts->clearCookies();
        sleep(5);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->fillForm(0);
        sleep(5);
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->fillForm(0);
            sleep(5);
        }
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->fillForm(0);
            sleep(5);
        }

        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->fillForm(0);
            sleep(5);
        }


        $this->exts->capture("after-login-submited");
        $this->exts->openUrl('https://my.setapp.com');
        sleep(15);

        if ($this->checkLogin()) {
            if ($this->exts->exists('button.cookie-banner__button')) {
                $this->exts->moveToElementAndClick('button.cookie-banner__button');
                sleep(1);
            }
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {
            $err_msg = $this->exts->extract('p.form-error');
            $this->exts->log($err_msg);
            if (stripos($err_msg, strtolower('Your email or password is incorrect')) !== false) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    } else {
        if ($this->exts->exists('button.cookie-banner__button')) {
            $this->exts->moveToElementAndClick('button.cookie-banner__button');
            sleep(1);
        }
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    }
}

/**
    * Method to fill login form
    * @param Integer $count Number of times portal is retried.
    */

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);

    $this->exts->waitTillPresent($this->username_selector);
    if ($this->exts->querySelector($this->username_selector) != null) {

        $this->exts->capture("1-pre-login");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("1-login-page-filled");
        if ($this->exts->exists($this->submit_button_selector)) {
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(2);
        }
        sleep(10);

        $this->checkFillRecaptcha($count);

        if ($this->exts->exists($this->submit_button_selector)) {
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(5);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}


private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector);
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(10);
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

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {

        if ($this->exts->getElement($this->bill_selector) != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}