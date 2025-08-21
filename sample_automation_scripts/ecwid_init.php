public $baseUrl = "https://my.ecwid.com/cp/";
public $loginUrl = "https://my.ecwid.com/cp/";
public $username_selector = '.login-main .block-view-on input[name="email"]';
public $password_selector = '.login-main .block-view-on input[name="password"]';
public $submit_button_selector = '.login-main .block-view-on  button.btn-login-main';
public $login_tryout = 0;
public $restrictPages = 3;
public $download_lang = 'en';
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->download_lang = isset($this->exts->config_array["download_lang"]) ? trim($this->exts->config_array["download_lang"]) : $this->download_lang;

    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->baseUrl);
        sleep(15);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
        }
    } else {
        $this->exts->openUrl($this->loginUrl);
    }

    if (!$isCookieLoginSuccess) {
        sleep(10);
        $this->fillForm(0);
        sleep(10);

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            if (filter_var($this->username, FILTER_VALIDATE_EMAIL) === false) {
                $this->exts->loginFailure(1);
            }
            if (strpos(strtolower($this->exts->extract('div.bubble-error, div.bubble--error')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if (
                strpos(strtolower($this->exts->extract('.bubble__container .bubble__text')), 'no accounts associated') !== false ||
                strpos(strtolower($this->exts->extract('.bubble__container .bubble__text')), 'pas de compte associÃ© avec ce courriel') !== false ||
                strpos(strtolower($this->exts->extract('.bubble__container .bubble__text')), 'unter dieser e-mail-adresse ist kein konto registriert') !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->invoicePage();
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(1);
        if ($this->exts->getElement($this->username_selector) != null && $this->exts->getElement($this->password_selector) != null) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(4);
            if (!$this->isValidEmail($this->username)) {
                $this->exts->loginFailure(1);
            }

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            $this->exts->moveToElementAndClick('input[name="remember"]');
            $this->check_solve_blocked_page();
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(5);
        }

        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

function isValidEmail($username)
{
    $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    if (preg_match($emailPattern, $username)) {
        return 'email';
    }
    return false;
}

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->getElement('.menu li[id="EcwidTab:My_Profile"] , .menu li[id="EcwidTab:Sales"], a[href*="logoff"]') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

function invoicePage()
{
    if (!empty($this->exts->config_array['allow_login_success_request'])) {
        $this->exts->triggerLoginSuccess();
    }
    $this->exts->success();
}

private function check_solve_blocked_page()
{
    $unsolved_cloudflare_input_xpath = '//div[contains(@id, "sign_in")]//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//div[contains(@id, "sign_in")]//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if (
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ) {
        for ($waiting = 0; $waiting < 10; $waiting++) {
            sleep(2);
            if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                sleep(3);
                break;
            }
        }
    }

    if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
    }
}