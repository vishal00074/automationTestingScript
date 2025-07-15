public $username_selector = 'div.login-form form input#loginemail';
public $password_selector = 'div.login-form form input#loginpassword';
public $remember_me_selector = 'input#rememberMe';
public $submit_login_selector = 'div.login-form form button[type="submit"]';

public $username_selector_1 = 'form#log_user input[name="user"]';
public $password_selector_1 = 'form#log_user input[name="pass"]';
public $remember_me_selector_1 = '';
public $submit_login_selector_1 = 'form#log_user [type="submit"]';

public $check_login_failed_selector = 'form#log_user div.error, div.login-form p[class*="is-invalid"]';
public $check_login_success_selector = 'span.s-header-user__avatar, div.bottom-nav-part img[src*="userAvatar"], button[data-identifier="app-nav__notifications"]';
public $custom_url = "https://webundstyle.eu.teamwork.com";

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    if (isset($this->exts->config_array["custom_url"]) && !empty($this->exts->config_array["custom_url"])) {
        $this->custom_url = trim($this->exts->config_array["custom_url"]);
    } else if (isset($this->exts->config_array["customUrl"]) && !empty($this->exts->config_array["customUrl"])) {
        $this->custom_url = trim($this->exts->config_array["customUrl"]);
    } else if (empty($this->custom_url)) {
        $this->custom_url = $this->custom_url;
    }


    if (strpos($this->custom_url, 'https://') === false && strpos($this->custom_url, 'http://') === false) {
        $this->custom_url = 'https://' . $this->custom_url;
    }


    $this->exts->log('custom_url    ' . $this->custom_url);


    if (strpos($this->custom_url, '?code=') !== false) {
        $this->custom_url = end(explode('?code=', $this->custom_url));
        if (strpos($this->custom_url, '%3A%2F%2F') !== false) {
            $this->custom_url = urldecode($this->custom_url);
        }
    }

    if (strpos($this->custom_url, '%') !== false) {
        $this->custom_url = preg_replace('/[\%]/', '', $this->custom_url);
    }

    if (substr($this->custom_url, -1) == '/') {
        $this->custom_url = substr($this->custom_url, 0, -1);
    }

    $this->baseUrl = $this->loginUrl = $this->custom_url;
    $this->exts->log('custom_url: ' . $this->custom_url);


    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    $this->exts->openUrl($this->baseUrl);
    sleep(25);

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->capture('not-logged-cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);

        if (!$this->isExists('div.login-form form, form#log_user')) {
            $this->exts->openUrl('https://www.teamwork.com/launchpad/login?continue=/launchpad/welcome');
            sleep(15);
        }

        if ($this->isExists('li.products-option a.btn.btn-green')) {
            $this->exts->moveToElementAndClick('li.products-option a.btn.btn-green');
            sleep(15);
        }
        $this->checkFillLogin();
        sleep(20);
        $this->checkFillTwoFactor();
        if ($this->isExists('div.w-product-list a[href="/"]')) {
            $this->exts->moveToElementAndClick('div.w-product-list a[href="/"]');
            sleep(5);
        }
        if ($this->isExists('#gdprConsent button')) {
            $this->exts->moveToElementAndClick('#gdprConsent button');
            sleep(3);
        }
        if (strpos($this->exts->getUrl(), 'teamwork.com/launchpad/welcome') !== false) {
            $this->exts->openUrl(explode('/launchpad/', $this->exts->getUrl())[0]);
            sleep(15);
        }
    }

    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->isExists('div.page-account-not-set-up')) {
            $this->exts->account_not_ready();
        }

        if ($this->isExists('div.page-login form [for="industry-category"]') || $this->isExists('div.page-login form [for="company-size"]')) {
            $this->exts->account_not_ready();
        }
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'the correct email or password') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
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
        sleep(5);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else if ($this->isExists($this->password_selector_1)) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector_1, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector_1, $this->password);
        sleep(5);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector_1);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector_1);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form.w-login-form input.w-auth-code__input';
    $two_factor_message_selector = 'form.w-login-form p.w-page-header__description';
    $two_factor_submit_selector = 'form.w-login-form button[type="submit"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
            $this->exts->getElement($two_factor_selector)->moveToElementAndType($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->getElement($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->notification_uid = '';
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