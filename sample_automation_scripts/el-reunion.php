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

    /*Define constants used in script*/
    public $baseUrl = 'https://sei-ael-reunion.edf.com/aelEDF/jsp/arc/habilitation/acteur.ZoomerDossierClient.go';
    public $loginUrl = 'https://sei-ael-reunion.edf.com/aelEDF/jsp/arc/habilitation/login.jsp';
    public $invoicePageUrl = 'https://sei-ael-reunion.edf.com/aelEDF/jsp/arc/habilitation/acteur.ZoomerDossierClient.go';

    public $username_selector = 'form[action="habilitation.ActorIdentificationAel.go"] input[name="lg"]';
    public $password_selector = 'form[action="habilitation.ActorIdentificationAel.go"] input[name="psw"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'a#valider';

    public $check_login_failed_selector = '.errorMessage p';
    public $check_login_success_selector = 'a#fermerSession';

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
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('div[id="bandeauCookie"] a[id="accederPageChoixCookie"][title="tout accepter"]')) {
                $this->exts->moveToElementAndClick('div[id="bandeauCookie"] a[id="accederPageChoixCookie"][title="tout accepter"]');
            }
            $this->checkFillLogin();
            sleep(30);
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            if ($this->exts->exists('a#consulterFactures')) {
                $this->exts->moveToElementAndClick('a#consulterFactures');
                $this->processInvoices();
            } else {
                $this->exts->moveToElementAndClick('ul[id="navigation02"] > li:nth-child(1) a');
                sleep(5);
                $this->exts->waitTillPresent('select[name="contrats"] option:not([value=""])');
                // Multi Contract Code
                $contracts = $this->exts->getElements('select[name="contrats"] option:not([value=""])');

                foreach ($contracts as $key => $contract) {
                    $num =  $key + 2;
                    $this->exts->log('num:: ' . $num);
                    $this->exts->moveToElementAndClick('ul[id="navigation02"] > li:nth-child(1) a');
                    sleep(5);
                    $this->exts->moveToElementAndClick('select[name="contrats"]');
                    sleep(5);
                    $optionValue = $this->exts->getElement('select[name="contrats"] option:nth-child(' . $num . ')');

                    if ($optionValue != null) {
                        // $value = $this->exts->execute_javascript("document.querySelector('select[name='contrats'] option:nth-child(' . $num . ')').value");
                        $value = $optionValue->getAttribute("value");

                        $this->exts->log('value:: ' . $value);

                        $this->changeSelectbox($value);
                        sleep(10);
                        $this->exts->moveToElementAndClick('a#consulterFactures');
                        sleep(15);
                        $this->processInvoices();
                    }
                }
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'nouveau votre login et votre mot de passe') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function changeSelectbox($value)
    {
        $this->exts->execute_javascript('
            let selectBox = document.querySelector(\'select[name="contrats"]\');
            selectBox.value = "' . addslashes($value) . '";
            selectBox.dispatchEvent(new Event("change"));
        ');
    }

    private function checkFillLogin()
    {
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
            sleep(10);
            if ($this->exts->exists($this->submit_login_selector) && stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'nouveau votre login et votre mot de passe') === false) {
                $submit_btn = $this->exts->getElement($this->submit_login_selector);
                try {
                    $this->exts->log('Click submit button');
                    $submit_btn->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click submit button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$submit_btn]);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = count($this->exts->getElements('table#tbl_mesFacturesExtrait > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table#tbl_mesFacturesExtrait > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[onclick*="FactureDuplicata"]', $tags[6]) != null) {
                $download_button = $this->exts->getElement('a[onclick*="FactureDuplicata"]', $tags[6]);
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceDate = trim($this->exts->getElement('input', $tags[1])->getAttribute('value'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->getElement('input', $tags[3])->getAttribute('value'))) . ' EUR';
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');

                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // click and download invoice
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
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
