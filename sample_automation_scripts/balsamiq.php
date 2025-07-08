<?php // migrated

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

    // Server-Portal-ID: 61478 - Last modified: 21.08.2024 14:35:38 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://balsamiq.cloud';
    public $loginUrl = 'https://balsamiq.cloud/#login';
    public $invoicePageUrl = 'https://balsamiq.cloud';

    public $username_selector = 'input#dialog-login-email';
    public $password_selector = 'input#dialog-login-password';
    public $remember_me_selector = '';
    public $submit_login_btn = 'button#dialog-login-submit';

    public $checkLoginFailedSelector = '#dialog-login-error';
    public $checkLoggedinSelector = '[data-cypress="user-avatar-menu"],div[class="lcBody"] div[class="logout"], div[data-testid="menu-User"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // $this->fake_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36');
        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in

        // Wait for selector that make sure user logged in
        $this->exts->waitTillPresent($this->checkLoggedinSelector);

        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');

            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            for ($i = 0; $i < 5 && $this->exts->exists('div#balsamiq-loading-screen'); $i++) {
                sleep(15);
            }
            $this->waitForLoginPage();
        }
    }

    private function waitForLoginPage()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-filled-login");
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(15);

            $this->waitForLogin();
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            if ($this->exts->getElement('input[class*="DialogNewSite__SiteNameInput"]') != null) {
                $this->exts->account_not_ready();
            }

            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }


    private function waitForLogin()
    {
        sleep(10);
        $currentUrl = $this->exts->getUrl();

        $this->exts->log('Current URL :' . $currentUrl);

        $parsed_url = parse_url($currentUrl);

        $path_parts = explode('/', $parsed_url['path']);
        $path_parts = array_slice($path_parts, 0, 2);
        $path_parts[] = 'billing';
        $new_path = implode('/', $path_parts);

        $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $new_path;

        $this->exts->log('New URL :' . $new_url);

        $this->exts->openUrl($new_url);
        sleep(10);

        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            sleep(3);
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            sleep(2);
            if ($this->exts->exists('div[class="content"] > div:nth-child(1) > div:nth-child(2)')) {
                $this->exts->moveToElementAndClick('div[class="content"] > div:nth-child(1) > div:nth-child(2)');
                sleep(15);
                $this->processInvoiceLatest();
            } else {
                sleep(15);
                // Open invoices url
                $this->exts->moveToElementAndClick('a[href*="/settings"]');
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");
            sleep(2);
            if ($this->exts->exists('div[class*="windowframe__titleLabel"]')) {
                $this->exts->account_not_ready();
                sleep(2);
            }

            if (stripos($this->exts->extract($this->checkLoginFailedSelector), 'Passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElement('input[class*="DialogNewSite__SiteNameInput"]') != null) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public $moreBtn = true;

    private function processInvoiceLatest()
    {
        $this->exts->log('Process Invoice : ');
        $totalInvoices = $this->exts->getElements('a[data-testid="hip-link"]');
        $this->exts->log('Initial No of Invoices : ' . count($totalInvoices));
        $stop = 0;
        while ($this->moreBtn &&  $stop < 50) {

            if ($this->exts->exists('div button[class="UnstyledLink ButtonLink IconParent Flex-flex"]:nth-child(2)')) {
                $this->viewMore();
                sleep(3);
            } else {

                $this->moreBtn = false;
            }
            $stop++;
        }

        $totalInvoices = $this->exts->getElements('a[data-testid="hip-link"]');

        $this->exts->log('Total No of Invoices : ' . count($totalInvoices));

        foreach ($totalInvoices as $invoice) {

            $invoiceBtn = $invoice;
            if ($invoiceBtn != null) {
                $invoiceBtn->click();
            }
            sleep(10);

            $this->exts->log('open new window');
            $newTab = $this->exts->openNewTab();

            if ($this->exts->exists('div[class="App-InvoiceDetails flex-item width-grow flex-container direction-column"]')) {
                $invoiceName = $this->exts->getElement('div[class="App-InvoiceDetails flex-item width-grow flex-container direction-column"] tr:nth-child(1) td:nth-child(2)')->getText();
                $invoiceDate = $this->exts->getElement('div[class="App-InvoiceDetails flex-item width-grow flex-container direction-column"] tr:nth-child(2) td:nth-child(2)')->getText();
                $invoiceFileName = $invoiceName . '.pdf';
                $download_button =  $this->exts->getElement('div[class="InvoiceDetailsRow-Container"] button:nth-child(1)');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);


                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log(__FUNCTION__ . ' trigger click.');

                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(4);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->totalFiles += 1;
                        $this->exts->new_invoice($invoiceName, $invoiceDate, '', $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }

                $this->exts->log('close new window');
                $this->exts->closeTab($newTab);
                sleep(2);
                $this->isNoInvoice = false;
            }
        }
    }

    public function viewMore()
    {
        $this->exts->moveToElementAndClick('div button[class="UnstyledLink ButtonLink IconParent Flex-flex"]:nth-child(2)');
    }


    private function processInvoices()
    {

        sleep(2);
        if ($this->exts->getElement('//li/a[contains(@href, "balsamiq.com/transaction/")]/../following-sibling::li/a[@href="#"]', null, 'xpath') != null) {
            $this->exts->getElement('//li/a[contains(@href, "balsamiq.com/transaction/")]/../following-sibling::li/a[@href="#"]', null, 'xpath')->click();
            sleep(2);
        }

        $this->exts->log('Invoices found');
        $this->exts->capture("4-page-opened");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('li a[href*="balsamiq.com/transaction/"]');
        foreach ($rows as $row) {
            $invoiceUrl = str_replace('/public/', '/downloadTransactionPdf/', $row->getAttribute("href"));
            $invoiceName = explode(
                '?',
                array_pop(explode('/', $invoiceUrl))
            )[0];
            $invoiceDate = trim(
                explode('-', $row->getText())[0]
            );
            $invoiceAmount = trim(
                array_pop(explode('-', $row->getText()))
            );
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' USD';
            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));

            $this->isNoInvoice = false;
        }

        // Download all invoices
        $this->exts->log('Invoices: ' . count($invoices));
        $count = 1;
        $totalFiles = count($invoices);

        foreach ($invoices as $invoice) {
            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

            $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F d, Y', 'Y-m-d');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->log('Dowloading invoice ' . $count . '/' . $totalFiles);
                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                sleep(2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                    sleep(1);
                    $count++;
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
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
