<?php // migrated handle empty invoice name added restrct page condition 
//  added check to check  start_date in exists in configuration or not to prevent undefined error
// added $this->exts->success();

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
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673830/screens/';
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

    // Server-Portal-ID: 94963 - Last modified: 19.07.2024 13:31:38 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.edv-buchversand.de/index.php?cnt=userportal';
    public $loginUrl = 'https://www.edv-buchversand.de/index.php?cnt=userportal';
    public $invoicePageUrl = 'https://www.edv-buchversand.de/shift/userportal/invoice';

    public $username_selector = 'input[id*="customer-email"]';
    public $password_selector = 'input[id*="customer-pass"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input#loginButton, input[id*="login_button"], button#login_button';

    public $check_login_failed_selector = 'span#password-error[style*="display: inline"], span#email-error[style*="display: inline"]';

    public $check_login_success_selector = 'button[onclick*="logout"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page-before-loadcookies');
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null || $this->exts->getElement($this->password_selector) != null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            sleep(5);
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            for ($i = 0; $i < 5; $i++) {
                if ($this->exts->exists('#reload-button')) {
                    $this->exts->moveToElementAndClick('#reload-button');
                    sleep(10);
                } else {
                    break;
                }
            }
            $this->exts->moveToElementAndClick('button[onclick*="login_modal"]');
            if (!$this->exts->exists($this->password_selector)) {
                sleep(2);
                $this->exts->moveToElementAndClick('button[onclick*="login_modal"]');
            }
            sleep(3);
            $this->checkFillLogin();
            sleep(20);
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null && $this->exts->getElement($this->password_selector) == null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->log('*********************Usename or password incorrect****************************');
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
            sleep(2);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public $totalInvoices = 0;
    private function processInvoices()
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $index => $row) {

            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }

            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('span.pdf-download', $tags[4]) != null) {
                $invoiceSelector = $this->exts->getElement('span.pdf-download', $tags[4]);
                $this->exts->executeSafeScript("arguments[0].setAttribute('id', 'custom-pdf-download-button-" . $index . "');", [$invoiceSelector]);
                $invoiceName = trim($tags[1]->getText());
                $invoiceDate = trim($tags[0]->getText());
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');

                if (isset($this->exts->config_array["start_date"]) && $this->exts->config_array["start_date"] != null && $invoiceDate < date($this->exts->config_array["start_date"])) {
                    break;
                }

                $this->isNoInvoice = false;
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // click and download invoice
                    $this->exts->moveToElementAndClick('span#custom-pdf-download-button-' . $index);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                        $this->totalInvoices++;
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'GastroHero', '2673830', 'cmVjaG51bmdlbkBsdXVjLWV2ZW50LmRl', 'I1R1ZXJrZW5zdHJhc3NlMTc=');
$portal->run();
