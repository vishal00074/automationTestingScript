public $baseUrl = 'https://www.zalando.de/';
public $loginUrl = 'https://www.zalando.de/login/';
public $invoicePageUrl = 'https://www.zalando.de/myaccount/orders/';
public $username_selector = 'input[inputmode="email"], input#lookup-email , input[name="login.email"]';
public $password_selector = 'form[name="login"] input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'form[name="login"] button[data-name="sso_login"]';
public $check_login_failed_selector = 'div[data-testid="login_error_notification"]';
public $check_login_success_selector = 'a.z-navicat-header_navToolItemLink-empty div svg path[data-name*="Layer 1"], a[href="/myaccount/"]';
public $isNoInvoice = true;
public $totalPage = 2;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_unexpected_extensions();
    $this->exts->openUrl('chrome://settings/help');
    sleep(5);
    $this->exts->capture('chrome-version');
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(5);
    $this->exts->openUrl($this->invoicePageUrl);
    sleep(10);
    $user_agent = $this->exts->executeSafeScript('return navigator.userAgent;');
    $this->exts->log('user_agent: ' . $user_agent);
    $this->exts->capture('1-init-page');

    $this->isExists('div[class*="navToolItem-profile"]');
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        //$this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->isExists('button#uc-btn-accept-banner')) {
            $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
            sleep(5);
        }
        $this->checkFillLogin();
        sleep(60);
        $this->checkFillTwoFactor();
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            sleep(120);
        }
    }

    // then check user logged in or not
    $this->isExists('div[class*="navToolItem-profile"]');
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
        $error_text = strtolower($this->exts->extract('form > div:nth-child(1) >  div[role="alert"] span'));
        $this->exts->log('error_text:: '. $error_text);

        if ($this->isExists($this->check_login_failed_selector)) {
            $this->exts->loginFailure(1);
        } else if (stripos($error_text, 'Bitte gib eine gültige') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function disable_unexpected_extensions()
{
    $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
    sleep(2);
    $this->exts->executeSafeScript("
    if(document.querySelector('extensions-manager') != null) {
        if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
            var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
            if(disable_button != null){
                disable_button.click();
            }
        }
    }
");
    sleep(1);

    $this->exts->openUrl('chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo');
    sleep(1);
    $this->exts->executeSafeScript("
    if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
        document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
    }
");

    sleep(2);
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
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
    $this->waitFor($this->username_selector, 10);
    if ($this->exts->querySelector($this->username_selector) != null) {
        sleep(5);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(5);

        $this->exts->log("Submit Username");
        sleep(4);
        if ($this->isExists('button[data-testid="verify-email-button"]')) {
            $this->exts->click_by_xdotool('button[data-testid="verify-email-button"]');
        }

        sleep(5);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(5);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(5);

        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#otp.passcode';
    $two_factor_message_selector = 'span[data-testid="otp.emailInfo"] font';
    $two_factor_submit_selector = 'button[data-testid="otp.submitButton"]';

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        $twoFaMessage = $this->exts->executeSafeScript('document.querySelector("' . $two_factor_message_selector . '").innerText');

        if ($twoFaMessage != null) {
            $this->exts->two_factor_notif_msg_en = "";
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
            $code_input = $this->exts->querySelector($two_factor_selector);
            $code_input->sendKeys($two_factor_code);
            $this->exts->log('"checkFillTwoFactor: Entered code ' . $two_factor_code);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
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