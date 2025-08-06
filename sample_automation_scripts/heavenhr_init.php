public $baseUrl = "https://www.heavenhr.com/dashboard";
public $loginUrl = "https://www.heavenhr.com/web/DE/de/login";
public $username_selector = 'input[name="_username"]';
public $password_selector = 'input[name="_password"]';
public $submit_button_selector = 'button[data-test-id="login-submit"]';
public $login_tryout = 0;
public $restrictPages = 3;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture("Home-page-without-cookie");
    $this->exts->loadCookiesFromFile();
    
    $this->exts->openUrl($this->baseUrl);
    sleep(20);


    $this->checkAndCloseCookiePopup();

    if (!$this->checkLogin()) {

        $this->fillForm(0);
        sleep(2);

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            $this->exts->capture("LoginFailed");
            if (
                strpos(strtolower($this->exts->extract('div[class*="Login_pag"] p.errorMessage')), 'please check your registration data') !== false
                || strpos(strtolower($this->exts->extract('div[class*="Login_pag"] p.errorMessage')), 'fen sie ihre anmeldedaten') !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->invoicePage();
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(1);
        $this->waitFor($this->password_selector, 10);
        if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(1);
            $err_msg = $this->exts->extract('form#formSignIn div#errorBag.alert-danger');
            if ($err_msg != "" && $err_msg != null) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            }
        }

        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

function checkAndCloseCookiePopup()
{
    if ($this->exts->exists('#compliAcceptCookies')) {
        $this->exts->moveToElementAndClick('#compliAcceptCookies .bannerFooter > button');
        sleep(2);
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
    $this->waitFor('nav li#headerUser', 25);
    try {
        if ($this->exts->exists('nav li#headerUser')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}
function invoicePage()
{
    if (!empty($this->exts->config_array['allow_login_success_request'])) {
        $this->exts->triggerLoginSuccess();
    }
    $this->exts->success();
}