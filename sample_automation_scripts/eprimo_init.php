public $baseUrl = 'https://www.eprimo.de';
public $loginUrl = 'https://www.eprimo.de/anmelden';
public $invoicePageUrl = 'https://www.eprimo.de/kundenportal/posteingang';
public $username_selector = 'input#email, form input#login[data-format="email"]';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = '.Login form button[type="submit"]';
public $check_login_failed_selector = 'div.form-error-block, .Login form > div.error_message';
public $check_login_success_selector = 'a[href*="/auth/logout"], a[href*="/abgemeldet"], div.loginButtonWrapper--loggedIn';
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
    sleep(10);

    //Accept Cookies button
    $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();');
    sleep(3);
    $this->exts->capture('1-init-page');
    $this->exts->click_by_xdotool('button#navAnchor_kupo');
    sleep(5);
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);

        //Accept Cookies button
        $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();');
        sleep(3);

        $this->checkFillLogin();
        sleep(20);
        if ($this->exts->exists('#modal button[type="submit"]') && $this->exts->exists('[for="termsOfUse_confirmed"]')) {
            $this->exts->moveToElementAndClick('[for="termsOfUse_confirmed"]');
            sleep(2);
            $this->exts->moveToElementAndClick('#modal button[type="submit"]');
            sleep(15);
        }
        if ($this->exts->exists('a[href="/warenkorb"] + button span.Badged')) {
            $this->exts->moveToElementAndClick('a[href="/warenkorb"] + button span.Badged');
            sleep(5);
        }
        $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();');
        sleep(3);
        $this->exts->click_by_xdotool('button#navAnchor_kupo');
        sleep(5);
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

        $account_lock_selector = $this->exts->getElementByText($this->check_login_failed_selector, ['Ihr Konto ist temporÃƒÆ’Ã‚Â¤r gesperrt', 'Your account is temporarily blocked'], null, false);
        if ($account_lock_selector != null) {
            $this->exts->log(__FUNCTION__ . '::Account is temporarily blocked');
            $this->exts->account_not_ready();
        }

        $logged_in_failed_selector = $this->exts->getElementByText($this->check_login_failed_selector, ['login details are not correct', 'Anmeldedaten sind nicht korrekt', 'Zugangsdaten sind leider nicht korrekt'], null, false);
        if ($logged_in_failed_selector != null) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
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
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
        sleep(10);
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}