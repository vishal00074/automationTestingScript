public $baseUrl = 'https://www.flyeralarm.com/de/shop/customer/orders';
public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = 'input[name="rememberMe"]:not(:checked) + label';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_success_selector = '.simplesubmenu a[href*="/index/logout"], a[href*="/logout"]';
public $isNoInvoice = true;
public $download_overview = 0;

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
    sleep(5);

    $this->waitFor($this->check_login_success_selector, 20);
    $this->exts->capture('1-init-page');

    $this->download_overview = isset($this->exts->config_array["download_overview"]) ? (int) $this->exts->config_array["download_overview"] : 0;

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (count($this->exts->getElements($this->check_login_success_selector)) == 0) {
        $this->exts->log('NOT logged via cookie');
        $this->checkFillLogin();
        sleep(30);
    }

    if ($this->exts->exists('button.iubenda-cs-accept-btn')) {
        $this->exts->moveToElementAndClick('button.iubenda-cs-accept-btn');
        sleep(10);
    }

    // then check user logged in or not
    if (count($this->exts->getElements($this->check_login_success_selector)) > 0) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos($this->exts->extract('.callout.alert'), 'Passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('#customerForm #submitCustomerData')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->username_selector);
        sleep(2);
        $this->exts->type_key_by_xdotool('ctrl+a');
        sleep(2);
        $this->exts->type_key_by_xdotool('Delete');
        sleep(2);
        $this->exts->type_text_by_xdotool($this->username);
        // $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(5);

        $this->exts->log("Enter Password");
        $this->exts->click_by_xdotool($this->password_selector);
        sleep(2);
        $this->exts->type_text_by_xdotool($this->password);
        // $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(3);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function waitFor($selector, $seconds = 30)
{
    for ($i = 1; $i <= $seconds && $this->exts->querySelector($selector) == null; $i++) {
        $this->exts->log('Waiting for Selector (' . $i . '): ' . $selector);
        sleep(1);
    }
}