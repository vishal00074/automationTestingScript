public $baseUrl = 'https://app.my-vpa.com/budget/bills';
public $loginUrl = 'https://app.my-vpa.com/users/login';
public $invoicePageUrl = 'https://app.my-vpa.com/budget/bills';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'input#kc-login';

public $check_login_failed_selector = '.alert.alert-error .kc-feedback-text';
public $check_login_success_selector = 'a[href*="/logout"]';

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
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(20);
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('div.portlet.mvagreen.box')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}