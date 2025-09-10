public $baseUrl = 'https://build.fillout.com/login';
public $loginUrl = 'https://build.fillout.com/login';
public $invoicePageUrl = 'https://build.fillout.com/home/settings/billing';
public $username_selector = 'input[type="email"]';
public $password_selector = 'input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form button[type="submit"]';
public $check_login_failed_selector = 'form div[class*="bg-yellow"]';
public $check_login_success_selector = 'button[aria-haspopup="menu"], div[data-cy="home-page-account-menu"] button[id*="headlessui-menu-button"]';
public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->fillForm(0);

        if ($this->exts->querySelector('input[name="mfa-code"]') != null) {
            $this->checkFillTwoFactor();
        }

        // Call again 2FA process in case wrong code
        $incorrectTFA = strtolower($this->exts->extract('div[class*="text-yellow"]'));
        $this->exts->log(__FUNCTION__ . '::incorrectTFA : ' . $incorrectTFA);
        if (stripos($incorrectTFA, strtolower('Incorrect 2FA code')) !== false) {
            $this->checkFillTwoFactor();
        }
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {

        $incorrectTFA = strtolower($this->exts->extract('div[class*="text-yellow"]'));
        $this->exts->log(__FUNCTION__ . '::incorrectTFA : ' . $incorrectTFA);
        if (stripos($incorrectTFA, strtolower('Incorrect 2FA code')) !== false) {
            $this->exts->loginFailure(1);
        }

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

            $this->exts->capture("1-login-page-filled");
            sleep(5);

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="mfa-code"]';
    $two_factor_message_selector = 'div[class*="text-yellow"]';
    $two_factor_submit_selector = 'button[type="submit"]';
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
            sleep(10);
            if ($this->exts->exists('div[class*="errorMessage"]')) {

                $this->exts->capture("wrong 2FA code error-" . $this->exts->two_factor_attempts);
                $this->exts->log('The code you entered is incorrect. Please try again.');
            }

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