<?php //

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


    // Server-Portal-ID: 771787 - Last modified: 11.06.2025 14:12:54 UTC - User: 1

    // Script here
    public $baseUrl = 'https://platform.openai.com/settings/organization/billing/history';
    public $loginUrl = 'https://platform.openai.com/';
    public $username_selector = 'input[name="email"], input#email-input, input#username';
    public $password_selector = 'input#password, input[name="password"]';

    public $isNoInvoice = true;
    public $login_with_google = 0;
    public $login_with_microsoft = 0;
    public $check_login_failed_selector = 'span[slot="errorMessage"] li';

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)$this->exts->config_array["login_with_google"] : $this->login_with_google;
        $this->login_with_microsoft = isset($this->exts->config_array["login_with_microsoft"]) ? (int)@$this->exts->config_array["login_with_microsoft"] : $this->login_with_microsoft;

        $this->exts->log('CONFIG login_with_google: ' . $this->login_with_google);
        $this->exts->log('CONFIG login_with_microsoft: ' . $this->login_with_microsoft);

        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->waitTillAnyPresent(['//button//*[contains(text(), "Log in")]', 'a[href="/settings/profile"]', '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]']);
        $this->check_solve_cloudflare_page();
        $this->exts->capture('1-init-page');

        if (!$this->isLoggedin()) {

            $this->userNotLoggedIn();

            $authError = strtolower($this->exts->extract('h1[class*="heading"] span'));
            $this->exts->log("auth Error:: " . $authError);

            if (
                stripos($authError, strtolower('Oops, an error occurred!')) !== false ||
                stripos($authError, strtolower('Authentication Error')) !== false
            ) {

                $invalidRequestError = strtolower($this->exts->extract('div[class*="subTitle"] > span > div[class*="subtitle"]'));
                $this->exts->log("invalidRequestError:: " . $invalidRequestError);
                if (stripos($invalidRequestError, strtolower('which is not the authentication method you used during sign up')) !== false) {
                    $this->exts->capture('capture-invalid_request_error');
                    $this->exts->loginFailure(1);
                }


                $this->exts->capture("try-again-login");
                $this->exts->moveToElementAndClick('button[aria-describedby]');
                $this->exts->moveToElementAndClick('a[href*="auth_provider=auth0"]');

                sleep(5);
                $this->clearChrome();
                $this->exts->openUrl($this->baseUrl);
                sleep(5);
                $this->exts->waitTillAnyPresent(['//button//*[contains(text(), "Log in")]', 'a[href="/settings/profile"]', '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]']);
                $this->check_solve_cloudflare_page();
                $this->userNotLoggedIn();
            }

            $this->check_solve_cloudflare_page();
            $this->checkFillTwoFactor();
            $this->check_solve_cloudflare_page();
            $this->exts->waitTillPresent('a[href="/settings/profile"]');
        }


        if ($this->exts->exists('input[id="ootp-pin"]')) {
            $this->checkFillTwoFactor();
        }

        if ($this->isLoggedin()) {
            sleep(5);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $popup_ok = $this->exts->getElementByText('[role="dialog"][data-state="open"] button.btn-primary', 'Okay', null, false);
            if ($popup_ok != null) {
                $this->exts->click_element($popup_ok);
                sleep(2);
            }
            if ($this->exts->exists('[role="dialog"][data-state="open"] .text-token-text-tertiary button')) {
                $this->exts->click_element('[role="dialog"][data-state="open"] .text-token-text-tertiary button');
                sleep(1);
            }
            if ($this->exts->exists('[role="dialog"][data-state="open"]')) {
                $unwanted_dialog = $this->exts->getElement('[role="dialog"][data-state="open"]');
                $this->exts->execute_javascript('arguments[0].remove();', [$unwanted_dialog]);
                sleep(1);
            }
            $this->exts->capture("3-login-success");

            $this->processAfterLogin();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $isTwoFAError = $this->exts->execute_javascript('document.body.innerHTML.includes("The code you entered is incorrect. Please try again.")');
            $this->exts->log('isTwoFAError ' . $isTwoFAError);

            // if microsoft LoginFailed
            $isMicroSoftError = strtolower($this->exts->extract('form[data-testid="usernameForm"] div.ext-error'));
            $isMicroSoftPassError = strtolower($this->exts->extract('div.fui-Field__validationMessage span:nth-child(2)'));
            $errorText = strtolower($this->exts->extract($this->check_login_failed_selector));
            $this->exts->log('Login Error:: ' . $errorText);

            if ($this->exts->exists('#error-element-password[data-error-code]')) {
                $this->exts->loginFailure(1);
            } elseif (stripos($errorText, strtolower('Incorrect email address, phone number, or password. Phone numbers must include the country code.')) !== false) {
                $this->exts->loginFailure(1);
            } elseif ($isTwoFAError) {
                $this->exts->capture("incorrect-2FA");
                $this->exts->log('incorrect TwoFA');
                $this->exts->loginFailure(1);
            } else if (stripos($isMicroSoftError, strtolower("That Microsoft account doesn't exist. Enter a different account or")) !== false) {
                $this->exts->capture("microsoft-login-failed");
                $this->exts->loginFailure(1);
            } else if (stripos($isMicroSoftPassError, strtolower("That password is incorrect for your Microsoft account.")) !== false) {
                $this->exts->capture("microsoft-login-failed-pass");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function userNotLoggedIn()
    {
        sleep(2);
        $this->exts->click_element('//button//*[contains(text(), "Log in")]');
        sleep(5);
        $this->check_solve_cloudflare_page();
        sleep(5);
        $this->exts->waitTillPresent($this->username_selector, 10);
        if ($this->exts->querySelector($this->username_selector) != null && $this->login_with_google != '1' && $this->login_with_microsoft != '1') {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->capture("2-username-filled");
            $this->exts->moveToElementAndClick('button[type="submit"]');
            sleep(5);
            $this->check_solve_cloudflare_page();
        }

        $this->selectLoginType();

        for ($i = 0; $i < 5 && $this->exts->getElementByText('h1', ['Oops, an error occurred!'], null, false) != null; $i++) {
            $this->clearChrome();
            $this->exts->openUrl($this->baseUrl);
            sleep(5);
            $this->exts->waitTillPresent('//button//*[contains(text(), "Log in")]');
            $this->check_solve_cloudflare_page();
            sleep(2);
            $this->exts->click_element('//button//*[contains(text(), "Log in")]');
            sleep(5);
            $this->check_solve_cloudflare_page();
            sleep(5);
            $this->exts->waitTillPresent($this->username_selector, 10);
            if ($this->exts->querySelector($this->username_selector) != null && $this->login_with_google != '1') {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);
                $this->exts->capture("2-username-filled");
                $this->exts->moveToElementAndClick('button[type="submit"]');
                sleep(5);
                $this->check_solve_cloudflare_page();
            }
            $this->selectLoginType();
        }

        if ($this->login_with_google == '1') {
            $this->loginGoogleIfRequired();
        } elseif ($this->login_with_microsoft == '1') {
            $this->loginMicrosoftIfRequired();
        } else {
            $this->checkFillLogin();
            sleep(6);
        }
    }

    private function selectLoginType()
    {
        if ($this->login_with_google == '1') {
            $this->exts->click_element('//button[contains(text(), "Google")]');
            sleep(5);
        } else if ($this->login_with_microsoft == '1') {
            $this->exts->click_element('//button[contains(text(), "Microsoft")]');
            sleep(5);
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
        sleep(1);
        $this->exts->capture("clear-page");
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearButton").click();');
        sleep(15);
        $this->exts->capture("after-clear");
    }
    private function checkFillLogin()
    {
        $this->exts->capture("2-login-page");
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->capture("2-username-filled");
            $this->exts->moveToElementAndClick('button[type="submit"]');
            sleep(5);
            $this->check_solve_cloudflare_page();
        }
        $this->exts->waitTillPresent($this->password_selector, 30);

        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);
            $this->exts->capture("2-password-filled");
            if ($this->exts->exists('//button[contains(text(), "Continue")]')) {
                $this->exts->click_element('//button[contains(text(), "Continue")]');
            } else {
                $this->exts->moveToElementAndClick('button[type="submit"][class*="button-login-password"]');
            }
            sleep(5);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-password-not-found");
        }
    }
    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[autocomplete="one-time-code"], input#code,input[id="ootp-pin"]';
        $two_factor_message_selector = 'header p, [class*="loginChallengePage"] > p,h1[id="headingText"]';
        $two_factor_submit_selector = 'button[value="continue"], button[class*="continueButton"], button[type="submit"][data-action-button-primary="true"], button[type="submit"]';

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

            $this->exts->log("checkFillTwoFactor: Entering two_factor_code. " . $two_factor_code);

            if (!empty($two_factor_code) && trim($two_factor_code) != '') {

                $this->exts->moveToElementAndClick('label[for="rememberBrowser"]');
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(1);
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
                    $this->exts->notification_uid = "";
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

    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }
    private function isLoggedin()
    {
        $isLoggedin = false;
        if ($this->exts->exists('a[href="/settings/profile"]')) {
            $isLoggedin = true;
        }
        return $isLoggedin;
    }

    // MICROSOFT Login
    public $microsoft_username_selector = 'input[name="loginfmt"], input[id="usernameEntry"]';
    public $microsoft_password_selector = 'input[name="passwd"]';
    public $microsoft_remember_me_selector = 'input[name="KMSI"] + span';
    public $microsoft_submit_login_selector = 'button[type="submit"]#idSIButton9, button[type="submit"][data-testid="primaryButton"]';

    public $microsoft_account_type = 0;
    public $microsoft_phone_number = '';
    public $microsoft_recovery_email = '';
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function loginMicrosoftIfRequired()
    {
        $this->microsoft_phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : '';
        $this->microsoft_recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
        $this->microsoft_account_type = isset($this->exts->config_array["account_type"]) ? (int)@$this->exts->config_array["account_type"] : 0;

        if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
            $this->checkFillMicrosoftLogin();
            sleep(10);
            $this->checkMicrosoftTwoFactorMethod();

            if ($this->exts->exists('input#newPassword')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->getElement('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required microsoft login.');
            $this->exts->capture("3-no-microsoft-required");
        }
    }
    private function checkFillMicrosoftLogin()
    {
        $this->exts->log(__FUNCTION__);
        // When open login page, sometime it show previous logged user, select login with other user.
        if ($this->exts->exists('[role="listbox"] .row #otherTile[role="option"], [role="listitem"] #otherTile')) {
            $this->exts->moveToElementAndClick('[role="listbox"] .row #otherTile[role="option"], [role="listitem"] #otherTile');
            sleep(10);
        }

        $this->exts->capture("2-microsoft-login-page");
        if ($this->exts->getElement($this->microsoft_username_selector) != null) {
            sleep(3);
            $this->exts->log("Enter microsoft Username");
            $this->exts->moveToElementAndType($this->microsoft_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
            sleep(15);
        }

        // Stay Signed In
        if ($this->exts->exists('div#pageContent button#acceptButton')) {
            $this->exts->moveToElementAndClick('div#pageContent button#acceptButton');
            sleep(15);
        }

        if ($this->exts->exists('div[data-testid="routeAnimationFluent"] button[data-testid="primaryButton"]')) {
            $this->exts->moveToElementAndClick('div[data-testid="routeAnimationFluent"] button[data-testid="primaryButton"]');
            sleep(15);
        }

        if ($this->exts->exists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
            // if site show: Already login with .. account, click logout and login with other account
            $this->exts->moveToElementAndClick('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
            sleep(10);
        }
        if ($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile')) {
            // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
            //if account type is 1 then only personal account will be selected otherwise business account.
            if ($this->microsoft_account_type == 1) {
                $this->exts->moveToElementAndClick('#msaTile');
            } else {
                $this->exts->moveToElementAndClick('a#mso_account_tile_link, #aadTile');
            }
            sleep(10);
        }
        if ($this->exts->exists('form #idA_PWD_SwitchToPassword')) {
            $this->exts->moveToElementAndClick('form #idA_PWD_SwitchToPassword');
            sleep(5);
        }

        if ($this->exts->getElement($this->microsoft_password_selector) != null) {
            $this->exts->log("Enter microsoft Password");
            $this->exts->moveToElementAndType($this->microsoft_password_selector, $this->password);
            sleep(1);
            $this->exts->moveToElementAndClick($this->microsoft_remember_me_selector);
            sleep(2);
            $this->exts->capture("2-microsoft-password-page-filled");
            $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
            sleep(15);
            $this->exts->capture("2-microsoft-after-submit-password");
            // Stay Signed In
            if ($this->exts->exists('div#pageContent button#acceptButton')) {
                $this->exts->moveToElementAndClick('div#pageContent button#acceptButton');
                sleep(15);
            }

            if ($this->exts->exists('div[data-testid="routeAnimationFluent"] button[data-testid="primaryButton"]')) {
                $this->exts->moveToElementAndClick('div[data-testid="routeAnimationFluent"] button[data-testid="primaryButton"]');
                sleep(15);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::microsoft Password page not found');
        }

        $this->checkConfirmMicrosoftButton();
    }
    private function checkConfirmMicrosoftButton()
    {
        // After submit password, It have many button can be showed, check and click it
        if ($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"], input#idSIButton9[aria-describedby="KmsiDescription"]')) {
            // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
            $this->exts->moveToElementAndClick('form input[name="DontShowAgain"] + span');
            sleep(3);
            $this->exts->moveToElementAndClick('form[action*="/kmsi"] input#idSIButton9, input#idSIButton9[aria-describedby="KmsiDescription"]');
            sleep(10);
        }
        if ($this->exts->getElement("#verifySetup a#verifySetupCancel") != null) {
            $this->exts->moveToElementAndClick("#verifySetup a#verifySetupCancel");
            sleep(10);
        }
        if ($this->exts->getElement('#authenticatorIntro a#iCancel') != null) {
            $this->exts->moveToElementAndClick('#authenticatorIntro a#iCancel');
            sleep(10);
        }
        if ($this->exts->getElement("input#iLooksGood") != null) {
            $this->exts->moveToElementAndClick("input#iLooksGood");
            sleep(10);
        }
        if ($this->exts->getElement("input#StartAction") != null) {
            $this->exts->moveToElementAndClick("input#StartAction");
            sleep(10);
        }
        if ($this->exts->getElement(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
            $this->exts->moveToElementAndClick(".recoveryCancelPageContainer input#iLandingViewAction");
            sleep(10);
        }
        if ($this->exts->getElement("input#idSubmit_ProofUp_Redirect") != null) {
            $this->exts->moveToElementAndClick("input#idSubmit_ProofUp_Redirect");
            sleep(10);
        }
        if ($this->exts->getElement('div input#iNext') != null) {
            $this->exts->moveToElementAndClick('div input#iNext');
            sleep(10);
        }
        if ($this->exts->getElement('input[value="Continue"]') != null) {
            $this->exts->moveToElementAndClick('input[value="Continue"]');
            sleep(10);
        }
        if ($this->exts->getElement('form[action="/kmsi"] input#idSIButton9') != null) {
            $this->exts->moveToElementAndClick('form[action="/kmsi"] input#idSIButton9');
            sleep(10);
        }
        if ($this->exts->getElement('a#CancelLinkButton') != null) {
            $this->exts->moveToElementAndClick('a#CancelLinkButton');
            sleep(10);
        }
        if ($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
            // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
            $this->exts->moveToElementAndClick('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
            sleep(3);
            $this->exts->moveToElementAndClick('form[action*="/kmsi"] input#idSIButton9');
            sleep(10);
        }
    }
    private function checkMicrosoftTwoFactorMethod()
    {
        // Currently we met 4 two factor methods
        // - Email 
        // - Text Message
        // - Approve request in Microsoft Authenticator app
        // - Use verification code from mobile app
        $this->exts->log(__FUNCTION__);
        sleep(5);
        $this->exts->capture("2.0-microsoft-two-factor-checking");
        // STEP 0 if it's hard to solve, so try back to choose list
        if ($this->exts->exists('[value="PhoneAppNotification"]') && $this->exts->exists('a#signInAnotherWay')) {
            $this->exts->moveToElementAndClick('a#signInAnotherWay');
            sleep(5);
        }
        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]')) {
            if ($this->exts->exists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])')) {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])');
            } else {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
            }
            sleep(3);
        } else if ($this->exts->exists('#iProofList input[name="proof"]')) {
            $this->exts->moveToElementAndClick('#iProofList input[name="proof"]');
            sleep(3);
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"]')) {
            // Updated 11-2020
            if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
            } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
            } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')) {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
            } else {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"]');
            }
            sleep(5);
        }

        // STEP 2: (Optional)
        if ($this->exts->querySelector('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc') != null) {
            // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
            $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
            $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText')));
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

            $this->exts->two_factor_attempts = 2;
            $this->fillMicrosoftTwoFactor('', '', '', '');
        } else if ($this->exts->querySelector('[data-bind*="Type.TOTPAuthenticatorV2"]') != null) {
            // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
            // Then wait. If not success, click to select two factor by code from mobile app
            $input_selector = '';
            $message_selector = 'div#idDiv_SAOTCAS_Description';
            $remember_selector = 'label#idLbl_SAOTCAS_TD_Cb';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 2;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            sleep(30);

            if ($this->exts->exists('a#idA_SAASTO_TOTP')) {
                $this->exts->moveToElementAndClick('a#idA_SAASTO_TOTP');
                sleep(5);
            }
        } else if ($this->exts->querySelector('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[name^="iProof"] .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"]:not([type="hidden"])') != null) {
            // If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
            $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[name^="iProof"] .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"]:not([type="hidden"])';
            $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
            $remember_selector = '';
            $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"]';
            $this->exts->two_factor_attempts = 1;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }

        // STEP 3: input code
        if ($this->exts->querySelector('input[name="otc"], input[name="iOttText"]') != null) {
            $input_selector = 'input[name="otc"], input[name="iOttText"]';
            $message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel';
            $remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
            $submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction';
            $this->exts->two_factor_attempts = 0;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }

        $this->exts->log('Other ways to sign in');

        $this->exts->click_element('//span[contains(text(), "Other ways to sign in")]');
        sleep(10);
        $this->exts->click_element('//span[contains(text(), "Use your password")]');
        sleep(10);

        if ($this->exts->getElement($this->microsoft_password_selector) != null) {
            $this->exts->log("Enter microsoft Password");
            $this->exts->moveToElementAndType($this->microsoft_password_selector, $this->password);
            sleep(2);
        }
        $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
        sleep(5);
    }
    private function fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("microsoft Two factor page found.");
        $this->exts->capture("2.1-microsoft-two-factor-page");
        $this->exts->log($message_selector);
        if ($this->exts->getElement($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->getElement($input_selector) != null) {
                $this->exts->log("microsoftfillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(2);
                if ($this->exts->exists($remember_selector)) {
                    $this->exts->moveToElementAndClick($remember_selector);
                }
                $this->exts->capture("2.2-microsoft-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log("microsoftfillTwoFactor: Clicking submit button.");
                    $this->exts->moveToElementAndClick($submit_selector);
                }
                sleep(15);

                if ($this->exts->getElement($input_selector) == null) {
                    $this->exts->log("microsoftTwo factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
                } else {
                    $this->exts->log("microsoft Two factor can not solved");
                }
            } else {
                $this->exts->log("Not found microsoft two factor input");
            }
        } else {
            $this->exts->log("Not received microsoft two factor code");
        }
    }
    //*********** END Microsoft Login

    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
    public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
    private function loginGoogleIfRequired()
    {
        if ($this->exts->urlContains('google.')) {
            if ($this->exts->urlContains('/webreauth')) {
                $this->exts->moveToElementAndClick('#identifierNext');
                sleep(6);
            }
            $this->googleCheckFillLogin();
            sleep(5);
            if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }

            // Click next if confirm form showed
            $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
            $this->googleCheckTwoFactorMethod();

            if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
                $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->exts->exists('#tos_form input#accept')) {
                $this->exts->moveToElementAndClick('#tos_form input#accept');
                sleep(10);
            }
            if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('.action-button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->moveToElementAndClick('input[name="later"]');
                sleep(7);
            }
            if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->exts->exists('#submit_approve_access')) {
                $this->exts->moveToElementAndClick('#submit_approve_access');
                sleep(10);
            } else if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
                sleep(10);
            }
            $this->exts->capture("3-google-before-back-to-main-tab");
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required google login.');
            $this->exts->capture("3-no-google-required");
        }
    }
    private function googleCheckFillLogin()
    {
        if ($this->exts->exists('form ul li [role="link"][data-identifier]')) {
            $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
            sleep(5);
        }

        if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
            $this->exts->capture("google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->exts->exists($this->google_username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
            } else if ($this->exts->urlContains('/challenge/recaptcha')) {
                $this->googlecheckFillRecaptcha();
                $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
                sleep(5);
            }

            // Which account do you want to use?
            if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->urlContains('/challenge/pk')) {
            $this->exts->type_key_by_xdotool('Return');
            sleep(3);
            $this->exts->capture("2.0-cancel-passkey-google");
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
            $this->exts->moveToElementAndClick('div[data-challengeid="2"]');
            sleep(5);
        }

        if ($this->exts->exists($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }

            $this->exts->capture("2-google-login-page-filled");
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->exts->exists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->capture("2-google-login-pageandcaptcha-filled");
                    $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                }
            } else {
                $this->googlecheckFillRecaptcha();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Google password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function googleCheckTwoFactorMethod()
    {
        // Currently we met many two factor methods
        // - Confirm email account for account recovery
        // - Confirm telephone number for account recovery
        // - Call to your assigned phone number
        // - confirm sms code
        // - Solve the notification has sent to smart phone
        // - Use security key usb
        // - Use your phone or tablet to get a security code (EVEN IF IT'S OFFLINE)
        $this->exts->log(__FUNCTION__);
        sleep(5);
        $this->exts->capture("2.0-before-check-two-factor-google");
        // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
        if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
            $this->exts->moveToElementAndClick('#assistActionId');
            sleep(5);
        } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
            if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
                $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
                sleep(5);
            }
        } else if ($this->exts->urlContains('/sk/webauthn')) {
            $this->exts->type_key_by_xdotool('Return');
            sleep(3);
            $this->exts->capture("2.0-cancel-security-usb-google");
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->urlContains('/challenge/pk')) {
            $this->exts->type_key_by_xdotool('Return');
            sleep(3);
            $this->exts->capture("2.0-cancel-passkey-google");
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            // We most RECOMMEND confirm security phone or email, then other method
            if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        }

        // STEP 2: (Optional)
        if ($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
            // If methos is recovery email, send 2FA to ask for email
            $this->exts->two_factor_attempts = 2;
            $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
            // If methos confirm recovery phone number, send 2FA to ask
            $this->exts->two_factor_attempts = 3;
            $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool("Return");
                sleep(5);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('input#phoneNumberId')) {
            // Enter a phone number to receive an SMS with a confirmation code.
            $this->exts->two_factor_attempts = 3;
            $input_selector = 'input#phoneNumberId';
            $message_selector = '[data-view-id] form section > div > div > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('input[name="Pin"]')) {
            $input_selector = 'input[name="Pin"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        }
    }
    private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Google two factor page found.");
        $this->exts->capture("2.1-two-factor-google");

        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }

        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->querySelector($input_selector) != null) {
                if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                    $two_factor_code = end(explode('G-', $two_factor_code));
                }
                if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                    $two_factor_code = end(explode('g-', $two_factor_code));
                }
                $this->exts->log(__FUNCTION__ . ": Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, '');
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(1);
                if ($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])) {
                    $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                    sleep(1);
                }
                $this->exts->capture("2.2-google-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log(__FUNCTION__ . ": Clicking submit button.");
                    $this->exts->moveToElementAndClick($submit_selector);
                } else if ($submit_by_enter) {
                    $this->exts->type_key_by_xdotool("Return");
                }
                sleep(10);
                $this->exts->capture("2.2-google-two-factor-submitted-" . $this->exts->two_factor_attempts);
                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("Google two factor solved");
                } else {
                    if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->notification_uid = '';
                        $this->exts->two_factor_attempts++;
                        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
                    } else {
                        $this->exts->log("Google Two factor can not solved");
                    }
                }
            } else {
                $this->exts->log("Google not found two factor input");
            }
        } else {
            $this->exts->log("Google not received two factor code");
            $this->exts->two_factor_attempts = 3;
        }
    }
    private function googlecheckFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);
            $url = reset(explode('?', $this->exts->getUrl()));
            $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
                    if(document.querySelector("[data-callback]") != null){
                        document.querySelector("[data-callback]").getAttribute("data-callback");
                    } else {
                        var result = ""; var found = false;
                        function recurse (cur, prop, deep) {
                            if(deep > 5 || found){ return;}console.log(prop);
                            try {
                                if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                                } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                                    for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                                }
                            } catch(ex) { console.log("ERROR in function: " + ex); return; }
                        }

                        recurse(___grecaptcha_cfg.clients[0], "", 0);
                        found ? "___grecaptcha_cfg.clients[0]." + result : null;
                    }
                ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    // End GOOGLE login

    private function processAfterLogin()
    {
        if (!$this->exts->urlContains('/billing/history')) {
            $this->exts->openUrl($this->baseUrl);
            sleep(5);
        }
        $this->exts->click_if_existed('[data-testid="cookie-consent-banner"] button + button.btn-primary');
        $this->processInvoices();

        // check if have more profiles
        $this->exts->click_if_existed('button[id="select-trigger-radix-:r0:"][data-state="closed"][aria-haspopup="dialog"]');
        sleep(2);
        if ($this->exts->exists('[role="dialog"][data-state="open"] [data-option-id]:not([aria-selected="true"])')) {
            $this->exts->moveToElementAndClick('[role="dialog"][data-state="open"] [data-option-id]:not([aria-selected="true"])');
            $this->processInvoices();
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }
    private function processInvoices()
    {
        sleep(10);
        exec("sudo docker exec -i --user root " . $this->exts->node_name . " sh -c 'sudo chmod -R 777 /home/seluser/Downloads/'");
        $this->exts->capture("4-invoices-page-" . time());
        $invoices = [];

        $rows = $this->exts->getElements('.billing-history-table tr');
        foreach ($rows as $row) {
            $invoice_link = $row->querySelector('a[href*="invoice.stripe.com"]');
            if ($invoice_link != null) {
                $invoice_url = $invoice_link->getAttribute("href");
                $invoice_name = trim($this->exts->extract('td:nth-child(1)', $row));
                $invoice_amount = trim($this->exts->extract('td:nth-child(3)', $row));
                array_push($invoices, array(
                    'invoice_name' => $invoice_name,
                    'invoice_date' => '',
                    'invoice_amount' => $invoice_amount,
                    'invoice_url' => $invoice_url
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        if (count($invoices) > 0) {
            $this->exts->openNewTab();
        }
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoice_name: ' . $invoice['invoice_name']);
            $this->exts->log('invoice_amount: ' . $invoice['invoice_amount']);
            $this->exts->log('invoice_url: ' . $invoice['invoice_url']);
            if ($this->exts->invoice_exists($invoice['invoice_name'])) {
                $this->exts->log('Invoice Existed ' . $invoice['invoice_name']);
            } else {
                $invoiceFileName =  !empty($invoice['invoice_name']) ?  $invoice['invoice_name'] . '.pdf' : '';
                $this->exts->openUrl($invoice['invoice_url']);
                sleep(2);

                $this->exts->click_element('//button//*[contains(text(), "Download invoice") or contains(text(), "Rechnung herunterladen")]/../..');
                sleep(6);
                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoice_name'], '', $invoice['invoice_amount'], $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoice['invoice_name']);
                }
            }
        }

        if (count($invoices) > 0) {
            $this->exts->switchToInitTab();
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
