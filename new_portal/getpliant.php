<?php // updated login and 2fa code alos optimize the script performance
// Server-Portal-ID: 1271397 - Last modified: 31.01.2025 07:43:53 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.getpliant.com/de/';
public $loginUrl = 'https://www.getpliant.com/de/';
public $invoicePageUrl = '';

public $username_selector = 'input[id="username"]';
public $password_selector = 'input[id="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name="action"]';

public $check_login_failed_selector = 'div[id="errors"]';
public $check_login_success_selector = 'a[href="/de/logout"]';

public $isNoInvoice = true;

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

    sleep(10);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        sleep(10);
        $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
        }
    ');
        sleep(5);
        if ($this->exts->exists('a[id="app-login"]')) {
            $this->exts->click_by_xdotool('a[id="app-login"]');
            sleep(1);
        }
        $this->fillForm(0);

        $this->exts->waitTillPresent('button[value="enter-otp-code"]');
        if ($this->exts->exists('button[value="enter-otp-code"]')) {
            $this->exts->moveToElementAndClick('button[value="enter-otp-code"]');
            sleep(2);
        }

        $this->exts->waitTillPresent('input#code');
        if ($this->exts->exists('input#code')) {
            $this->checkFillTwoFactor();
        }
    }
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->success();

        sleep(10);
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
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
    $this->exts->waitTillPresent($this->username_selector);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
           
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(10);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[id="code"]';
    $two_factor_message_selector = 'span[data-error-code="invalid-code"]';
    $two_factor_submit_selector = 'button[value="default"]';
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

