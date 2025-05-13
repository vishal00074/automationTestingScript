<?php // added condition in case invoice name is empty

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

    // Server-Portal-ID: 129025 - Last modified: 05.05.2025 13:35:13 UTC - User: 1

    // Script here
    public $baseUrl = 'https://service.vattenfall.de/vertragskonto';
    public $loginUrl = 'https://service.vattenfall.de/login';
    public $invoicePageUrl = 'https://service.vattenfall.de/postfach';

    public $username_selector = 'input[id*="loginModel.username"]';
    public $password_selector = 'input[id*="loginModel.password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form.cso-login-with-password button[type="submit"]';

    public $check_login_failed_selector = 'input[id*="loginModel.password"]';
    public $check_login_success_selector = 'a[href="/benutzerprofil"]';

    public $isNoInvoice = true;

    private function initPortal(int $count): void
    {
        $this->exts->log('Begin initPortal ' . $count);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        // For this portal, if not logged in, it will redirect to the login page.
        // For other portals that do not show the login page, check for the login page selector.
        // Don't just wait for check_login_success_selector because if the user is not logged in via cookie, it will wait for 15s.
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(3);
        }
        $this->exts->capture('1-init-page');

        // If the user has not logged in from cookie, do login.
        if ($this->exts->getElement($this->check_login_success_selector) === null) {
            $this->exts->log('NOT logged in via cookie');

            // If it did not redirect to login page after opening baseUrl, open loginUrl and wait for login page.
            $this->checkFillLogin();
            $this->exts->waitTillPresent($this->check_login_success_selector); // Wait for login to complete
        }

        if ($this->exts->getElement($this->check_login_success_selector) !== null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices URL and download invoices
            $this->exts->openUrl($this->invoicePageUrl);

            $this->selectContractAndProcess();

            // Final check: no invoices found
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::User login failed');

            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null || $this->exts->getElement('.cso-box.cso-error-handler .link.link--custom.link--button') != null) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('p.server-error', null, 'innerText')), 'und ihrem passwort ist falsch') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin(): void
    {
        sleep(2);
        if ($this->exts->getElement($this->password_selector) !== null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            if ($this->remember_me_selector !== '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            }

            $this->exts->capture("2-login-page-filled");

            // $this->checkFillRecaptcha(); // Uncomment this line if there is reCaptcha in the login page.

            // Need to check if submit button exists because sometimes after solving captcha, login form is submitted automatically.
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function selectContractAndProcess()
    {
        $this->exts->waitTillPresent('.contract-selector-dialog ul:not(.pagination-list) li');
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(3);
        }
        $this->exts->log("Begin selectContractAndProcess");
        $selectContractButtonSelector = '.cso-contract-selector-wrapper .pull-end';
        $accounts = $this->exts->getElements('.contract-selector-dialog ul:not(.pagination-list) li');
        $this->exts->log('accounts found: ' . count($accounts));
        for ($i = 0; $i < count($accounts); $i++) {

            $account = $accounts[$i];
            $this->exts->moveToElementAndClick($selectContractButtonSelector);
            sleep(5);

            # at the time of implementation contrct text was like this 
            # Strom - Bayreuther Str. 8, 10787 Berlin (Vertragskonto 836544047575) 

            // $contractTextParts = explode("(", $account->getAttribute('innerText'));
            // $contractText = array_pop($contractTextParts);
            // $contractNumber = preg_replace('/[^0-9-]+/', '', $contractText);

            $contractNumber = $this->exts->extract('div[class="table-end"] div:last-child', $account);
            $this->exts->log('select contract :' . $contractNumber);
            $index = $i + 1;
            $this->exts->moveToElementAndClick('.contract-selector-dialog ul:not(.pagination-list) li:nth-child(' . $index . ')');
            sleep(10);
            $this->exts->log('processing contract :' . $contractNumber);
            $this->processInvoices(1, $contractNumber);


            $accounts = $this->exts->getElements('.contract-selector-dialog ul:not(.pagination-list) li');
        }
    }

    private function processInvoices($pageCount = 1, $contractNumber)
    {
        $this->exts->waitTillPresent('div.download span:nth-child(2)');
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            if ($this->exts->exists('.cso-box .button--custom')) {
                $this->exts->moveToElementAndClick('.cso-box .button--custom');
            }
        }

        $rows = $this->exts->getElements('.cso-box .panel');
        $this->exts->log('invoice found: ' . count($rows));

        for ($i = 1; $i <= count($rows); $i++) {
            $row = $this->exts->getElement('.cso-box .panel:nth-child(' . $i . ')');
            $tags = $this->exts->getElements('div', $row);
            if (count($tags) >= 3 && $this->exts->getElement('div.download span:nth-child(2)', $row) != null) {
                $this->isNoInvoice = false;


                $download_button = $this->exts->getElement('div.download span:nth-child(2)', $row);
                $invoiceDate = $this->exts->extract('div[class="date-column"]', $row);
                $parsed_date = is_null($invoiceDate) ? null : $this->exts->parse_date($invoiceDate, 'm/d/Y', 'Y-m-d');
                $invoiceName = $contractNumber . '_' . $invoiceDate . '_' . trim($this->exts->extract('div[class="name-column"]', $row));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoiceAmount = null;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $downloaded_file = $this->exts->click_and_download($download_button, 'pdf', $invoiceFileName);
                    sleep(5);
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

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
