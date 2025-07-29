public $baseUrl = 'https://www.dkv-mobility.com/en/';
public $loginUrl = 'https://my.dkv-mobility.com/dkv-portal-webapp';
public $invoicePageUrl = 'https://my.dkv-mobility.com/customer/invoices/overview';
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'input[name="login"]';

public $check_login_success_selector = '//div[contains(@class, "wireframe-icon") and contains(text(), "logout")]';
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
    $this->waitFor($this->check_login_success_selector);
    
    if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
        sleep(5);
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(3);
        $this->waitFor($this->username_selector);
        $button_cookie = $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\')');
        if ($button_cookie != null) {
            $this->exts->execute_javascript("arguments[0].click()", [$button_cookie]);
            sleep(5);
        }
        $this->checkFillLogin();
        $this->waitFor($this->check_login_success_selector, 5);
        if ($this->exts->getElement('a#loginContinueLink') != null) {
            $this->exts->moveToElementAndClick('a#loginContinueLink');
            sleep(5);
        }
        $this->waitFor($this->check_login_success_selector, 5);
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
        }
        //click terms
        if ($this->exts->exists('input#kc-accept') && $this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->moveToElementAndClick('input#kc-accept');
            sleep(3);
                $this->waitFor($this->check_login_success_selector, 5);
            if ($this->exts->exists('input#kc-accept') && $this->exts->getElement($this->check_login_success_selector) == null) {
                $this->exts->moveToElementAndClick('input#kc-accept');
                sleep(3);
                    $this->waitFor($this->check_login_success_selector, 5);
            }
        }
        if ($this->exts->getElements('button#onetrust-accept-btn-handler') != null) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
    }

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
        if (strpos(strtolower($this->exts->extract('.kc-feedback-text')), 'account is disabled') !== false) {
            $this->exts->account_not_ready();
        }
        if ($this->exts->exists('input[name="password-new"]')) {
            $this->exts->account_not_ready();
        }
        if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
        }
        if ($this->exts->getElementByText('#dkv-login-container .alert-error, div#password-error', ['invalid username or password', 'tiger benutzername oder passwort', "Nom d'utilisateur ou mot de passe invalide.", 'utilisateur ou mot de passe invalide', 'Benutzername oder Passwort'], null, false) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
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