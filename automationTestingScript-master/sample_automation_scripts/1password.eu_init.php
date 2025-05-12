public $baseUrl = 'https://my.1password.eu/signin?landing-page=%2Fbilling%2Finvoices';
public $loginUrl = 'https://my.1password.eu/signin?landing-page=%2Fbilling%2Finvoices';
public $invoicePageUrl = 'https://my.1password.eu/signin?landing-page=%2Fbilling%2Finvoices';

public $username_selector = 'input[type="email"]';
public $user_secretKey_selector = 'input#account-key';
public $secret_key;
public $password_selector = 'input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = '.signin-actions button,button[type="submit"], main#signin-form button';

public $check_login_failed_selector = 'p[data-test="validation-message"],p.error-description';
public $check_login_success_selector = 'a[name="myreicheltstatusbarlink"]';

public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{
    $this->secret_key = isset($this->exts->config_array['secret_key']) ? trim($this->exts->config_array['secret_key']) : '';
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);


    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } elseif (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'secret') !== false) {
            $this->exts->log("Wrong Secret Key !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 10);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(2);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(4);
            }
            $this->exts->waitTillPresent($this->user_secretKey_selector, 30);

            if (isset($this->exts->config_array['secret_key']) || $this->secret_key != '') {
                $this->exts->moveToElementAndType($this->user_secretKey_selector, $this->secret_key);
            } else {
                $this->checkFillTwoFactor();
            }
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }
            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
function checkLogin()
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
private function checkFillTwoFactor()
{
    $two_factor_selector = $this->user_secretKey_selector;
    $two_factor_message_selector = '';
    $two_factor_submit_selector = 'button[type="submit"][name="action"]';
    $this->exts->waitTillPresent($two_factor_selector, 10);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
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
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            sleep(2);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


            //$this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
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