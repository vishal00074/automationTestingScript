<?php // hanlde empty invoice name added restrict page logic accordign to date

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

    // Server-Portal-ID: 138734 - Last modified: 05.08.2025 13:38:01 UTC - User: 1

    public $baseUrl = 'https://service.handyvertrag.de/mytariff/invoice/showAll';
    public $loginUrl = 'https://service.handyvertrag.de/';
    public $invoicePageUrl = 'https://service.handyvertrag.de/mytariff/invoice/showAll';

    public $username_selector = 'input#UserLoginType_alias';
    public $password_selector = 'input#UserLoginType_password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div#buttonLogin a, div#buttonLogin, a[onclick="submitForm(\'loginAction\');"]';

    public $check_login_failed_selector = 'div.error.s-validation';
    public $check_login_success_selector = 'a#logoutLink, div#userData span.logout, #logoutLink a';

    public $isNoInvoice = true;
    public $restrictPages = 3;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();

        $this->exts->openUrl($this->baseUrl);

        $this->exts->capture('1-init-page');
        // $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 20);
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
            sleep(15);
        }

        if ($this->exts->getElement('dialog a.c-overlay-close') != null) {
            $this->exts->moveToElementAndClick('dialog a.c-overlay-close');
            sleep(2);
        }
        if ($this->exts->getElement('div[role="dialog"] #consent_wall_optin') != null) {
            $this->exts->moveToElementAndClick('div[role="dialog"] #consent_wall_optin');
            sleep(2);
        }

        // $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 20);
        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            if ($this->exts->exists('button#preferences_prompt_submit_all')) {
                $this->exts->click_by_xdotool('button#preferences_prompt_submit_all');
                sleep(5);
            }
            $this->exts->openUrl('https://service.handyvertrag.de/start');
            sleep(7);
            $this->exts->capture("3-multi-account-checking");
            $only_consolidated_Invoice = isset($this->exts->config_array["only_consolidated_Invoice"]) ? (int) @$this->exts->config_array["only_consolidated_Invoice"] : (isset($this->exts->config_array["only_consolidated_invoice"]) ? (int) @$this->exts->config_array["only_consolidated_invoice"] : 0);
            $accounts = $this->exts->getElementsAttribute('select#SwitchUserType_currentSubscriberId option', 'innerText');
            if (count($accounts) > 1) {
                foreach ($accounts as $key => $account) {
                    if (!$this->exts->exists('div.c-form-select-wrapper div.c-form-select')) {
                        $this->exts->openUrl('https://service.handyvertrag.de/start');
                        sleep(7);
                    }
                    $this->exts->click_by_xdotool('div.c-form-select-wrapper div.c-form-select');
                    sleep(2);
                    $option = $this->exts->querySelector('div.c-form-select-wrapper div.c-form-select div.c-form-select-visual-options div:nth-child(' . ($key + 1) . ')');
                    $this->exts->click_element($option);
                    sleep(5);
                    $this->exts->capture("3-account-" . $account);
                    $this->exts->openUrl($this->invoicePageUrl);
                    if ($only_consolidated_Invoice == 1) {
                        $this->processConsolidatedInvoice();
                    } else {
                        $this->processInvoices();
                    }
                }
            } else {
                // Open invoices url and download invoice
                $this->exts->openUrl($this->invoicePageUrl);
                if ($only_consolidated_Invoice == 1) {
                    $this->processConsolidatedInvoice();
                } else {
                    $this->processInvoices();
                }
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'nicht korrekt') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me_selector != '')
                $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function dateRange($invoiceDate)
    {

        $selectDate = new DateTime();
        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $restrictDate = $selectDate->format('d.m.Y');
        } else {
            $selectDate->modify('-3 months');
            $restrictDate = $selectDate->format('d.m.Y');
        }

        $this->exts->log("formattedDate:: " . $restrictDate);
        $restrictDateTimestamp = strtotime($restrictDate);
        $invoiceDateTimestamp = strtotime($invoiceDate);


        if ($restrictDateTimestamp < $invoiceDateTimestamp) {
            return true;
        } else {
            return false;
        }
    }

    public $totalInvoices = 0;

    private function processInvoices()
    {
        $this->exts->waitTillPresent('div#accordionrechnungen div.card', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $selectorConfigs = [
            ['selector' => 'div#accordionrechnungen div.card', 'dateSelector' => 'button'],
            ['selector' => '[data-name*="rechnung"]', 'dateSelector' => 'summary']
        ];

        // Process each selector
        foreach ($selectorConfigs as $config) {
            $rows = $this->exts->querySelectorAll($config['selector']);
            $this->exts->log('Total No of Rows : ' . count($rows));
            foreach ($rows as $row) {
                if ($this->exts->querySelector('a[href*="PDF"]', $row) != null) {
                    $invoiceUrl = $this->exts->querySelector('a[href*="PDF"]', $row)->getAttribute("href");
                    $invoiceName = array_pop(explode('PDF/', $invoiceUrl));
                    $invoiceDate = trim(array_pop(explode('vom', $this->exts->querySelector($config['dateSelector'], $row)->getAttribute('innerText'))));
                    $invoiceAmount = '';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));
                    $this->isNoInvoice = false;
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {

            if ($this->totalInvoices >= 100) {
                return;
            }

            $dateRange = $this->dateRange($invoice['invoiceDate']);

            if (!$dateRange) {
                return;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
                $this->totalInvoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processConsolidatedInvoice()
    {
        $this->exts->waitTillPresent('div#accordionrechnungensam div.card', 30);
        $this->exts->capture("4-Sammelrechnungen-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div#accordionrechnungensam div.card');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('a[href*="PDF"]', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="PDF"]', $row)->getAttribute("href");
                $invoiceName = array_pop(explode('PDF/', $invoiceUrl));
                $invoiceDate = trim(array_pop(explode('vom', $this->exts->querySelector('button', $row)->getAttribute('innerText'))));
                $invoiceAmount = '';

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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
