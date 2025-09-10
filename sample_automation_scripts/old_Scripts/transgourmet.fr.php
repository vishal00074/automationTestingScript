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

    // Server-Portal-ID: 67772 - Last modified: 03.07.2024 13:54:54 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://espaceclient.transgourmet.fr';
    public $loginUrl = 'https://espaceclient.transgourmet.fr';
    public $invoicePageUrl = 'https://espaceclient.transgourmet.fr/mes-factures';

    public $username_selector = '#loginForm input#username';
    public $password_selector = '#loginForm input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[action="login"] input[type="submit"]';

    public $check_login_failed_selector = 'div#loginFailed';
    public $check_login_success_selector = 'a[href*="deconnexion"], a#btnLogout, a.my-account[href*="accueil"]';

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
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('#tarteaucitronPersonalize')) {
                $this->exts->moveToElementAndClick('#tarteaucitronPersonalize');
            }
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            }
            
            $this->checkFillLogin();
            $mesg = strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText'));
            $this->exts->log($mesg);
            if (stripos($mesg, "Your account is not recognized") !== false) {
                $this->exts->loginFailure(1);
            }
            if (strpos($mesg, 'ou mot de passe inexact') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos($mesg, 'ltige anmeldedaten') !== false) {
                $this->exts->loginFailure(1);
            }
            if (!$this->checkLogin() && strpos($mesg, 'ou mot de passe inexact') === false) {
                $this->exts->log('must refresh page');
                $this->exts->openUrl($this->baseUrl);
                sleep(10);
            }
            sleep(10);

            // Connect to your online services
            if ($this->exts->exists('a[href*="site=eGourmet"]')) {
                $this->exts->moveToElementAndClick('a[href*="site=eGourmet"]');
                sleep(5);
            }
            
        }

        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            if ($this->exts->exists('#tarteaucitronPersonalize')) {
                $this->exts->moveToElementAndClick('#tarteaucitronPersonalize');
                sleep(5);
            }

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            if ($this->exts->urlContains('transgourmet.fr/mes-factures')) {
                $this->processInvoices();
            } else {
                $this->exts->moveToElementAndClick('a#dropdownMenuCompte');
                sleep(5);
                if ($this->exts->exists('a#btnMyAccount')) {
                    $this->exts->moveToElementAndClick('a#btnMyAccount');
                    sleep(15);
                    $this->exts->moveToElementAndClick('button[routerlink="/page/mes-commandes"]');
                    sleep(15);
                    $this->processInvoicesWebshop();
                } else {
                    $this->exts->moveToElementAndClick('a[href="/portail/mes-factures"].btn-account');
                    sleep(10);
                    $this->processInvoicesfactures();
                }

                $this->exts->waitTillPresent('a[href*="site=espaceclient"]');
                
                if($this->exts->exists('a[href*="site=espaceclient"]')){
                    $this->exts->moveToElementAndClick('a[href*="site=espaceclient"]');
                    sleep(5);
                }
            }


            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $mesg = strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText'));
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos($mesg, "Your account is not recognized") !== false) {
                $this->exts->loginFailure(1);
            }
            if (stripos($mesg, "Cet identifiant n'est plus actif") !== false) {
                $this->exts->loginFailure(1);
            }
            if (strpos($mesg, 'ou mot de passe inexact') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos($mesg, 'ltige anmeldedaten') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);

        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(15);
            $this->exts->capture("2-login-aftersubmit");
            if ($this->exts->getElement('a[onclick*="#mailForm"]') != null) {
                $this->exts->account_not_ready();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function processInvoices($pageCount = 1)
    {
        sleep(5);
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
        // 	$this->exts->log('Waiting for invoice...');
        // 	sleep(5);
        // }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 14 && $this->exts->getElement('a[href*="facture"]', $tags[13]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="facture"]', $tags[13])->getAttribute("href");
                $invoiceName = trim($tags[1]->getText());
                $invoiceDate = trim($tags[7]->getText());
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[11]->getText())) . ' EUR';

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

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $pageCount < 50 &&
            $this->exts->getElement('a[title="Suivant"]') != null
        ) {
            $pageCount++;
            $this->exts->moveToElementAndClick('a[title="Suivant"]');
            sleep(5);
            $this->processInvoices($pageCount);
        }
    }

    private function processInvoicesWebshop($pageCount = 1)
    {
        sleep(5);

        $this->exts->capture("4-invoices-page");
        $invoices = [];
        // scroll to bottom
        $this->exts->executeSafeScript('window.scrollTo(0,document.body.scrollHeight);');
        sleep(5);

        $rows = $this->exts->getElements('table.liste_items > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td,th', $row);
            if (count($tags) >= 9 && $this->exts->getElement('a[href*="printOrder?orderId="]', $tags[7]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="printOrder?orderId="]', $tags[7])->getAttribute("href");
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';

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

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processInvoicesfactures()
    {
        sleep(25);

        $this->exts->capture("4-invoices-page-factures");
        $invoices = [];

        $rows = count($this->exts->getElements('.account-factures-list table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('.account-factures-list table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 10 && $this->exts->getElement('a.download.clickable', $tags[9]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('a.download.clickable', $tags[9]);
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = trim($tags[6]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[8]->getAttribute('innerText'))) . ' USD';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
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
                    sleep(8);
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

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
