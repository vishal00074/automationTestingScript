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

    public $baseUrl = 'https://mubi.com/en/login?return_to=%2Fsubscription';
    public $loginUrl = 'https://mubi.com/en/login?return_to=%2Fsubscription';
    public $invoicePageUrl = 'https://mubi.com/en/subscription/invoices';
    public $username_selector = 'input[name="identifier"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[class*="EnterPassword_submit"]';
    public $check_login_failed_selector = 'div[class*="EnterPassword_error"]';
    public $check_login_success_selector = 'a[name="logout"]';
    public $isNoInvoice = true;

    /**
  
     * Entry Method thats called for a portal
  
     * @param Integer $count Number of times portal is retried.
  
     */
    private function initPortal($count)
    {
        $this->disable_extensions();
        
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
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
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();
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


    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 15);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                sleep(5);
                if ($this->exts->exists('button[class*="Login_submit"]')) {
                    $this->exts->click_by_xdotool('button[class*="Login_submit"]');
                    sleep(5);
                }
                sleep(5);
                $this->exts->waitTillPresent($this->password_selector, 25);
                sleep(5);

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

    private function disable_extensions()
    {
        $this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
        sleep(2);
        $this->exts->execute_javascript("
        let manager = document.querySelector('extensions-manager');
        if (manager && manager.shadowRoot) {
            let itemList = manager.shadowRoot.querySelector('extensions-item-list');
            if (itemList && itemList.shadowRoot) {
                let items = itemList.shadowRoot.querySelectorAll('extensions-item');
                items.forEach(item => {
                    let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                    if (toggle) toggle.click();
                });
            }
        }
    ");
    }


    /**
  
     * Method to Check where user is logged in or not
  
     * return boolean true/false
  
     */
    private function checkLogin()
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

    private function processInvoices($paging_count = 1, $totalFiles = 0)
    {

        $this->exts->waitTillPresent('table tbody tr', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(2)', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('td:nth-child(4) a', $row)->getAttribute('href');
                $invoiceName = '';
                $invoiceAmount =  $this->exts->extract('td:nth-child(2)', $row);
                $invoiceDate =  $this->exts->extract('td:nth-child(1)', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            if ((int) @$restrictPages != 0 && $totalFiles >= 100) {
                break;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);
            $this->exts->log('new filename: ' .  $invoiceFileName);
            $this->exts->download_capture($invoice['invoiceUrl'], 'file');
            // $this->exts->click_element($invoice['invoiceUrl']);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceFileName, $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                $totalFiles = $totalFiles + 1;
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        $this->exts->log('Total Files found: ' . $totalFiles);
    }
}
