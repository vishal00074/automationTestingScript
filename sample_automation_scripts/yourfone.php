<?php // update check_login_success_selector,check_login_success_selector and updated download code 

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

    // Server-Portal-ID: 11277 - Last modified: 21.11.2024 13:25:17 UTC - User: 1

    // Script here
    public $baseUrl = 'https://service.yourfone.de';
    public $loginUrl = 'https://service.yourfone.de';
    public $invoicePageUrl = 'https://service.yourfone.de/mytariff/invoice/showAll';

    public $username_selector = 'input[id*="UserLoginType_alias"]';
    public $password_selector = 'input[id*="UserLoginType_password"]';
    public $remember_me_selector = 'input[type*="checkbox"]';
    public $submit_login_selector = 'a[onclick*="submitForm"]';

    public $check_login_failed_selector = 'div.error.s-validation';
    public $check_login_success_selector = 'div#logoutLink, span#logoutLink';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
            $this->exts->waitTillPresent($this->check_login_success_selector);
        }
        if ($this->exts->exists('div[class*="layout-wrap"] button[id*="submit_all"], button#consent_wall_optin')) {
            $this->exts->log("Accept Cookie");
            $this->exts->moveToElementAndClick('div[class*="layout-wrap"] button[id*="submit_all"], button#consent_wall_optin');
            sleep(15);
        }
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('div.c-overlay-content a.c-overlay-close')) {
                $this->exts->moveToElementAndClick('div.c-overlay-content a.c-overlay-close');
                sleep(5);
            }


            if ($this->exts->exists('button#consent_wall_optout')) {
                $this->exts->moveToElementAndClick('button#consent_wall_optout');
                sleep(5);
            }

            if ($this->exts->exists('button#preferences_prompt_submit_all')) {
                $this->exts->moveToElementAndClick('button#preferences_prompt_submit_all');
                sleep(5);
            }
            $this->exts->moveToElementAndClick('button#consent_wall_optin');
            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            if ($this->exts->exists('button#consent_wall_optin')) {
                $this->exts->moveToElementAndClick('button#consent_wall_optin');
                sleep(2);
            }
            $this->Loop_phone_numbers();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . 'error_text::  ' . $$error_text);

            if (stripos($error_text, strtolower('Die Angaben sind nicht korrekt.')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
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
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('div[class*="group-wrapper"] div[data-name*="rechnungsjahr"]:not(.hide)');

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div[class*="group-wrapper"] div[data-name*="rechnungsjahr"]:not(.hide)');
        foreach ($rows as $row) {

            try {
                $row->click();
                sleep(5);
            } catch (\Exception $e) {
                $this->exts->log(':: Error In invoice opening:: ' . $e->getMessage());
            }

            if ($this->exts->getElement('p:nth-child(1) a', $row) != null) {
                $invoiceUrl = $this->exts->getElement('p:nth-child(1) a', $row)->getAttribute("href");
                $invoiceName = explode(
                    '&',
                    array_pop(explode('showPDF/', $invoiceUrl))
                )[0];
                $invoiceDate =  $this->exts->extract('summary', $row);
                $cleanedDate = str_replace("Rechnung vom ", "", $invoiceDate);
                $invoiceDate = $cleanedDate;
                $invoiceAmount = "";

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function Loop_phone_numbers()
    {
        $accounts = count($this->exts->getElements('select#SwitchUserType_currentSubscriberId option'));
        $this->exts->log('ACCOUNTS found: ' . $accounts);
        // Click again to close dropdown
        if ($accounts > 1) {
            for ($a = 0; $a < $accounts; $a++) {
                $this->exts->log('SWITCH Phone Numbers');
                sleep(2);
                $account_button = $this->exts->getElements('select#SwitchUserType_currentSubscriberId option')[$a];
                try {
                    $account_button->click();
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript("arguments[0].click()", [$account_button]);
                }

                $this->exts->moveToElementAndClick('form div.userSwitchForm2');
                $this->processInvoices();
            }
        } else {
            $this->exts->log('************************ACCOUNTS found:********************');

            $this->processInvoices();
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
