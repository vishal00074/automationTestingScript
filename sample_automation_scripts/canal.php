<?php // i have updated the before login code and optimized secript performance i have added condition to handle empty invoice name
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

    public $baseUrl = "https://client.canal.fr/";
    public $loginUrl = "https://client.canal.fr/";
    public $homePageUrl = "https://client.canal.fr/abonnement/";
    public $username_selector = 'input[name="identifier"]';
    public $password_selector = 'input[name="credentials.passcode"][type="password"]';
    public $submit_button_selector = 'input[type="submit"]';
    public $month_names_fr = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
    public $login_tryout = 0;
    public $restrictPages = 3;

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $nav_driver = $this->exts->execute_javascript('(function() { return navigator.webdriver; })();');
        $this->exts->log('nav_driver: ' . json_decode($nav_driver, true)['result']['result']['value']);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $this->exts->capture("Home-page-without-cookie");

        if ($this->exts->exists('button#didomi-notice-agree-button')) {
            $this->exts->click_by_xdotool('button#didomi-notice-agree-button');
        }

        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('button#didomi-notice-agree-button')) {
                $this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
                sleep(5);
            }

            if ($this->exts->exists('div[class*="user__popin__auth-section"] button')) {
                $this->exts->moveToElementAndClick('div[class*="user__popin__auth-section"] button');
                sleep(5);
            }

            $this->fillForm(0);
        }

        $this->exts->log($this->exts->extract('div[class*="HeaderLayout__userOptions"]', null, 'innerHTML'));

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if ($this->exts->exists('form[name="sso-form"] input[value="blockedAccount"]')) {
                $this->exts->account_not_ready();
            } else if (strpos($this->exts->extract('form.form-error .errorDiv .message'), 'votre email et votre mot de passe') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('span#error-message')), 'ake sure that you have right password') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            if ($this->exts->exists($this->password_selector)) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, '');
                $this->exts->click_by_xdotool($this->username_selector);
                sleep(2);
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, '');
                $this->exts->click_by_xdotool($this->password_selector);
                sleep(2);
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->click_by_xdotool($this->submit_button_selector);

                sleep(8);
            }
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
            if ($this->exts->getElementByText('div.header-user-popin-button button', ['Se déconnecter', 'Sign out'], null, true) && !$this->exts->exists($this->password_selector) || $this->exts->exists('div[class="uic-header-user__trigger --authenticated"]')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    function invoicePage()
    {
        $this->exts->log("Invoice page");

        $this->exts->openUrl('https://client.canalplus.com/compte/factures');
        $this->processInvoices();

        $this->exts->openUrl('https://client.canalplus.com/paiement');
        $this->processInvoicesNew();
    }

    private function processInvoices()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div.invoice__row a[href*="pdf"]');
        foreach ($rows as $row) {
            $invoiceUrl = $row->getAttribute("href");
            $invoiceName = array_pop(explode('documentId=', $invoiceUrl));
            $invoiceDate = '';
            $invoiceAmount = '';

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));
            $this->isNoInvoice = false;
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName =  !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processInvoicesNew($paging_count = 1)
    {
        $this->exts->waitTillPresent('ul li a.arrow-list__item[href*="/paiement"]', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        // Keep clicking more but maximum upto 10 times
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts && $this->exts->exists('div.list-expand button')) {
            $this->exts->execute_javascript('
            var btn = document.querySelector("div.list-expand button");
            if(btn){
                btn.click();
            }
        ');
            $attempt++;
            sleep(5);
        }

        $rows = $this->exts->querySelectorAll('ul li a.arrow-list__item[href*="/paiement"]');
        foreach ($rows as $row) {
            if ($row != null) {
                $invoiceUrl = $row->getAttribute('href');
                preg_match('/\bdate=([0-9]{6})\b/', $invoiceUrl, $matches);
                $invoiceName = $matches[1];
                $invoiceAmount =  '';
                $invoiceDate =  '';

                $downloadBtn = '';

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

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(15);
            $this->exts->waitTillPresent("div.pdf-download button", 25);

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName =  !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->click_and_download($this->exts->querySelector("div.pdf-download button"), 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
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
