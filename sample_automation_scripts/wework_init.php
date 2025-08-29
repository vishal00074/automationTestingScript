public $baseUrl = 'https://accountcentral.wework.com/member/content/#/app/dashboard';
public $loginUrl = 'https://accountcentral.wework.com/member/content/login';
public $invoicePageUrl = 'https://accountcentral.wework.com/member/content/#/app/myaccount';
public $username_selector = 'input[type="email"], input[name="username"]';
public $password_selector = 'input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'div.submit-button:not(.disabled)';
public $check_login_failed_selector = 'div[class*="hint--error"], .fieldset-error-list-item, div.auth0-global-message';
public $check_login_success_selector = 'a[ng-click="logOut()"], div[data-testid="logout-item"]';
public $isNoInvoice = true;
public $legacyAccount = false;
public $restrictPages = 3;
public $totalInvoices = 0;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    $this->exts->log('Begin initPortal ' . $count);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->check_solve_cloudflare_page();

    $this->exts->waitTillAnyPresent([$this->check_login_success_selector, 'a[ng-click="loginWithAuth0WW()"]']);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        if ($this->exts->exists('a[ng-click="loginWithAuth0WW()"]')) {
            $this->exts->moveToElementAndClick('a[ng-click="loginWithAuth0WW()"]');
            sleep(10);
            $this->check_solve_cloudflare_page();
            $this->exts->waitTillPresent($this->username_selector);
            $this->checkFillLogin();
            $this->exts->waitTillAnyPresent([$this->check_login_success_selector, 'a[href="https://accounts.wework.com/"].redirect-button']);
            if ($this->exts->exists('a[href="https://accounts.wework.com/"].redirect-button')) {
                $this->legacyAccount = true;
                $this->exts->moveToElementAndClick('a[href="https://accounts.wework.com/"].redirect-button');
                sleep(3);
                $this->exts->waitTillAnyPresent([$this->check_login_success_selector, 'button[data-testid="login__button"]']);
                if ($this->exts->exists('button[data-testid="login__button"]')) {
                    $this->exts->moveToElementAndClick('button[data-testid="login__button"]');
                    sleep(5);
                    $this->exts->waitTillPresent($this->username_selector);
                    $this->checkFillLogin();
                    $this->exts->waitTillPresent($this->check_login_success_selector);
                }
            }
        }
    }

    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in. Current URL: ' . $this->exts->getUrl());
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->loginFailure(1);
        }
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div[class*="NotAuthorized_titleText"]', null, 'innerText')), 'you do not have permission to view') !== false) {
            $this->exts->account_not_ready();
        } else if (strpos(strtolower($this->exts->extract('div.NotAuthorized_textWrapper__3KiOn', null, 'innerText')), 'hast keine berechtigung zum anzeigen dieser informationen') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function check_solve_cloudflare_page()
{
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if (
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
        $this->exts->exists(selector_or_xpath: '#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
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


private function checkFillLogin()
{
    if ($this->exts->exists($this->username_selector)) {
        $this->exts->capture("2-login-page-new");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->moveToElementAndClick('button[name="action"]');
        sleep(7);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType('input[name="password"]', $this->password);
        sleep(1);

        if ($this->remember_me_selector != '' && $this->exts->exists($this->remember_me_selector))
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-new-filled");
        $this->exts->moveToElementAndClick('button[type="submit"]');
        sleep(5);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}