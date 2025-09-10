public $baseUrl = "https://motionarray.com/";
public $loginUrl = "https://motionarray.com/account/login";
public $invoiceUrl = "https://motionarray.com/account/invoices/";
public $username_selector = 'input#login-email, form input[type="email"]';
public $password_selector = 'input#login-password, form input[type="password"]';
public $submit_button_selector = '.login-form button[type="submit"], form button[type="submit"]';
public $check_login_success_selector = 'a[href*="/logout"], a[href="/account/details/"]';
public $login_tryout = 0;
public $restrictPages = 3;


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    $this->waitFor($this->check_login_success_selector);
    sleep(5);
    $this->check_solve_cloudflare_page();
    sleep(20);
    if ($this->exts->getElement('.adroll_consent_banner a#adroll_consent_accept div#adroll_allow') != null) {
        $this->exts->moveToElementAndClick('.adroll_consent_banner a#adroll_consent_accept div#adroll_allow');
        sleep(3);
    }
    if ($this->isExists('#cookiescript_accept')) {
        $this->exts->moveToElementAndClick('#cookiescript_accept');
        sleep(3);
    }
    $this->exts->capture('1-init-page');

    // If the user has not logged in from cookie, do login.
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged in via cookie');
        $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
        $this->waitFor($this->check_login_success_selector);
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->capture("LoginFailed");
        if (strpos($this->exts->extract('form.login-form div[class*="bg-ma-red"], p[class*="text-red"]', null, 'innerText'), 'passwor') !== false) {
            $this->exts->log("Login fail!!!!: " . $this->exts->extract('form.login-form div[class*="bg-ma-red"], p[class*="text-red"]', null, 'innerText'));
            $this->exts->loginFailure(1);
        }
        $this->exts->loginFailure();
    }
}

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        $this->waitFor($this->username_selector, 10);
        if ($this->isExists($this->username_selector) && $this->isExists($this->password_selector)) {
            sleep(1);
            $this->login_tryout = (int) $this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(3);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            $this->exts->moveToElementAndClick('input[name="remember_me"]');
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(5);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
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

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function check_solve_cloudflare_page()
{
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if (
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
        $this->isExists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ) {
        for ($waiting = 0; $waiting < 10; $waiting++) {
            sleep(2);
            if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                sleep(3);
                break;
            }
        }
    }

    if ($this->exts->getElement($unsolved_cloudflare_input_xpath) != null) {
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if ($this->exts->getElement($unsolved_cloudflare_input_xpath) != null) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if ($this->exts->getElement($unsolved_cloudflare_input_xpath) != null) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
    }
}