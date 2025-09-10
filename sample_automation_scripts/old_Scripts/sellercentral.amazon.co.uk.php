<?php // updated login code

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
    
    public $baseUrl = 'https://sellercentral.amazon.co.uk/gp/payments-account/view-transactions.html';
    public $username_selector = 'form[name="signIn"] input[name="email"]:not([type="hidden"])';
    public $password_selector = 'form[name="signIn"] input[name="password"]';
    public $submit_login_selector = 'form[name="signIn"] input#signInSubmit';
    public $remember_me = 'form[name="signIn"] input[name="rememberMe"]:not(:checked)';
    public $isNoInvoice = true;

    public $payment_settlements = 0;
    public $seller_invoice = 0;
    public $transaction_invoices = 0;
    public $seller_fees = 0;
    public $no_advertising_bills = 0;
    public $language_code = 'de_DE';
    public $currentSelectedMarketPlace = "";
    public $no_marketplace = 1;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->seller_invoice = isset($this->exts->config_array["seller_invoice"]) ? (int)@$this->exts->config_array["seller_invoice"] : $this->seller_invoice;
        $this->payment_settlements = isset($this->exts->config_array["payment_settlements"]) ? (int)@$this->exts->config_array["payment_settlements"] : $this->payment_settlements;
        $this->transaction_invoices = isset($this->exts->config_array["transaction_invoices"]) ? (int)@$this->exts->config_array["transaction_invoices"] : $this->transaction_invoices;
        $this->seller_fees = isset($this->exts->config_array["seller_fees"]) ? (int)@$this->exts->config_array["seller_fees"] : $this->seller_fees;
        $this->no_advertising_bills = isset($this->exts->config_array["advertising_bills"]) ? (int)@$this->exts->config_array["advertising_bills"] : $this->no_advertising_bills;
        $this->exts->log('CONFIG seller_invoice: ' . $this->seller_invoice);
        $this->exts->log('CONFIG payment_settlements: ' . $this->payment_settlements);
        $this->exts->log('CONFIG transaction view: ' . $this->transaction_invoices);
        $this->exts->log('CONFIG seller fees: ' . $this->seller_fees);
        $this->exts->log('CONFIG No Advert Invoices: ' . $this->no_advertising_bills);

        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->isLoginSuccess()) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
            if (!$this->exts->exists($this->password_selector)) {
                $this->exts->capture("2-login-exception");
                $this->exts->clearCookies();
                $this->exts->openUrl($this->baseUrl);
                sleep(10);
            }
            // Login, retry few time since it show captcha
            $this->checkFillLogin();
            sleep(5);
            // retry if captcha showed
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->checkFillLogin();
                sleep(5);
            }
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->checkFillLogin();
                sleep(5);
            }
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->checkFillLogin();
                sleep(5);
                if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                    $this->checkFillLogin();
                    sleep(5);
                }
                if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                    $this->checkFillLogin();
                    sleep(5);
                }
                if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                    $this->checkFillLogin();
                    sleep(5);
                }
            }
            // End handling login form
            $this->checkFillTwoFactor();

            $isOtpExpired =  $this->exts->extract('div.a-alert-content');
            $this->exts->log('::Otp Expired Message:: ' . $isOtpExpired);

            if (stripos($isOtpExpired, strtolower("Your One Time Password (OTP) has expired. Please request another from the ‘Didn't receive the One Time Password?’ link below.")) !== false) {

                $this->exts->moveToElementAndClick('a[id="auth-get-new-otp-link"]');
                sleep(4);
                $this->exts->waitTillPresent('input[id="auth-send-code"]');
                $this->exts->moveToElementAndClick('input[id="auth-send-code"]');
                sleep(10);

                $this->checkFillTwoFactor();
            }

            if ($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
                $this->exts->moveToElementAndClick('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
                sleep(2);
            }
        }
        if ($this->exts->exists('button.full-page-account-switcher-account-details')) {
            //  This portal is for UK so select UK first, else select default
            $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['United Kingdom'], null, true);
            if ($target_selection == null) {
                //Sometime we need to expand to see the list
                $this->exts->moveToElementAndClick('button.full-page-account-switcher-account-details');
                sleep(10);
                $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['United Kingdom'], null, true);
            }

            if ($target_selection == null) {
                $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['Regno Unito'], null, true);
            }

            if ($target_selection == null) {
                $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['Royaume-Uni'], null, true);
            }

            if ($target_selection == null && count($this->exts->getElements('button.full-page-account-switcher-account-details')) > 1) { // If do not found, get default picker
                $target_selection = $this->exts->getElements('button.full-page-account-switcher-account-details')[1];
            }
            if ($target_selection != null) {
                $this->exts->click_element($target_selection);
            }
            sleep(2);
            if ($this->exts->exists('button.kat-button--primary:not([disabled])')) {
                $this->exts->moveToElementAndClick('button.kat-button--primary:not([disabled])');
                sleep(10);
            } else {
                $this->exts->account_not_ready();
            }
        }

        // then check user logged in or not
        if ($this->isLoginSuccess()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('button.full-page-account-switcher-account-details')) {
                //  This portal is for UK so select UK first, else select default
                $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['United Kingdom'], null, true);
                if ($target_selection == null) {
                    //Sometime we need to expand to see the list
                    $this->exts->moveToElementAndClick('button.full-page-account-switcher-account-details');
                    sleep(10);
                    $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['United Kingdom'], null, true);
                }

                if ($target_selection == null) {
                    $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['Regno Unito'], null, true);
                }

                if ($target_selection == null) {
                    $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['Royaume-Uni'], null, true);
                }

                if ($target_selection == null && count($this->exts->getElements('button.full-page-account-switcher-account-details')) > 1) { // If do not found, get default picker
                    $target_selection = $this->exts->getElements('button.full-page-account-switcher-account-details')[1];
                }
                if ($target_selection != null) {
                    $this->exts->click_element($target_selection);
                }
                sleep(1);
                if ($this->exts->exists('button.kat-button--primary:not([disabled])')) {
                    $this->exts->moveToElementAndClick('button.kat-button--primary:not([disabled])');
                    sleep(10);
                } else {
                    $this->exts->account_not_ready();
                }
            }

            $this->doAfterLogin();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
            $error_text = strtolower($this->exts->extract('div#auth-email-invalid-claim-alert div.a-alert-content'));
            $OtpPageError =  $this->exts->extract('div.a-alert-content');

            $this->exts->log('::Error text ' . $error_text);
            $this->exts->log('::Error text Otp Page ' . $OtpPageError);

            if ($this->isIncorrectCredential()) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div[id*="error-message"]', null, 'innerText')), 'incorrect') !== false) {
                $this->exts->capture("loginFailedConfirmed");
                $this->exts->loginFailure(1);
            } else if (
                stripos($OtpPageError, strtolower('Die von dir angegebenen Anmeldeinformationen waren inkorrekt. Überprüfe sie und versuche es erneut.')) !== false ||
                stripos($OtpPageError, strtolower('The credentials you provided were incorrect. Check them and try again.')) !== false ||
                stripos($OtpPageError, strtolower("Your One Time Password (OTP) has expired. Please request another from the ‘Didn't receive the One Time Password?’ link below.")) !== false
            ) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('form[name="forgotPassword"], [data-metric-name="sc:auth-failed:no-account:start-registration-button"]')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->password_selector]);
        if ($this->exts->querySelector($this->username_selector) != null || $this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick('input[id="continue"]');
            sleep(2);
            $this->exts->waitTillPresent($this->password_selector);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->moveToElementAndClick('form[name="signIn"] input[name="rememberMe"]:not(:checked)');

            if ($this->exts->exists('input#auth-captcha-guess')) {
                $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
            }
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function checkFillTwoFactor()
    {
        $this->exts->capture("2.0-two-factor-checking");
        if ($this->exts->exists('div.auth-SMS input[type="radio"]')) {
            $this->exts->moveToElementAndClick('div.auth-SMS input[type="radio"]:not(:checked)');
            sleep(2);
            $this->exts->moveToElementAndClick('input#auth-send-code');
            sleep(5);
        } else if ($this->exts->exists('div.auth-TOTP input[type="radio"]')) {
            $this->exts->moveToElementAndClick('div.auth-TOTP input[type="radio"]:not(:checked)');
            sleep(2);
            $this->exts->moveToElementAndClick('input#auth-send-code');
            sleep(5);
        } else if ($this->exts->allExists(['input[type="radio"]', 'input#auth-send-code'])) {
            $this->exts->moveToElementAndClick('input[type="radio"]:not(:checked)');
            sleep(2);
            $this->exts->moveToElementAndClick('input#auth-send-code');
            sleep(5);
        }

        if ($this->exts->exists('input[name="otpCode"]')) {
            $two_factor_selector = 'input[name="otpCode"]';
            $two_factor_message_selector = '#auth-mfa-form h1 + p';
            $two_factor_submit_selector = '#auth-signin-button';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->notification_uid = "";
            $this->exts->two_factor_attempts++;
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                if ($this->exts->exists('input[name="otpCode"]:not([type="hidden"])')) {
                    $this->exts->moveToElementAndType('input[name="otpCode"]:not([type="hidden"])', $two_factor_code);
                } else if ($this->exts->exists('input[name="otc-1"]')) {
                    $this->exts->moveToElementAndClick('input[name="otc-1"]');
                    $this->exts->type_text_by_xdotool($two_factor_code);
                }
                if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                    $this->exts->moveToElementAndClick('label[for="auth-mfa-remember-device"]');
                }
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(1);
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->exts->exists('[name="transactionApprovalStatus"], #resend-approval-form')) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            $message_selector = '.a-spacing-large .transaction-approval-word-break, #channelDetails, .transaction-approval-word-break, #channelDetailsWithImprovedLayout';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('.transaction-approval-word-break.a-size-medium'));
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n" . trim($this->exts->extract('#channelDetails'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirmation on device";

            $this->exts->notification_uid = "";
            $this->exts->two_factor_attempts++;
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                // Click refresh page if user confirmed
                $this->exts->moveToElementAndClick('a.a-link-normal[href*="/ap/cvf/approval"]');
            }
        }
    }
    private function isIncorrectCredential()
    {
        $incorrect_credential_keys = [
            'Es konnte kein Konto mit dieser',
            'dass die eingegebene Nummer korrekt ist oder melde dich',
            't find an account with that',
            'Falsches Passwort',
            'password is incorrect',
            'password was incorrect',
            'Passwort war nicht korrekt',
            'Impossible de trouver un compte correspondant',
            'Votre mot de passe est incorrect',
            'Je wachtwoord is onjuist',
            'La tua password non',
            'a no es correcta'
        ];
        $error_message = $this->exts->extract('#auth-error-message-box');
        foreach ($incorrect_credential_keys as $incorrect_credential_key) {
            if (strpos(strtolower($error_message), strtolower($incorrect_credential_key)) !== false) {
                return true;
            }
        }
        return false;
    }
    private function processCaptcha($captcha_image_selector, $captcha_input_selector)
    {
        $this->exts->log("--IMAGE CAPTCHA--");
        if ($this->exts->exists($captcha_image_selector)) {
            $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
            $source_image = imagecreatefrompng($image_path);
            imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', 90);

            if (!empty($this->exts->config_array['captcha_shell_script'])) {
                $cmd = $this->exts->config_array['captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid;
                $this->exts->log('Executing command : ' . $cmd);
                exec($cmd, $output, $return_var);
                $this->exts->log('Command Result : ' . print_r($output, true));

                if (!empty($output)) {
                    $output = $output[0];
                    if (stripos($output, 'OK|') !== false) {
                        $captcha_code = trim(end(explode("OK|", $output)));
                    } else {
                        $this->exts->log('1:processCaptcha::ERROR when get response:' . $output);
                    }
                }
                if ($captcha_code == '') {
                    $this->exts->log("Can not get result from API");
                } else {
                    $this->exts->moveToElementAndType($captcha_input_selector, $captcha_code);
                    return true;
                }
            }
        } else {
            $this->exts->log("Image does not found!");
        }

        return false;
    }
    private function captcha_required()
    {
        // Supporting de, fr, en, es, it, nl language
        $captcha_required_keys = [
            'wie sie auf dem Bild erscheinen',
            'die in der Abbildung unten gezeigt werden',
            'Geben Sie die Zeichen so ein, wie sie auf dem Bild erscheinen',
            'the characters as they are shown in the image',
            'Enter the characters as they are given',
            'luego introduzca los caracteres que aparecen en la imagen',
            'Introduce los caracteres tal y como aparecen en la imagen',
            "dans l'image ci-dessous",
            "apparaissent sur l'image",
            'quindi digita i caratteri cos',
            'Inserire i caratteri cos',
            'en voer de tekens in zoals deze worden weergegeven in de afbeelding hieronder om je account',
            'Voer de tekens in die je uit veiligheidsoverwegingen moet'
        ];
        $error_message = $this->exts->extract('#auth-error-message-box, #auth-warning-message-box');
        foreach ($captcha_required_keys as $captcha_required_key) {
            if (strpos(strtolower($error_message), strtolower($captcha_required_key)) !== false) {
                return true;
            }
        }
        return false;
    }
    private function isLoginSuccess()
    {
        return $this->exts->exists('.nav-right-section [data-test-tag="nav-settings-button"], li.sc-logout-quicklink, .sc-header #partner-switcher button.dropdown-button, li#sc-quicklink-logout') && !$this->exts->exists($this->password_selector) && !$this->exts->exists('[data-metric-name="sc:auth-failed:no-account:start-registration-button"]');
    }

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->exts->waitTillPresent($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $option = $select_box . ' option[value=' . $option_value . ']';
            $this->exts->click_element($select_box);
            sleep(1);
            if ($this->exts->exists($option)) {
                $this->exts->log('Select box Option exists');
                $this->exts->click_by_xdotool($option);
                sleep(3);
            } else {
                $this->exts->log('Select box Option does not exist');
            }
        } else {
            $this->exts->log('Select box does not exist');
        }
    }

    private function doAfterLogin()
    {
        if ($this->exts->exists('#remind-me-later span.a-button')) {
            $this->exts->moveToElementAndClick('#remind-me-later span.a-button');
            sleep(10);
        }

        // Download from seller-vat-invoices
        if ((int)@$this->seller_invoice == 1) {
            $this->exts->openUrl('https://sellercentral.amazon.co.uk/tax/vatreports/bulkdownload');
            $this->downloadSellerVATInvoice(1);

            //Download Order Invoices
            $this->exts->moveToElementAndClick('#navbar .nav-button[role="button"]');
            sleep(2);
            if ($this->exts->exists('.side-nav a[href*="/orders-v3/ref=xx_"]')) {
                $this->exts->moveToElementAndClick('.side-nav a[href*="/orders-v3/ref=xx_"]');
                sleep(10);

                $this->exts->moveToElementAndClick('a[data-test-id="tab-/mfn/shipped"], a[href*="/orders-v3/mfn/shipped?"]');
            } else {
                $this->exts->openUrl('https://sellercentral.amazon.co.uk/orders-v3/mfn/shipped?page=1');
            }
            sleep(10);
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == '0') {
                //https://sellercentral.amazon.co.uk/orders-v3/mfn/shipped?page=1&date-range=1611220364000-1622159998000
                /*$startDate = date('d.m.y', strtotime('-2 years'));
            $currentUrl = $this->exts->getUrl();
            $orderPageURL = $currentUrl.'&date-range='.strtotime($startDate).'-'.strtotime(date('d.m.y'));
            */
                $this->changeSelectbox('select[name="myo-table-date-range"]', 'last-365', 15);
            } else {
                $this->changeSelectbox('select[name="myo-table-date-range"]', 'last-90', 15);
            }
            sleep(15);
            $this->changeSelectbox('select[name="myo-table-results-per-page"]', '100', 15);
            if (!$this->exts->exists('#orders-table tbody tr td .a-row [data-test-id="manage-idu-invoice-button"] input[type="submit"]')) {
                sleep(15);
            }
            $this->downloadOrderInvoices(1);
        }

        // Loop through all Marketplace, then download transaction, advertiser invoices and statement
        $this->exts->openUrl('https://sellercentral.amazon.co.uk/gp/payments-account/past-settlements.html');
        sleep(20);
        $marketplaces = $this->exts->getElementsAttribute('select#sc-mkt-picker-switcher-select option.sc-mkt-picker-switcher-select-option', 'value');
        $this->exts->log('NUMBER OF marketplaces: ' . count($marketplaces));
        if (count($marketplaces) > 0) {
            $this->no_marketplace = 1;
            foreach ($marketplaces as $key => $marketplace_option) {
                $this->exts->log('SWITCHING TO market place with path: ' . $marketplace_option);
                $this->currentSelectedMarketPlace = $marketplace_option;
                $this->exts->openUrl('https://sellercentral.amazon.co.uk/gp/payments-account/past-settlements.html');
                sleep(15);
                $this->changeSelectbox('select#sc-mkt-picker-switcher-select', $marketplace_option);
                sleep(15);

                if ($this->exts->querySelector($this->password_selector) == null) {
                    $market_place_homepage = $this->exts->getUrl();
                    if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]')) {
                        $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]');
                        sleep(15);
                    } else {
                        if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]')) {
                            $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]');
                            sleep(15);
                        }
                    }
                    $market_place_homepage = $this->exts->getUrl();

                    $Urldomain = "sellercentral.amazon.co.uk";
                    $currentUrl = $this->exts->getUrl();
                    $tempArr = parse_url($currentUrl);
                    $Urldomain = $tempArr["host"];

                    if ((int)@$this->transaction_invoices == 1) {
                        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
                        // Download from transaction page
                        if ($restrictPages == 0) {
                            $startDate = date('d.m.y', strtotime('-1 years'));
                        } else {
                            $startDate = date('d.m.y', strtotime('-2 months'));
                        }
                        $endDate = date('d.m.y');
                        $transaction_url = 'https://' . $Urldomain . '/gp/payments-account/view-transactions.html?searchLanguage=de_DE&view=filter&eventType=&subview=dateRange&startDate=' . $startDate . '&endDate=' . $endDate . '&Update=&pageSize=Ten&mostRecentLast=0';
                        $this->exts->log('TRANSACTION URL: ' . $transaction_url);
                        $this->exts->openUrl($transaction_url);
                        $this->downloadTransaction();
                    }

                    // Download from advertiser invoices
                    if ((int)@$this->no_advertising_bills != 1) {
                        $this->exts->openUrl($market_place_homepage);
                        sleep(10);
                        if (stripos($this->exts->getUrl(), '/payments/reports/summary') !== false || stripos(
                            $this->exts->getUrl(),
                            '/payments-account/settlement-summary.html'
                        ) !== false) {
                            if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]')) {
                                $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]');
                                sleep(15);
                            } else {
                                if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]')) {
                                    $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]');
                                    sleep(15);
                                }
                            }
                        } else {
                            $this->exts->openUrl('https://' . $Urldomain . '/payments/reports/summary');
                            sleep(15);
                        }

                        if ($this->exts->exists('a[href*="/gp/advertiser/transactions/transactions.html"]')) {
                            $this->exts->moveToElementAndClick('a[href*="/gp/advertiser/transactions/transactions.html"]');
                            $this->downloadAdvertiserInvoices();
                        } else {
                            //$this->exts->openUrl('https://'.$Urldomain.'/gp/advertiser/transactions/transactions.html');
                            //$this->downloadAdvertiserInvoices();
                            $this->exts->log('No Advertising Billing page found');
                        }
                    }

                    // Download from statement page
                    if ((int)@$this->payment_settlements == 1) {
                        $this->exts->openUrl('https://' . $Urldomain . '/gp/payments-account/past-settlements.html');
                        $this->downloadStatements();
                    }

                    // Download from seller-fee-invoices
                    if ((int)@$this->seller_fees == 1) {
                        $this->exts->openUrl('https://' . $Urldomain . '/tax/seller-fee-invoices');
                        $this->downloadSellerFeeInvoice();
                    }
                } else {
                    $this->exts->log("Need username password both. ");
                    $this->exts->capture("login-page-after-marketplace-change-" . $marketplace_option);
                }
            }
        } else if ($this->exts->exists('#partner-switcher button.dropdown-button, button.partner-dropdown-button')) {
            if ($this->exts->exists('[id*="react-joyride-step"] button[data-action="close"]')) {
                $this->exts->moveToElementAndClick('[id*="react-joyride-step"] button[data-action="close"]');
                sleep(2);
            }
            // Click partner dropdown
            $this->exts->moveToElementAndClick('#partner-switcher button.dropdown-button, button.partner-dropdown-button');
            sleep(1);
            $partner_levels = $this->exts->getElements('#partner-switcher .partner-level .dropdown-arrow');
            // It can be multil parter level, expand all then get all partner IDs
            foreach ($partner_levels as $key => $partner_level) {
                try {
                    $this->exts->log('Expand partner level');
                    $partner_level->click();
                } catch (\Exception $exception) {
                    $this->exts->executeSafeScript("arguments[0].click()", [$partner_level]);
                }
                sleep(2);
            }
            $this->exts->capture('multil-partner-checking');
            $merchantLinks = $this->exts->querySelectorAll('#partner-switcher .partner-level ul.merchant-level li a');
            $this->exts->log('Total partner Links - ' . count($merchantLinks));
            $merchantLinkIDs = array();
            // Process United Kingdom first, then other merchant
            foreach ($merchantLinks as $merchantLink) {
                $link_text = $merchantLink->getAttribute('innerText');
                $link_id = $merchantLink->getAttribute('id');
                if (
                    strpos($link_text, 'United Kingdom') !== false ||
                    strpos($link_text, 'Vereinigtes K') !== false ||
                    strpos($link_text, 'Regno Unito') !== false ||
                    strpos($link_text, 'Reino Unido') !== false ||
                    strpos($link_text, 'Royaume-Uni') !== false
                ) {
                    // If selection is UK, push to First Item in array
                    array_unshift($merchantLinkIDs, $link_id);
                } else {
                    array_push($merchantLinkIDs, $link_id);
                }
            }
            $totalMerchantLinks = count($merchantLinkIDs);
            $this->exts->log('Total Link IDs - ' . $totalMerchantLinks);
            for ($idx = 0; $idx < $totalMerchantLinks; $idx++) {
                $this->exts->log($merchantLinkIDs[$idx]);
                $linkSelector = '#partner-switcher .partner-level ul.merchant-level li a#' . $merchantLinkIDs[$idx];
                $this->exts->log('Link Selector - ' . $linkSelector);
                $this->exts->moveToElementAndClick($linkSelector);
                sleep(10);

                if ($this->exts->querySelector($this->username_selector) != null || $this->exts->querySelector($this->password_selector) != null) {
                    $this->checkFillLogin();
                    $this->checkFillTwoFactor();
                    if ($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
                        $this->exts->moveToElementAndClick('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
                        sleep(2);
                    }
                }

                $this->exts->capture("partner-switch-" . $merchantLinkIDs[$idx]);

                if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]');
                    sleep(15);
                } else {
                    if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]')) {
                        $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]');
                        sleep(15);
                    }
                }

                $market_place_homepage = $this->exts->getUrl();
                $this->exts->log('MarketPlace Home Page - ' . $market_place_homepage);

                $Urldomain = "sellercentral.amazon.co.uk";
                $currentUrl = $this->exts->getUrl();
                $tempArr = parse_url($currentUrl);
                $Urldomain = $tempArr["host"];

                if ((int)@$this->transaction_invoices == 1) {
                    // Download from transaction page
                    if ($this->restrictPages == 0) {
                        $startDate = strtotime('-1 years') . '000';
                    } else {
                        $startDate = strtotime('-2 months') . '000';
                    }
                    $endDate = strtotime('now') . '000';
                    $transaction_url = 'https://' . $Urldomain . '/payments/event/view?accountType=PAYABLE&startDate=' . $startDate . '&endDate=' . $endDate . '&resultsPerPage=50&pageNumber=1';
                    $this->exts->log('TRANSACTION URL (Standard Orders): ' . $transaction_url);
                    $this->exts->openUrl($transaction_url);
                    $this->downloadTransaction();
                    $transaction_url = 'https://' . $Urldomain . '/payments/event/view?accountType=INVOICING&startDate=' . $startDate . '&endDate=' . $endDate . '&resultsPerPage=50&pageNumber=1';
                    $this->exts->log('TRANSACTION URL (Invoiced Orders): ' . $transaction_url);
                    $this->exts->openUrl($transaction_url);
                    $this->downloadTransaction();
                }

                // Download from advertiser invoices
                if ((int)@$this->no_advertising_bills != 1) {
                    $this->exts->openUrl($market_place_homepage);
                    sleep(10);
                    if (stripos($this->exts->getUrl(), '/payments/reports/summary') !== false || stripos(
                        $this->exts->getUrl(),
                        '/payments-account/settlement-summary.html'
                    ) !== false) {
                        if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]')) {
                            $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]');
                            sleep(15);
                        } else {
                            if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]')) {
                                $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]');
                                sleep(15);
                            }
                        }
                    } else {
                        $this->exts->openUrl('https://' . $Urldomain . '/payments/reports/summary');
                        sleep(15);
                    }

                    if (!$this->exts->exists('a[href*="/gp/advertiser/transactions/transactions.html"]')) {
                        sleep(10);
                    }
                    if ($this->exts->exists('a[href*="/gp/advertiser/transactions/transactions.html"]')) {
                        $this->exts->moveToElementAndClick('a[href*="/gp/advertiser/transactions/transactions.html"]');
                        $this->downloadAdvertiserInvoices($merchantLinkIDs[$idx]);
                    } else {
                        //$this->exts->openUrl('https://'.$Urldomain.'/gp/advertiser/transactions/transactions.html');
                        //$this->downloadAdvertiserInvoices();
                        $this->exts->capture("No-advertising-Bill-" . $merchantLinkIDs[$idx]);
                        $this->exts->log('No Advertising Billing page found');
                    }
                }

                // Download from statement page
                if ((int)@$this->payment_settlements == 1) {
                    $this->exts->openUrl('https://' . $Urldomain . '/gp/payments-account/past-settlements.html');
                    $this->downloadStatements();
                }

                // Download from seller-fee-invoices
                if ((int)@$this->seller_fees == 1) {
                    $this->exts->openUrl('https://' . $Urldomain . '/tax/seller-fee-invoices');
                    $this->downloadSellerFeeInvoice();
                }

                sleep(5);

                if (!$this->exts->exists('#partner-switcher button.dropdown-button, button.partner-dropdown-button')) {
                    $this->exts->openUrl($market_place_homepage);
                    sleep(10);
                }
                $this->exts->moveToElementAndClick('#partner-switcher button.dropdown-button, button.partner-dropdown-button');
                sleep(1);
            }
        } else {
            $this->no_marketplace = 0;
            $this->exts->openUrl('https://sellercentral.amazon.co.uk/gp/payments-account/past-settlements.html');
            sleep(15);

            $Urldomain = "sellercentral.amazon.co.uk";

            if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]')) {
                $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]');
                sleep(15);
            } else if ($this->exts->querySelector('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]')) {
                $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]');
                sleep(15);
            } else {
                $this->exts->openUrl('https://' . $Urldomain . '/payments/reports/summary');
                sleep(15);
            }

            $advertDocsExists = false;
            if ($this->exts->exists('a[href*="/gp/advertiser/transactions/transactions.html"]')) {
                $advertDocsExists = true;
            }
            if ((int)@$this->transaction_invoices == 1) {
                // Download from transaction page
                if ($this->restrictPages == 0) {
                    $startDate = strtotime('-1 years') . '000';
                } else {
                    $startDate = strtotime('-2 months') . '000';
                }
                $endDate = strtotime('now') . '000';
                $transaction_url = 'https://' . $Urldomain . '/payments/event/view?accountType=PAYABLE&startDate=' . $startDate . '&endDate=' . $endDate . '&resultsPerPage=50&pageNumber=1';
                $this->exts->log('TRANSACTION URL (Standard Orders): ' . $transaction_url);
                $this->exts->openUrl($transaction_url);
                $this->downloadTransaction();
                $transaction_url = 'https://' . $Urldomain . '/payments/event/view?accountType=INVOICING&startDate=' . $startDate . '&endDate=' . $endDate . '&resultsPerPage=50&pageNumber=1';
                $this->exts->log('TRANSACTION URL (Invoiced Orders): ' . $transaction_url);
                $this->exts->openUrl($transaction_url);
                $this->downloadTransaction();
            }

            // Download from advertiser invoices
            if ((int)@$this->no_advertising_bills != 1 && $advertDocsExists) {
                $this->exts->openUrl('https://' . $Urldomain . '/gp/advertiser/transactions/transactions.html');
                $this->downloadAdvertiserInvoices();
            }

            // Download from statement page
            if ((int)@$this->payment_settlements == 1) {
                $this->exts->openUrl('https://' . $Urldomain . '/gp/payments-account/past-settlements.html');
                $this->downloadStatements();
            }

            // Download from seller-fee-invoices
            if ((int)@$this->seller_fees == 1) {
                $this->exts->openUrl('https://' . $Urldomain . '/tax/seller-fee-invoices');
                sleep(15);
                $this->downloadSellerFeeInvoice();
            }
        }
    }
    private function downloadTransaction($pageCount = 1)
    {
        sleep(10);
        $this->exts->capture("4-transaction-page");
        // 2021-12, maybe code in below if block is no longer work since this site changed, but still keep it as Mukesh request.
        if ($this->exts->exists('table > tbody > tr a[href*="/transaction-details.html?"]')) {
            $invoices = [];
            $rows = $this->exts->querySelectorAll('table > tbody > tr');
            $this->exts->log("Number of transactions rows - " . count($rows));
            foreach ($rows as $row) {
                $tags = $this->exts->querySelectorAll('td', $row);
                if (count($tags) >= 9 && $this->exts->querySelector('a[href*="/transaction-details.html?"]', end($tags)) != null) {
                    $invoiceUrl = $this->exts->querySelector('a[href*="/transaction-details.html?"]', end($tags))->getAttribute("href");
                    $invoiceName = explode(
                        '&',
                        array_pop(explode('transaction_id=', $invoiceUrl))
                    )[0];
                    $invoiceName = preg_replace("/[^\w]/", '', $invoiceName);
                    $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                    $amountText = trim(end($tags)->getAttribute('innerText'));
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

                    $invoiceAltName = trim($tags[2]->getAttribute('innerText'));
                    $checkText = preg_replace('/[^\d\.\,]/', '', $invoiceAltName);
                    if ($invoiceAltName == "---" || empty($checkText) || trim($checkText) == "") {
                        $invoiceAltName = $invoiceName;
                    }

                    if ($invoiceName == '' || $invoiceName == null) {
                        $invoiceName = time(); // create custom name in case no name found for this invoice
                        sleep(2);
                    }

                    if (!$this->exts->invoice_exists($invoiceName) || !$this->exts->invoice_exists($invoiceAltName)) {
                        array_push($invoices, array(
                            'invoiceName' => ($invoiceAltName != "" && $invoiceAltName != "---") ? $invoiceAltName : $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                    } else {
                        $this->exts->log('Invoice existed ' . $invoiceName);
                    }
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


                $invoiceFileName =  !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";

                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd M Y', 'Y-m-d');
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd#m#Y', 'Y-m-d');
                }
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                }
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'j M# y', 'Y-m-d');
                }
                $this->exts->log('Date parsed: ' . $parsed_date);

                $this->exts->openNewTab();
                $this->exts->openUrl($invoice['invoiceUrl']);
                sleep(5);

                //Check and Fill login page
                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(1);

                    if ($this->exts->querySelector($this->remember_me) != null) {
                        $checkboxElements = $this->exts->querySelectorAll($this->remember_me);
                        // $bValue = false;
                        if (count($checkboxElements) > 0) {
                            // $bValue = $checkboxElements[0]->isSelected();
                            // if($bValue == false) {
                            $this->exts->click_element($checkboxElements[0]);
                            // }
                        }
                    }

                    $this->exts->capture("2-login-page-filled");
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(5);
                }
                if (!$this->isLoginSuccess()) {
                    $this->exts->init_required();
                }
                sleep(5);
                $this->exts->executeSafeScript('
                document.querySelectorAll(\'div#container div#predictive-help\')[0].remove();
                document.querySelectorAll(\'div#sc-top-nav\')[0].remove();
                document.querySelectorAll(\'div#sc-footer-container\')[0].remove();
                document.querySelectorAll(\'div#left-side\')[0].setAttribute("style","float:left; text-align:left; width:100%;");
            ');

                $downloaded_file = $this->exts->download_current($invoiceFileName, 3);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                // close new tab too avoid too much tabs
                $this->exts->switchToInitTab();
                sleep(2);
                $this->exts->closeAllTabsButThis();
            }


            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == 0 && $pageCount < 100 && $this->exts->querySelector('.currentpagination + a') != null) {
                if (count($invoices) == 0) {
                    $this->exts->update_process_lock();
                }
                $pageCount++;
                $this->exts->executeSafeScript('
                document.querySelectorAll(\'.currentpagination + a\')[0].click();
            ');
                //$this->exts->moveToElementAndClick('.currentpagination + a');
                sleep(5);
                $this->downloadTransaction($pageCount);
            }
        } else if ($this->exts->exists('.transactions-table-content [role="row"]')) {
            // Huy added 2021-12
            for ($paging_count = 1; $paging_count < 100; $paging_count++) {
                $invoices = [];
                $rows = count($this->exts->querySelectorAll('.transactions-table-content [role="row"]'));
                for ($i = 0; $i < $rows; $i++) {
                    $row = $this->exts->querySelectorAll('.transactions-table-content [role="row"]')[$i];
                    $detail_button = $this->exts->getElement('a#link-target', $row);
                    if ($detail_button != null) {
                        $this->isNoInvoice = false;
                        $invoiceName =  $this->exts->extract('[role="cell"]:nth-child(3)', $row);
                        $invoiceName = trim($invoiceName);
                        $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                        $invoiceDate = $this->exts->extract('[role="cell"]:nth-child(1)', $row);
                        $amountText = $this->exts->extract('a#link-target', $row);
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

                        $this->exts->log('--------------------------');
                        $this->exts->log('invoiceName: ' . $invoiceName);
                        $this->exts->log('invoiceDate: ' . $invoiceDate);
                        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                        $parsed_date = $this->exts->parse_date($invoiceDate, 'd-M-Y', 'Y-m-d');
                        $this->exts->log('Date parsed: ' . $parsed_date);

                        // Download invoice if it not exisited
                        if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            try {
                                $this->exts->log('Click detail button');
                                $detail_button->click();
                            } catch (\Exception $exception) {
                                $this->exts->log('Click detail button by javascript');
                                $this->exts->executeSafeScript("arguments[0].click()", [$detail_button]);
                            }
                            sleep(1);
                            $this->exts->waitTillPresent('#sc-content-container .transaction-details-body-section .event-details-body');
                            if ($this->exts->exists('#sc-content-container .transaction-details-body-section .event-details-body')) {
                                // Clear some alert, popup..etc
                                $this->exts->executeSafeScript('
                                if(document.querySelector("kat-alert") != null){
                                   document.querySelector("kat-alert").shadowRoot.querySelector("[part=alert-dismiss-button]").click();
                                }
                            ');
                                $this->exts->moveToElementAndClick('.katHmdCancelBtn');
                                // END clearing alert..

                                // Capture page if detail displayed
                                $this->exts->executeSafeScript('
                                var divs = document.querySelectorAll("body > div > *:not(#sc-content-container)");
                                for( var i = 0; i < divs.length; i++){
                                    divs[i].style.display = "none";
                                }
                            ');

                                $downloaded_file = $this->exts->download_current($invoiceFileName, 0);
                                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                                } else {
                                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                                }
                            } else {
                                $this->exts->capture("4-transaction-detail-error");
                            }

                            // back to transaction list
                            $this->exts->moveToElementAndClick('.transaction-details-footer-section a#link-target');
                            sleep(2);
                        }
                        $this->isNoInvoice = false;
                    }
                }

                // Process next page
                // This page using shadow element, We must process via JS
                $is_next = $this->exts->executeSafeScript('
                try {
                    document.querySelector("kat-pagination").shadowRoot.querySelector("[part=pagination-nav-right]:not(.end)").click();
                    return true;
                } catch(ex){
                    return false;	
                }
            ');
                if ($is_next && $this->exts->config_array["restrictPages"] == '0') {
                    sleep(7);
                } else {
                    break;
                }
            }
        }
    }
    private function downloadStatements($pageCount = 1)
    {
        $this->exts->log(__FUNCTION__);
        sleep(15);
        $this->exts->capture("4-statements-page");
        if ($this->exts->exists('table > tbody > tr a[href*="/settlement-summary"]')) {
            $invoices = [];
            $rows = $this->exts->querySelectorAll('table > tbody > tr');
            foreach ($rows as $row) {
                $tags = $this->exts->querySelectorAll('td', $row);
                if (count($tags) >= 7 && $this->exts->querySelector('a[href*="/settlement-summary"]', end($tags)) != null && $this->exts->querySelector('a[href*="/payments/reports/download?"]', end($tags)) != null) {
                    $invoiceUrl = $this->exts->querySelector('a[href*="/settlement-summary"]', end($tags))->getAttribute("href");
                    $invoiceName = explode(
                        '&',
                        array_pop(explode('groupId=', $invoiceUrl))
                    )[0];
                    $invoiceName = preg_replace("/[^\w]/", '', $invoiceName);
                    $invoiceDate = trim(end(explode(' - ', $tags[0]->getText())));
                    $amountText = trim($tags[count($tags) - 2]->getText());
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

                    if ($invoiceName == '' || $invoiceName == null) {
                        $invoiceName = time(); // create custom name in case no name found for this invoice
                        sleep(2);
                    }

                    $invoiceAltName = "Seller-Invoice" . $invoiceDate;
                    if (!$this->exts->invoice_exists($invoiceName) && !$this->exts->invoice_exists($invoiceAltName)) {
                        array_push($invoices, array(
                            'invoiceName'   => $invoiceName,
                            'invoiceDate'   => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl'    => $invoiceUrl
                        ));
                        $this->isNoInvoice = false;
                    } else {
                        $this->exts->log("Invoice exists - " . $invoiceName);
                    }
                }
            }

            // Download all invoices
            $this->exts->log('Statements found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd M Y', 'Y-m-d');
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd#m#Y', 'Y-m-d');
                }
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                }
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'j M# y', 'Y-m-d');
                }
                $this->exts->log('Date parsed: ' . $parsed_date);

                $this->exts->openNewTab();
                $this->exts->openUrl($invoice['invoiceUrl']);
                sleep(5);

                $this->checkFillLogin();
                if (!$this->isLoginSuccess()) {
                    $this->checkFillTwoFactor();

                    if (!$this->isLoginSuccess()) {
                        $this->exts->init_required();
                    }
                }

                if (count($this->exts->querySelectorAll('#printableSections')) > 0) {
                    $this->exts->executeSafeScript('
                    var printableView = document.getElementById("printableSections");
                    var allLinks = document.getElementsByTagName("link");
                    var allStyles = document.getElementsByTagName("style");
                    var printableHTML = Array.from(allLinks).map(link => link.outerHTML).join("")
                                        + Array.from(allStyles).map(link => link.outerHTML).join("")
                                        + printableView.outerHTML;
                    document.body.innerHTML = printableHTML;
                ');

                    $downloaded_file = $this->exts->download_current($invoiceFileName, 3);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                } else if (count($this->exts->querySelectorAll('#sc-navbar-container')) > 0) {
                    $this->exts->executeSafeScript('
                    document.querySelectorAll("#sc-navbar-container")[0].remove();
                    document.querySelectorAll("article.dashboard-header")[0].remove();
                    document.querySelectorAll(".sc-footer")[0].remove();
                ');

                    $downloaded_file = $this->exts->download_current($invoiceFileName, 3);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::Page design is changed for print ' . $invoiceFileName);
                }

                // close new tab too avoid too much tabs
                $this->exts->switchToInitTab();
                sleep(2);
                $this->exts->closeAllTabsButThis();
            }

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if (
                $restrictPages == 0 &&
                $pageCount < 100 &&
                $this->exts->querySelector('.currentpagination + a') != null
            ) {
                if (count($invoices) == 0) {
                    $this->exts->update_process_lock();
                }

                $pageCount++;
                //$this->exts->moveToElementAndClick('.currentpagination + a');
                $this->exts->executeSafeScript('
                document.querySelectorAll(\'.currentpagination + a\')[0].click();
            ');
                sleep(15);
                $this->downloadStatements($pageCount);
            }
        } else if ($this->exts->exists('kat-data-table tbody tr kat-link[href*="/detail"]')) {
            // Huy added this 2021-12
            if ($this->exts->config_array["restrictPages"] == '0') {
                $currentPageHeight = 0;
                for ($i = 0; $i < 15 && $currentPageHeight != $this->exts->executeSafeScript('return document.body.scrollHeight;'); $i++) {
                    $this->exts->log('Scroll to bottom ' . $currentPageHeight);
                    $currentPageHeight = $this->exts->executeSafeScript('return document.body.scrollHeight;');
                    $this->exts->executeSafeScript('window.scrollTo(0,document.body.scrollHeight);');
                    sleep(7);
                }
                sleep(5);
            }

            // It using shadow root, so collect invoice detail by JS
            $invoices = $this->exts->executeSafeScript('
            var data = [];
            var trs = document.querySelectorAll("kat-data-table tbody tr kat-link[href*=detail][href*=groupId]");
        
            // Skip first row because it is current period, do not get it
            for (var i = 1; i < trs.length; i ++) {
                var link = trs[i].shadowRoot.querySelector("a");
                var url = link.href;

                data.push({
                    invoiceName: url.split("groupId=").pop().split("&")[0],
                    invoiceDate: "",
                    invoiceAmount: "",
                    invoiceUrl: url
                });
            }
            return data;
        ');
            // Download all invoices
            $this->exts->log('Statements found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
                $this->isNoInvoice = false;

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoice['invoiceName']) || $this->exts->document_exists($invoiceFileName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->openNewTab();
                    $this->exts->openUrl($invoice['invoiceUrl']);
                    sleep(2);
                    $this->checkFillLogin();
                    if (!$this->isLoginSuccess()) {
                        $this->checkFillTwoFactor();
                    }

                    if ($this->exts->exists('.dashboard-content #print-this-page-link')) {
                        // Clear some alert, popup..etc
                        $this->exts->executeSafeScript('
                        if(document.querySelector("kat-alert") != null){
                           document.querySelector("kat-alert").shadowRoot.querySelector("[part=alert-dismiss-button]").click();
                        }
                    ');
                        $this->exts->moveToElementAndClick('.katHmdCancelBtn');
                        // END clearing alert..

                        $this->exts->moveToElementAndClick('.dashboard-content #print-this-page-link');
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['invoiceName'], '', '', $downloaded_file);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    } else {
                        $this->exts->capture('statement-detail-error');
                    }

                    // close new tab too avoid too much tabs
                    $this->exts->switchToInitTab();
                    sleep(2);
                    $this->exts->closeAllTabsButThis();
                }
            }
        }
    }
    private function downloadSellerFeeInvoice()
    {
        sleep(25);
        $this->exts->capture("4-seller-fee-invoices-page");

        $total_fee_downloaded = 0;
        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        $this->exts->log("Number of seller fees rows - " . count($rows));
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 14 && $this->exts->querySelector('button[data-invoice]', end($tags)) != null) {
                $invoice_button = $this->exts->querySelector('button[data-invoice]', end($tags));
                $invoiceName = $this->exts->extract('td:nth-child(6)', $row);
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : time();
                sleep(1);
                $invoiceDate = trim($tags[count($tags) - 3]->getText());
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'D M d H:i:s * Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $invoice_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$invoice_button]);
                    }
                    sleep(10);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No pdf ' . $invoiceFileName);
                        // Invoice maybe old and it is html, implement code if user require them
                    }

                    // close new tab too avoid too much tabs
                    $this->exts->switchToInitTab();
                    sleep(2);
                    $this->exts->closeAllTabsButThis();
                }
                $total_fee_downloaded++;
                $this->isNoInvoice = false;
            }
            if ($total_fee_downloaded >= 50) break;
        }
    }
    private function downloadSellerVATInvoice($pageCount = 1)
    {
        sleep(10);
        $date_format = "m.d.Y";
        $startDate = date($date_format, strtotime('-7 days'));
        if ($this->exts->exists('select[name="reportType"]') && $pageCount == 1) {
            $this->changeSelectbox('select[name="reportType"]', "VAT Invoices");
            sleep(10);
            if ($this->exts->exists('li#vtr-start-date2 input#vtr-start-date-calendar2')) {
                $currentStart_date = $this->exts->querySelector('li#vtr-start-date2 input#vtr-start-date-calendar2')->getAttribute('aria-label');

                if (stripos($currentStart_date, "m/d") !== false || stripos($currentStart_date, "d/y") !== false) {
                    $date_format = "m/d/Y";
                } else if (stripos($currentStart_date, "m-d") !== false || stripos($currentStart_date, "d-y") !== false) {
                    $date_format = "m-d-Y";
                }
                $this->exts->log(__FUNCTION__ . '::Date format ' . $date_format);
                $startDate = date($date_format, strtotime('-7 days'));
                $endDate = date($date_format);

                $this->exts->moveToElementAndType('li#vtr-start-date2 input#vtr-start-date-calendar2', $startDate);
                sleep(1);
                $this->exts->moveToElementAndType('li#vtr-end-date1 input#vtr-end-date-calendar1', $endDate);
                sleep(1);
                $this->exts->capture("4-seller-vat-1-filter");
                $this->exts->moveToElementAndClick('form#vtr-request-report-form-Bulk-Downlaod input#generate-report-button[type="submit"]');
                sleep(15);
                $this->exts->capture("4-seller-vat-2-submitted");
                $this->exts->openUrl('https://sellercentral.amazon.co.uk/tax/vatreports/bulkdownload');
                sleep(60);
            }
        }

        $this->exts->capture("4-seller-vat-invoices-page");

        $invoices = [];
        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        $this->exts->log("Number of VAT Invoice rows - " . count($rows));
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 4 && $this->exts->querySelector('a[href*="/invoice/download/id/"]', $tags[3]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="/invoice/download/id/"]', $tags[3])->getAttribute("href");
                $invoiceName = explode(
                    '/',
                    array_pop(explode('/id/', $invoiceUrl))
                )[0];
                $invoiceName = preg_replace('/[^\w]/', '', $invoiceName);
                $invoiceDate = trim($tags[1]->getText());
                $invoiceAmount = '';

                $downloadBtn = $this->exts->querySelector('a[href*="/invoice/download/id/"]', $tags[3]);

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
        $this->exts->log('Seller VAT Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = $invoice['invoiceName'] . '.zip';
            $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd M Y', 'Y-m-d');
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd# M Y', 'Y-m-d');
            }
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'M d# Y', 'Y-m-d');
            }
            $this->exts->log('Date parsed: ' . $parsed_date);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'zip', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                try {
                    $this->exts->log('Click download button');
                    $invoice['downloadBtn']->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$invoice['downloadBtn']]);
                }
                sleep(15);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    sleep(15);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0 && $pageCount < 25 && $this->exts->querySelector('#formForNextPage button#nextButton') != null) {
            if (count($invoices) == 0) {
                $this->exts->update_process_lock();
            }
            $pageCount++;
            $this->exts->moveToElementAndClick('#formForNextPage button#nextButton');
            sleep(5);
            $this->downloadSellerVATInvoice($pageCount);
        }
    }
    private function downloadAdvertiserInvoices($partnerId = '')
    {
        sleep(25);
        if ($this->exts->exists('button[class*="CloseButton"]')) {
            $this->exts->moveToElementAndClick('button[class*="CloseButton"]');
            sleep(2);
        }
        $this->exts->capture("4-advertiser-invoices-page" . $partnerId);

        //if($this->exts->querySelector('select#sc-mkt-picker-switcher-select option.sc-mkt-picker-switcher-select-option[value*="'.trim($this->currentSelectedMarketPlace).'"]') != null || (int)@$this->no_marketplace == 0) {
        /* With New Design Marketplace check is not possible
    if((int)@$this->no_marketplace != 0) {
        $this->exts->log("Checking marketplace is swtiched correctly");
        $selectedMarketplace = $this->exts->querySelector('select#sc-mkt-picker-switcher-select option.sc-mkt-picker-switcher-select-option[value*="'.trim($this->currentSelectedMarketPlace).'"]')->getAttribute('selected');
        $this->exts->log("Selected Marketplace - ".$selectedMarketplace);
    } else {
        $this->exts->log("Selected Marketplace - No MarketPlace");
    }*/
        $selectedMarketplace = $this->currentSelectedMarketPlace;
        $this->exts->log("Selected Marketplace - " . $selectedMarketplace);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        //Remove this check because now checking marketplace is not possible in billing page
        //if($selectedMarketplace != null || (int)@$this->no_marketplace == 0) {
        if ($this->exts->exists('.calendarsContainer input#startCal')) {
            $currentStart_date = $this->exts->querySelector('.calendarsContainer input#startCal')->getAttribute('aria-label');
            $date_format = "d.m.Y";
            if (stripos($currentStart_date, "d/m") !== false || stripos($currentStart_date, "m/y") !== false) {
                $date_format = "d/m/Y";
            } else if (stripos($currentStart_date, "d-m") !== false || stripos($currentStart_date, "m-y") !== false) {
                $date_format = "d-m-Y";
            }
            $this->exts->log(__FUNCTION__ . '::Date format ' . $date_format);
            // if restrictpages == 0 then 2 years otherwise 2 month
            $endDate = date($date_format);
            if ($restrictPages == 0) {
                $startDate = date($date_format, strtotime('-2 years'));
            } else {
                $startDate = date($date_format, strtotime('-2 months'));
            }

            $this->exts->moveToElementAndType('.calendarsContainer input#startCal', $startDate);
            sleep(1);
            $this->exts->moveToElementAndType('.calendarsContainer input#endCal', $endDate);
            sleep(1);
            $this->exts->capture("4-advertiser-invoices-1-filter");
            $this->exts->moveToElementAndClick('.calendarsContainer [type="submit"]');
            sleep(15);
            $this->exts->capture("4-advertiser-invoices-2-submitted");
        } else if ($this->exts->exists('.calendarsContainer button')) {
            $this->exts->moveToElementAndClick('.calendarsContainer button');
            sleep(1);
            if ($restrictPages != 0) {
                $this->exts->moveToElementAndClick('.calendarsContainer button[value="last90Days"]');
                sleep(15);
            }
        }

        // get invoice
        for ($paging_count = 1; $paging_count < 100; $paging_count++) {
            $invoices = [];
            $rows = count($this->exts->querySelectorAll('table#paidTable > tbody > tr'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->querySelectorAll('table#paidTable > tbody > tr')[$i];
                $download_button = $this->exts->getElement('.dwnld-icon-alignment .dwnld-btn-enb', $row);
                if ($download_button != null) {
                    $invoiceName =  trim($this->exts->extract('td[id^="invoice-number"]', $row));
                    $invoiceFileName =  !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceDate = trim($this->exts->extract('td[id^="invoice-date"]', $row));
                    $amountText = trim($this->exts->extract('td[id^="invoice-total"]', $row));
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

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'd-M-Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $parsed_date);

                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                        }
                        sleep(1);
                        $this->exts->moveToElementAndClick('.a-popover[aria-hidden="false"] a[id$="invoicePDF"]');
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                    $this->isNoInvoice = false;
                }
            }

            // Process next page
            if ($this->exts->exists('ul.a-pagination li.a-last:not(.a-disabled)')) {
                $this->exts->moveToElementAndClick('ul.a-pagination li.a-last:not(.a-disabled)');
                sleep(10);
            } else {
                break;
            }
        }
    }
    private function downloadOrderInvoices($pagecount = 1)
    {
        $this->exts->capture('order-page');
        $temp_paths = explode('/', $this->exts->getUrl());
        $current_domain_country = end(explode('amazon.', $temp_paths[2]));

        $rows = $this->exts->querySelectorAll('#orders-table tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            $invoice_manage_button = $this->exts->querySelector('[data-test-id="manage-idu-invoice-button"] input[type="submit"]', $row);
            if ($invoice_manage_button != null) {
                try {
                    $invoice_manage_button->click();
                } catch (\Exception $exception) {
                    $this->exts->executeSafeScript("arguments[0].click();", [$invoice_manage_button]);
                }
                sleep(2);
                $this->exts->waitTillPresent('.a-popover-modal[aria-hidden="false"] kat-table-body [role="row"]');
                sleep(2);

                $popRows = $this->exts->querySelectorAll('.a-popover-modal[aria-hidden="false"] kat-table-body [role="row"]');
                foreach ($popRows as $popRow) {
                    $invoice_link = $this->exts->querySelector('a[href*="/invoice/download"]', $popRow);
                    if ($invoice_link != null) {
                        $this->isNoInvoice = false;
                        $invoiceName = trim($this->exts->extract('td:nth-child(3)', $popRow));
                        if (!$this->exts->invoice_exists($invoiceName)) {
                            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                            $invoiceAmount = '';
                            $invoiceDate = '';
                            $invoiceUrl = $invoice_link->getAttribute('href');

                            $this->exts->log('--------------------------');
                            $this->exts->log('invoiceName: ' . $invoiceName);
                            $this->exts->log('invoiceDate: ' . $invoiceDate);
                            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                            $this->exts->log('invoiceUrl: ' . $invoiceUrl);
                            $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                            // Huy 2021-11, If invoice url is from other domain, It required login, do below trick to avoid login form
                            $invoice_url_paths = explode('/', $invoiceUrl);
                            $invoice_domain_country = end(explode('amazon.', $invoice_url_paths[2]));
                            if ($invoice_domain_country != $current_domain_country) {
                                $invoice_url_paths[2] = str_replace($invoice_domain_country, $current_domain_country, $invoice_url_paths[2]);
                                $invoiceUrl = join('/', $invoice_url_paths);
                                $this->exts->log('invoice Url domain corrected: ' . $invoiceUrl);
                            }

                            $this->exts->openNewTab();
                            $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                                sleep(1);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            }

                            // close new tab too avoid too much tabs
                            $this->exts->switchToInitTab();
                            sleep(2);
                            $this->exts->closeAllTabsButThis();
                        } else {
                            $this->exts->log('Invoice existed - ' . $invoiceName);
                        }
                    }
                }
            }
        }

        $have_next_page = $this->exts->exists('.footer .pagination-controls .a-pagination .a-last a');
        if ($have_next_page && $pagecount < 10) {
            $this->exts->moveToElementAndClick('.footer .pagination-controls .a-pagination .a-last a');
            sleep(15);
            $pagecount++;
            $this->downloadOrderInvoices($pagecount);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
