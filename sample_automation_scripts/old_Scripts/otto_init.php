public $baseUrl = 'https://portal.otto.market/';
public $loginUrl = 'https://portal.otto.market/';
public $invoicePageUrl = '';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name="login"]';

public $check_login_failed_selector = '.login form div.obc_alert--error div.obc_alert__text';
public $check_login_success_selector = 'a[href="/oauth2/sign_out"],b2b-flyout-menu.hydrated.portal_header-nav-item';

public $no_sales_invoice = 0;
public $only_sales_invoices = 0;
public $only_billing_invoice = 0;
public $isNoInvoice = true;
public $credit_note = 0;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->no_sales_invoice = isset($this->exts->config_array["no_sales_invoice"]) ? (int) @$this->exts->config_array["no_sales_invoice"] : $this->no_sales_invoice;
    $this->only_billing_invoice = isset($this->exts->config_array["only_billing_invoice"]) ? (int) @$this->exts->config_array["only_billing_invoice"] : $this->only_billing_invoice;
    $this->credit_note = isset($this->exts->config_array["credit_note"]) ? (int) @$this->exts->config_array["credit_note"] : $this->credit_note;
    $this->only_sales_invoices = isset($this->exts->config_array["only_sales_invoices"]) ? (int) @$this->exts->config_array["only_sales_invoices"] : $this->only_sales_invoices;

    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);

    $this->exts->waitTillAnyPresent('button.mkt-cct-button-accept-all');
    if ($this->exts->exists('button.mkt-cct-button-accept-all')) {
        $this->exts->click_element('button.mkt-cct-button-accept-all');
    }

    sleep(13);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);


        $this->checkFillLogin();


        $this->checkFillTwoFactor();
        sleep(12);


        $maxRetries = 3; // Define the maximum number of retries
        $retryDelay = 15; // Delay between retries in seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $mesg = strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText'));
            $this->exts->log('Attempt ' . $attempt . ': error message - ' . $mesg);

            // Check for specific error messages
            if (
                strpos($mesg, 'alse haben zu lange gebraucht, um sich anzumelden') !== false ||
                strpos($mesg, 'die aktion ist nicht mehr gültig. bitte fahren sie nun mit der anmeldung fort.') !== false
            ) {

                // Retry login
                $this->exts->log('Retrying login... Attempt ' . $attempt);
                $this->exts->openUrl($this->loginUrl);
                sleep($retryDelay);

                $this->checkFillLogin();


                $this->checkFillTwoFactor();


                // Check if login was successful after retry
                $this->exts->waitTillPresent($this->check_login_success_selector, 20);
                if ($this->exts->exists($this->check_login_success_selector)) {

                    break;
                }
            }

            // Log failure if max retries exceeded
            if ($attempt === $maxRetries) {
                $this->exts->log('Max retries reached. Unable to log in.');
            }
        }
    }


    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        if ($this->exts->exists('#cookieBannerButtonAccept')) {
            $this->exts->moveToElementAndClick('#cookieBannerButtonAccept');
        }
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $mesg = strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText'));
        if (strpos($mesg, 'benutzername und passwort stimmen nicht') !== false || strpos($mesg, 'tige e-mail oder passwort') !== false) {
            $this->exts->loginFailure(1);
        } elseif ($this->exts->urlContains('login-actions/required-action?execution=CONFIGURE_TOTP')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector, 20);
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        //$this->exts->moveToElementAndType($this->username_selector, $this->username);
        $this->exts->click_by_xdotool($this->username_selector);
        sleep(2);
        $this->exts->type_text_by_xdotool($this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->click_by_xdotool($this->password_selector);
        $this->exts->type_text_by_xdotool($this->password);
        //$this->exts->moveToElementAndType($this->password_selector, $this->password);
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
private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="otp"]';
    $two_factor_message_selector = 'form#kc-otp-login-form > h1, form#kc-otp-login-form > p';
    $two_factor_submit_selector = 'form#kc-otp-login-form input[name="login"]';
    $this->exts->waitTillPresent($two_factor_selector, 20);
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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
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