public $baseUrl = "https://restaurants.takeaway.com/dashboard";
public $loginUrl = "https://restaurants.takeaway.com/dashboard";
public $invoicePageUrl = 'https://restaurants.takeaway.com/reporting/invoices';
public $username_selector = 'input[id="username"]';
public $password_selector = 'input[id="password"]';
public $submit_button_selector = 'button[id="kc-login"]';
public $check_login_failed_selector = 'div[id="form-error-message-internal"] p';
public $check_login_success_selector = 'div[data-testid="profile-icon"]';
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
        sleep(5);
        $this->fillForm(0);
        sleep(5);
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