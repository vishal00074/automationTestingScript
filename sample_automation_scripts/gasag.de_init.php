public $baseUrl = 'https://www.gasag.de/onlineservice/login';
public $loginUrl = 'https://www.gasag.de/onlineservice/login';
public $invoicePageUrl = 'https://www.gasag.de/onlineservice/postbox';

public $username_selector = 'input#bpcLoginUsername, input#loginUsername, input#signInName';
public $password_selector = 'input#bpcLoginPassword, input#loginPassword, form input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#loginBtn, button[type="submit"]';

public $check_login_failed_selector = 'form#localAccountForm div.error.pageLevel';
public $check_login_success_selector = 'li:not([data-hidden="true"]) a[href*="/meine-gasag/logout"], li:not([data-hidden="true"]) a[href*="/logout"] button, a[href*="/dashboard/"], li:not([data-hidden="true"]) a[href*="/onlineservice/"], img[src*="/logout"]';

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
    // $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null || $this->isExists($this->username_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(20);

        $mesg = strtolower(trim($this->exts->extract('[role="dialog"]', null, 'innerText')));
        $this->exts->log($mesg);
        if (strpos($mesg, 'falscheingaben gesperrt') !== false) {
            $this->exts->account_not_ready();
        }

        if (strpos($mesg, 'oder passwort falsch') !== false) {
            $this->exts->loginFailure(1);
        }

        if (strpos($mesg, 'leider ist ein fehler aufgetreten') !== false) {
            $this->exts->loginFailure(1);
        }
    }

    $this->exts->execute_javascript('var shadow = document.querySelector("#usercentrics-root").shadowRoot; shadow.querySelector("button[data-testid=\"uc-accept-all-button\"]").click();');
    sleep(30);
    if ($this->exts->getElement($this->check_login_success_selector) != null && !$this->isExists($this->username_selector)) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");
       
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'keine passenden anmeldung finden') !== false) {
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
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function isExists($selector = '')
{
    $safeSelector = addslashes($selector);
    $this->exts->log('Element:: ' . $safeSelector);
    $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
    if ($isElement) {
        $this->exts->log('Element Found');
        return true;
    } else {
        $this->exts->log('Element not Found');
        return false;
    }
}