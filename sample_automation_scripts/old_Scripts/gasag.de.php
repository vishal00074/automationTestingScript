<?php // handle empty invoiceName case and replace exists to  custom js function isExists

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

    public $baseUrl = 'https://www.gasag.de/onlineservice/login';
    public $loginUrl = 'https://www.gasag.de/onlineservice/login';
    public $invoicePageUrl = 'https://www.gasag.de/onlineservice/postbox';

    public $username_selector = 'input#bpcLoginUsername, input#loginUsername, input#signInName';
    public $password_selector = 'input#bpcLoginPassword, input#loginPassword, form input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#loginBtn, button[type="submit"]';

    public $check_login_failed_selector = 'form#localAccountForm div.error.pageLevel';
    public $check_login_success_selector = 'li:not([data-hidden="true"]) a[href*="/meine-gasag/logout"], li:not([data-hidden="true"]) a[href*="/logout"] button, a[href*="/dashboard/"], li:not([data-hidden="true"]) a[href*="/onlineservice/"], img[src*="/logout"]';

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
        // $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null || $this->isExists($this->username_selector)) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);

            $mesg = strtolower(trim($this->exts->extract('[role="dialog"]', null, 'innerText')));
            $this->exts->log($mesg);
            if (strpos($mesg, 'falscheingaben gesperrt') !== false) {
                $this->exts->account_not_ready();
            }

            if (strpos($mesg, 'oder passwort falsch') !== false) {
                $this->exts->loginFailure(1);
            }

            if (strpos($mesg, 'leider ist ein fehler aufgetreten') !== false) {
                $this->exts->loginFailure(1);
            }
        }

        $this->exts->execute_javascript('var shadow = document.querySelector("#usercentrics-root").shadowRoot; shadow.querySelector("button[data-testid=\"uc-accept-all-button\"]").click();');
        sleep(30);
        if ($this->exts->getElement($this->check_login_success_selector) != null && !$this->isExists($this->username_selector)) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            if ($this->isExists('datatable-body datatable-body-row')) {
                $this->processInvoices();
            } else {
                if ($this->isExists('css-contract-tabs ul.contract-tabs li a')) {
                    $contracts = $this->exts->getElements('css-contract-tabs ul.contract-tabs li a');
                    $this->exts->log('Total Contracts = ' . count($contracts));
                    if (count($contracts) > 1) {
                        foreach ($contracts as $key => $contract) {
                            $contract =  $this->exts->getElements('css-contract-tabs ul.contract-tabs li a')[$key];
                            try {
                                $contract->click();
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click();', [$contract]);
                            }
                            sleep(10);

                            $this->processInvoices_new();
                            sleep(2);

                            $this->exts->openUrl($this->invoicePageUrl);
                            sleep(10);
                        }
                    } else {
                        $this->exts->moveToElementAndClick('css-contract-tabs ul.contract-tabs li a');
                        sleep(5);

                        $this->processInvoices_new();
                    }
                } else {
                    $this->processInvoices_new();
                }
            }


            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'keine passenden anmeldung finden') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
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
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    private function processInvoices()
    {
        sleep(15);
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
        // 	$this->exts->log('Waiting for invoice...');
        // 	sleep(5);
        // }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $isDownloadAll =  isset($this->exts->config_array["download_all_documents"]) ? true : false;
        $rows = $this->exts->getElements('datatable-body datatable-body-row');
        foreach ($rows as $index => $row) {
            $tags = $this->exts->getElements('datatable-body-cell', $row);
            if (count($tags) >= 4 && $this->exts->getElement('a', $tags[2]) != null) {
                $invoiceSelector = $this->exts->getElement('a', $tags[2]);
                $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-" . $index . "');", [$invoiceSelector]);
                $invoiceUrl = $this->exts->getElement('bpc-document-download', $tags[2])->getAttribute("ng-reflect-url");
                $invoiceName = explode(
                    '/',
                    array_pop(explode('postbox/', $invoiceUrl))
                )[0];
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText')));

                //I assume that invoice record will have amount and other document doesn't have amount.
                if ($invoiceAmount != '' || $isDownloadAll) {
                    $invoiceAmount = $invoiceAmount . ' EUR';
                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                    $this->isNoInvoice = false;
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        // click and download invoice
                        $this->exts->moveToElementAndClick('a#custom-pdf-download-button-' . $index);
                        sleep(2);
                        $this->exts->moveToElementAndClick('a#confirmYes');
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log('Timeout when download ' . $invoiceFileName);
                        }
                    }
                }
            }
        }
    }

    private function processInvoices_new()
    {
        sleep(25);
        if ($this->isExists(' li.nav-item:nth-child(2)') && $this->isExists('li.nav-item:nth-child(2) li.dropdown-item:nth-child(3) a.contract-anchor__link') && !$this->isExists('css-tile-documents.grid-list-item-medium css-contract-anchor, css-tile-documents css-contract-anchor a.contract-anchor__link')) {
            $this->exts->log("Choose bill page");
            $this->exts->moveToElementAndClick(' li.nav-item:nth-child(2)');
            sleep(15);
            $this->exts->moveToElementAndClick('li.nav-item:nth-child(2) li.dropdown-item:nth-child(3) a.contract-anchor__link');
            sleep(10);
            if (stripos($this->exts->getUrl(), 'onlineservice/postbox') == false) {
                $this->exts->moveToElementAndClick(' li.nav-item:nth-child(2)');
                sleep(15);
                $this->exts->moveToElementAndClick('li.nav-item:nth-child(2) li.dropdown-item:nth-child(4) a.contract-anchor__link');
                sleep(10);
            }
        } else if ($this->isExists('css-tile-documents.grid-list-item-medium css-contract-anchor, css-tile-documents css-contract-anchor a.contract-anchor__link')) {
            $this->exts->log("Choose bill page");
            $this->exts->moveToElementAndClick('css-tile-documents.grid-list-item-medium css-contract-anchor, css-tile-documents css-contract-anchor a.contract-anchor__link');
            sleep(3);
        } else {
            $this->exts->moveToElementAndClick('a[href*="/onlineservice/inbox"]');
            sleep(15);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->getElements('table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 4 && $this->exts->getElement('fa-icon', $tags[0]) != null) {
                $download_button = $this->exts->getElement('fa-icon', $tags[0]);
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = "";
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'm.d.Y', 'Y-m-d');
                }
                $this->exts->log('Date parsed: ' . $parsed_date);

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(2);
                if ($this->isExists('div.modalBox__box button[type="button"]')) {
                    $this->exts->log("Choose Phone");
                    $this->exts->moveToElementAndClick('div.modalBox__box button[type="button"]');
                    sleep(2);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else if (count($tags) >= 4 && strpos(strtolower($tags[2]->getAttribute('innerText')), 'rechnung') !== false) {
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = "";
                $invoiceName = trim(str_replace('.', '-', $tags[1]->getAttribute('innerText')));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'm.d.Y', 'Y-m-d');
                }
                $this->exts->log('Date parsed: ' . $parsed_date);

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }

                try {
                    $this->exts->log('Click row button');
                    $row->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click row button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$row]);
                }
                if ($this->isExists('div.modalBox__box button[type="button"]')) {
                    $this->exts->log("Choose Phone");
                    $this->exts->moveToElementAndClick('div.modalBox__box button[type="button"]');
                    sleep(2);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                // sometimes the page is unresponsive, refresh
                $this->exts->refresh();
                sleep(10);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
