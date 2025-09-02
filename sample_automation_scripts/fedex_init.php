public $baseUrl = 'https://www.fedex.com/online/billing/cbs/invoice';
public $loginUrl = 'https://www.fedex.com/en-us/billing-online.html';
public $invoicePageUrl = 'https://www.fedex.com/online/billing/cbs/invoices';

public $username_selector = 'input#username,input#userId';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#login_button,button#login-btn';

public $check_login_failed_selector = '#invalidCredentials';
public $check_login_success_selector = 'a[onclick*="Logout"]';

public $account_numbers = '';
public $restrictPages = 3;

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    $this->account_numbers = isset($this->exts->config_array["account_numbers"]) ? trim($this->exts->config_array["account_numbers"]) : $this->account_numbers;

    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->clearChrome();

        for ($i = 0; $i <= 5; $i++) {
            $this->exts->openUrl($this->baseUrl);
            $this->waitFor('.js-modal-close');
            if ($this->exts->exists('.js-modal-close')) {
                $this->exts->moveToElementAndClick('.js-modal-close');
                sleep(5);
            }

            if ($this->exts->exists('button.fxg-gdpr__accept-all-btn')) {
                $this->exts->moveToElementAndClick('button.fxg-gdpr__accept-all-btn');
                sleep(5);
            }



            $this->exts->moveToElementAndClick('div#global-login-wrapper');
            $this->waitFor('a[href*="/secure-login"]');
            $this->exts->moveToElementAndClick('a[href*="/secure-login"]');
            $this->exts->log(__FUNCTION__ . '::Try login attempt: ' . ($i + 1));

            $this->checkFillLogin();

            $this->exts->log("initiate 2FA check");
            $this->checkFillTwoFactor();
            $this->waitFor('button#cancelBtn', 10);
            if ($this->exts->exists('button#cancelBtn')) {
                $this->exts->moveToElementAndClick('button#cancelBtn');
                sleep(10);
            }
            if ($this->exts->exists('button#retry-btn')) {
                $this->exts->moveToElementAndClick('button#retry-btn');
                sleep(10);
            }
            if ((!$this->exts->exists($this->password_selector) && $this->exts->exists($this->check_login_success_selector)) || $this->exts->exists($this->check_login_failed_selector)) {
                break;
            }
        }
    }

    if ($this->exts->exists('button#cancelBtn')) {
        $this->exts->moveToElementAndClick('button#cancelBtn');
    }

    // then check user logged in or not
    if ($this->checkLogin()) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(15);
        if ($this->exts->urlContains('login')) {
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
        }
        if ($this->exts->exists('button[aria-label*="close"]')) {
            $this->exts->moveToElementAndClick('button[aria-label*="close"]');
            sleep(5);
        }
        $this->checkMultiAccounts();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Login incorrect') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->getElement('#invalidCredentials') != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->waitFor($this->password_selector);
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->exts->getElement($this->remember_me_selector) != null) {
            $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(5);
        }

        $this->exts->capture("2-login-page-filled");

        if ($this->exts->getElement($this->submit_login_selector) != null) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
        sleep(15);
        if ($this->exts->getElement('button[aria-label="close"]') != null) {
            $this->exts->moveToElementAndClick('button[aria-label="close"]');
            $this->exts->log('2FA popup closed');
            sleep(5);
        }
        if ($this->exts->getElement($this->password_selector) != null && !$this->exts->getElement($this->check_login_failed_selector) != null && $this->exts->extract($this->password_selector, null, 'value') != null) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function checkFillTwoFactor()
{
    $this->exts->log("checkFillTwoFactor");

    $two_factor_content_selector = '';

    $two_factor_selector = 'div[class*="fdx-c-single-digits__item"]';
    $two_factor_selector_shadow = 'return document.querySelector("fdx-authenticate").shadowRoot.querySelector("div[class*=\'fdx-c-single-digits__item\']")';

    $two_factor_message_selector = 'h2[id="verifySubtitleCall-email"]';
    $two_factor_message_selector_shadow = 'return document.querySelector("fdx-authenticate").shadowRoot.querySelector("h2[id=\'verifySubtitleCall-email\']")';

    $two_factor_submit_selector = 'button[id="submit-btn"]';
    $two_factor_submit_selector_shadow = 'return  document.querySelector("fdx-authenticate").shadowRoot.querySelector("button[id=\'submit-btn\']")';

    $two_factor_resend_selector = 'a[id="requestCode-btn"]';
    $two_factor_resend_selector_shadow = 'return document.querySelector("fdx-authenticate").shadowRoot.querySelector("a[id=\'requestCode-btn\']")';

    $this->exts->executeSafeScript('fdx-authenticate');
    if ($this->exts->executeSafeScript($two_factor_selector_shadow) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->capture("2.1-two-factor");
        $this->exts->log("2.1-two-factor");

        $this->exts->two_factor_notif_msg_en = $this->exts->execute_javascript('document.querySelector("fdx-authenticate").shadowRoot.querySelector("h2[id=\'verifySubtitleCall-email\']").innerText');

        if ($this->exts->two_factor_notif_msg_en != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en;
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
            $resultCodes = str_split($two_factor_code);
            $two_factor_selector = 'document.querySelector("fdx-authenticate").shadowRoot.querySelector("div[class*=\'fdx-c-single-digits__item\']")';
            $this->exts->execute_javascript('
    var inputs = document.querySelector("fdx-authenticate").shadowRoot.querySelectorAll(".fdx-c-single-digits__item input");
    var resultCodes = "' . $two_factor_code . '";
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].value = resultCodes[i] || ""; // If resultCodes[i] is undefined, set empty string
    }
');

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            //$this->exts->moveToElementAndClick($two_factor_submit_selector);
            $this->exts->execute_javascript('document.querySelector("fdx-authenticate").shadowRoot.querySelector("button[id=\'submit-btn\']").click()');
            sleep(15);

            if ($this->exts->getElement($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public  function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

private function checkMultiAccounts()
{
    if (!empty($this->exts->config_array['allow_login_success_request'])) {
        $this->exts->triggerLoginSuccess();
    }

    $this->exts->success();
}