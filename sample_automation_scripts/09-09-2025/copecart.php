<?php //  added loadCookiesFromFile function and trigger success and added trasection invoices download code
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

    // Server-Portal-ID: 144290 - Last modified: 19.08.2025 13:56:17 UTC - User: 1

    /*start script*/

    public $baseUrl = 'https://www.copecart.com/users/sign_in';
    public $loginUrl = 'https://www.copecart.com/users/sign_in';
    public $invoiceUrl = 'https://www.copecart.com/payouts';
    public $username_selector = '#user_email';
    public $password_selector = 'input#user_password';
    public $submit_btn = 'input[name="commit"]';
    public $login_link_selector = 'a[href*="user/signin"]';
    public $logout_link = 'a[href*="users/sign_out"]';
    public $login_error_selector = '.dc-control--error';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $this->exts->openUrl($this->baseUrl);
        sleep(4);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        sleep(7);
        $isCookieLoginSuccess = false;

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        }

        if (!$isCookieLoginSuccess) {

            $this->exts->capture("Home-page-without-cookie");
            $this->exts->clearCookies();

            $login_link = $this->exts->querySelector($this->login_link_selector);
            if ($login_link != null) {
                $this->exts->moveToElementAndClick($this->login_link_selector);
            } else {
                $this->exts->log("initPortal:: could not click on login link, try opening login URL");
                $this->exts->openUrl($this->loginUrl);
                sleep(2);
            }

            $this->fillForm(0);
            sleep(5);

            $this->exts->capture("after-login");
            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");

                $accecptAllBtn = 'div#user_agreement_popup div.modal-footer button';
                $this->exts->waitTillPresent($accecptAllBtn, 15);
                if ($this->exts->exists($accecptAllBtn)) {
                    $this->exts->click_element('div#user_agreement_popup input#user-terms-popup-checkbox');
                    sleep(2);
                    $this->exts->click_element($accecptAllBtn);
                }


                // transection invoices
                if ($this->exts->querySelector('a[href="/transactions"].nav--item') != null) {
                    $this->exts->click_element('a[href="/transactions"].nav--item');
                    sleep(12);
                    $this->dateRange();
                }
                $this->transactionsInvoices();

                $this->exts->openUrl($this->invoiceUrl);
                sleep(15);

                if ($this->exts->exists('div.table-container--header a:nth-child(2)')) {
                    $this->exts->click_element('div.table-container--header a:nth-child(2)');
                    sleep(2);
                    $this->exts->click_element('div.table-container--header input.checkbox');
                    sleep(8);
                    $this->processInvoices();
                }

                // Final, check no invoice
                if ($this->isNoInvoice) {
                    $this->exts->no_invoice();
                }

                $this->exts->success();
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log("Username is not a valid email address");
                $this->exts->loginFailure(1);
            } else if ($this->exts->querySelector($this->login_error_selector) != null) {
                $this->exts->log("Email or password incorrect!!!");
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('form.edit_user')) {
                //New fee structure
                $this->exts->account_not_ready();
            } else {
                $this->exts->log(">>>>>>>>>>>>>> after-login check failed!!!!");
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful with cookie!!!!");
            $this->exts->capture("LoginSuccess");

            $accecptAllBtn = 'div#user_agreement_popup div.modal-footer button';
            $this->exts->waitTillPresent($accecptAllBtn, 15);
            if ($this->exts->exists($accecptAllBtn)) {
                $this->exts->click_element('div#user_agreement_popup input#user-terms-popup-checkbox');
                sleep(2);
                $this->exts->click_element($accecptAllBtn);
            }

            // transection invoices
            if ($this->exts->querySelector('a[href="/transactions"].nav--item') != null) {
                $this->exts->click_element('a[href="/transactions"].nav--item');
                sleep(12);
                $this->dateRange();
            }
            $this->transactionsInvoices();

            $this->exts->openUrl($this->invoiceUrl);
            sleep(15);

            $this->exts->openUrl($this->invoiceUrl);
            sleep(15);

            if ($this->exts->exists('div.table-container--header a:nth-child(2)')) {
                $this->exts->click_element('div.table-container--header a:nth-child(2)');
                sleep(2);
                $this->exts->click_element('div.table-container--header input.checkbox');
                sleep(8);
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->capture("pre-fill-login");
        $this->exts->querySelector($this->username_selector);
        try {
            if ($this->exts->exists($this->username_selector)) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
            }
            if ($this->exts->exists($this->password_selector)) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
            }
            sleep(8);
            $this->exts->capture("post-fill-login");
            $this->exts->moveToElementAndClick($this->submit_btn);
            sleep(10);
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

            $isLoginForm = $this->exts->querySelector($this->username_selector);
            if (!$isLoginForm) {
                if ($this->exts->querySelector($this->logout_link) != null) {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful 1!!!!");
                    $isLoggedIn = true;
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    public function dateRange()
    {

        if ($this->exts->querySelector('header div.date-picker .date-picker--info .date-picker--info-start') != null) {
            $this->exts->moveToElementAndClick('header div.date-picker .date-picker--info .date-picker--info-start');
            sleep(5);
        }

        $selectDate = new DateTime();
        $currentDate = $selectDate->format('M Y');

        if ($this->restrictPages == 0) {
            $selectDate->modify('-2 years');
            $formattedDate = $selectDate->format('M Y');
            $this->exts->capture('date-range-2-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('M Y');
            $this->exts->capture('date-range-3-months');
        }


        $stop = 0;
        while (true) {
            $calendarMonth = $this->exts->extract('header .column-from-end-2 div[class="vc-header align-center"] .vc-title');
            $this->exts->log('previous currentMonth:: ' . trim($calendarMonth));
            $this->exts->log('previous formattedDate:: ' . trim($formattedDate));

            if (trim($calendarMonth) === trim($formattedDate)) {
                sleep(4);
                break;
            }

            $this->exts->moveToElementAndClick('header div[class="vc-arrows-container title-center"] div.is-left');
            sleep(1);
            $stop++;

            if ($stop > 12) {
                break;
            }
        }

        $this->exts->click_element("//span[normalize-space(.)='1']");
        sleep(5);

        $stop2  = 0;
        while (true) {
            $calendarMonth = $this->exts->extract('header .column-from-end-1 div[class="vc-header align-center"] .vc-title');
            $this->exts->log('next currentMonth:: ' . trim($calendarMonth));
            $this->exts->log('next currentDate:: ' . trim($currentDate));

            if (trim($calendarMonth) === trim($currentDate)) {
                sleep(4);
                break;
            }

            $this->exts->moveToElementAndClick('header div[class="vc-arrows-container title-center"] div.is-right');
            sleep(1);

            $stop2++;
            if ($stop2 > 12) {
                break;
            }
        }

        $this->exts->click_element("//span[normalize-space(.)='30']");
        sleep(5);

        $this->exts->click_element("//header//button[normalize-space(.)='Annehmen']");
        sleep(10);
    }

    private function processInvoices($paging_count = 1)
    {
        $this->dateRange();
        sleep(5);


        $restrictPages = $this->restrictPages;
        $this->exts->log('Restrict Pages: ' .  $this->restrictPages);

        $restrictDate =  $this->restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 10;
        $invoiceCount = 0;
        $pageCount = 0;


        $this->exts->waitTillPresent('table tbody tr td:nth-child(1) a.download-pdf', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        if ($this->exts->exists('ul.pagination > li.last > a')) {
            $this->clickByJS('ul.pagination > li.last > a');
            sleep(5);
        }

        do {

            $pageCount++;

            $this->exts->waitTillPresent('table tbody tr td:nth-child(1) a.download-pdf', 30);

            $rows = $this->exts->querySelectorAll('table tbody tr');
            $rows = !empty($rows) ? array_reverse($rows) : [];

            foreach ($rows as $row) {

                if ($this->exts->querySelector('td:nth-child(1) a.download-pdf', $row) != null) {

                    $invoiceCount++;

                    $invoiceUrl = '';
                    $invoiceName = $this->exts->extract('td:nth-child(3) span a', $row);
                    $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);
                    $invoiceDate = $this->exts->extract('td:nth-child(2)', $row);

                    $downloadBtn = $this->exts->querySelector('td:nth-child(1) a.download-pdf', $row);

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                        'downloadBtn' => $downloadBtn
                    ));

                    $this->isNoInvoice = false;


                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                    $invoiceFileName = $invoiceName . '.pdf';
                    $invoiceDate = $this->exts->parse_date(trim($invoiceDate), 'd.m.Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoiceDate);

                    $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);

                    sleep(2);

                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');
                    $invoiceFileName = basename($downloaded_file);

                    $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                    $this->exts->log('invoiceName: ' . $invoiceName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }

                    $this->exts->log(' ');
                    $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
                    $this->exts->log(' ');


                    $lastDate = !empty($invoiceDate) && $invoiceDate <= $restrictDate;

                    if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                        break 2;
                    } elseif ($restrictPages == 0 && $dateRestriction && $lastDate) {
                        break 2;
                    }
                }
            }

            // pagination handle			
            if ($this->exts->exists('ul.pagination > li:nth-last-child(' . ($pageCount + 1) . ') > a')) {
                $this->exts->log('Click Next Page in Pagination!');
                $this->clickByJS('ul.pagination > li:nth-last-child(' . ($pageCount + 1) . ') > a');
                sleep(5);
            } else {
                $this->exts->log('Last Page!');
                break;
            }
        } while (true);

        $this->exts->log('Invoices found: ' . count($invoices));
    }

    private function clickByJS($selector)
    {
        $this->exts->execute_javascript("
		
				var element = document.querySelector('" . $selector . "');
				
				var rect = element.getBoundingClientRect();
				
				element.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: rect.x, clientY: rect.y }));
				element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, clientX: rect.x, clientY: rect.y }));
				element.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: rect.x, clientY: rect.y }));
				
			");
    }
    public $totalInvoices = 0;

    private function transactionsInvoices($pageCount = 1)
    {
        sleep(5);
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('div[id="transactions-table-body"] table tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div[id="transactions-table-body"] table tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a[href*="orders"]', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('a[href*="orders"]', $row);
                $invoiceDate = $this->exts->extract('th div:nth-child(3)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(9)', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        $newTab =  $this->exts->openNewTab();
        sleep(5);
        foreach ($invoices as $invoice) {

            if ($this->totalInvoices >= 100) {
                $this->exts->closeTab($newTab);
                sleep(5);
                return;
            }

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);
            $this->exts->waitTillPresent('a[href*="download_invoice"]');

            $invoiceBtn = $this->exts->getElement('a[href*="download_invoice"]');

            if ($invoiceBtn != null) {
                $invoice['invoiceUrl'] =  $invoiceBtn->getAttribute("href");
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ');
            }
        }
        $this->exts->closeTab($newTab);
        sleep(5);
        $pageCount++;
        if ($this->exts->exists('ul.pagination >  li:nth-child(' . ($pageCount) . ') > a')) {
            $this->exts->log('Click Next Page in Pagination!');
            $this->clickByJS('ul.pagination >  li:nth-child(' . ($pageCount) . ') > a');
            sleep(5);
            $this->transactionsInvoices($pageCount);
        } else {
            $this->exts->log('Last Page!');
        }
    }
}
