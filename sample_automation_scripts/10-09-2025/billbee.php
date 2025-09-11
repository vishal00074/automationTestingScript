<?php //  adjust sleep time to optimized the script and addded logs for invoiceName invoiceDate and invoiceAmount in download code
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

    public $baseUrl = 'https://app.billbee.io/app_v2/';
    public $loginUrl = 'https://app.billbee.io/app_v2/sign-in';

    public $username_selector = 'input[name="UserName"]';
    public $password_selector = 'input[name="Password"]';
    public $remember_me_selector = 'button[name="RememberMe"]';
    public $submit_login_selector = 'bb-membership-frame button.mat-focus-indicator.mat-flat-button.mat-button-base.mat-primary, bb-membership-frame button.mdc-button.mat-primary';

    public $check_login_failed_selector = 'div.validation-summary-errors, [id*="mat-error"]:not([hidden])[style], [id*="mat-error"]:not([hidden])>font';
    public $check_login_success_selector = 'a[href*="/Logout"],a[href*="/logout"], mat-toolbar:not([hidden=""]) mat-icon[class*="fa-user"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(7);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture('1-init-page');
        $this->waitFor($this->check_login_success_selector, 5);
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->clearChrome();
            sleep(5);
            $this->exts->clearCookies();
            sleep(3);
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
            if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
                $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
                sleep(5);
            }
            $this->checkFillLogin();
            $this->checkFillTwoFactor();
            $this->waitFor($this->check_login_success_selector);
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->exts->openUrl('https://app.billbee.io/app_v2/account/invoices-and-payment');
            sleep(10);
            $this->waitFor('a[href*="/invoices-and-payment/invoices"]');
            if ($this->exts->exists('a[href*="/invoices-and-payment/invoices"]')) {
                $this->exts->click_element('a[href*="/invoices-and-payment/invoices"]');
                sleep(15);
                $this->processInvoices();
            } else {
                $this->exts->log('Not found selector a[href*="/invoices-and-payment/invoices"] !!');
            }
            sleep(3);
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'text')), 'benutzername oder passwort sind') !== false) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->exists('input[aria-invalid="true"]')) {
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
        for ($i = 0; $i < 6; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }


    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
            // Add fallback click
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->type_key_by_xdotool('Return');
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form input[name="Otp"]';
        $two_factor_message_selector = 'bb-request-2fa-dialog .mat-dialog-content > p';
        $two_factor_submit_selector = 'bb-request-2fa-dialog button.mat-mdc-button-base:not([mat-dialog-close])';
        $this->waitFor($two_factor_selector);
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
                $this->exts->type_text_by_xdotool($two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(5);

                if ($this->exts->getElement($two_factor_selector) == null && stripos(strtolower($this->exts->extract('[id*=mat-dialog-title]', null, 'text')), 'falscher 2fa code') === false) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {

                    if (stripos(strtolower($this->exts->extract('[id*=mat-dialog-title]', null, 'text')), 'falscher 2fa code') !== false) {
                        $this->exts->moveToElementAndClick('mat-dialog-actions button');
                        sleep(7);
                    }
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

    public $totalInvoices = 0;

    private function processInvoices()
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        sleep(3);
        $this->waitFor('table > tbody > tr[ng-repeat*="invoices"] a[ng-click*="download"]');
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr[ng-repeat*="invoices"]');
        foreach ($rows as $index => $row) {

            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }

            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 8 && $this->exts->getElement('a[ng-click*="download"]', $tags[10]) != null) {
                $invoiceSelector = $this->exts->getElement('a[ng-click*="download"]', $tags[10]);
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));

                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                
                $this->isNoInvoice = false;
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // click and download invoice
                    $downloaded_file = $this->exts->click_and_download($invoiceSelector, 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        $this->totalInvoices++;
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }
        }
    }
}
