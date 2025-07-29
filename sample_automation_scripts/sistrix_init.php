public $baseUrl = 'https://de.sistrix.com/';
public $loginUrl = 'https://de.sistrix.com/';
public $invoicePageUrl = 'https://de.sistrix.com/account/invoices';

public $username_selector = 'form#login-form input#username-input';
public $password_selector = 'form#login-form input#password-input';
public $remember_me_selector = '';
public $submit_login_selector = 'form#login-form button[type="submit"]';

public $check_login_failed_selector = 'div.alert.alert-warning, .alert.alert-danger';
public $check_login_success_selector = 'a.profile-box,a[href*="/profile/"],a[href*="/logout"]';

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
        $this->checkFillLogin();
    }

    $this->exts->waitTillPresent($this->check_login_success_selector, 15);
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
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if ($this->exts->urlContains('test/phone') && $this->exts->exists('#user-phone-input')) {
            $this->exts->account_not_ready();
        }
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'falsche zugangsdaten') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'incorrect login-data') !== false || strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), ' incorrect e-mail address or password') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->username_selector, 15);
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}