public $baseUrl = 'https://mein.medatixx.de/dashboard.html';
public $loginUrl = 'https://mein.medatixx.de/login.html';
public $invoicePageUrl = 'https://mein.medatixx.de/dashboard/meine-dokumente.html';

public $username_selector = 'input#signInName';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#next';

public $check_login_failed_selector = 'div.error[aria-hidden="false"]';
public $check_login_success_selector = 'a[href="https://mein.medatixx.de/login/logout/"]';

public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->exts->waitTillPresent('form#webflow-portalLogin--portalLogin a.btn-login', 10);
        if ($this->exts->exists('form#webflow-portalLogin--portalLogin a.btn-login')) {
            $this->exts->click_element('form#webflow-portalLogin--portalLogin a.btn-login');
            sleep(5);
        }
        $this->fillForm(0);

        $this->exts->waitTillPresent('button#continue');
        if ($this->exts->exists('button#continue')) {
            $this->exts->click_element('button#continue');
            sleep(5);
        }

        // for 2fa
        $this->exts->waitTillPresent('button.sendCode');
        if ($this->exts->exists('button.sendCode')) {
            $this->exts->click_element('button.sendCode');
            sleep(5);
        }
        $this->checkFillTwoFactor();
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}

        $this->exts->success();
    } else {
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->log("Remember Me");
                $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->capture("1-login-page-filled");
            sleep(5);

            if ($this->exts->exists($this->submit_login_selector)) {
                $loginBtn = $this->exts->querySelector($this->submit_login_selector);
                $this->exts->execute_javascript("arguments[0].click();", [$loginBtn]);
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#VerificationCode';
    $two_factor_message_selector = 'div.verificationSuccessText';
    $two_factor_submit_selector = 'button.verifyCode';

    $this->exts->waitTillPresent($two_factor_selector, 15);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
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
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(5);
            $this->exts->moveToElementAndClick('button#continue');
            sleep(10);

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

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}