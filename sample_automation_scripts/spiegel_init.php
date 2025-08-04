public $baseUrl = 'https://gruppenkonto.spiegel.de/';

public $username_selector = 'input[name="email"], input#loginname, input#username';
public $password_selector = 'input[name="password"], input#password';
public $submit_login_selector = 'form button[type="submit"], button[id*="loginform:submit"], button#submit';

public $check_login_failed_selector = 'form .Access-error, [data-sel="LOGIN_FAILED"]';
public $check_login_success_selector = '.Navigation-login a[href*="/logout"], a.Navigation-mainbarContainerLink[href*="/access/account"], a[href*="abmelden"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        if ($this->exts->exists('.OffsetContainer a[href*="authenticate"]') && $this->exts->querySelector($this->password_selector) == null) {
            $this->exts->moveToElementAndClick('.OffsetContainer  a[href*="authenticate"]');
            sleep(15);
        }
        $this->checkFillLogin();
        sleep(20);
    }

    // then check user logged in or not
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('passwor')) !== false) {
            $this->exts->loginFailure(1);
        }  else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{

    if ($this->exts->querySelector($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);
        $this->exts->waitTillPresent($this->password_selector, 15);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(5);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
        }
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}