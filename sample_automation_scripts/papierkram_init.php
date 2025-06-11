public $baseUrl = 'https://www.papierkram.de/';
public $loginUrl = 'https://www.papierkram.de/login/';

public $username_selector = 'form[action="/login"] input#user_email , form[action="/login"] input#user_new_email';
public $password_selector = 'form[action="/login"] input#user_new_password, form[action="/login"] input#user_password';
public $remember_me_selector = 'form[action="/login"] input#user_remember_me';
public $submit_login_selector = 'form[action="/login"] input[type="submit"]';

public $check_login_failed_selector = 'form[action="/login/"] span.text-danger';
public $check_login_success_selector = 'a[href="/logout"]';
public $subDomain_selector = '#loginRedirectForm input[name="subdomain"] , form[action="/login/"] input[name="subdomain"]';
public $submit_subDomain = '#loginRedirectForm button[type="submit"]';

public $isNoInvoice = true;
public $subDomain = '';

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    $this->subDomain = $this->exts->config_array["subdomain"];
    $this->exts->log('subDomain:: ' . $this->subDomain);
    // $this->subDomain = str_replace('.papierkram.de', '', $this->subDomain);
    // $this->exts->log('subDomain:: ' . $this->subDomain);

    if ($this->subDomain == '') {
        $this->exts->loginFailure(1);
    }
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        // $loginUrl = 'https://'.$this->exts->config_array["subdomain"].'.papierkram.de/login';
        // $this->exts->openUrl($loginUrl);
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('div#consent_manager-wrapper a.consent_manager-accept-all')) {
            $this->exts->moveToElementAndClick('div#consent_manager-wrapper a.consent_manager-accept-all');
            sleep(1);
        }
        $this->checkFillLogin();
        sleep(20);

        if ($this->exts->exists('form[action="/login/email_code"]')) {
            $this->checkFillTwoFactor();
        }
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
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'domain ist leider ung') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('form[action="/einstellungen/neujahr"]')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->subDomain_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->openUrl($this->subDomain);
        sleep(10);
        $this->exts->waitTillPresent($this->username_selector);

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '') {
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        }
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);

        // $this->exts->waitForCssSelectorPresent('div.toast-error div.toast-message', function() {
        // 	$this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Found login failed screen!!!! ");
        // 	if(stripos(strtolower($this->exts->extract('div.toast-error div.toast-message')), 'anmeldedaten oder sie sind deaktiviert') !== false) {
        // 		$this->exts->loginFailure(1);
        // 		sleep(5);
        // 	}

        // }, function() {
        // 	$this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Timed out waiting for login failure message");
        // }, 15);
        $this->exts->waitTillPresent('div.toast-error div.toast-message', 5);
        if (stripos(strtolower($this->exts->extract('div.toast-error div.toast-message')), 'anmeldedaten oder sie sind deaktiviert') !== false) {
            $this->exts->loginFailure(1);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#user_email_code_attempt';
    $two_factor_message_selector = 'form[action="/login/email_code"] p:nth-child(03)';
    $two_factor_submit_selector = 'input[name="commit"]';
    $two_factor_resend_selector = 'a#re-send-link';

    if ($this->exts->getElement($two_factor_selector) != null) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en .= ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de .= ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());

        if (!empty($two_factor_code)) {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code: " . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            sleep(1);

            if ($this->exts->exists($two_factor_resend_selector)) {
                $this->exts->moveToElementAndClick($two_factor_resend_selector);
                sleep(1);
            }

            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->getElement($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor cannot be solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}