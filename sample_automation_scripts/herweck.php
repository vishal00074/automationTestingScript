<?php // replace exists to isExists I have updated login button selector and invoice tab selector use xpath with invoice tab name
// 

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
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
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

    // Server-Portal-ID: 74298 - Last modified: 03.04.2025 14:22:45 UTC - User: 1

    public $baseUrl = 'https://www.herweck.de/kundencenter/';
    public $loginUrl = 'https://www.herweck.de/kundencenter/belege/';
    public $invoicePageUrl = 'https://kundencenter.herweck.de/belege#/';
    public $username_selector = 'input#userNameInput, input#username';
    public $password_selector = 'input#passwordInput, input#Password';
    public $remember_me = 'input#kmsiInput, input#rememberMe';
    public $submit_login_btn = 'span#submitButton, button[name="login"]';
    public $checkLoginFailedSelector = 'label#errorText';
    public $checkLoggedinSelector = 'a[href*="/logout"]';

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
        for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->checkLoggedinSelector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(2);
        }

        if ($this->isExists($this->checkLoggedinSelector)) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('user Logged in from initPortal');
            $this->exts->capture('user-loggedin');
            $this->waitForLogin();
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('user-not-loggedin from initPortal');
            $this->exts->capture('user-not-loggedin');

            $this->exts->openUrl($this->loginUrl);
            sleep(12);

            if ($this->isExists('button[class*="accept"]')) {
                $this->exts->click_element('button[class*="accept"]');
                sleep(7);
            }


            if ($this->exts->querySelector('a[href*="?auth=login"]') != null) {
                $this->exts->click_element('a[href*="?auth=login"]');
                sleep(10);
            }
            $this->waitForLoginPage();
        }
    }

    private function waitForLoginPage()
    {
        $this->waitFor($this->password_selector, 25);
        if ($this->isExists($this->password_selector)) {
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->moveToElementAndClick($this->remember_me);
            sleep(2);

            $this->exts->capture("1-filled-login");
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            $this->waitForLogin();
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    private function waitForLogin()
    {
        for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->checkLoggedinSelector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }

        if ($this->isExists($this->checkLoggedinSelector)) {
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            // If it have confirm popup for Accept cookies, click Accept
            if ($this->isExists('.cmplz-accept')) {
                $this->exts->moveToElementAndClick('.cmplz-accept');
                sleep(2);
            }

            sleep(15);
            // Open the manager page
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(2);
            if (!$this->exts->urlContains('/belege')) {
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(5);
            }
            sleep(15);

            // Click invoices tab
            $this->exts->moveToElementAndClick(".//li[contains(normalize-space(.),'Invoices')]");
            sleep(15);
            // Fill start date go back one year
            $this->waitFor('select[class="searchDateList"]', 15);
            if ($this->isExists('select[class="searchDateList"]')) {
                $this->exts->click_element('select[class="searchDateList"]');
                sleep(3);
                if ($this->isExists('option[value="1 year"]')) {
                    $this->exts->click_element('option[value="1 year"]');
                    sleep(3);
                }
            }

            sleep(15);
            $this->processInvoices();


            if ($this->totalFiles == 0) {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");

            $err_msg = $this->exts->extract($this->checkLoginFailedSelector);

            if ($err_msg != '' && $err_msg != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    public function dateRange()
    {

        if ($this->exts->querySelector('div.searchDatePickerContainer input[type="checkbox"]') != null) {
            $this->exts->moveToElementAndClick('div.searchDatePickerContainer input[type="checkbox"]');
            sleep(5);
        }


        $selectDate = new DateTime();

        // get german 
        $formatter = new IntlDateFormatter(
            'de_DE',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'MMM yyyy'
        );

        // Format the current date in German
        $currentDate = strtoupper($formatter->format($selectDate));

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = strtoupper($formatter->format($selectDate));
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = strtoupper($formatter->format($selectDate));
            $this->exts->capture('date-range-3-months');
        }


        $this->exts->moveToElementAndClick('input[placeholder="from ..."]');
        sleep(5);


        $stop = 0;
        while (true) {
            $calendarMonth = $this->exts->extract('div.filter-input:nth-child(2) span[class="day__month_btn up"]');
            $this->exts->log('previous currentMonth:: ' . trim($calendarMonth));
            $this->exts->log('previous formattedDate:: ' . trim($formattedDate));

            if (trim($calendarMonth) === trim($formattedDate)) {
                sleep(4);
                break;
            }

            $this->exts->moveToElementAndClick('div.filter-input:nth-child(2) div.vdp-datepicker__calendar:nth-child(2) span[class="prev"]');
            sleep(1);
            $stop++;

            if ($stop > 200) {
                break;
            }
        }

        $this->exts->click_element("//div[contains(@class,'filter-input')][1]//span[contains(@class,'cell') and contains(@class,'day') and normalize-space(text())='1']");
        sleep(5);


        $this->exts->moveToElementAndClick('input[placeholder="to ..."]');
        sleep(5);

        // select To date

        $stop2  = 0;
        while (true) {
            $calendarMonth = $this->exts->extract('div.filter-input:nth-child(3) span[class="day__month_btn up"]');
            $this->exts->log('next currentMonth:: ' . trim($calendarMonth));
            $this->exts->log('next currentDate:: ' . trim($currentDate));

            if (trim($calendarMonth) === trim($currentDate)) {
                sleep(4);
                break;
            }

            $this->exts->moveToElementAndClick('div.filter-input:nth-child(2) div.vdp-datepicker__calendar:nth-child(2) span[class="next"]');
            sleep(1);

            $stop2++;
            if ($stop2 > 200) {
                break;
            }
        }

        $this->exts->moveToElementAndClick('span.today');
        sleep(5);

        $this->downloadInvoices();
    }

    public $totalFiles = 0;
    function processInvoices()
    {
        for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('table#table-rechnungen tbody tr, div[class=\"tree-container\"] ul.item');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table#table-rechnungen tbody tr,  div[class="tree-container"] ul.item');
        foreach ($rows as $row) {

            if ($this->exts->querySelector('td:nth-child(5) a', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('td:nth-child(5) a', $row)->getAttribute('href');
                preg_match('/\/belege#\/([A-Za-z0-9]+)/', $invoiceUrl, $matches);
                $invoiceName = $matches[1];
                $invoiceAmount = '';
                $invoiceDate = '';
                $downloadBtn = $this->exts->querySelector('a', $row);

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

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(7);
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $this->totalFiles += 1;

            $this->waitFor('button[id*="modal-btn"]', 20);
            if ($this->isExists('button[id*="modal-btn"]')) {
                $this->exts->click_element('button[id*="modal-btn"]');
            }

            sleep(2);
            $downloaded_file = $this->exts->click_and_print('button[title="Print"]',  $invoiceFileName);
            sleep(1);
            $this->exts->click_element('button[title="Close"]');
            sleep(2);
            $this->exts->moveToElementAndClick('a#belege-rechnungen, ul[class*="Filter"] li:nth-child(5)');
            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                $this->exts->log("New Invoice");
                $this->exts->new_invoice($invoice['invoiceName'], '', '', $downloaded_file);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
