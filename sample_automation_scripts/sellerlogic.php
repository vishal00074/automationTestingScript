<?php // triggring loginfailedConfirmed locally 
/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673482/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // load cookies from file for desktop app
                if (!empty($this->exts->config_array["without_password"])) {
                    $this->exts->loadCookiesFromFile();
                }
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 31693 - Last modified: 01.08.2025 14:56:32 UTC - User: 1

    // Start Script

    public $baseUrl = 'https://app.sellerlogic.com/';
    public $loginUrl = 'https://app.sellerlogic.com/';
    public $invoicePageUrl = 'https://app.sellerlogic.com/payments/invoice?sort=-date_published&page=1&pageSize=10';
    public $username_selector = 'input#username';
    public $password_selector = 'form#login-form input[type="password"], form#login-form input#password';
    public $remember_me_selector = '';
    public $submit_login_btn = 'button#loginButton';
    public $checkLoginFailedSelector = 'form#login-form input.error, div#error-password, div#error-username';
    public $checkLoggedinSelector = 'span[aria-label="icnUser"], a[data-action="userLogout"], a[href="/site/logout/"], a[href*="/payments/invoice"], [class*="navLinks_userDetailsMenuItem"], [data-nested-menu-id="profile"] a[href="/userSetting"]';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in

        // Wait for selector that make sure user logged in
        sleep(10);
        if ($this->exts->querySelector($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');
            $this->clearChrome();

            $this->exts->openUrl($this->loginUrl);
            $this->waitForLoginPage();
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(3);
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
    }

    private function waitForLoginPage($count = 1)
    {
        sleep(25);
        $this->exts->capture(__FUNCTION__);
        $this->exts->waitTillPresent($this->username_selector, 30);
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("1-filled-login");
            $this->exts->click_element($this->submit_login_btn);
            sleep(5);
            $this->checkFillTwoFactor();
            sleep(5);
            $this->waitForLogin($count);
        } else if ($this->exts->querySelector($this->username_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            //click next
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(8);
            $emailRegex = "/^[^\s@]+@[^\s@]+\.[^\s@]+$/";
            $isEmailFormat = (bool)preg_match($emailRegex, $this->username);

            // Output the result
            if (!$isEmailFormat) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure(1);
            }
            $this->exts->waitTillAnyPresent($this->password_selector, 50);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("1-filled-login");
            $this->exts->waitTillAnyPresent($this->submit_login_btn, 50);
            $this->exts->moveToElementAndClick($this->submit_login_btn);

            $this->exts->waitTillPresent('div[class*="password error"]', 20);
            if ($this->exts->exists('div[class*="password error"]')) {
                $this->exts->loginFailure(1);
            }

            $this->checkFillTwoFactor();
            $this->waitForLogin($count);
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }

    private function waitForLogin($count = 1)
    {
        sleep(35);

        if ($this->exts->exists('.ant-spin-dot-spin')) {
            $this->exts->openUrl($this->baseUrl);
            sleep(20);
        }
        for ($wait_count = 1; $wait_count <= 10 && $this->exts->exists('.ant-spin-text'); $wait_count++) {
            $this->exts->log('Waiting for load page after submit login...');
            sleep(5);
        }
        $error_msg = $this->exts->extract('#error-username');
        if (strpos($error_msg, 'Ihr Benutzer ist gesperrt') !== false || strpos($error_msg, 'Your user is locked') !== false) {
            $this->exts->log('account not ready');
            $this->exts->account_not_ready();
        }

        if ($this->exts->exists(".input-container.password-with-web-auth.field-password.error")) {
            $error_login_msg = $this->exts->getElement('#error-password');
            $err_text = $this->exts->executeSafeScript('return arguments[0].textContent;', [$error_login_msg]);
            if ($err_text !== null && strpos(strtolower($err_text), 'passwor') !== false) {
                $this->exts->log('Timeout waitForLogin ' . $this->exts->getUrl());
                $this->exts->capture("LoginFailed");
                if (strpos(strtolower($err_text), 'passwor') === 0 || strpos(strtolower($err_text), 'passwor') > 0) {
                    $this->exts->log($err_text);
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        }

        $error_change_email_msg = $this->exts->extract('.change-email-modal .change-email-modal__content');
        $this->exts->log('Waiting ---------' . $error_change_email_msg);
        if ((strpos($error_change_email_msg, 'tigen Sie die E-Mail-Adresse, um die Registrierung abzuschlie') !== false || strpos($error_change_email_msg, 'and confirm the email address to finish registration') !== false) && $this->exts->exists('.change-email-modal #change-email')) {
            $this->exts->log('account not ready');
            $this->exts->account_not_ready();
        }
        $error_change_email_msg = $this->exts->extract('.change-email-modal .change-email-modal__content');
        $this->exts->log('Waiting ---------' . $error_change_email_msg);
        if ((strpos($error_change_email_msg, 'tigen Sie die E-Mail-Adresse, um die Registrierung abzuschlie') !== false || strpos($error_change_email_msg, 'and confirm the email address to finish registration') !== false) && $this->exts->exists('.change-email-modal #change-email')) {
            $this->exts->log('account not ready');
            $this->exts->account_not_ready();
        }
        $error_setup2FA_msg = $this->exts->extract('div[class*="twoFA_container"] .ant-alert');
        $this->exts->log('Waiting ---------' . $error_setup2FA_msg);
        if ((strpos($error_setup2FA_msg, 'Die globalen Einstellungen erfordern, dass Sie die Zwei-Faktor-Authentifizierung') !== false
            || strpos($error_setup2FA_msg, 'The global settings require you to enable two-factor authentication for your account') !== false)) {
            $this->exts->log('account not ready');
            $this->exts->account_not_ready();
        }


        $this->exts->capture("timeout-after-submit-login");
        $this->exts->openUrl($this->baseUrl);
        sleep(20);

        if ($this->exts->exists('[class*="privacy_confirmText"]')) {
            $this->exts->moveToElementAndClick('[class*="privacy_confirmText"]');
            sleep(2);
            $this->exts->moveToElementAndClick('[class*="privacy_confirmBtn"]');
            sleep(10);
        }
        $this->exts->moveToElementAndClick('.anticon-user');
        sleep(5);
        if ($this->exts->exists('button.main-modal-btn-ok')) {
            $this->exts->moveToElementAndClick('button.main-modal-btn-ok');
            sleep(3);
        }

        $this->exts->capture(__FUNCTION__);
        if ($this->exts->querySelector($this->checkLoggedinSelector) != null) {
            sleep(3);
            $this->exts->log('User logged in.');

            // Open invoices url
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#validategoogle2facodeform-secret';
        $two_factor_message_selector = 'div.form_wrapper__two_factory_auth, form#two-factor-auth-login-form div.text-centered';
        $two_factor_submit_selector = 'button.submit.auth-btn, button[type="submit"]';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
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

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->querySelector($two_factor_selector)->clear();
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $total_invoices = 0;
        $this->exts->waitTillPresent('table tbody tr', 30);
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(1) span', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = '';
                $invoiceAmount = '';
                $invoiceDate = '';
                $downloadBtn = $this->exts->querySelector('td:nth-child(1) span', $row);

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
            if ($this->restrictPages != 0 && $total_invoices >= 100) break;
            $this->exts->log('--------------------------');
            // $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            // $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'y-m-d', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $total_invoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 && $paging_count < 50 &&
            $this->exts->querySelector('.ant-pagination-next:not(.ant-pagination-disabled)') != null
        ) {
            $paging_count++;
            $this->exts->click_element('.ant-pagination-next:not(.ant-pagination-disabled)');
            sleep(5);
            $this->processInvoices($paging_count);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('.ant-pagination-next:not(.ant-pagination-disabled)') != null) {
            $paging_count++;
            $this->exts->click_element('.ant-pagination-next:not(.ant-pagination-disabled)');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
