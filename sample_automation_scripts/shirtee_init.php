public $baseUrl = "https://www.shirtee.com/de/customer/account/login/";
public $username_selector = '#login-form #email, input[id="header-login-form-email"]';
public $password_selector = '#login-form #pass, input[id="header-login-form-password"]';
public $submit_btn = "#login-form #send2, button.btn-checkout";
public $logout_btn = '[href*="/logout"]';
public $wrong_credential_selector = "li.error-msg li span, #login-form .error-msg li";

public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $isCookieLoaded = false;
    if ($this->exts->loadCookiesFromFile()) {
        sleep(1);
        $isCookieLoaded = true;
    }
    $this->exts->openUrl($this->baseUrl);
    sleep(12);

    if ($this->exts->querySelector('div.before-header div.bhi-link-right > a') != null) {
        $this->exts->moveToElementAndClick('div.before-header div.bhi-link-right > a');
        sleep(5);
        $this->fillForm(0);
    }

    if ($isCookieLoaded) {
        $this->exts->capture("Home-page-with-cookie");
    } else {
        $this->exts->capture("Home-page-without-cookie");
    }

    if (!$this->checkLogin()) {
        if ($this->exts->exists('.cc-btn.cc-dismiss')) {
            $this->exts->moveToElementAndClick('.cc-btn.cc-dismiss');
            sleep(1);
        }
        if ($this->exts->exists('button#acceptAll')) {
            $this->exts->moveToElementAndClick('button#acceptAll');
            sleep(5);
        }
        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->capture("LoginFailed");
        if ($this->isWrongCredential()) {
            $this->exts->log($this->exts->extract($this->wrong_credential_selector, null));
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function isWrongCredential()
{
    $tag = $this->exts->getElement($this->wrong_credential_selector);
    if ($tag != null) {
        return true;
    }
    return false;
}


/**
    * Method to fill login form
    * @param Integer $count Number of times portal is retried.
    */
function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {

        if ($this->exts->exists($this->username_selector)) {
            sleep(2);
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username, 5);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password, 5);

            $this->exts->capture("1-pre-login-1");
            $this->checkFillRecaptcha(0);

            $this->exts->moveToElementAndClick($this->submit_btn);
            sleep(10);
            $this->checkFillRecaptcha(0);
        } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
            $this->checkFillRecaptcha(0);
            if ($count < 5) {
                $this->fillForm($count + 1);
            } else {
                $this->exts->log(__FUNCTION__ . " :: too many recaptcha attempts " . $count);
                $this->exts->loginFailure();
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

function checkFillRecaptcha($count)
{

    if (
        $this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') &&
        $this->exts->exists('textarea[name="g-recaptcha-response"]') &&
        $count < 3
    ) {

        if ($this->exts->exists("div.g-recaptcha[data-sitekey]")) {
            $data_siteKey = trim($this->exts->getElement("div.g-recaptcha")->getAttribute("data-sitekey"));
        } else {
            $iframeUrl = $this->exts->getElement("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]")->getAttribute("src");
            $tempArr = explode("&k=", $iframeUrl);
            $tempArr = explode("&", $tempArr[count($tempArr) - 1]);

            $data_siteKey = trim($tempArr[0]);
            $this->exts->log("iframe url  - " . $iframeUrl);
        }
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            $this->exts->log("isCaptchaSolved");
            $this->exts->execute_javascript("document.querySelector(\"#g-recaptcha-response\").value = '" . $this->exts->recaptcha_answer . "';");
            $this->exts->execute_javascript("document.querySelector(\"#g-recaptcha-response\").innerHTML = '" . $this->exts->recaptcha_answer . "';");
            sleep(5);
        } else {
            $this->exts->log("Captcha expired, retry...");
            $this->checkFillRecaptcha($count + 1);
        }
    } else if ($count >= 3) {
        $this->exts->log('Recaptcha exceeds 3 times');
    } else {
        $this->exts->log('There are no recaptcha');
    }
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists($this->logout_btn)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}
