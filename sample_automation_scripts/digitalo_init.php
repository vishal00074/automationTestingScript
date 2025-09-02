public $baseUrl = 'https://www.digitalo.de/my/orders.html';
public $loginUrl = 'https://www.digitalo.de/my/orders.html';
public $invoicePageUrl = 'https://www.digitalo.de/my/orders.html';

public $username_selector = 'input#auth_login_email, form#login_form input[type="email"]';
public $password_selector = 'input#auth_login_password, form#login_form input[name="password_login"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name="auth_submit"], form#login_form button[name="btn_login"]';

public $check_login_failed_selector = '.message_stack .callout--alert';
public $check_login_success_selector = 'a[href*="/user/logoff.html"], a[href*="/auth/logout.html"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    // sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    if ($this->exts->exists('a[href*="/myracloud-captcha"]')) {
        $this->exts->moveToElementAndClick('a[href*="/myracloud-captcha"]');
        sleep(10);
        if ($this->exts->exists('img#captcha_image')) {
            $this->exts->processCaptcha('img#captcha_image', 'input#captcha_code');
            sleep(8);
            $this->exts->moveToElementAndClick('input[name="submit"]');
            sleep(3);
        }
    }
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('a[href*="/myracloud-captcha"]')) {
            $this->exts->moveToElementAndClick('a[href*="/myracloud-captcha"]');
            sleep(10);
            if ($this->exts->exists('img#captcha_image')) {
                $this->exts->processCaptcha('img#captcha_image', 'input#captcha_code');
                sleep(8);
                $this->exts->moveToElementAndClick('input[name="submit"]');
                sleep(10);
            }
        }
        if ($this->exts->exists('button.js_button_cookie_consent, span.btn.btn-cookie-consent')) {
            $this->exts->moveToElementAndClick('button.js_button_cookie_consent, span.btn.btn-cookie-consent');
            sleep(5);
        }
        if ($this->exts->exists('button[data-cookie_consent="1"]')) {
            $this->exts->moveToElementAndClick('button[data-cookie_consent="1"]');
            sleep(5);
        }
        if ($this->exts->exists('div[data-toggle="js_login_pane"], button[data-toggle="js_login_pane"]')) {
            $this->exts->moveToElementAndClick('div[data-toggle="js_login_pane"], button[data-toggle="js_login_pane"]');
            sleep(5);
        }
        $this->checkFillLogin();
        sleep(20);
    }

    if ($this->exts->exists('#js_button_security_score_dont_show_again')) {
        $this->exts->click_element('#js_button_security_score_dont_show_again');
        sleep(5);
    }

    if ($this->exts->exists('button[id="js_my_navigation"]')) {
        $this->exts->click_element('button[id="js_my_navigation"]');
        sleep(5);
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
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
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
        sleep(5);
        if ($this->exts->exists($this->submit_login_selector) && strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') == false) {
            $submit_btn = $this->exts->getElement($this->submit_login_selector);
            try {
                $this->exts->log('Click submit button');
                $submit_btn->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click submit button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$submit_btn]);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}