<?php // accecpt cookies updated selectors login failed and continue button
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

    // Server-Portal-ID: 413100 - Last modified: 28.01.2025 13:38:11 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://manager.eu.shadow.tech';
    public $loginUrl = 'https://shadow.tech/';
    public $invoicePageUrl = 'https://manager.eu.shadow.tech/billing/invoices';

    public $continue_login_button = 'div a[href*="/account"]';
    public $username_selector = 'input#identifier';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = '.form-button button[type="submit"]';

    public $accept_cookie_selector = '#didomi-notice-agree-button';

    public $check_login_failed_selector = 'main#authCard > div > div[data-testid]';
    public $check_login_success_selector = 'button[data-testid="header-user-button"]';

    public $isNoInvoice = true;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_cloudflare_page();

        $this->acceptCookies();
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            if ($this->exts->exists('button#didomi-notice-agree-button')) {
                $this->exts->click_element('button#didomi-notice-agree-button');
                sleep(10);
            }
            $this->fillForm(0);

            $this->exts->openUrl($this->baseUrl);
            sleep(10);
            $this->check_solve_cloudflare_page();

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('button#didomi-notice-agree-button')) {
                $this->exts->click_element('button#didomi-notice-agree-button');
                sleep(7);
            }

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            $this->exts->loginFailure();
        }
    }

    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");
        $this->waitFor('div[style="display: grid;"] > div > div', 15);
        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
                $this->exts->refresh();
                sleep(10);

                $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
                sleep(15);

                if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                    break;
                }
            } else {
                break;
            }
        }
    }
    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }

    function acceptCookies()
    {
        $this->waitFor($this->accept_cookie_selector, 7);
        if ($this->exts->exists($this->accept_cookie_selector)) {
            $this->exts->click_element($this->accept_cookie_selector);
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }


    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 8);

        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
                sleep(5);
            }
            $this->check_solve_cloudflare_page();
        }

        $error_text = strtolower($this->exts->extract('div#error-identifier'));
        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if (stripos($error_text, strtolower('The email format is invalid')) !== false) {
            $this->exts->loginFailure(1);
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
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }


    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('table tbody tr', 10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $pages = $this->exts->querySelectorAll('li[class*="ant-pagination-item"]');
        $this->exts->log('Invoices pages: ' . count($pages));
        $currentPage = 1;
        foreach ($pages as $page) {
            $rows = $this->exts->querySelectorAll('table tbody tr');
            foreach ($rows as $index => $row) {
                $this->isNoInvoice = false;

                $invoiceAmount = trim(str_replace('&nbsp;', '', $row->querySelectorAll('td')[2]->getText()));
                $this->exts->log('invoice amount: ' . $invoiceAmount);

                $invoiceDate =  $row->querySelectorAll('td')[0]->getText();
                $this->exts->log('invoice date: ' . $invoiceDate);
                $parsedDate = $this->exts->parse_date($invoiceDate, '', 'Y-m-d');
                $this->exts->execute_javascript("arguments[0].click();", [$row->querySelectorAll('td')[3]->querySelector('button')]);
                sleep(3);
                $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelectorAll('ul.ant-dropdown-menu')[$index]->querySelectorAll('li')[0]->querySelector('a')]);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);
                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));
                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                sleep(2);
            }
            $currentPage++;

            $this->exts->execute_javascript("document.querySelector('li.ant-pagination-item-{$currentPage}').click()");
            sleep(5);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
