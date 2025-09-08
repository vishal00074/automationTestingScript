public $baseUrl = 'https://www.seobility.net/de/dashboard/';
public $loginUrl = 'https://www.seobility.net/de/login/index/';
public $invoicePageUrl = 'https://www.seobility.net/de/settings/invoices/';

public $username_selector = 'form input#email';
public $password_selector = 'form input#pw, input#password';
public $remember_me = '';
public $submit_login_btn = 'form input.btn-success[type="submit"], button#btn-login';

public $checkLoginFailedSelector = '.accountmessage.alert-danger, div.shadow-color-error';
public $checkLoggedinSelector = 'a[href*="/login/logout.do"]';
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
    $this->exts->capture("Home-page-without-cookie");

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    // after load cookies and open base url, check if user logged in

    $this->exts->waitTillPresent($this->checkLoggedinSelector);

    if ($this->exts->exists($this->checkLoggedinSelector)) {

        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->waitForLogin();
    } else {

        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');
        $this->exts->clearCookies();

        $this->exts->openUrl($this->loginUrl);
        $this->checkFillLogin();
        $this->waitForLogin();
    }
}

private function checkFillLogin()
{

    $this->exts->waitTillPresent($this->username_selector, 10);

    if ($this->exts->exists($this->username_selector)) {

        $this->exts->capture("1-pre-login");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("1-filled-login");

        if ($this->exts->exists($this->submit_login_btn)) {
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(2);
        }
    } else {
        $this->exts->log('Timeout waitForLoginPage');
        $this->exts->capture("LoginFailed");
        $this->exts->loginFailure();
    }
}

private function waitForLogin()
{

    if ($this->exts->exists($this->checkLoggedinSelector)) {
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log('Timeout waitForLogin');
        $this->exts->capture("LoginFailed");

        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract($this->checkLoginFailedSelector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('authentifizierung fehlgeschlagen')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}