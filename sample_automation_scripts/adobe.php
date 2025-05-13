<?php

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package uwa
 *
 * @copyright   GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/remote-chrome/utils/');

$gmi_browser_core = realpath('/var/www/remote-chrome/utils/GmiChromeManager.php');
require_once($gmi_browser_core);
class PortalScriptCDP
{

    private $exts;
    public $setupSuccess = false;
    private $chrome_manage;
    private $username;
    private $password;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/remote-chrome/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $username, $password);
        $this->setupSuccess = true;
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            try {
                // Start portal script execution
                $this->initPortal(0);
            } catch (\Exception $exception) {
                $this->exts->log('Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }


            $this->exts->log('Execution completed');

            $this->exts->process_completed();
            $this->exts->dump_session_files();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 21642 - Last modified: 12.03.2025 14:17:43 UTC - User: 1

    /*Define constants used in script*/
    public $base_url = 'https://www.adobe.com/';
    public $home_url = 'https://account.adobe.com';
    public $echosign_url = 'https://secure.echosign.com/public/login';
    public $username_selector = 'input#EmailPage-EmailField';
    public $username_readonly_selector = 'form#adobeid_signin input#adobeid_username[readonly]';
    public $next_button = 'button[data-id="EmailPage-ContinueButton"]';
    public $password_selector = 'input#PasswordPage-PasswordField';
    public $remember_me_selector = '';

    public $echosign_username_selector = 'form#loginForm input#userEmail';
    public $echosign_password_selector = 'form#loginForm input#userPassword';
    public $submit_login_selector = 'form#adobeid_signin button#sign_in, button.echosign.button-signin, button[data-id="PasswordPage-ContinueButton"]';

    public $check_login_failed_selector = 'label[data-id="PasswordPage-PasswordField-Error"], label[data-id="EmailPage-EmailField-Error"]';
    public $check_echosign_login_success_selector = '#id-navbar-dropdown a[href*="/logout"]';
    public $check_login_success_selector = 'a[data-profile="sign-out"], button[data-menu-id="profile"], main [data-e2e="plan-card-payment-invoice-btn"]';


    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_unexpected_extensions();
        $this->exts->openUrl($this->echosign_url);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->echosign_url);
        sleep(15);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->isLoggedin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->echosign_url);
            sleep(15);
            $loginViaEchosign = $this->checkFillEchosignLogin();

            //Select 2FA
            if ($this->exts->exists('a[data-id="PasswordlessSignInWait-SelectAnotherMethod"]')) {
                $this->exts->moveToElementAndClick('a[data-id="PasswordlessSignInWait-SelectAnotherMethod"]');
                sleep(5);
                if ($this->exts->exists('div[data-id="AuthenticationFactor-totp"]')) {
                    $this->exts->moveToElementAndClick('div[data-id="AuthenticationFactor-totp"]');
                } elseif ($this->exts->exists('div[data-id="AuthenticationFactor-phone"]')) {
                    $this->exts->moveToElementAndClick('div[data-id="AuthenticationFactor-phone"]');
                } elseif ($this->exts->exists('div[data-id="AuthenticationFactor-email"]')) {
                    $this->exts->moveToElementAndClick('div[data-id="AuthenticationFactor-email"]');
                } else {
                    $this->exts->moveToElementAndClick('div.ActionList-Item');
                }
                sleep(5);
            }

            $this->checkFillTwoFactor();

            /*if($this->exts->querySelector('.spectrum-Heading1') != null && !$this->exts->exists($this->password_selector)){
$this->exts->account_not_ready();
}*/

            if (!$this->isLoggedin() && (!$loginViaEchosign || $this->exts->exists($this->username_selector))) {
                $this->exts->log(__FUNCTION__ . "::User is not legacy Echosign account - Login Adobe");
                if (!$this->exts->exists($this->username_selector)) {
                    $this->exts->openUrl($this->home_url);
                    sleep(15);
                }

                $this->checkFillLogin();
                $this->checkFillRecaptcha();
                sleep(10);
                if ($this->exts->exists($this->password_selector) && !$this->exts->exists($this->check_login_failed_selector)) {
                    $this->checkFillLogin();
                    $this->checkFillRecaptcha();
                    sleep(10);
                }
                $this->checkFillTwoFactor();
                $this->checkConfirmPassword();
            }
        }

        if ($this->isLoggedin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User is passed login');
            $this->exts->log(__FUNCTION__ . '');
            $this->exts->log(__FUNCTION__ . '::Opening homepage and check if this site ask for confirm password');

            $this->processAfterLogin();
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else if (
                $this->exts->exists('form[action*="/force_password_reset.do"] button#continue') ||
                strpos($this->exts->extract('h1.spectrum-Heading1.PP-ProfileChooser__title'), 'wählen sie das profil aus, mit dem sie sich anmelden möchten') !== false
            ) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function disable_unexpected_extensions()
    {
        $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
        sleep(2);
        $this->exts->executeSafeScript("
if(document.querySelector('extensions-manager') != null) {
if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
    var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
    if(disable_button != null){
        disable_button.click();
    }
}
}
");
        sleep(1);
        $this->exts->openUrl('chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo');
        sleep(1);
        $this->exts->executeSafeScript("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
}");
        sleep(2);
    }

    function getInnerTextByJS($selector_or_object, $parent = null)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
            return;
        }
        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $element = $this->exts->getElement($selector_or_object, $parent);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
        }
    }

    private function checkFillEchosignLogin()
    {
        $this->exts->log(__FUNCTION__);
        if ($this->exts->exists($this->echosign_password_selector)) {
            sleep(3);
            $this->exts->capture("2-login-echosign-page");

            $this->exts->log(__FUNCTION__ . "::Enter Echosign Username");
            $this->exts->moveToElementAndType($this->echosign_username_selector, $this->username);
            sleep(2);
            // After input username and click outside username input field
            // If user is NOT legacy echosign user, This site will be redirected to Adobe login page, call Adobelogin module in this case.
            // If user is legacy echosign user, This site will stay here and we can input password
            if ($this->exts->exists($this->echosign_password_selector)) {
                $this->exts->moveToElementAndType($this->echosign_password_selector, $this->password, 3);
            }
            sleep(10);
            // if ($this->exts->exists($this->echosign_password_selector)) {
            //     $this->exts->moveToElementAndClick($this->echosign_password_selector);
            // }
            $this->exts->log(__FUNCTION__ . "::Checking Echosign user or Adobe user");
            sleep(15);
            $this->exts->capture("2-login-echosign-after-username");
            if ($this->exts->exists($this->echosign_password_selector)) {
                $this->exts->log(__FUNCTION__ . "::Enter Echosign Password");
                $this->exts->moveToElementAndClick($this->echosign_password_selector);
                sleep(5);
                $this->exts->moveToElementAndType($this->echosign_password_selector, '');
                sleep(3);
                $this->exts->moveToElementAndType($this->echosign_password_selector, $this->password);
                sleep(3);
                $this->exts->capture("2-login-echosign-page-filled");

                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(20);

                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Echosign login page not found');
            $this->exts->capture("login-not-found");
        }

        return false;
    }

    private function checkFillLogin()
    {
        if ($this->exts->querySelector($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->capture("2-email-page-filled");
            $this->exts->moveToElementAndClick($this->next_button);
            sleep(10);
            if ($this->exts->querySelector('.IdentitiesPage__chooser [data-id="Profile"]') != null) {
                $this->exts->capture("x-profile-selection-page");
                $this->exts->moveToElementAndClick('.IdentitiesPage__chooser [data-id="Profile"]');
                sleep(10);
            }

            // 2FA may be required right after inputing username
            // Maybe first confirm phone number, then enter code, so call 2FA two time
            $this->checkFillTwoFactor();
            $this->exts->two_factor_attempts++;
            $this->exts->notification_uid = "";
            $this->checkFillTwoFactor();

            if ($this->exts->querySelector($this->password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->remember_me_selector != '')
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(2);
                $this->exts->capture("2-login-page-filled");

                $this->checkFillRecaptcha();
                sleep(3);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(3);
                }
                $this->exts->capture("2-login-page-submitted");

                // $this->checkFillRecaptcha();
                // $this->checkFillRecaptcha();
                sleep(25);
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page not found');
                $this->exts->capture("2-password-page-not-found");
                // .IconMessage__icon [src="/mfa/S_Illu_Authenticate_58"]
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->executeSafeScript('
if(document.querySelector("[data-callback]") != null){
    return document.querySelector("[data-callback]").getAttribute("data-callback");
}

var result = ""; var found = false;
function recurse (cur, prop, deep) {
    if(deep > 5 || found){ return;}console.log(prop);
    try {
        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
        } else { deep++;
            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
        }
    } catch(ex) { console.log("ERROR in function: " + ex); return; }
}

recurse(___grecaptcha_cfg.clients[0], "", 0);
return found ? "___grecaptcha_cfg.clients[0]." + result : null;
');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    private function checkFillTwoFactor()
    {
        if ($this->exts->exists('.IconMessage__icon [src*="/mfa/S_Illu_MailTo_58"], [data-id="ChallengePushPage-EnterCode"], div.IconHeading img[src*="S_Illu_MailTo_"], button[data-id="AdditionalAccountDetailsPage-ContinueButton"]')) {
            // Confirm send code to email or to input code
            $this->exts->moveToElementAndClick('button[name="submit"][data-id="Page-PrimaryButton"], [data-id="ChallengePushPage-EnterCode"], button[data-id="AdditionalAccountDetailsPage-ContinueButton"]');
            sleep(10);
        }

        if ($this->exts->querySelector('input[data-id*="CodeInput"]') != null) {
            $this->exts->log("Current URL - " . $this->exts->getUrl());
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor-" . $this->exts->two_factor_attempts);

            if ($this->exts->querySelector('div.ChallengeCode-Description') != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->getInnerTextByJS('div.ChallengeCode-Description'));
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            // if($this->exts->two_factor_attempts == 2) {
            // 	$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            // 	$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
            // }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $two_factor_code = $this->exts->fetchTwoFactorCode();
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->getElements('input[data-id*="CodeInput"]');
                $code_inputs_count = count($code_inputs);
                for ($key = 0; $key < $code_inputs_count; $key++) {
                    $code_input = $this->exts->getElements('input[data-id*="CodeInput"]')[$key];
                    if ($code_input == null) continue;
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('data-id'));
                        $code_input->sendKeys($resultCodes[$key]);
                    } else {
                        $this->exts->log('"checkFillTwoFactor: Have no char for input #' . $code_input->getAttribute('data-id'));
                    }
                }
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->moveToElementAndClick('button[data-id="ChallengeCodePage-VerifyCode"], [data-id="ChallengeCodePage-Continue"]');
                sleep(15);
                $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->exts->exists('.IconMessage__icon [src*="/mfa/S_Illu_Authenticate_58"]')) {
            $this->exts->capture("2.2-two-factor-approval");
            $message_selector = '.IconMessage__description';
            $this->exts->two_factor_notif_msg_en = trim($this->getInnerTextByJS($message_selector)) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->getInnerTextByJS($message_selector)) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $two_factor_code = $this->exts->fetchTwoFactorCode();
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                sleep(5);
                $this->exts->capture("2.2-two-factor-approval-accepted");
                if ($this->exts->exists('.IconMessage__icon [src*="/mfa/S_Illu_Authenticate_58"]')) {
                    sleep(10);
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->exts->exists('[data-id="PasswordlessSignInWait-Description"]')) {
            $this->exts->capture("2.2-two-factor-passwordlesss");
            $message_selector = '[data-id="PasswordlessSignInWait-Description"]';
            $this->exts->two_factor_notif_msg_en = trim($this->getInnerTextByJS($message_selector)) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->getInnerTextByJS($message_selector)) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $two_factor_code = $this->exts->fetchTwoFactorCode();
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                sleep(5);
                $this->exts->capture("2.2-two-factor-passwordlesss-accepted");
                if ($this->exts->exists('[data-id="PasswordlessSignInWait-Description"]')) {
                    sleep(10);
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    private function checkConfirmPassword()
    {
        $this->exts->log(__FUNCTION__);
        if ($this->exts->exists($this->password_selector) && $this->exts->exists($this->username_readonly_selector)) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . "::This is confirm password form");
            $this->exts->capture('confirm-password-page');

            $this->exts->log(__FUNCTION__ . "::Enter confirm password");
            $this->exts->moveToElementAndClick($this->password_selector);
            sleep(2);
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(3);
            $this->exts->capture("confirm-password-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            $this->exts->log(__FUNCTION__ . "::Checking after confirm..");
            sleep(15);

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->capture("failed-confirm-password");
                $this->exts->log(__FUNCTION__ . "::Confirm password failed");
                $this->exts->log(__FUNCTION__ . "::Exit progress to avoid locked");
                $this->exts->exitFinal();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::No password confirm required');
        }
    }

    private function isLoggedin()
    {
        $this->exts->log(__FUNCTION__ . '::Checking login status..');
        return ($this->exts->exists($this->check_login_success_selector) || $this->exts->exists($this->check_echosign_login_success_selector)) &&
            !$this->exts->exists($this->password_selector);
    }

    private function processAfterLogin()
    {
        $this->exts->log(__FUNCTION__ . '');
        $this->exts->log(__FUNCTION__ . '::User logged in successfully');
        $this->exts->capture("3-login-success");

        if ($this->exts->urlContains('.echosign.com')) {
            $this->exts->log(__FUNCTION__ . '::This is Echosign user');
            sleep(10);
            $this->exts->openUrl('https://secure.na1.echosign.com/account/showInvoices');
            $this->processEchosignInvoices();
        } else if ($this->exts->urlContains('team.')) {
            $this->exts->log(__FUNCTION__ . '::This is TEAM user');
            sleep(10);
            // Currently, with team users, It required confirm password, So we open home page and input password if required.
            $this->exts->openUrl($this->home_url);
            sleep(15);
            $this->checkConfirmPassword();

            $this->exts->openUrl('https://team.accounts.adobe.com/orders');
            sleep(20);
            // Collect all plans or Products
            $orders = $this->exts->getElementsAttribute('table.table-order-history td a[href*="/orders/"]', 'href');
            $this->exts->log('Plan or Products found: ' . count($orders));
            // Loop through alls account by account number
            foreach ($orders as $view_detail_url) {
                sleep(5);
                $this->exts->openUrl($view_detail_url);
                sleep(10);
                $this->checkConfirmPassword();

                $this->downloadTeamInvoices();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::This is PERSONAL user');
            sleep(10);
            // $this->exts->openUrl('https://account.adobe.com/billing-history');
            // sleep(10);

            //click my account link
            // $this->exts->moveToElementAndClick('li a[href*="account/myAccount"]');
            // sleep(10);

            //click billing seciction menu
            if ($this->exts->exists('//a[contains(text(), "View billing history")]')) {
                $this->exts->moveToElementAndClick('//a[contains(text(), "View billing history")]');
            } else {
                $this->exts->moveToElementAndClick('li[data-pageid="Rechnungsinformationen"]');
            }
            sleep(10);

            //click bills menu
            // $this->exts->moveToElementAndClick('li[data-pageid="INVOICES"]');
            // sleep(10);


            $browser_windows = $this->exts->get_all_tabs();
            $this->exts->switchToTab(end($browser_windows));
            $this->downloadInvoices();
            if ($this->isNoInvoice) {
                $this->processEchosignInvoices();
            }
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
    }

    private function downloadInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table tbody tr', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(6) > div div:nth-child(2) button', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('td:nth-child(1)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(5)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);

                $downloadBtn = $this->exts->querySelector('td:nth-child(6) > div div:nth-child(2) button', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices    
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function downloadTeamInvoices()
    {
        sleep(10);
        $this->exts->capture("4-team-invoices-page");
        $this->exts->switchToFrame('iframe#billingList');

        $invoices = [];
        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 4 && $this->exts->querySelector('a[href*="/billing/"]', end($tags)) != null) {
                $this->exts->log('--------------------------');
                $invoiceUrl = str_replace('.html/', '.pdf/', $this->exts->extract('a[href*="/billing/"]', end($tags), 'href'));
                $invoiceName = end(explode(
                    '/',
                    trim(explode('?', $invoiceUrl)[0], '/')
                ));
                $invoiceDate = trim($this->getInnerTextByJS($tags[0]));
                $amountText = trim($this->getInnerTextByJS($tags[count($tags) - 2]));
                $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
                if (stripos($amountText, 'A$') !== false) {
                    $invoiceAmount = $invoiceAmount . ' AUD';
                } else if (stripos($amountText, '$') !== false) {
                    $invoiceAmount = $invoiceAmount . ' USD';
                } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                    $invoiceAmount = $invoiceAmount . ' GBP';
                } else {
                    $invoiceAmount = $invoiceAmount . ' EUR';
                }

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $parse_date = $invoice['invoiceDate'];
            $invoice['invoiceDate'] = $this->exts->parse_date($parse_date, 'j M, Y', 'Y-m-d');
            $invoice['invoiceDate'] == '' ? $this->exts->parse_date($parse_date, 'F j. Y', 'Y-m-d') : $invoice['invoiceDate'];
            if ($invoice['invoiceDate'] == '') {
                try {
                    $invoice['invoiceDate'] = date('Y-m-d', (new \DateTime($parse_date))->getTimestamp());
                } catch (\Exception $ex) {
                    $invoice['invoiceDate'] = '';
                }
            }
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processEchosignInvoices()
    {
        sleep(25);
        $this->exts->capture("4-echosign-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table#invId > tbody > tr.jqgrow');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 5 && $this->exts->querySelector('a', $tags[0]) != null) {
                $invoiceUrl = $this->exts->querySelector('a', $tags[0])->getAttribute("href");
                $invoiceName = trim($tags[0]->getText());
                $invoiceDate = trim($tags[1]->getText());
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getText())) . ' USD';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Y-m-d', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
