public $baseUrl = 'https://app.socialpilot.co/launchpad';
public $username_selector = 'input#companyloginform-email , input[name="email"]';
public $password_selector = 'input#companyloginform-password , input[name="password"]';
public $submit_login_selector = 'button[type="submit"]';
public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(7);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->isLoggedin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->baseUrl);
        sleep(8);
        $this->checkFillLogin();
        sleep(20);
    }

    if ($this->isLoggedin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->exists('a.exiper_upgrade_btn') && $this->exts->urlContains('users/lock')) {
            $this->exts->log("Your account has been locked.");
            $this->exts->account_not_ready();
        } else {
            if (stripos($this->exts->extract('.signin-container  .invalid-feedback'), "couldn't recognize that login information") !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
}
private function checkFillLogin()
{
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(1);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function isLoggedin()
{
    return $this->exts->querySelector('.notification-icons-header');
}