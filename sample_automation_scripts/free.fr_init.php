public $baseUrl = 'https://adsl.free.fr/liste-factures.pl';
public $loginUrl = 'https://subscribe.free.fr/login/';
public $invoicePageUrl = 'https://adsl.free.fr/liste-factures.pl';

public $username_selector = 'input[name="login"]';
public $password_selector = 'input[name="pass"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form#log_form input.login_button,button.login_button';

public $check_login_failed_selector = 'div.loginalert';
public $check_login_success_selector = 'a[href*="/logout.pl"], .monabo.mesfactures';

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
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();

        $this->waitFor($this->check_login_success_selector, 15);

        if (!$this->exts->exists($this->check_login_success_selector) && !$this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->refresh();
            sleep(20);
            if ($this->exts->exists($this->password_selector)) {
                $this->checkFillLogin();
                sleep(20);
            }
        }
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
        if (strpos($this->exts->extract($this->check_login_failed_selector), 'Identifiant ou mot de passe incorrect') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'sessions maximum atteint') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{

    $this->exts->waitTillPresent($this->username_selector, 10);
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '') {
            $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);
        }

        $this->exts->capture("2-login-page-filled");
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}




public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}