public $baseUrl = 'https://mein.ewe.de/ewetelcss/secure/billingOverview.xhtml';
public $loginUrl = 'https://mein.ewe.de/ewetelcss/secure/billingOverview.xhtml';
public $invoiceECarePageUrl = 'https://tkgk.mein.ewe.de/eCare/billing';
public $invoicePageUrl = 'https://mein.ewe.de/ewetelcss/secure/billingOverview.xhtml';
public $username_selector = 'div#formLogin input#username';
public $password_selector = 'div#formLogin input#password';
public $submit_login_selector = 'div#formLogin button[type="submit"]';
public $check_login_failed_selector = 'form#frm_login .css__errorbubble, #error_INVALID';
public $check_login_e_care_success_selector = 'button[class*="_header-ecare_btnLogout"]';
public $check_login_success_selector = 'a[id="logoutLink"]';
public $isNoInvoice = true;
public $err_msg = '';
public $metadataError = 'fieldset';
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->loadCookiesFromFile();
    // Load cookies
    sleep(20);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $this->exts->waitTillAnyPresent([$this->check_login_e_care_success_selector, $this->check_login_success_selector], 20);
    if ($this->exts->querySelector($this->check_login_e_care_success_selector) == null || $this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(5);
        }
        $this->checkFillLogin();
        sleep(10);
    }

    sleep(20);
    $this->exts->waitTillAnyPresent([$this->check_login_e_care_success_selector, $this->check_login_success_selector], 20);
    if ($this->exts->querySelector($this->check_login_e_care_success_selector) != null || $this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'die eingegebenen zugangsdaten sind nicht korrekt') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->err_msg, 'Die eingegebenen Zugangsdaten sind nicht korrekt.') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract($this->metadataError, null, 'innerText'), 'Metadata not found') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector, 20);
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(2);

        if ($this->exts->exists($this->check_login_failed_selector, 15)) {
            $this->err_msg = $this->exts->extract($this->check_login_failed_selector, null, 'innerText');
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}