public $baseUrl = 'https://artlist.io/account';
public $loginUrl = 'https://artlist.io/page/signin';
public $invoicePageUrl = 'https://artlist.io/account';

public $username_selector = 'form#loginForm input#logemail, form input[type=email]';
public $password_selector = 'form#loginForm input#logpassword, form input[type=password]';
public $remember_me_selector = '';
public $submit_login_btn = 'form#loginForm button#btnlogin, form button[type=submit]';

public $checkLoginFailedSelector = 'label#ermsg a';
public $checkLoggedinSelector = '.login a#user-logined-btn:not([style*="display"]):not([style*="none"]), #temporary-navbar .group > .absolute';

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->capture("Home-page-without-cookie");
    sleep(1);
    $this->exts->openUrl($this->loginUrl);
    sleep(30);
    $this->check_solve_cloudflare_page();
    if ($this->checkLoggedIn()) {
        // If user has logged in via cookies, call waitForLogin
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->exts->moveToElementAndClick('#cookiescript_accept');
        sleep(1);
        if ($this->exts->exists('iframe.ab-modal-interactions')) {
            $this->exts->makeFrameExecutable('iframe.ab-modal-interactions')->moveToElementAndClick('button#braze_closeBtn');
            sleep(1);
        }
        $this->waitForLogin();
    } else {
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');
        $this->exts->openUrl($this->loginUrl);
        sleep(20);
        $this->check_solve_cloudflare_page();
        $this->exts->moveToElementAndClick('#cookiescript_accept');
        sleep(3);
        $this->waitForLoginPage(0);
    }
}

private function waitForLoginPage($count)
{
    if ($this->exts->exists($this->password_selector)) {
        sleep(5);
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->log($this->username);
        if ($this->exts->getElement($this->username_selector) != null) {
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
        }
        sleep(1);
        $this->exts->log("Enter Password");
        $this->exts->log($this->password);
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
        }
        sleep(2);

        $this->exts->capture("1-filled-login");

        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(2);
        for ($i = 0; $i < 10 && $this->exts->getElement('//span[contains(text(),"Password is a required field")]') == null; $i++) {
            sleep(1);
        }
        // moveToElementAndType not work
        if ($this->exts->getElement('//span[contains(text(),"Password is a required field")]') != null) {
            $this->exts->click_by_xdotool($this->password_selector, 2, 3);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);
            $this->exts->capture("1-filled-login-1");

            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(15);
        }
        $this->waitForLogin();
    } else {
        $this->exts->log('Timeout waitForLoginPage');
        $this->exts->capture("2-login-page-not-found");
        $this->exts->loginFailure();
    }
}

private function waitForLogin()
{

    if ($this->checkLoggedIn()) {
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (count($this->exts->getElements($this->checkLoginFailedSelector)) > 0) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('span.text-error[data-test-id="InputControlTemplate__hintText"]')), 'email not found') !== false || strpos(strtolower($this->exts->extract('span.text-error[data-test-id="InputControlTemplate__hintText"]')), 'wrong password') !== false || strpos(strtolower($this->exts->extract('span.text-error[data-test-id="InputControlTemplate__hintText"]')), 'valid email') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkLoggedIn()
{
    $isLoggedIn = false;
    if ($this->exts->exists('div[data-testid="AvatarMenu"]')) {
        $this->exts->moveToElementAndClick('div[data-testid="AvatarMenu"]');
        sleep(3);
    }
    $selector_elementSignOut = 'li[role="menuitem"]';
    $elementSignOut = $this->exts->getElementByText($selector_elementSignOut, 'Sign Out', null, false);
    if ($elementSignOut != null || $this->exts->exists('a[href="/account/profile"]')) {
        $isLoggedIn = true;
    } else if ($this->exts->exists('div[data-testid="AvatarMenu"]')) {
        $this->exts->moveToElementAndClick('div[data-testid="AvatarMenu"]');
        sleep(3);
        $elementSignOut = $this->exts->getElementByText('li[role="menuitem"]', 'Sign Out', null, false);
        if ($elementSignOut != null || $this->exts->exists('a[href="/account/profile"]')) {
            $isLoggedIn = true;
        }
    }
    return $isLoggedIn;
}

private function check_solve_cloudflare_page()
{
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
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