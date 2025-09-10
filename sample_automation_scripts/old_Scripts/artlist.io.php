<?php // i have increase sleep timeout after loginurl opening
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

    // Server-Portal-ID: 21941 - Last modified: 23.07.2025 06:51:46 UTC - User: 1

    public $baseUrl = 'https://artlist.io/account';
    public $loginUrl = 'https://artlist.io/page/signin';
    public $invoicePageUrl = 'https://artlist.io/account';

    public $username_selector = 'form#loginForm input#logemail, form input[type=email]';
    public $password_selector = 'form#loginForm input#logpassword, form input[type=password]';
    public $remember_me_selector = '';
    public $submit_login_btn = 'form#loginForm button#btnlogin, form button[type=submit]';

    public $checkLoginFailedSelector = 'label#ermsg a';
    public $checkLoggedinSelector = '.login a#user-logined-btn:not([style*="display"]):not([style*="none"]), #temporary-navbar .group > .absolute';

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->capture("Home-page-without-cookie");
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        sleep(30);
        $this->check_solve_cloudflare_page();
        if ($this->checkLoggedIn()) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->exts->moveToElementAndClick('#cookiescript_accept');
            sleep(1);
            if ($this->exts->exists('iframe.ab-modal-interactions')) {
                $this->exts->makeFrameExecutable('iframe.ab-modal-interactions')->moveToElementAndClick('button#braze_closeBtn');
                sleep(1);
            }
            $this->waitForLogin();
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');
            $this->exts->openUrl($this->loginUrl);
            sleep(20);
            $this->check_solve_cloudflare_page();
            $this->exts->moveToElementAndClick('#cookiescript_accept');
            sleep(3);
            $this->waitForLoginPage(0);
        }
    }

    private function waitForLoginPage($count)
    {
        if ($this->exts->exists($this->password_selector)) {
            sleep(5);
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->log($this->username);
            if ($this->exts->getElement($this->username_selector) != null) {
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
            }
            sleep(1);
            $this->exts->log("Enter Password");
            $this->exts->log($this->password);
            if ($this->exts->getElement($this->password_selector) != null) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
            }
            sleep(2);

            $this->exts->capture("1-filled-login");

            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(2);
            for ($i = 0; $i < 10 && $this->exts->getElement('//span[contains(text(),"Password is a required field")]') == null; $i++) {
                sleep(1);
            }
            // moveToElementAndType not work
            if ($this->exts->getElement('//span[contains(text(),"Password is a required field")]') != null) {
                $this->exts->click_by_xdotool($this->password_selector, 2, 3);
                sleep(2);
                $this->exts->type_text_by_xdotool($this->password);
                sleep(1);
                $this->exts->capture("1-filled-login-1");

                $this->exts->moveToElementAndClick($this->submit_login_btn);
                sleep(15);
            }
            $this->waitForLogin();
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("2-login-page-not-found");
            $this->exts->loginFailure();
        }
    }

    private function waitForLogin()
    {

        if ($this->checkLoggedIn()) {
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");
            $this->exts->moveToElementAndClick('#cookiescript_accept');
            sleep(2);
            // Open invoices url
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            $this->exts->moveToElementAndClick('#cookiescript_accept');
            sleep(1);
            if ($this->exts->getElement('[data-test-id="account-page__license-section"]') != null) {
                $this->processSubscriptions();
            } else if ($this->exts->exists('a[href="/account/plan-and-billings"]')) {
                $this->exts->moveToElementAndClick('a[href="/account/plan-and-billings"]');
                sleep(12);
                $this->processSubscriptions();
            }

            if ($this->exts->exists('a[href="/account/invoices"]')) {
                $this->exts->moveToElementAndClick('a[href="/account/invoices"]');
                sleep(13);
                $this->processInvoices();
            }


            if ($this->totalFiles == 0) {
                $this->exts->log("no invoice!!");
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (count($this->exts->getElements($this->checkLoginFailedSelector)) > 0) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('span.text-error[data-test-id="InputControlTemplate__hintText"]')), 'email not found') !== false || strpos(strtolower($this->exts->extract('span.text-error[data-test-id="InputControlTemplate__hintText"]')), 'wrong password') !== false || strpos(strtolower($this->exts->extract('span.text-error[data-test-id="InputControlTemplate__hintText"]')), 'valid email') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkLoggedIn()
    {
        $isLoggedIn = false;
        if ($this->exts->exists('div[data-testid="AvatarMenu"]')) {
            $this->exts->moveToElementAndClick('div[data-testid="AvatarMenu"]');
            sleep(3);
        }
        $selector_elementSignOut = 'li[role="menuitem"]';
        $elementSignOut = $this->exts->getElementByText($selector_elementSignOut, 'Sign Out', null, false);
        if ($elementSignOut != null || $this->exts->exists('a[href="/account/profile"]')) {
            $isLoggedIn = true;
        } else if ($this->exts->exists('div[data-testid="AvatarMenu"]')) {
            $this->exts->moveToElementAndClick('div[data-testid="AvatarMenu"]');
            sleep(3);
            $elementSignOut = $this->exts->getElementByText('li[role="menuitem"]', 'Sign Out', null, false);
            if ($elementSignOut != null || $this->exts->exists('a[href="/account/profile"]')) {
                $isLoggedIn = true;
            }
        }
        return $isLoggedIn;
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

    private function processInvoices()
    {
        sleep(5);
        if ($this->exts->exists('table tbody tr')) {
            $this->exts->log('Invoices found');
            $this->exts->capture("4-page-opened");
            $invoices = [];

            $rows = $this->exts->getElements('table tbody tr');
            foreach ($rows as $row) {
                //
                $invoiceName = $this->exts->extract('td:nth-child(2)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(3)', $row);
                $invoiceAmount = '';

                $downloadBtn = $this->exts->getElement('td:nth-child(4) button', $row);


                $this->totalFiles++;
                $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';

                $this->exts->log('date before parse: ' . $invoiceDate);

                $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'd M Y', 'Y-m-d');
                $this->exts->log('invoiceName: ' .  $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // $this->exts->log('Dowloading invoice '.$count.'/'.$totalFiles);

                    $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        } else {
            $this->exts->log('Timeout processInvoices');
            if ($this->exts->getElement('ul#myaccountpayments li a.download-invoice-link') != null) {
                $this->processInvoices1();
            }
        }
    }

    public $totalFiles = 0;
    public function processInvoices1()
    {
        $this->exts->log("Begin download invoice 1");

        $this->exts->capture('4-List-invoice-1');

        try {
            if ($this->exts->getElement('ul#myaccountpayments li a.download-invoice-link') != null) {
                $receipts = $this->exts->getElements('ul#myaccountpayments li');
                $invoices = array();
                foreach ($receipts as $receipt) {
                    if ($this->exts->getElement('a.download-invoice-link', $receipt) != null) {
                        $receiptDate = $this->exts->extract('p', $receipt);
                        $receiptDate = trim(explode('-', $receiptDate)[0]);
                        $receiptUrl = $this->exts->extract('a.download-invoice-link', $receipt, 'href');
                        $receiptName = trim(end(explode('paymentID=', $receiptUrl)));
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd M Y', 'Y-m-d');
                        $receiptAmount = trim(preg_split("/\d{4}/", $this->exts->extract('p', $receipt))[-1]);
                        $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' USD';

                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice URL: " . $receiptUrl);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("Invoice parsed_date: " . $parsed_date);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'receiptUrl' => $receiptUrl,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                $this->exts->log("Invoice found: " . count($invoices));

                foreach ($invoices as $invoice) {
                    $this->totalFiles += 1;
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }

    private function processSubscriptions()
    {
        $this->exts->log("Begin download invoice");
        $this->exts->capture('4-List-invoice');
        if ($this->exts->getElement('[data-test-id="account-page__license-section"]') != null) {
            $subscriptions = $this->exts->getElements('[data-test-id="account-page__license-section"]');
            for ($i = 0; $i < count($subscriptions); $i++) {
                if ($this->exts->getElement('button', $subscriptions[$i]) != null) {
                    $download_button = $this->exts->getElement('button', $subscriptions[$i]);
                    $subName = strtolower($subscriptions[$i]->getAttribute('innerText'));
                    $subName = explode('license number', $subName);
                    $subName = end($subName);
                    $subName = explode(PHP_EOL, $subName);
                    $subName = array_shift($subName);

                    $this->exts->log("--------------------");
                    $this->exts->log("Invoice Name: " . $subName);
                    $this->isNoInvoice = false;
                    $invoiceFileName = $subName . '.pdf';
                    try {
                        $this->exts->log("Click invoice link ");
                        $download_button->click();
                    } catch (Exception $e) {
                        $this->exts->log("Click invoice link by javascript ");
                        $this->exts->executeSafeScript('arguments[0].click()', [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($subName, "", "", $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }

            $this->exts->log("Invoice found: " . count($subscriptions));
            $this->totalFiles = count($subscriptions);
        } else if ($this->exts->getElement('[data-testid="LicenseItem"]') != null) {
            $subscriptions = $this->exts->getElements('[data-testid="LicenseItem"]');
            for ($i = 0; $i < count($subscriptions); $i++) {
                if ($this->exts->getElement('button', $subscriptions[$i]) != null) {
                    $download_button = $this->exts->getElement('button', $subscriptions[$i]);
                    $subName = strtolower($subscriptions[$i]->getAttribute('innerText'));
                    $subName = explode('license number', $subName);
                    $subName = end($subName);
                    $subName = explode(PHP_EOL, $subName);
                    $subName = array_shift($subName);

                    $this->exts->log("--------------------");
                    $this->exts->log("Invoice Name: " . $subName);
                    $this->isNoInvoice = false;
                    $invoiceFileName = $subName . '.pdf';
                    try {
                        $this->exts->log("Click invoice link ");
                        $download_button->click();
                    } catch (Exception $e) {
                        $this->exts->log("Click invoice link by javascript ");
                        $this->exts->executeSafeScript('arguments[0].click()', [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($subName, "", "", $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }

            $this->exts->log("Invoice found: " . count($subscriptions));
            $this->totalFiles = count($subscriptions);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
