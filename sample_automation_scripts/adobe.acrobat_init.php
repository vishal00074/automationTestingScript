public $baseUrl = "https://acrobat.adobe.com/link/documents/files";
public $loginUrl = "https://acrobat.adobe.com/link/documents/files";
public $username_selector = "input#EmailPage-EmailField";
public $password_selector = "input#PasswordPage-PasswordField";
public $signin_button_selector = 'div.profile-signed-out button[data-test-id="unav-profile--sign-in"]';
public $continue_1_button_selector = 'button[data-id="EmailPage-ContinueButton"]';
public $continue_2_button_selector = 'button[data-id="PasswordPage-ContinueButton"]';
public $submit_button_selector = 'button[id="continue-btn-unknown login-button"]';
public $check_invalid_email_address = 'label[data-id="EmailPage-EmailField-Error"]';

public $check_login_success_selector = 'div#unav-profile';
public $login_tryout = 0;
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
    $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');

        $this->fillForm(0);
        $this->waitFor($this->check_login_success_selector, 25);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        sleep(2);
        
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->loginFailure();
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(1);

            $this->exts->click_by_xdotool($this->continue_1_button_selector);
            sleep(5); // Portal itself has one second delay after showing toast
            $this->checkFillTwoFactor();
        }
        $this->waitFor($this->password_selector, 5);
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->click_by_xdotool($this->continue_2_button_selector);
            sleep(5);
        } else {
            $this->waitFor($this->check_invalid_email_address, 5);
            if ($this->exts->querySelector($this->check_invalid_email_address) != null) {
                $this->exts->log("Invalid email address !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor()
{
    $this->waitFor('button[data-id="Page-PrimaryButton"]', 10);
    if ($this->exts->exists('button[data-id="Page-PrimaryButton"]')) {
        $this->exts->click_element('button[data-id="Page-PrimaryButton"]');
    }

    $two_factor_selector = 'input[class="spectrum-Textfield CodeInput-Digit"]';
    $two_factor_message_selector = 'div[data-id="ChallengeCodePage-email"]';
    $two_factor_submit_selector = '';
    $this->waitFor($two_factor_selector, 5);
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
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($two_factor_code);
            // $resultCodes = str_split($two_factor_code);
            // $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
            // foreach ($code_inputs as $key => $code_input) {
            //     if (array_key_exists($key, $resultCodes)) {
            //         $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
            //         $this->exts->moveToElementAndType('i[class*="cobeItem"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
            //         // $code_input->sendKeys($resultCodes[$key]);
            //     } else {
            //         $this->exts->log('"checkFillTwoFactor: Have no char for input #');
            //     }
            // }

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            // if ($this->exts->exists('span[role="checkbox"] input')) {
            //     $this->exts->click_by_xdotool('span[role="checkbox"] input');
            //     sleep(1);
            // }

            // $this->exts->click_by_xdotool($two_factor_submit_selector);
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
public function checkLogin()
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