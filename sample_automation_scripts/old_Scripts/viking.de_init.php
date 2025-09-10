public $baseUrl = 'https://www.viking.de/de/login';
public $loginUrl = 'https://www.viking.de/de/login';
public $invoicePageUrl = 'https://service.vattenfall.de/meinerechnung';

public $username_selector = 'input[id="username"]';
public $password_selector = 'input[id="password"]';
public $remember_me_selector = 'input[id="rememberMe"]';
public $submit_login_selector = 'button[id="loginSubmit"]';

public $check_login_failed_selector = 'div.alert.alert-danger';
public $check_login_success_selector = 'input.btn-logout, button#logoutButton';
public $invoice_portal_selector = 'a[href*="/my-account/e-billing-info"]';
public $invoice_portal_page_selector = 'a[class="odExternalLink"], a[href*="e-billing"]';
public $invoice_selector = 'body > table > tbody > tr > td:nth-child(2) > table > tbody > tr:nth-child(4) > td > table > tbody > tr > td:nth-child(3) > font > center > table > tbody > tr > td > table > tbody > tr:nth-child(4) > td > table > tbody > tr';
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{ // starting point for any portal script, do not change method name as it referenced from outside
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile(); // This function loads any cookies/session storage existing for this credential (in your server this loads cookies from /home/ubuntu/selenium/screens/cookie.txt)
    sleep(1);
    $this->exts->openUrl($this->baseUrl); //Load same url again for cookies to reflect () exts is the util object that has useful functions.)
    sleep(10);
    $this->exts->capture('1-init-page'); // capture the screen for debugging purposes, not: do not capture too many screens

    // If cookie login didn't work, clear cookie, open the login url and login again
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged in via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if (strpos(strtolower($this->exts->extract('body h1')), 'access denied')) {
            $this->exts->refresh();
            sleep(15);
        }
        if (strpos(strtolower($this->exts->extract('body h1')), 'access denied') !== false) {
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
        }
        $this->checkFillLogin();
    }


    $this->waitFor($this->check_login_failed_selector, 10);
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
        $error_text = $this->exts->extract($this->check_login_failed_selector, null, 'innerText');
        $this->exts->log('Error Text:: ' . $error_text);
        if (strpos($error_text, 'Ihrem korrekten Benutzernamen oder E-Mail Adresse ein') !== false) {
            $this->exts->loginFailure(1); // param 1 means, userid/pwd is definitely wrong, don't call this unless you're sure the credentials are incorrect
        } else {
            $this->exts->loginFailure(); // unknown reason, so call loginFailed with no params
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
    sleep(10);
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
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

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}