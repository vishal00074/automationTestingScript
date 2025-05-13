<?php // updated login 
// Server-Portal-ID: 1750704 - Last modified: 12.02.2025 06:19:17 UTC - User: 1

public $baseUrl = 'https://moncompte.laposte.fr';
public $loginUrl = 'https://moncompte.laposte.fr';
public $invoicePageUrl = '';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = 'input#rememberMe';
public $submit_login_selector = 'input#submit';

public $check_login_failed_selector = 'div[class="message error"]';
public $check_login_success_selector = 'a[href="/espaceclient/services/logout"]';

public $isNoInvoice = true;

/**

* Entry Method thats called for a portal

* @param Integer $count Number of times portal is retried.

*/
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    // $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);

    $this->exts->waitTillPresent('button[id="footer_tc_privacy_button"]', 10);

    if($this->exts->exists('button[id="footer_tc_privacy_button"]')){
    $this->exts->click_by_xdotool('button[id="footer_tc_privacy_button"]');
    sleep(5);
    }

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->clearChrome();
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);


        $this->exts->waitTillPresent('button[id="footer_tc_privacy_button"]', 10);
        if($this->exts->exists('button[id="footer_tc_privacy_button"]')){
        $this->exts->click_by_xdotool('button[id="footer_tc_privacy_button"]');
        sleep(5);
    }

    $this->fillForm(0);
    if ($this->exts->querySelector($this->username_selector) != null) {
    for ($i = 0; $i < 5; $i++) {
        if($this->exts->querySelector($this->username_selector) == null){
          break;
        }
        $count = $i + 1;
        $this->fillForm($count);
        sleep(10);
        }
    }


    $this->checkFillTwoFactor();
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

    // Final, check no invoice
    if ($this->isNoInvoice) {
        $this->exts->no_invoice();
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

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

        $this->exts->execute_javascript("document.querySelector('input#li-antibot-token').value = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJyZXF1ZXN0SWQiOiI4M2IxOTMxM2IwOWU0YzQzYWQxYzkwOWRlZGIzYTA3MCIsImludmlzaWJsZUNhcHRjaGFJZCI6IjMyYTI2NGNjLThkZmYtNDNmNS1iMTQ2LTVjYmMzN2MwN2ExMCIsImFudGlib3RJZCI6ImQyNzE1NjUwMjk0OTQ4MjJhNDQ1ZWQxYTY1NjhkZWUzIiwiYW50aWJvdE1ldGhvZCI6IkNBUFRDSEEiLCJleHAiOjE3Mzc1MzEwMjgsImlhdCI6MTczNzUzMDcyOCwiY2FwdGNoYUlkIjoiYzk5Y2ZjMWEzMDZlNDg4YjllZTM1ODBlY2U5YmQyYTkifQ.QpzZ52-c6fDmKGfhgAOHMxGMrXmRk_ofpIZ3xkPjqiQ';");    

        $this->exts->capture("1-pre-login");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);

        $this->exts->log("Click Remember Me");
        $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(1);

        $this->exts->capture("1-login-page-filled");
        sleep(5);

        if($this->exts->exists('button[id="footer_tc_privacy_button"]')){
        $this->exts->click_by_xdotool('button[id="footer_tc_privacy_button"]');
        sleep(5);
        }

        $this->exts->waitTillPresent('input#li-antibot-token');
        if ($this->exts->exists('input#li-antibot-token')) {
            $this->exts->execute_javascript("document.querySelector('input#li-antibot-token').value = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJyZXF1ZXN0SWQiOiI4M2IxOTMxM2IwOWU0YzQzYWQxYzkwOWRlZGIzYTA3MCIsImludmlzaWJsZUNhcHRjaGFJZCI6IjMyYTI2NGNjLThkZmYtNDNmNS1iMTQ2LTVjYmMzN2MwN2ExMCIsImFudGlib3RJZCI6ImQyNzE1NjUwMjk0OTQ4MjJhNDQ1ZWQxYTY1NjhkZWUzIiwiYW50aWJvdE1ldGhvZCI6IkNBUFRDSEEiLCJleHAiOjE3Mzc1MzEwMjgsImlhdCI6MTczNzUzMDcyOCwiY2FwdGNoYUlkIjoiYzk5Y2ZjMWEzMDZlNDg4YjllZTM1ODBlY2U5YmQyYTkifQ.QpzZ52-c6fDmKGfhgAOHMxGMrXmRk_ofpIZ3xkPjqiQ';");
        }
        sleep(2);

        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
        }
        sleep(15);


        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="otp-code"], input#optCode';
    $two_factor_message_selector = 'p[class="text-center text-info"]';
    $two_factor_submit_selector = 'input#submit';

    $this->exts->waitTillPresent($two_factor_selector, 10);

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {

        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
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

        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);

            sleep(10);

            if(!$this->exts->exists($two_factor_selector)){
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