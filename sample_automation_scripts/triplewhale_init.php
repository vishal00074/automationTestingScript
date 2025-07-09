public $baseUrl = "https://app.triplewhale.com/";
public $loginUrl = "https://app.triplewhale.com/";
public $invoicePageUrl = 'https://app.triplewhale.com/store-settings/orders-invoices';
public $username_selector = "input#login-email-input";
public $password_selector = "input#login-password-input";
public $submit_button_selector = 'button[id="continue-btn-unknown login-button"]';
public $check_login_failed_selector = "div.Toastify__toast-container";
public $check_login_success_selector = 'div[id="user-settings-popover"]';
public $login_tryout = 0;
public $isNoInvoice = true;
public $isFailed = false;
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
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        // $this->exts->waitTillPresent('a[href*=signin]');
        // if ($this->exts->exists('a[href*=signin]')) {
        //     $this->exts->click_element('a[href*=signin]');
        // }
        $this->fillForm(0);
    }
    if (!$this->checkLogin() && $this->exts->urlContains('com/signin') && $this->exts->getElement($this->username_selector) == null) {
        // site redirect to error page
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
    }
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
      
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 15);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->click_by_xdotool($this->submit_button_selector);
            sleep(2); // Portal itself has one second delay after showing toast
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {

        // $this->waitForSelectors($this->check_login_success_selector, 20);
        for ($i = 0; $i < 35 && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
            sleep(1);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}
