public $baseUrl = 'https://cargoboard.com/';
public $loginUrl = 'https://my.cargoboard.com/de/login';
public $invoicePageUrl = 'https://my.cargoboard.com/de/account/payment';

public $username_selector = '.form-login-email input[name="_username"], div#content input[name="email"]';
public $password_selector = '.form-login-email input[name="_password"], div#content input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = '.form-login-email input[type="submit"], div#content button[type="submit"]';

public $check_login_failed_selector = '.form-login-email div.alert.alert-danger';
public $check_login_success_selector = "//a[contains(., 'Log out') or contains(., 'Abmelden')]";

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    sleep(3);
    $this->waitFor($this->check_login_success_selector, 10);
    if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
        $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
    }
    $this->exts->capture('1-init-page');
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->queryXpath($this->check_login_success_selector) == null) {
        $this->checkFillLogin();
        sleep(3);
        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->queryXpath($this->check_login_success_selector) == null) {
            $this->exts->openUrl($this->loginUrl);
            sleep(3);
            $this->checkFillLogin();
            sleep(3);
        }
    }
    $this->waitFor($this->check_login_success_selector, 10);
    if ($this->exts->queryXpath($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector), 'Fehlerhafte Zugangsdaten') !== false) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->waitFor($this->password_selector, 10);
    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->click_by_xdotool($this->remember_me_selector);
        sleep(2);
        $this->solve_login_cloudflare();
        $this->exts->capture("2-login-page-filled");
        $this->exts->click_element($this->submit_login_selector);
        sleep(5);
        $this->solve_login_cloudflare();
        sleep(5);
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->click_element($this->submit_login_selector);
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

private function solve_login_cloudflare()
{
    $this->waitFor('form div.cf-turnstile#turnstile-captcha-box-login-password', 10);
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
    if ($this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password') && !$this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password.-success')) {
        $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
        $this->exts->click_by_xdotool('form div.cf-turnstile#turnstile-captcha-box-login-password', 30, 28);
        $this->waitFor('form div.cf-turnstile#turnstile-captcha-box-login-password', 10);
        if ($this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password') && !$this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password.-success')) {
            $this->exts->click_by_xdotool('form div.cf-turnstile#turnstile-captcha-box-login-password', 30, 28);
            sleep(20);
        }
        if ($this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password') && !$this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password.-success')) {
            $this->exts->click_by_xdotool('form div.cf-turnstile#turnstile-captcha-box-login-password', 30, 28);
            sleep(20);
        }
        if ($this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password') && !$this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password.-success')) {
            $this->exts->click_by_xdotool('form div.cf-turnstile#turnstile-captcha-box-login-password', 30, 28);
            sleep(20);
        }
    }
}