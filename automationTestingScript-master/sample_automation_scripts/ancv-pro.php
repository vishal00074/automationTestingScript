<?php

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


    public $baseUrl = "https://espace-ptl.ancv.com/espacePrestataire/accueil-ptl";
    public $loginUrl = "https://espace-ptl.ancv.com/user/login";
    public $invoicePageUrl = 'https://espace-ptl.ancv.com/espacePrestataire/remb/suivi';
    public $username_selector = 'input[id="edit-name"]';
    public $password_selector = 'input[id="edit-pass"]';
    public $submit_button_selector = 'input[id="edit-submit"]';
    public $check_login_failed_selector = 'div.messages.error';
    public $check_login_success_selector = 'div.content > a.lienDeconnexion';
    public $login_tryout = 0;
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

        if ($this->exts->exists('button.agree-button')) {
            $this->exts->moveToElementAndClick("button.agree-button");
            sleep(10);
        }



        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(7);
            if ($this->exts->exists('button.agree-button')) {
                $this->exts->moveToElementAndClick("button.agree-button");
                sleep(10);
            }
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            $this->exts->log("::Error Text" .  $error_text);

            if (stripos($error_text, strtolower("Votre numéro de convention et/ou votre mot de passe est incorrect. Pour davantage d'information, se référer aux bulles d'aide.")) !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->log("Failed due to unknown reasons");
                $this->exts->loginFailure();
            }
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(2);
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
            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function processInvoices($count = 0)
    {
        $this->exts->waitTillPresent('table[id="sort-table"] > tbody', 20);
        $this->exts->capture("1 invoice page");
        $invoices = [];
        $rows = $this->exts->querySelectorAll('table[id="sort-table"] > tbody > tr');

        foreach ($rows as $row) {
            $invoiceName = $row->querySelector('td:nth-child(1)');
            if ($invoiceName != null) {
                $invoiceName = $invoiceName->getText();
            }
            $invoiceDate = $row->querySelector('td:nth-child(6)');
            if ($invoiceDate != null) {
                $invoiceDate = $invoiceDate->getText();
            }
            $amount = $row->querySelector('td:nth-child(7)');
            if ($amount != null) {
                $amount = $amount->getText();
            }
            $downloadBtn = $row->querySelector('td:nth-child(9) > a');

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $amount,
                'downloadBtn' => $downloadBtn,
            ));
            $this->isNoInvoice = false;
        }
        $this->exts->log('Invoices found: ' . count($invoices));

        foreach ($invoices as $invoice) {
            if ($invoice['downloadBtn']) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if ($count < $restrictPages && $this->exts->exists('a[class="paginate_button next"]')) {
            $this->exts->moveToElementAndClick('a[class="paginate_button next"]');
            sleep(7);
            $count++;
            $this->processInvoices($count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
