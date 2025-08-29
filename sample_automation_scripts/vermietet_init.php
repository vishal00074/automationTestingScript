public $baseUrl = 'https://portal.vermietet.de/';
public $loginUrl = 'https://portal.vermietet.de/authentication/login';
public $invoicePageUrl = 'https://portal.vermietet.de/shop/orders';
public $invoicePageUrl1 = 'https://www.immobilienscout24.de/rechnungsuebersicht/uebersicht';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $submit_login_selector = 'button#submit,button#loginOrRegistration';

public $check_login_failed_selector = 'div[class="status-message status-error margin-bottom"] > p';
public $check_login_success_selector = '//*[contains(text(),"Mein Konto")]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->loginUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);

    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector, null, 'xpath') == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(5);

        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');

        $this->checkFillLogin();

        $this->exts->waitTillPresent('span.intercom-post-close', 5);
        if ($this->exts->exists('span.intercom-post-close')) {
            $this->exts->moveToElementAndClick('span.intercom-post-close');
            sleep(5);
        }
    }

    if ($this->exts->getElement($this->check_login_success_selector, null, 'xpath') != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if ($this->exts->urlContains('required-email-confirmation')) {
            $this->exts->account_not_ready();
        }
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'Bitte geben Sie ein gültiges Passwort ein.') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="answer"][type="tel"]';
    $two_factor_message_selector = 'div[class*=mfa-email]';
    $two_factor_submit_selector = 'input[type="submit"]';
    $this->exts->log('Inside two factor function');
    $this->exts->waitTillPresent($two_factor_selector, 30);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3 || $this->exts->urlContains('verify/okta/email')) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->notification_uid = '';
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            sleep(2);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            sleep(2);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
            if ($this->exts->querySelector($two_factor_selector) == null) {
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

private function checkFillLogin()
{
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
        sleep(5);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
        sleep(5);
        $this->exts->log('Enter Two Factor');

        $this->checkFillTwoFactor();
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}