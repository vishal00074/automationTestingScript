public $baseUrl = "https://kundenportal.ww-ag.de";
public $loginUrl = "https://kundenportal.ww-ag.de";
public $invoicePageUrl = 'https://kundenportal.ww-ag.de/pk/postfach';
public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $submit_button_selector = 'button[type="submit"]';
public $check_login_failed_selector = 'error-message';
public $check_login_success_selector = 'button[aria-label="Log out"], button[aria-label="Abmelden"], div.logout-btn';
public $login_tryout = 0;
public $isNoInvoice = true;

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
        $this->exts->waitTillPresent($this->check_login_failed_selector, 20);
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 20);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->moveToElementAndClick($this->submit_button_selector);
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
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}