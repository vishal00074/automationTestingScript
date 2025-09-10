public $baseUrl = 'https://my.kaspersky.com/';
public $loginUrl = 'https://my.kaspersky.com/';
public $invoicePageUrl = 'https://my.kaspersky.com/MyDownloads#(modal:myaccount/orderHistory)';
public $username_selector = 'input[type="email"]';
public $password_selector = 'input[type="password"]';
public $remember_me_selector = 'input[data-at-selector="checkboxRememberMe"]';
public $submit_login_selector = 'button[type="submit"], button[data-at-selector="welcomeSignInBtn"]';
public $check_login_failed_selector = 'div.is-critical';
public $check_login_success_selector = 'li a[href*="Password"]';
public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);

    $this->waitFor('button[id*="AllowAll"]', 30);
    if ($this->exts->exists('button[id*="AllowAll"]')) {
        $this->exts->click_element('button[id*="AllowAll"]');
    }
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);

    $this->waitFor('button[id*="AllowAll"]', 30);
    if ($this->exts->exists('button[id*="AllowAll"]')) {
        $this->exts->click_element('button[id*="AllowAll"]');
    }
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->waitFor('button[id*="AllowAll"]', 20);
        if ($this->exts->exists('button[id*="AllowAll"]')) {
            $this->exts->click_element('button[id*="AllowAll"]');
        }

        sleep(35);
        $this->checkFillLogin();
        sleep(20);
        if ($this->exts->exists('button#reload-button')) {
            //redirected you too many times.
            $this->exts->moveToElementAndClick('button#reload-button');
            sleep(15);
            if ($this->exts->querySelector($this->password_selector) != null) {
                $this->checkFillLogin();
                sleep(20);
            }
        }
        $this->checkFillTwoFactor();
        sleep(15);
    }
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function isValidEmail($username)
{
    // Regular expression for email validation
    $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';


    if (preg_match($emailPattern, $username)) {
        return 'email';
    }
    return false;
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
    sleep(15);
    if ($this->exts->exists('div.signin-invite button[class*="signin"]')) {
        $this->exts->log("Open Login form");
        $this->exts->moveToElementAndClick('div.signin-invite button[class*="signin"]');
        sleep(15);
    }
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        if (!$this->isValidEmail($this->username)) {
            $this->exts->loginFailure(1);
        }
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);

        sleep(30);
        //Accept Cookies
        $this->waitFor('button[id*="AllowAll"]', 20);
        if ($this->exts->exists('button[id*="AllowAll"]')) {
            $this->exts->click_element('button[id*="AllowAll"]');
        }

        sleep(5);
        if ($this->exts->exists('label[data-at-selector="allowedAgreements"]')) {
            $this->exts->log("*************************Accept to use*************************");
            $this->exts->click_element('label[data-at-selector="allowedAgreements"]');
            sleep(5);
            $this->exts->moveToElementAndClick('button[data-at-selector="agreementProceedBtn"]');
            sleep(15);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

// 2 FA
private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name*="OtpCode"]';
    $two_factor_message_selector = 'wp-modal[analyticsmodalname*="OtpDialog"] div[class*="descri"]';
    $two_factor_submit_selector = '';

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            // $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}