<?php // working fine

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

    // Server-Portal-ID: 14249 - Last modified: 29.07.2025 08:31:25 UTC - User: 1

    public $baseUrl = "https://selfserve.fleetcor.de/";
    public $loginUrl = "https://selfserve.fleetcor.de/gfnsmewww/pages/public/login.aspx";
    public $invoicePageUrl_01 = "https://simplyui-sme.azurewebsites.net/reports";
    public $invoicePageUrl_02 = "https://selfserve.fleetcor.de/GFNSMEWWW/Client/Pages/User/DirectDebit.aspx";
    public $username_selector = 'input#ctl00_MainBody_txtUserName, input[name="loginName"], input#username';
    public $password_selector = 'input#ctl00_MainBody_txtPassword, input[name="password"], input#password';
    public $submit_button_selector = 'input#ctl00_MainBody_btnLogin, #login-btn, button[type=submit]';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;
    public $summary_invoice = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->summary_invoice = isset($this->exts->config_array["summary_invoice"]) ? (int)@$this->exts->config_array["summary_invoice"] : 0;
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        $this->exts->openUrl($this->invoicePageUrl_01);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->openUrl($this->invoicePageUrl_02);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
            }
        }


        if (!$isCookieLoginSuccess) {
            $this->waitFor('button[id*=AllowAll]', 10);
            if ($this->exts->exists('button[id*=AllowAll]')) {
                $this->exts->click_element('button[id*=AllowAll]');
            }
            $this->fillForm(0);
            sleep(20);

            $mesg = strtolower($this->exts->extract('h2', null, 'innerText'));
            if (strpos($mesg, '404 ')  !== false && strpos($mesg, 'File or directory not found')  !== false) {
                $this->exts->capture("before- refresh-page");
                $this->exts->refresh();
                sleep(15);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->waitFor('button[id*=AllowAll]', 10);
                if ($this->exts->exists('button[id*=AllowAll]')) {
                    $this->exts->click_element('button[id*=AllowAll]');
                }
                sleep(2);

                $this->invoicePage();

                $this->exts->success();
            } else {
                $this->exts->capture("LoginFailed");
                if ($this->exts->querySelector('div.login-error-display.errMessage') != null) {
                    $this->exts->loginFailure(1);
                } else if ($this->exts->getElementByText('div[class*="_error"]', 'Anmeldedaten werden nicht erkannt', null, false) != null) {
                    $this->exts->loginFailure(1);
                }
                $this->exts->loginFailure();
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful with cookie!!!!");
            $this->exts->capture("LoginSuccess");
            $this->waitFor('button[id*=AllowAll]', 10);
            if ($this->exts->exists('button[id*=AllowAll]')) {
                $this->exts->click_element('button[id*=AllowAll]');
            }
            sleep(2);
            $this->exts->capture("LoginSuccessWithCookie");
            $this->invoicePage();
            $this->exts->success();
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            $this->waitFor($this->username_selector);
            if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
                sleep(1);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("2-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->capture("2-filled-login");
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5);
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page not found');
                $this->exts->capture("2-login-page-not-found");
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitFor('div[class*=profileButton] button');
            if ($this->exts->exists('div[class*=profileButton] button')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function invoicePage()
    {
        $this->exts->log("Invoice page");

        if ($this->exts->urlContains('azurewebsites.net')) {
            $this->exts->openUrl($this->invoicePageUrl_01);
            $this->waitFor('button[btn-radio*=year]', 15);
            $this->waitFor('button[ng-model*="filters.dateRange"]', 7);
            $this->exts->moveToElementAndClick('button[ng-model*="filters.dateRange"]');
            sleep(5);

            if ($this->restrictPages == 0) {
                $startDate = date('d.m.Y', strtotime('-3 years'));
            } else {
                $startDate = date('d.m.Y', strtotime('-1 years'));
            }
            $this->exts->moveToElementAndType('input[name=daterangepicker_start]', '');
            sleep(3);
            $this->exts->moveToElementAndType('input[name=daterangepicker_start]', $startDate);
            sleep(3);
            $this->exts->moveToElementAndClick('div.range_inputs button.applyBtn');
            $this->waitFor('a[ng-click*="downloadpdf"]', 15);
            $this->downloadInvoiceAW();
        } else {
            $this->exts->openUrl('https://sme.myfleetcor.com/_app/ucx/statement');
            $this->waitFor('input#date-dd', 10);
            if ($this->exts->exists('input#date-dd')) {
                if ($this->restrictPages == 0) {
                    $filter_date = Date('d.m.Y', strtotime("-1 years")) . ' - ' . Date('d.m.Y');
                    $this->exts->log("Enter filter-date: " . $filter_date);
                    // $this->exts->moveToElementAndClick('input#date-dd');
                    $this->exts->moveToElementAndType('input[name="calendar-input"]', $filter_date);
                    sleep(2);
                    $this->exts->executeSafeScript('document.querySelector("input[name=\"calendar-input\"]").dispatchEvent(new Event("change"));');
                } else {
                    $filter_date = Date('d.m.Y', strtotime("-6 months")) . ' - ' . Date('d.m.Y');
                    $this->exts->log("Enter filter-date: " . $filter_date);
                    // $this->exts->moveToElementAndClick('input#date-dd');
                    $this->exts->moveToElementAndType('input#date-dd', $filter_date);
                    sleep(2);
                    $this->exts->executeSafeScript('document.querySelector("input[name=\"calendar-input\"]").dispatchEvent(new Event("change"));');
                }
            }
            $this->exts->openUrl('https://sme.myfleetcor.com/_app/de/ucx/');
            sleep(15);
            if ($this->exts->exists("//button[span/span[contains(text(), 'Rechnungen') or contains(text(), 'Invoice')]]")) {
                $this->exts->click_element("//button[span/span[contains(text(), 'Rechnungen') or contains(text(), 'Invoice')]]");
                sleep(5);
                if ($this->exts->exists("//button[.//span[contains(text(), 'Rechnungsdokumente')]]")) {
                    $this->exts->click_element("//button[.//span[contains(text(), 'Rechnungsdokumente')]]");
                }
            }
            $this->downloadStatement();
        }

        $this->exts->openUrl($this->invoicePageUrl_02);
        $this->processAccount();
        if ($this->isNoInvoice) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
    }

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->waitFor($select_box, 5);
        if ($this->exts->exists($select_box)) {
            $option = $select_box . ' option[value="' . $option_value . '"]';
            $this->exts->log('Option Box : ' . $option);
            $this->exts->click_element($select_box);
            sleep(1);
            if ($this->exts->exists($option)) {
                $this->exts->log('Select box Option exists');
                try {
                    $this->exts->execute_javascript(
                        'var select = document.querySelector("' . $select_box . '"); 
			if (select) {
				select.value = "' . $option_value . '";
				select.dispatchEvent(new Event("change", { bubbles: true }));
			}'
                    );
                } catch (\Exception $e) {
                    $this->exts->log('JavaScript selection failed, error: ' . $e->getMessage());
                }

                sleep(3);
            } else {
                $this->exts->log('Select box Option does not exist');
            }
        } else {
            $this->exts->log('Select box does not exist');
        }
    }

    private function processAccount()
    {
        $accountConfig = isset($this->exts->config_array["user_account"]) ? trim($this->exts->config_array["user_account"]) : '';
        $this->exts->log("Account Config: " . $accountConfig);
        $user_selected_accounts = explode(',', $accountConfig);
        // get list acccount
        $list_account = [];
        $this->waitFor('select[name*="Accounts"] option', 7);
        $total_accounts = $this->exts->querySelectorAll('select[name*="Accounts"] option');
        $this->exts->log('total accounts ' . count($total_accounts));
        foreach ($total_accounts as $account) {
            $account_name = $account->getAttribute("innerText");
            $account_value = $account->getAttribute("value");
            $this->exts->log('accounts name ' . $account_name);
            if (empty($accountConfig)) {
                $this->exts->log('Added account: ' . $account_name);
                array_push($list_account, array(
                    'account_name' => $account_name,
                    'account_value' => $account_value,
                ));
            } else {
                foreach ($user_selected_accounts as $user_selected_account_name) {
                    if (stripos($account_name, $user_selected_account_name) !== false) {
                        $this->exts->log('Added account: ' . $account_name);
                        array_push($list_account, array(
                            'account_name' => $account_name,
                            'account_value' => $account_value,
                        ));
                        break;
                    }
                }
            }
        }

        if (count($list_account) > 0) {
            foreach ($list_account as $account) {
                $this->exts->log('Process account: ' . $account['account_name']);
                $this->changeSelectbox('select[name*="Accounts"]', $account['account_value']);
                sleep(5);
                $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
                if ($restrictPages == 0) {
                    $fromDate = date('d.m.Y', strtotime('-2 years'));
                    $this->exts->moveToElementAndType('input[name*="dteDateFrom"]', $fromDate);
                    sleep(2);
                }
                $this->exts->moveToElementAndClick('input[name*="btnSearch"]');
                $this->processInvoices();
            }
        } else {
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == 0) {
                $fromDate = date('d.m.Y', strtotime('-2 years'));
                $this->exts->moveToElementAndType('input[name*="dteDateFrom"]', $fromDate);
                sleep(2);
            } else {
                $fromDate = date('d.m.Y', strtotime('-1 years'));
                $this->exts->moveToElementAndType('input[name*="dteDateFrom"]', $fromDate);
                sleep(2);
            }
            $this->exts->moveToElementAndClick('label[for="ctl00_MainBody_dteDateTo"]');
            sleep(1);
            $this->exts->moveToElementAndClick('input[name*="btnSearch"]');
            $this->processInvoices();
        }
    }
    private function processInvoices($paging_count = 1)
    {
        $total_invoices = 0;
        for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('table > tbody > tr');") !== true; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(2);
        }

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        if ($this->exts->exists('table > tbody > tr')) {
            foreach ($rows as $row) {
                $tags = $this->exts->querySelectorAll('td', $row);
                if (count($tags) >= 8 && $this->exts->querySelector('a', $tags[2]) != null) {
                    $invoiceId = $this->exts->querySelector('a', $tags[2])->getAttribute("id");
                    $invoiceName = str_replace("/", "", trim($tags[5]->getAttribute('innerText')));
                    $invoiceDate = trim($tags[3]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[7]->getAttribute('innerText'))) . ' EUR';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceId' => $invoiceId
                    ));
                    $this->isNoInvoice = false;
                }
            }

            // Download all invoices
            $this->exts->log('Invoices found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                if ($this->restrictPages != 0 && $total_invoices >= 100) break;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceId: ' . $invoice['invoiceId']);

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $this->exts->moveToElementAndClick('a#' . $invoice['invoiceId']);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                    $total_invoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            $paging_count++;
            $next_page_link = $this->exts->getElementByText('span[id*="Main"]:not(.top) a.num', [$paging_count], null, true);
            if ($restrictPages == 0 && $next_page_link != null) {
                try {
                    $this->exts->log('Click next page link');
                    $next_page_link->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click next page link by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$next_page_link]);
                }
                $this->processInvoices($paging_count);
            }
        }
    }

    private function downloadStatement($paging_count = 1)
    {
        $total_invoices = 0;
        $this->waitFor('button#CybotCookiebotDialogBodyButtonAccept', 5);
        if ($this->exts->exists('button#CybotCookiebotDialogBodyButtonAccept')) {
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyButtonAccept');
            sleep(3);
        }
        $invoices = [];

        $this->waitFor('tbody tr', 10);
        $this->exts->capture("4-statement-invoices-page");

        $rows = count($this->exts->querySelectorAll('tbody tr'));
        $this->exts->log('Total invoice Rows---- ' . $rows);

        for ($i = 0; $i < $rows; $i++) {
            if ($this->restrictPages != 0 && $total_invoices >= 100) break;
            $row = $this->exts->querySelectorAll('tbody tr')[$i];
            $tags = $this->exts->querySelectorAll('td', $row);
            if (
                count($tags) >= 5 && $this->exts->querySelector('i[class*="downloadButton"]', $row) != null
                && $this->exts->querySelector('td:nth-child(1) > div:not([class*="unpaid"])', $row) != null
            ) {
                $download_popup_button = $this->exts->querySelector('i[class*="downloadButton"]', $row);

                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('Date parsed: ' . $parsed_date);
                $this->isNoInvoice = false;

                try {
                    $this->exts->log('Click popup button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_popup_button]);
                } catch (\Exception $exception) {
                    $this->exts->log('Click popup button ');
                    $download_popup_button->click();
                }
                sleep(5);
                $this->waitFor('div#modal-portal table tbody tr');

                $this->exts->capture("4.1-statement-invoices-page");

                $file_rows = count($this->exts->querySelectorAll('div#modal-portal table tbody tr'));
                $this->exts->log('Number of modal rows :' . $file_rows);
                if ($this->summary_invoice == 1) {
                    for ($j = 0; $j < $file_rows; $j++) {
                        $file_row = $this->exts->querySelectorAll('div#modal-portal table tbody tr')[$j];
                        if ($file_row != null) {
                            if (
                                strpos(strtolower($file_row->getText()), 'bersicht ') !== false ||
                                strpos(strtolower($file_row->getText()), 'summary ') !== false
                            ) {
                                $invoiceName = trim($this->exts->querySelector('td:nth-child(3)', $file_row)->getAttribute('innerText'));
                                $invoiceName = str_replace(['/', '-'], '', $invoiceName);

                                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                                $checkbox = $this->exts->querySelector('span[data-testid="checkbox-pseudo"] input', $file_row);

                                $this->exts->executeSafeScript("arguments[0].click()", [$checkbox]);
                                $this->exts->capture("4.3-after-clicking-option");

                                sleep(2);
                                // Download invoice if it not exisited
                                if ($this->exts->invoice_exists($invoiceName)) {
                                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                                } else {
                                    $this->exts->moveToElementAndClick('div#modal-portal button[data-testid="pdf-download-button"]');
                                    sleep(2);
                                    $this->exts->click_element('//li//*[contains(text(), "PDF (.pdf)")]');
                                    sleep(2);
                                    $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                                    $this->exts->click_element('//li//*[contains(text(), "PDF (.pdf)")]');
                                    $this->exts->wait_and_check_download('pdf');
                                    $downloaded_file = $this->exts->find_saved_file('pdf');
                                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                                        sleep(1);
                                        $total_invoices++;
                                    } else {
                                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                                    }
                                    sleep(1);
                                    $row = $this->exts->querySelectorAll('tbody tr')[$i];
                                    $download_popup_button = $this->exts->querySelector('i[class*="downloadButton"]', $row);
                                    $this->exts->click_element($download_popup_button);
                                    sleep(3);
                                }
                            }
                        }
                    }
                }
                sleep(1);
                $this->exts->log('--------------------------');

                $checkbox = $this->exts->querySelector('input#select-all-checkbox-input');
                $this->exts->executeSafeScript("arguments[0].click()", [$checkbox]);

                $this->exts->capture("4.2-statement-invoices-page");

                $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'F d, Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $this->exts->moveToElementAndClick('div#modal-portal button[data-testid="pdf-download-button"]');

                sleep(2);

                $this->exts->click_element('//li//*[contains(text(), "PDF (.pdf)")]');
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
                sleep(2);
                if ($this->exts->exists('button[data-testid="modal-wrapper-close"]')) {
                    $this->exts->click_element('button[data-testid="modal-wrapper-close"]');
                    sleep(3);
                }
            }
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 10 &&
            $this->exts->querySelector('button[class*="_navButton"]:last-child:not(:disabled)')
        ) {
            $paging_count++;
            $paginateButton = $this->exts->querySelector('button[class*="_navButton"]:last-child:not(:disabled)');
            $this->exts->execute_javascript("arguments[0].click();", [$paginateButton]);
            sleep(5);
            $this->downloadStatement($paging_count);
        } else if (
            $restrictPages != 0 &&
            $paging_count < $restrictPages &&
            $this->exts->querySelector('button[class*="_navButton"]:last-child:not(:disabled)')
        ) {
            $this->exts->log('Click paginateButton');
            $paging_count++;
            $paginateButton = $this->exts->querySelector('button[class*="_navButton"]:last-child:not(:disabled)');
            $this->exts->execute_javascript("arguments[0].click();", [$paginateButton]);
            sleep(5);
            $this->downloadStatement($paging_count);
        }
    }

    private function downloadInvoiceAW($count = 1, $pageCount = 1)
    {

        $this->waitFor('table.table-rows-middle > tbody > tr', 15);
        $this->exts->log("Begin download invoice AW");
        $this->exts->capture('4-List-invoice-AW');

        $rows = count($this->exts->querySelectorAll('table.table-rows-middle > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('table.table-rows-middle > tbody > tr')[$i];
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 6 && $this->exts->querySelector('a[ng-click*="downloadpdf"]', $tags[5]) != null) {
                if ($this->summary_invoice == 1) {
                    $this->isNoInvoice = false;
                    $download_button = $this->exts->querySelector('a[ng-click*="downloadpdf"]', $tags[5]);
                    $invoiceName = $this->exts->extract('td > select > option[selected]', $row, 'value');
                    $invoiceName = str_replace('/', '-', end(explode('--', $invoiceName)));
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                    $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $parsed_date);

                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                        }
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }

                sleep(2);

                if ($this->exts->exists('select[ng-model*="selDocument"] option[ng-repeat*="invoice"]')) {
                    $this->isNoInvoice = false;
                    $InvSelVal = $this->exts->querySelectorAll('select[ng-model*="selDocument"] option[ng-repeat*="invoice"]', $tags[5]);
                    foreach ($InvSelVal as $item) {
                        $invoice_value =  $item->getAttribute('value');
                        $this->exts->log('Current Item Value - ' . $invoice_value);
                        $type_select =  $this->exts->querySelector('select[ng-model*="selDocument"]', $tags[5]);

                        $this->exts->executeSafeScript("arguments[0].value = '" . $invoice_value . "';", [$type_select]);
                        sleep(1);
                        $this->exts->executeSafeScript("arguments[0].dispatchEvent(new Event('change'));", [$type_select]);

                        sleep(5);
                        $download_button = $this->exts->querySelector('a[ng-click*="downloadpdf"]', $tags[5]);
                        $invoiceName = $invoice_value;
                        $invoiceName = trim(end(explode('--', $invoiceName)));
                        $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                        $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';

                        $this->exts->log('--------------------------');
                        $this->exts->log('invoiceName: ' . $invoiceName);
                        $this->exts->log('invoiceDate: ' . $invoiceDate);
                        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                        $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                        $this->exts->log('Date parsed: ' . $parsed_date);

                        // Download invoice if it not exisited
                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            try {
                                $this->exts->log('Click download button');
                                $download_button->click();
                            } catch (\Exception $exception) {
                                $this->exts->log('Click download button by javascript');
                                $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                            }

                            sleep(5);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            }
                        }
                    }
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
