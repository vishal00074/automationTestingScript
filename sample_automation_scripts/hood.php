<?php // updated login success selector and added code to click on cookies button and added restriction to download only 50 invoice if restricpage != 0

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

    public $baseUrl = 'https://www.hood.de/mein-hood.htm?sec=1';
    public $loginUrl = 'https://www.hood.de/mein-hood.htm?sec=1';
    public $invoicePageUrl = 'https://www.hood.de/mein-hood.htm?sec=1';

    public $username_selector = 'form#hoodForm input[name="email"]';
    public $password_selector = 'form#hoodForm input[name="accountpass"]';
    public $remember_me_selector = 'form#hoodForm label input[data-parsley-multiple*="noLoginFlag"] ';
    public $submit_login_selector = 'form#hoodForm button[type="submit"]';

    public $check_login_failed_selector = 'div[class*="iError iErrorActive"] ul[class*="iListMessage"] li';
    public $check_login_success_selector = 'div.iHeaderSubMenu  div[onclick*="logout"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->waitFor($this->check_login_success_selector);
        $this->exts->capture('1-init-page');

        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-cmp-ui");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[id="accept"]\').click();
            }
        ');
        sleep(5);
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(3);
            $this->checkFillLogin();
            sleep(2);
            $this->waitFor($this->check_login_success_selector);
        }

        // then check user logged in or not
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
        //  $this->exts->log('Waiting for login...');
        //  sleep(5);
        // }
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);

            $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-cmp-ui");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button[id="accept"]\').click();
                }
            ');
            sleep(5);

            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);

            $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-cmp-ui");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button[id="accept"]\').click();
                }
            ');
            sleep(5);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->waitFor($this->password_selector);
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    public $totalInvoices = 0;

    private function processInvoices()
    {
        sleep(15);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

        $this->exts->capture("4-invoices-page");
        $this->exts->moveToElementAndClick('div.iMySettingsHeader');
        sleep(5);
        $this->exts->capture("4.1 Click button");
        $this->exts->moveToElementAndClick('a[href="/kontostand.htm?sec=1"]');
        $this->exts->capture("4.1 Click button");
        sleep(15);
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('td[class*="iAlignCenter"] a[href*=":invoiceID="]', $tags[4]) != null) {
                $invoiceUrl = $this->exts->getElement('td[class*="iAlignCenter"] a[href*=":invoiceID="]', $tags[4])->getAttribute("href");

                $invoiceName = explode(
                    "'",
                    array_pop(explode(':invoiceID=', $invoiceUrl))
                )[0];

                $cell = $this->exts->getElement('td[class*="iAlignCenter"] a[href*=":invoiceID="]', $tags[4]);
                $invoiceUrl = 'td[class*="iAlignCenter"] a[href*="invoiceID=' . $invoiceName . '"]';

                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(

                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'cell' => $cell
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {

            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            // Check if already exists 
            if (!$this->exts->document_exists($invoiceFileName)) {
                try {
                    $this->exts->log('Click download button');
                    $invoice['cell']->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$invoice['cell']]);
                }
                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['invoiceFileName']);
                sleep(1);

                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->log("create file");
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                    $this->totalInvoices++;
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
