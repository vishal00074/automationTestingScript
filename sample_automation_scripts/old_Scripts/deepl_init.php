public $baseUrl = 'https://www.deepl.com/en/home';
public $loginUrl = 'https://www.deepl.com/en/login';
public $invoicePageUrl = 'https://www.deepl.com/en/your-account/billing';

public $username_selector = 'input#menu-login-username';
public $password_selector = 'input#menu-login-password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#menu-login-submit';

public $check_login_failed_selector = 'span[data-testid="fieldError"], div[data-testid="error-notification"]';
public $check_login_success_selector = 'button[id="usernav-button"]';

public $isNoInvoice = true;

/**<input type="password" name="password" autocomplete="current-password" class="textinput textInput" required id="id_password">

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);

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
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 15);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->check_solve_blocked_page();

            $this->exts->capture("1-login-page-filled");
            sleep(5);

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(10);
                $this->checkFillTwoFactor();
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


private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
    sleep(10);
    $element = 'iframe#challenge-widget';
    $this->exts->waitTillPresent($element, 20);
    if ($this->exts->exists($element)) {
        $this->exts->capture("blocked-by-cloudflare");

        $this->exts->click_by_xdotool($element, 30, 28);
        sleep(10);
    }
}


private function checkFillTwoFactor()
{
    $this->exts->capture("2-checking-two-factor");
    $two_factor_selector = 'input#menu-login-mfa';
    $two_factor_message_selector = 'form > p';
    $two_factor_submit_selector = 'button#menu-mfa-submit';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            sleep(1);
            $this->exts->moveToElementAndClick('input[name="trusted"]:not(:checked) + span');
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(5);
            if ($this->exts->exists('form.two-factor-form span[class*="_error-message_"]')) {
                $this->exts->log("Two factor can not solved");
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}