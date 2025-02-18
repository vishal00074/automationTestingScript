<?php
// Server-Portal-ID: 39647 - Last modified: 27.01.2025 13:49:51 UTC - User: 1

/*Define constants used in script*/
 
public $baseUrl = 'https://accounts.zoho.eu/home#profile/personal';
public $loginUrl = 'https://accounts.zoho.com/signin';
public $invoicePageUrl = '';

public $username_selector = 'input#login_id';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#nextbtn';

public $check_login_failed_selector = 'div.errorlabel';
public $check_login_success_selector = 'a[href="/ZB_logoutAction.do"],div.pp_expand_signout';

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
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->success();
        sleep(2);
        
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
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->exts->click_by_xdotool($this->submit_login_selector);

            sleep(8);
            $this->exts->exists('div[onclick="showTryanotherWay()"]');
		    $this->exts->click_by_xdotool('div[onclick="showTryanotherWay()"]');

            sleep(10);
            $this->checkFillTwoFactor();
            sleep(10);
            $this->exts->waitTillPresent($this->password_selector, 20);
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
                $this->exts->click_by_xdotool($this->submit_login_selector);
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

function checkFillTwoFactor() {
    $two_factor_selector = 'div[id="verify_totp"]';
    $two_factor_message_selector = 'div[id="trytotp"] div[class="option_title_try"]';
    $two_factor_submit_selector = 'button[id="totpverifybtn"]';
    $two_factor_resend_selector = '';

    if($this->exts->querySelector($two_factor_selector) != null){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            sleep(1);
            if($this->exts->exists($two_factor_resend_selector)){
                $this->exts->moveToElementAndClick($two_factor_resend_selector);
                sleep(1);
            }
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if($this->exts->querySelector($two_factor_selector) == null){
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