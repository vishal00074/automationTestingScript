public $baseUrl = 'https://service.handyvertrag.de/mytariff/invoice/showAll';
public $loginUrl = 'https://service.handyvertrag.de/';
public $invoicePageUrl = 'https://service.handyvertrag.de/mytariff/invoice/showAll';

public $username_selector = 'input#UserLoginType_alias';
public $password_selector = 'input#UserLoginType_password';
public $remember_me_selector = '';
public $submit_login_selector = 'div#buttonLogin a, div#buttonLogin, a[onclick="submitForm(\'loginAction\');"]';

public $check_login_failed_selector = 'div.error.s-validation';
public $check_login_success_selector = 'a#logoutLink, div#userData span.logout, #logoutLink a';

public $isNoInvoice = true;
public $restrictPages = 3;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();

    $this->exts->openUrl($this->baseUrl);

    $this->exts->capture('1-init-page');
    // $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 20);
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->checkFillLogin();
        sleep(15);
    }

    if ($this->exts->getElement('dialog a.c-overlay-close') != null) {
        $this->exts->moveToElementAndClick('dialog a.c-overlay-close');
        sleep(2);
    }
    if ($this->exts->getElement('div[role="dialog"] #consent_wall_optin') != null) {
        $this->exts->moveToElementAndClick('div[role="dialog"] #consent_wall_optin');
        sleep(2);
    }

    // $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 20);
    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'nicht korrekt') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector);
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '')
            $this->exts->click_by_xdotool($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}