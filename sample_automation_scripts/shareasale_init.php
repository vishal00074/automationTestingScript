public $baseUrl = 'https://account.shareasale.com/a-revenuereport.cfm';
public $loginUrl = 'https://account.shareasale.com/a-revenuereport.cfm';
public $invoicePageUrl = 'https://account.shareasale.com/a-revenuereport.cfm';

public $username_selector = 'div#sas-outer form#form1 input#username';
public $password_selector = 'div#sas-outer form#form1 input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'div#sas-outer form#form1 div.loginBox-cmd button[type="submit"]';

public $check_login_failed_selector = 'div.loginFailMsg';
public $check_login_success_selector = 'a[href*="/a-logoff.cfm"]';

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
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->check_solve_blocked_page();
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->check_solve_blocked_page();
        $this->checkFillLogin();
        sleep(20);
        $this->check_solve_blocked_page();
    }

    $tries = 0;
    while ($this->exts->exists('form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]') && $tries < 3) {
        $this->checkCaptcha($tries);
        sleep(10);
        $tries++;
    }
    if ($this->exts->exists('.password-new input[name="newPass"]')) {
        $this->exts->capture('account-must-change_password');
        $this->exts->account_not_ready();
    }

    if ($this->exts->exists('form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]')) {
        $this->exts->log(__FUNCTION__ . '::captcha not resolved after 3 tries');
        $this->exts->loginFailure();
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
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('input[src*="GoToMyAccount"]') && $this->exts->exists('form[action="completeverification.cfm"]')) {
            $this->exts->account_not_ready();
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

function checkCaptcha($count)
{
    $this->exts->log("Begin checkCaptcha " . $count);
    try {

        if ($this->exts->getElement('form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]') != null) {
            $this->exts->capture("1-checkCaptcha-" . $count);

            $this->exts->processCaptcha('img[src*="captchaImage"]', 'form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]');
            sleep(5);
            $this->exts->moveToElementAndClick('input[type="submit"]');
            sleep(5);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checkCaptcha " . $exception->getMessage());
    }
}
private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->refresh();
            sleep(10);

            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}