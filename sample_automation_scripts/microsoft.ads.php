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

    // Server-Portal-ID: 452 - Last modified: 17.04.2025 14:26:41 UTC - User: 1

    public $base_url = 'https://ads.microsoft.com/';
    public $bing_username_selector = 'input[name="LoginModel.Username"]';
    public $username_selector = 'input[name="loginfmt"]:not([aria-hidden="true"])';
    public $password_selector = 'input[name="passwd"]:not([aria-hidden="true"])';
    public $remember_me_selector = 'input[name="KMSI"] + span';
    public $submit_login_selector = 'input[type="submit"]#idSIButton9, button[type="submit"]#idSIButton9, button[type="submit"]';
    public $isNoInvoice = true;
    public $account_type = 0;
    public $lang = '';
    public $phone_number = '';
    public $recovery_email = '';
    public $restrictPages = 3;
    public $account_numbers = "";
    public $advance_payment = 0;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : (isset($this->exts->config_array["RESTRICTPAGES"]) ? (int) @$this->exts->config_array["RESTRICTPAGES"] : 3);

        $this->account_numbers = isset($this->exts->config_array["account_numbers"]) ? trim($this->exts->config_array["account_numbers"]) : (isset($this->exts->config_array["ACCOUNT_NUMBERS"]) ? trim($this->exts->config_array["ACCOUNT_NUMBERS"]) : '');

        $this->phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : (isset($this->exts->config_array["PHONE_NUMBER"]) ? $this->exts->config_array["PHONE_NUMBER"] : '');

        $this->recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : (isset($this->exts->config_array["RECOVERY_EMAIL"]) ? $this->exts->config_array["RECOVERY_EMAIL"] : '');

        $this->account_type = isset($this->exts->config_array["account_type"]) ? (int) @$this->exts->config_array["account_type"] : (isset($this->exts->config_array["ACCOUNT_TYPE"]) ? (int) @$this->exts->config_array["ACCOUNT_TYPE"] : 0);

        $this->advance_payment = isset($this->exts->config_array["advance_payment"]) ? (int) $this->exts->config_array["advance_payment"] : (isset($this->exts->config_array["ADVANCE_PAYMENT"]) ? (int) $this->exts->config_array["ADVANCE_PAYMENT"] : 0);

        $this->lang = isset($this->exts->config_array["lang"]) ? trim($this->exts->config_array["lang"]) : (isset($this->exts->config_array["LANG"]) ? trim($this->exts->config_array["LANG"]) : '');

        // Load cookies
        // $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->base_url);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->isLoggedIn()) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->base_url);
            $this->checkFillLogin();
            sleep(10);
            if (stripos(strtolower($this->exts->extract('h1[data-testid="title"]')), 'stay signed in') !== false) {
                $this->exts->click_element('button[data-testid="primaryButton"]');
            }
            $this->checkExternalFillLogin();
            if (stripos(strtolower($this->exts->extract('h1[data-testid="title"]')), 'stay signed in') !== false) {
                $this->exts->click_element('button[data-testid="primaryButton"]');
            }
            $this->checkConfirmButton();
            sleep(10);
            $this->checkTwoFactorMethod();
            $this->checkConfirmButton();
            $this->checkTwoFactorMethod();
            $this->checkConfirmButton();
        }

        sleep(3);
        $this->exts->log(__FUNCTION__);

        // then check user logged in or not
        if ($this->isLoggedIn()) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            // #customers-page .mcc-widget a[href*="/campaign/"]
            sleep(3);
            $this->exts->openUrl("https://ads.microsoft.com/cc/Billing/History");
            sleep(5);

            if ($this->exts->exists('div.lightboxNotification button.btn.btn-link.lightboxSecondRedirect')) {
                $this->exts->moveToElementAndClick('div.lightboxNotification button.btn.btn-link.lightboxSecondRedirect');
                sleep(1);
            }

            if ($this->exts->exists('button.teaching-bubble-close-button')) {
                $this->exts->moveToElementAndClick('button.teaching-bubble-close-button');
                sleep(3);
            }

            $this->selectCustomer();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed ');
            if ($this->exts->exists('#AdditionalSecurityVerificationTabSpan, input#newPassword, [data-bind*="Lockout_Reason"]')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->urlContains('account.live.com/ar/cancel')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->urlContains('/Abuse?')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->querySelector('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->getElementByText('div[id*="Error"]', ["account doesn't exist", "username may be incorrect", "username may not be correct", "Dieser Benutzername ist möglicherweise nicht korrekt", "Der Benutzername ist möglicherweise falsch", "Konto existiert nicht"], null, false) != null) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->getElementByText('div[id="error_Info"]', ["incorrect account or password", "falschen Konto oder Kennwort anzumelden"], null, false) != null || $this->exts->exists('input#LoginModel_Password.error')) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->getElementByText('div[id="usernameError"]', ["Eine Anmeldung mit einem persönlichen Konto ist hier nicht möglich", "You can't sign in here with a personal account"], null, false) != null) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->getElementByText('div[id*="Error"]', ["Sign-in is blocked"], null, false) != null) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        $this->exts->log(__FUNCTION__);
        $this->waitForSelectors($this->bing_username_selector, 3, 5);
        if ($this->exts->querySelector($this->bing_username_selector) != null) {
            sleep(3);
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->bing_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick('input#LoginSectionNextButton');
            sleep(15);
            if ($this->exts->querySelector($this->bing_username_selector) != null) {
                sleep(3);
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->bing_username_selector, $this->username);
                sleep(1);
                $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector('input#LoginSectionNextButton')]);
                sleep(10);
            }
            if ($this->exts->querySelector('input#LoginModel_Password') != null) {
                $this->exts->moveToElementAndType('input#LoginModel_Password', $this->password);
                sleep(1);
                $this->exts->click_by_xdotool('input#LoginSectionLoginButton');
                sleep(10);
            }
        }

        // When open login page, sometime it show previous logged user, select login with other user.
        sleep(20);
        if ($this->exts->exists('[role="listbox"] .row #otherTile[role="option"], div#otherTile')) {
            $this->exts->click_by_xdotool('[role="listbox"] .row #otherTile[role="option"], div#otherTile');
            sleep(10);
        }

        if ($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile, div[aria-label="Personal account"] button, div[data-testid="msaTile"], div[data-testid="entraTile"], div[aria-label="Work or school account"] button')) {
            // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
            //if account type is 1 then only personal account will be selected otherwise business account.
            if ($this->account_type == 1) {
                $this->exts->click_element('#msaTile, div[aria-label="Personal account"] button, div[data-testid="msaTile"]');
            } else {
                $this->exts->click_element('a#mso_account_tile_link, #aadTile, div[data-testid="entraTile"], div[aria-label="Work or school account"] button');
            }
            sleep(10);
        }

        $this->exts->capture("2-microsoft-login-page");
        if ($this->exts->querySelector($this->username_selector) != null) {
            sleep(3);
            $this->exts->log("Enter microsoft Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(10);
        }

        //Some user need to approve login after entering username on the app
        if ($this->exts->exists('div#idDiv_RemoteNGC_PollingDescription')) {
            $this->exts->two_factor_timeout = 5;
            $polling_message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
            $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($polling_message_selector)));
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                }
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->two_factor_timeout = 15;
            } else {
                if ($this->exts->exists('a#idA_PWD_SwitchToPassword')) {
                    $this->exts->click_by_xdotool('a#idA_PWD_SwitchToPassword');
                    sleep(5);
                } else {
                    $this->exts->log("Not received two factor code");
                }
            }
        }
        if ($this->exts->exists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
            // if site show: Already login with .. account, click logout and login with other account
            $this->exts->click_by_xdotool('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
            sleep(10);
        }
        if ($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile, div[aria-label="Personal account"] button, div[data-testid="msaTile"], div[data-testid="entraTile"], div[aria-label="Work or school account"] button')) {
            // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
            //if account type is 1 then only personal account will be selected otherwise business account.
            if ($this->account_type == 1) {
                $this->exts->click_element('#msaTile, div[aria-label="Personal account"] button, div[data-testid="msaTile"]');
            } else {
                $this->exts->click_element('a#mso_account_tile_link, #aadTile, div[data-testid="entraTile"], div[aria-label="Work or school account"] button');
            }
            sleep(10);
        }
        if ($this->exts->exists('form #idA_PWD_SwitchToPassword')) {
            $this->exts->click_by_xdotool('form #idA_PWD_SwitchToPassword');
            sleep(5);
        } else if ($this->exts->exists('#idA_PWD_SwitchToCredPicker')) {
            $this->exts->moveToElementAndClick('#idA_PWD_SwitchToCredPicker');
            sleep(5);
            $this->exts->moveToElementAndClick('[role="listitem"] img[src*="password"]');
            sleep(3);
        }


        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->log("Enter microsoft Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);
            $this->exts->capture("2-microsoft-password-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(10);
            $this->exts->capture("2-microsoft-after-submit-password");
        } else {
            $this->exts->log(__FUNCTION__ . '::microsoft Password page not found');
        }
    }
    private function checkExternalFillLogin()
    {
        $this->exts->log(__FUNCTION__);
        if ($this->exts->urlContains('balassalabs.com/')) {
            $this->exts->capture("2-login-external-page");
            if ($this->exts->getElement('input#userNameInput') != null) {
                sleep(3);
                $this->exts->log("Enter balassalabs Username");
                $this->exts->moveToElementAndType('input#userNameInput', $this->username);
                sleep(1);
                $this->exts->log("Enter balassalabs Password");
                $this->exts->moveToElementAndType('input#passwordInput', $this->password);
                sleep(1);
                $this->exts->capture("2-login-external-filled");
                $this->exts->click_by_xdotool('#submitButton');
                sleep(5);

                if ($this->exts->extract('#error #errorText') != '') {
                    $this->exts->loginFailure(1);
                }
                sleep(15);
            }
        } else if ($this->exts->urlContains('idaptive.app/login')) {
            $this->exts->capture("2-login-external-page");
            if ($this->exts->getElement('#usernameForm:not(.hidden) input[name="username"]') != null) {
                sleep(3);
                $this->exts->log("Enter idaptive Username");
                $this->exts->moveToElementAndType('#usernameForm:not(.hidden) input[name="username"]', $this->username);
                sleep(1);
                $this->exts->click_by_xdotool('#usernameForm:not(.hidden) [type="submit"]');
                sleep(5);
            }
            if ($this->exts->getElement('#passwordForm:not(.hidden) input[name="answer"][type="password"]') != null) {
                $this->exts->log("Enter idaptive Password");
                $this->exts->moveToElementAndType('#passwordForm:not(.hidden) input[name="answer"][type="password"]', $this->password);
                sleep(1);
                $this->exts->click_by_xdotool('#passwordForm:not(.hidden ) [name="rememberMe"]');
                $this->exts->capture("2-login-external-filled");
                $this->exts->click_by_xdotool('#passwordForm:not(.hidden) [type="submit"]');
                sleep(5);
            }

            if ($this->exts->extract('#errorForm:not(.hidden) .error-message, #usernameForm:not(.hidden ) .error-message:not(.hidden )') != '') {
                $this->exts->loginFailure(1);
            }
            sleep(15);
        } else if ($this->exts->urlContains('noveldo.onelogin.com')) {
            $this->exts->capture("2-login-external-page");
            if ($this->exts->getElement('input[name="username"]') != null) {
                sleep(3);
                $this->exts->log("Enter noveldo Username");
                $this->exts->moveToElementAndType('input[name="username"]', $this->username);
                sleep(1);
                $this->exts->click_by_xdotool('button[type="submit"]');
                sleep(3);
            }
            if ($this->exts->getElement('input#password') != null) {
                $this->exts->log("Enter noveldo Password");
                $this->exts->moveToElementAndType('input#password', $this->password);
                sleep(1);
                $this->exts->capture("2-login-external-filled");
                $this->exts->click_by_xdotool('button[type="submit"]');
                sleep(3);
            }
            sleep(15);
        } else if ($this->exts->urlContains('godaddy.') && $this->exts->getElement('input#password') != null) {
            // $devTools = new Chrome\ChromeDevToolsDriver($this->exts->webdriver);
            // $data_siteKey = $devTools->execute( // This website getting redirect error when loading on linux-selenium environment, then we must do this command
            // 	'Network.setUserAgentOverride',
            // 	['userAgent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36', 'platform' => 'Win32']
            // );
            $this->exts->capture("2-godaddy-login-page");

            $this->exts->log("Enter godaddy Username");
            $this->exts->moveToElementAndType('input#username', $this->username);
            sleep(1);

            $this->exts->log("Enter godaddy Password");
            $this->exts->moveToElementAndType('input#password', $this->password);
            sleep(1);

            if ($this->exts->exists('input#remember-me:not(:checked)'))
                $this->exts->click_by_xdotool('label[for="remember-me"]');
            sleep(2);

            $this->exts->capture("2-login-godaddy-page-filled");
            $this->exts->click_by_xdotool('button#submitBtn');
            sleep(15);
        }
    }
    private function checkConfirmButton()
    {
        // After submit password, It have many button can be showed, check and click it
        if ($this->exts->exists('form input[name="DontShowAgain"] + span')) {
            // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
            $this->exts->click_by_xdotool('form input[name="DontShowAgain"] + span');
            sleep(1);
            $this->exts->click_by_xdotool('button#acceptButton');
            sleep(10);
        }
        if ($this->exts->querySelector('input#btnAskLater') != null) {
            $this->exts->click_by_xdotool('input#btnAskLater');
            sleep(10);
        }
        if ($this->exts->querySelector('a[data-bind*=SkipMfaRegistration]') != null) {
            $this->exts->click_by_xdotool('a[data-bind*=SkipMfaRegistration]');
            sleep(10);
        }
        if ($this->exts->querySelector('input#idSIButton9[aria-describedby="KmsiDescription"]') != null) {
            $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby="KmsiDescription"]');
            sleep(10);
        }
        if ($this->exts->querySelector('input#idSIButton9[aria-describedby*="landingDescription"]') != null) {
            $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby*="landingDescription"]');
            sleep(3);
        }
        if ($this->exts->getElement("#verifySetup a#verifySetupCancel") != null) {
            $this->exts->click_by_xdotool("#verifySetup a#verifySetupCancel");
            sleep(10);
        }
        if ($this->exts->getElement('#authenticatorIntro a#iCancel') != null) {
            $this->exts->click_by_xdotool('#authenticatorIntro a#iCancel');
            sleep(10);
        }
        if ($this->exts->getElement("input#iLooksGood") != null) {
            $this->exts->click_by_xdotool("input#iLooksGood");
            sleep(10);
        }
        if ($this->exts->getElement("input#StartAction") != null) {
            $this->exts->click_by_xdotool("input#StartAction");
            sleep(10);
        }
        if ($this->exts->getElement(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
            $this->exts->click_by_xdotool(".recoveryCancelPageContainer input#iLandingViewAction");
            sleep(10);
        }
        if ($this->exts->getElement("input#idSubmit_ProofUp_Redirect") != null) {
            $this->exts->click_by_xdotool("input#idSubmit_ProofUp_Redirect");
            sleep(10);
        }
        if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
            // Great job! Your security information has been successfully set up. Click "Done" to continue login.
            $this->exts->click_by_xdotool(' #id__11');
            sleep(10);
        }
        if ($this->exts->getElement('div input#iNext') != null) {
            $this->exts->click_by_xdotool('div input#iNext');
            sleep(10);
        }
        if ($this->exts->getElement('input[value="Continue"]') != null) {
            $this->exts->click_by_xdotool('input[value="Continue"]');
            sleep(10);
        }
        if ($this->exts->getElement('form[action="/kmsi"] input#idSIButton9') != null) {
            $this->exts->click_by_xdotool('form[action="/kmsi"] input#idSIButton9');
            sleep(10);
        }
        if ($this->exts->getElement('a#CancelLinkButton') != null) {
            $this->exts->click_by_xdotool('a#CancelLinkButton');
            sleep(10);
        }
        if (stripos(strtolower($this->exts->extract('span[role="button"]')), 'use my microsoft authenticator') !== false) {
            $this->exts->click_element('span[role="button"]');
            sleep(10);
            $this->waitForSelectors('div[aria-label="Use a verification code"] button', 3, 5);
            if ($this->exts->exists('div[aria-label="Use a verification code"] button')) {
                $this->exts->click_element('div[aria-label="Use a verification code"] button');
                sleep(10);
                $this->fillTwoFactor('input#otc-confirmation-input', 'div#oneTimeCodeDescription', '', 'button#oneTimeCodePrimaryButton');
            }
        }
        if ($this->exts->querySelector('form[action*="/kmsi"] input[name="DontShowAgain"]') != null) {
            // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
            $this->exts->click_by_xdotool('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
            sleep(3);
            $this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9');
            sleep(10);
        }
    }
    private function checkTwoFactorMethod()
    {
        // Currently we met 4 two factor methods
        // - Email
        // - Text Message
        // - Approve request in Microsoft Authenticator app
        // - Use verification code from mobile app
        $this->exts->log(__FUNCTION__);
        // sleep(5);
        $this->exts->capture("2.0-two-factor-checking");
        // STEP 0 if it's hard to solve, so try back to choose list
        if (($this->exts->querySelector('[value="PhoneAppNotification"]') != null || $this->exts->querySelector('[value="CompanionAppsNotification"]') != null) && $this->exts->querySelector('a#signInAnotherWay') != null) {
            $this->exts->click_by_xdotool('a#signInAnotherWay');
            sleep(5);
        } else if ($this->exts->querySelector('#iTimeoutDesc') != null && $this->exts->querySelector('#iTimeoutOptionLink') != null) {
            $this->exts->click_by_xdotool('#iTimeoutOptionLink');
            sleep(5);
        } else if ($this->exts->querySelector('[data-bind*="login-confirm-send-view"] [type="submit"]') != null) {
            $this->exts->click_by_xdotool('[data-bind*="login-confirm-send-view"] [type="submit"]');
            sleep(5);
        } else if ($this->exts->querySelector('form[name="CredPickerViewForm"] button') != null) {
            $this->exts->click_by_xdotool('form[name="CredPickerViewForm"] button');
            sleep(5);
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->querySelector('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]') != null) {
            if ($this->exts->querySelector('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])') != null) {
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])');
            } else {
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
            }
            sleep(3);
        } else if ($this->exts->querySelector('#iProofList input[name="proof"]') != null) {
            $this->exts->click_by_xdotool('#iProofList input[name="proof"]');
            sleep(3);
        } else if ($this->exts->querySelector('#idDiv_SAOTCS_Proofs [role="listitem"]') != null) {
            // Updated 11-2020
            if ($this->exts->querySelector('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]') != null) { // phone SMS
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
            } else if ($this->exts->querySelector('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]') != null) { // phone SMS
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]');
            } else if ($this->exts->querySelector('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]') != null) { // Email 
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]');
            } else if ($this->exts->querySelector('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]') != null) {
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
            } else if ($this->exts->querySelector('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]') != null) {
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
            } else {
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"]');
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
            $this->fillTwoFactor('', '', '', '');
        } else if ($this->exts->querySelector('[data-bind*="Type.TOTPAuthenticatorV2"]') != null) {
            // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
            // Then wait. If not success, click to select two factor by code from mobile app
            $input_selector = '';
            $message_selector = 'div#idDiv_SAOTCAS_Description';
            $remember_selector = 'label#idLbl_SAOTCAS_TD_Cb, #idChkBx_SAOTCAS_TD:not(:checked)';
            $submit_selector = '';
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($message_selector, null, 'innerText');
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->two_factor_attempts = 2;
            $this->exts->two_factor_timeout = 5;
            $this->fillTwoFactor('', '', $remember_selector, $submit_selector);
            // sleep(30);

            if ($this->exts->querySelector('a#idA_SAASTO_TOTP') != null) {
                $this->exts->click_by_xdotool('a#idA_SAASTO_TOTP');
                sleep(5);
            }
        } else if ($this->exts->querySelector('input[value="TwoWayVoiceOffice"]') != null && $this->exts->querySelector('div#idDiv_SAOTCC_Description') != null) {
            // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
            // Then wait. If not success, click to select two factor by code from mobile app
            $input_selector = '';
            $message_selector = 'div#idDiv_SAOTCC_Description';
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($message_selector, null, 'innerText');
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->two_factor_attempts = 2;
            $this->exts->two_factor_timeout = 5;
            $this->fillTwoFactor('', '', '', '');
        } else if ($this->exts->querySelector('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"]), input#proof-confirmation') != null) {
            // If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
            $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"]), input#proof-confirmation';
            $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain, form[name="proof-confirmation"]   > div:nth-child(2)';
            $remember_selector = '';
            $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], button[aria-label="Send code"]';
            $this->exts->two_factor_attempts = 1;
            if ($this->recovery_email != '' && filter_var($this->recovery_email, FILTER_VALIDATE_EMAIL) !== false) {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                sleep(1);
                $this->exts->click_by_xdotool($submit_selector);
                sleep(5);
            } else {
                $this->exts->two_factor_attempts = 1;
                $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
                if (stripos(strtolower($this->exts->extract('div#proof-confirmationError')), "doesn't match") !== false) {
                    $this->exts->log("Auto 2FA returned!!!! Ask for recovery email again");
                    $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
                }
            }
        } else if ($this->exts->querySelector('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])') != null) {
            // If method is phone code, This site may be ask for confirm phone first, So send 2FA to ask user phone
            $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])';
            $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
            $remember_selector = '';
            $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"]';
            $this->exts->two_factor_attempts = 1;
            if ($this->phone_number != '' && is_numeric(trim(substr($this->phone_number, -1, 4)))) {
                $last4digit = substr($this->phone_number, -1, 4);
                $this->exts->moveToElementAndType($input_selector, $last4digit);
                sleep(3);
                $this->exts->click_by_xdotool($submit_selector);
                sleep(5);
            } else {
                $this->exts->two_factor_attempts = 1;
                $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            }
        }

        // STEP 3: input code
        if ($this->exts->querySelector('input[name="otc"], input[name="iOttText"]') != null) {
            $input_selector = 'input[name="otc"], input[name="iOttText"]';
            $message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel, #idDiv_SAOTCC_Description, div#oneTimeCodeDescription';
            $remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
            $submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction, button#oneTimeCodePrimaryButton';
            $this->exts->two_factor_attempts = 0;
            $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    }
    private function fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor-page");
        $this->exts->log($message_selector);
        if ($this->exts->getElement($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (empty($two_factor_code) || trim($two_factor_code) == '') {
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        }
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->getElement($input_selector) != null) {
                $this->exts->log("fillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(2);
                if ($this->exts->exists($remember_selector)) {
                    $this->exts->click_by_xdotool($remember_selector);
                }
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log("fillTwoFactor: Clicking submit button.");
                    $this->exts->click_by_xdotool($submit_selector);
                }
                sleep(15);

                if ($this->exts->getElement($input_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not found two factor input");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
    private function isLoggedIn()
    {
        return $this->exts->exists('button#O365_MainLink_Me #O365_MainLink_MePhoto, div.msame_Drop_signOut a, a[href*="/logout"]:not(#footerSignout), button#user-center') ||
            $this->exts->exists('ul[role="menubar"] li button[data-value="billing"]') || $this->exts->exists('ul[role="menubar"] li button[data-value="Billing"]');
    }

    private function selectCustomer()
    {
        $this->exts->log('start function selectCustomer');
        $this->exts->openUrl('https://ads.microsoft.com/cc/customers/selection');
        sleep(15);

        $user_selected_accounts = array();
        if (trim($this->account_numbers) != "" && !empty($this->account_numbers)) {
            $user_selected_accounts = explode(",", $this->account_numbers);
        }

        if ($this->exts->exists('div#customers-page a.payment-link')) {
            $customers = $this->exts->querySelectorAll('div#customers-page a.payment-link');
            $customers_array = array();

            foreach ($customers as $customer) {
                $cust_url = $customer->getAttribute('href');
                $customer_uid = trim(explode("&", array_pop(explode("cid=", $cust_url)))[0]);
                $this->exts->log('Customer ID -' . $customer_uid);
                $url_text = trim($customer->getText());
                if (!empty($user_selected_accounts)) {
                    foreach ($user_selected_accounts as $user_selected_account) {
                        if (stripos($url_text, trim($user_selected_account)) !== false || trim($customer_uid) == trim($user_selected_account)) {
                            array_push($customers_array, $cust_url);
                        }
                    }
                } else {
                    array_push($customers_array, $cust_url);
                }
            }

            foreach ($customers_array as $cust_url) {
                $this->exts->openUrl($cust_url);
                $this->waitForSelectors('button#tools', 15, 3);
                if ($this->exts->exists('button#tools')) {
                    $this->exts->moveToElementAndClick('button#tools');
                }

                $this->waitForSelectors('.app-shell-header #billing-payments', 10, 3);
                if ($this->exts->exists('.app-shell-header #billing-payments')) {
                    $this->exts->moveToElementAndClick('.app-shell-header #billing-payments');
                }
                sleep(15);
                $this->selectAccount();
            }
        } else {
            $this->waitForSelectors('button#tools', 15, 3);
            if ($this->exts->exists('button#tools')) {
                $this->exts->moveToElementAndClick('button#tools');
            }
            $this->waitForSelectors('.app-shell-header #billing-payments', 15, 3);
            if ($this->exts->exists('.app-shell-header #billing-payments')) {
                $this->exts->moveToElementAndClick('.app-shell-header #billing-payments');
            }
            $this->selectAccount();
        }

        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }
    private function waitForSelectors($selector, $max_attempt, $sec)
    {
        for (
            $wait = 0;
            $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1;
            $wait++
        ) {
            $this->exts->log('Waiting for Selectors!!!!!!');
            sleep($sec);
        }
    }
    private function selectAccount()
    {
        $this->exts->log('start function selectAccount');

        if ($this->exts->exists('select#CurrentAccount option')) {
            $accounts = $this->exts->querySelectorAll('select#CurrentAccount option');
            $accounts_array = array();

            foreach ($accounts as $i => $account) {
                if ($i > 0 || count($accounts) == 1) {
                    $acc_id = $account->getAttribute('value');
                    array_push($accounts_array, $acc_id);
                }
            }

            foreach ($accounts_array as $account) {
                $this->exts->click_by_xdotool('select#CurrentAccount');
                sleep(1);
                $this->exts->click_by_xdotool('select#CurrentAccount option[value="' . $account . '"]');
                // $this->exts->changeSelectbox('select#CurrentAccount', $account);
                sleep(15);
                $this->exts->moveToElementAndClick('.sidebar-menu-content a.billing, button#billing');

                // Button takes a lof od time to show
                $this->exts->refresh();
                sleep(10);
                for ($wait = 0; $wait < 30 && $this->exts->executeSafeScript("return !!document.querySelector('#viewAllBillingDocuments[data-url*=\"22Values%22%3A1%\"], a.transactions, .billing-summary-recent-documents-card button#viewAllBillingDocuments, button#secondary-transactions');") != 1; $wait++) {
                    $this->exts->log('Waiting for button.....');
                    sleep(10);
                }

                // Schwellenwert ... Zahlung
                $this->exts->moveToElementAndClick('#viewAllBillingDocuments[data-url*="22Values%22%3A1%"], a.transactions, .billing-summary-recent-documents-card button#viewAllBillingDocuments, button#secondary-transactions');
                sleep(15);
                $this->exts->capture('4-transaction');
                if ($this->restrictPages == 0) {
                    $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                    sleep(1);
                    $this->exts->moveToElementAndClick('li[data-value="EntireTimeLast5Years"]');
                    sleep(15);
                    $this->downloadInvoice();
                } else {
                    $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                    sleep(1);
                    $this->exts->moveToElementAndClick('li[data-value="ThisMonth"]');
                    sleep(15);
                    $this->downloadInvoice();

                    $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                    sleep(1);
                    $this->exts->moveToElementAndClick('li[data-value="Last3Months"]');
                    sleep(15);
                    $this->downloadInvoice();
                }

                // Download Advance payment
                //
                if ($this->advance_payment == 1) {
                    $this->exts->moveToElementAndClick('.sidebar-menu-content a.billing, button#billing');
                    sleep(5);
                    // Click Vorauszahlung (Advance payment)
                    $this->exts->moveToElementAndClick('button#viewAllBillingDocuments[data-url*="Values%22%3A12%"]');
                    sleep(10);
                    $this->exts->capture('4-advance-payment');
                    if ($this->restrictPages == 0) {
                        $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                        sleep(1);
                        $this->exts->moveToElementAndClick('li[data-value="EntireTimeLast5Years"]');
                        sleep(15);
                        $this->downloadInvoice();
                    } else {
                        $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                        sleep(1);
                        $this->exts->moveToElementAndClick('li[data-value="ThisMonth"]');
                        sleep(15);
                        $this->downloadInvoice();

                        $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                        sleep(1);
                        $this->exts->moveToElementAndClick('li[data-value="Last3Months"]');
                        sleep(15);
                        $this->downloadInvoice();
                    }
                }
            }
        } else {
            $this->exts->moveToElementAndClick('.sidebar-menu-content a.billing, button#billing');

            // Button takes a lof od time to show
            $this->exts->refresh();
            sleep(10);
            for ($wait = 0; $wait < 30 && $this->exts->executeSafeScript("return !!document.querySelector('#viewAllBillingDocuments[data-url*=\"22Values%22%3A1%\"], a.transactions, .billing-summary-recent-documents-card button#viewAllBillingDocuments, button#secondary-transactions');") != 1; $wait++) {
                $this->exts->log('Waiting for button.....');
                sleep(10);
            }
            // Schwellenwert ... Zahlung
            $this->exts->moveToElementAndClick('#viewAllBillingDocuments[data-url*="22Values%22%3A1%"], a.transactions, .billing-summary-recent-documents-card button#viewAllBillingDocuments, button#secondary-transactions');
            sleep(15);
            $this->exts->capture('4-transaction');
            if ($this->restrictPages == 0) {
                $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                sleep(1);
                $this->exts->moveToElementAndClick('li[data-value="EntireTimeLast5Years"]');
                sleep(15);
                $this->downloadInvoice();
            } else {
                $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                sleep(1);
                $this->exts->moveToElementAndClick('li[data-value="ThisMonth"]');
                sleep(15);
                $this->downloadInvoice();

                $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                sleep(1);
                $this->exts->moveToElementAndClick('li[data-value="Last3Months"]');
                sleep(15);
                $this->downloadInvoice();
            }


            // Download Advance payment
            //
            if ($this->advance_payment == 1) {
                $this->exts->moveToElementAndClick('.sidebar-menu-content a.billing, button#billing');
                sleep(5);
                // Click Vorauszahlung (Advance payment)
                $this->exts->moveToElementAndClick('button#viewAllBillingDocuments[data-url*="Values%22%3A12%"]');
                sleep(10);
                $this->exts->capture('4-advance-payment');
                if ($this->restrictPages == 0) {
                    $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                    sleep(1);
                    $this->exts->moveToElementAndClick('li[data-value="EntireTimeLast5Years"]');
                    sleep(15);
                    $this->downloadInvoice();
                } else {
                    $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                    sleep(1);
                    $this->exts->moveToElementAndClick('li[data-value="ThisMonth"]');
                    sleep(15);
                    $this->downloadInvoice();

                    $this->exts->moveToElementAndClick('.compared-date-range-picker button');
                    sleep(1);
                    $this->exts->moveToElementAndClick('li[data-value="Last3Months"]');
                    sleep(15);
                    $this->downloadInvoice();
                }
            }
        }
    }
    private function downloadInvoice($count = 0)
    {
        sleep(30);
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-downloadInvoice');

        if ($this->exts->querySelector('div.billing-grid-container table tbody tr') != null) {
            $receipts = $this->exts->querySelectorAll('div.billing-grid-container table tbody tr');
            $invoices = array();
            foreach ($receipts as $receipt) {
                $tags = $this->exts->querySelectorAll('td', $receipt);
                if (count($tags) >= 5 && $this->exts->querySelector('td a[href*="/billing/RenderBillingDocument"]', $receipt) != null) {
                    $receiptDate = $this->exts->extract('span.grid-cell-text', $tags[1]);
                    $receiptDate = trim(explode('-', $receiptDate)[0]);
                    $receiptUrl = $this->exts->extract('td a[href*="/billing/RenderBillingDocument"]', $receipt, 'href');
                    $receiptName = trim($this->exts->extract('td a[href*="/billing/RenderBillingDocument"]', $receipt));
                    if (strtolower(trim($receiptName)) == 'ausstehend' || strtolower(trim($receiptName)) == 'pending' || stripos($receiptUrl, '=pending') !== FALSE) {
                        continue;
                    }
                    $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';

                    $parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
                    if (trim($parsed_date) == "") {
                        $parsed_date = $this->exts->parse_date($receiptDate);
                    }

                    $receiptAmount = $this->exts->extract('div', $tags[5]);
                    $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' EUR';

                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice URL: " . $receiptUrl);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'receiptUrl' => $receiptUrl,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName
                    );
                    array_push($invoices, $invoice);
                }
            }

            $this->exts->log("Invoice found: " . count($invoices));

            foreach ($invoices as $invoice) {
                $this->isNoInvoice = false;
                $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                }
            }
        } else if ($this->exts->querySelector('.billing-summary .customer-billing-summary-cards-board .ms-List .ms-List-cell') != null) {
            $receipts = $this->exts->querySelectorAll('.billing-summary .customer-billing-summary-cards-board .ms-List .ms-List-cell');
            $invoices = array();
            foreach ($receipts as $receipt) {
                $tags = $this->exts->querySelectorAll('[role="gridcell"]', $receipt);
                if (count($tags) >= 4 && $this->exts->querySelector('a[href*="/billing/RenderBillingDocument"]', $receipt) != null) {
                    $receiptDate = trim($tags[1]->getText());
                    $receiptDate = trim(explode('-', $receiptDate)[0]);
                    $receiptUrl = $this->exts->extract('a[href*="/billing/RenderBillingDocument"]', $receipt, 'href');
                    $receiptName = trim($this->exts->extract('a[href*="/billing/RenderBillingDocument"]', $receipt));
                    if (strtolower(trim($receiptName)) == 'ausstehend' || strtolower(trim($receiptName)) == 'pending' || stripos($receiptUrl, '=pending') !== FALSE) {
                        continue;
                    }
                    $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';

                    $parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
                    if (trim($parsed_date) == "") {
                        $parsed_date = $this->exts->parse_date($receiptDate);
                    }

                    $receiptAmount = '';

                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice URL: " . $receiptUrl);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'receiptUrl' => $receiptUrl,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName
                    );
                    array_push($invoices, $invoice);
                }
            }

            $this->exts->log("Invoice found: " . count($invoices));

            foreach ($invoices as $invoice) {
                $this->isNoInvoice = false;
                $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                }
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->log('restrictPages count:: ' . $restrictPages);

        if ($count < $restrictPages && $this->exts->exists('.nav-button.next:not([disabled])')) {
            $this->exts->moveToElementAndClick('.nav-button.next:not([disabled])');
            sleep(7);
            $count++;
            $this->downloadInvoice($count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
