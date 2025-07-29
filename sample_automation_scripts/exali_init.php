public $baseUrl = 'https://mein.exali.de/';
public $loginUrl = 'https://mein.exali.de/';
public $invoicePageUrl = 'URL_url_to_invoice_page';

public $username_selector = 'input[name="mebe_username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button#loginBtn';

public $check_login_failed_selector = 'div#div_fail, div.error--text';
public $check_login_success_selector = 'a#logoutButton';

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
    $this->exts->openUrl($this->baseUrl);
    sleep(3);
    $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector, 'div.user-login span.user-login__text']);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null && $this->exts->getElementByText('div.user-login span.user-login__text', ['logout'], null, false) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->checkFillLogin();
        $this->exts->waitTillPresent($this->check_login_success_selector);
    }

    if ($this->exts->exists('div.modal-header button.close')) {
        $this->exts->moveToElementAndClick('div.modal-header button.close');
        sleep(3);
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->getElementByText('div.user-login span.user-login__text', ['logout'], null, false) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->getElement('div.error--text') != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
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
        sleep(3);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
