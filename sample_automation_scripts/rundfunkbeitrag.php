<?php //   updated loginfailedConfirmed Message

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

    // Server-Portal-ID: 19835 - Last modified: 15.07.2025 14:42:33 UTC - User: 1

    public $baseUrl = "https://portal.rundfunkbeitrag.de";
    public $loginUrl = "https://portal.rundfunkbeitrag.de/portal/";
    public $homePageUrl = "https://portal.rundfunkbeitrag.de/anmeldung/index.xhtml";
    public $username_selector = "input[name=\"login:ctnutzername:nutzername\"]";
    public $password_selector = "input[name=\"login:ctpasswort:passwort\"]";
    public $submit_button_selector = "button[type=\"button\"]";
    public $login_tryout = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(2);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");
            $this->fillForm(0);
            sleep(5);

            $error_text = strtolower($this->exts->extract("ul.singleErrorlist.form_errortext"));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('korrekt')) !== false) {
                $this->exts->loginFailure(1);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");

                if ($this->exts->exists('td.vbottom a')) {
                    $this->exts->moveToElementAndClick("td.vbottom a");
                }

                sleep(5);
                $this->processInvoices();

                $this->exts->success();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {
            sleep(5);

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);

            $this->processSalesInvoice();

            $this->exts->success();
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            if ($this->exts->getElement($this->username_selector) != null) {
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");

                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
                $this->exts->capture("login filled");

                if ($this->exts->exists($this->submit_button_selector)) {
                    $this->exts->click_by_xdotool($this->submit_button_selector);
                }

                sleep(10); // Portal itself has one second delay after showing toast 
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function processSalesInvoice()
    {
        sleep(5);
        try {
            $this->exts->waitTillPresent("a[data-target='#zahlungsaufforderungen-panel']", 20);
            $this->exts->click_by_xdotool("a[data-target='#zahlungsaufforderungen-panel']");

            sleep(30);
            $rows = $this->exts->querySelectorAll("table#beitragskonto\:rechnungen tbody tr");
            $this->exts->log("Number of Sales Invoice Rows- " . count($rows));
            $total_rows = count($rows);
            if (count($rows) > 0) {
                for ($i = 0; $i < $total_rows; $i++) {
                    $rowItem = $rows[$i];
                    $columns =  $this->exts->querySelectorAll('td', $rowItem);
                    $this->exts->log("Number of Sales Invoice columns- " . count($columns));
                    if (count($columns) > 1) {
                        $selector = ".text-left a.btn";
                        $invoice_link_ele = $this->exts->querySelectorAll('.text-left a.btn', $columns[1]);
                        $this->exts->log("Number of Sales Invoice columns- " . count($invoice_link_ele));
                        if (count($invoice_link_ele) > 0) {

                            $invoiceText = trim($columns[0]->getAttribute('innerText'));
                            $onclickAttribute = $invoice_link_ele[0]->getAttribute('onclick');

                            // Check if 'onclick' attribute exists before using it
                            if ($onclickAttribute !== null) {
                                // Use regular expression to extract the 'referenz' value
                                preg_match("/'referenz':'([^']+)'/", $onclickAttribute, $matches);

                                if (isset($matches[1])) {
                                    $referenzValue = $matches[1];
                                    $documentName = strstr($referenzValue, $invoiceText, true);
                                    $files = glob($this->exts->config_array['download_folder'] . '*' . $documentName . '*');
                                    $files = array_filter($files, 'is_file');
                                    if (!empty($files)) {
                                        $this->exts->log("Files found with the partial file name: " . implode(', ', $files));
                                    } else {
                                        try {
                                            $this->exts->log('click -> ' . $selector);
                                            $invoice_link_ele[0]->click();
                                            sleep(30);
                                        } catch (\Exception $exception) {
                                            $this->exts->log('ERROR in click. Could not locate element - ' . $selector);
                                            $this->exts->log(print_r($exception, true));
                                        }

                                        // Wait for completion of file download
                                        $this->exts->wait_and_check_download('pdf');


                                        // find new saved file and return its path
                                        $downloaded_file = $this->exts->find_saved_file('pdf', '');
                                        $this->exts->log('downloaded file name ' . basename($downloaded_file));
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $this->exts->log('downloaded file name ' . basename($downloaded_file));
                                            $invoice_number = basename($downloaded_file, '.pdf');
                                            $this->exts->new_invoice($invoice_number, "", "", $downloaded_file);
                                        }
                                    }
                                } else {
                                    try {
                                        $this->exts->log('click -> ' . $selector);
                                        $invoice_link_ele[0]->click();
                                        sleep(30);
                                    } catch (\Exception $exception) {
                                        $this->exts->log('ERROR in click. Could not locate element - ' . $selector);
                                        $this->exts->log(print_r($exception, true));
                                    }

                                    // Wait for completion of file download
                                    $this->exts->wait_and_check_download('pdf');

                                    // find new saved file and return its path
                                    $downloaded_file = $this->exts->find_saved_file('pdf', '');
                                    $this->exts->log('downloaded file name ' . basename($downloaded_file));
                                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                        $this->exts->log('downloaded file name ' . basename($downloaded_file));
                                        $invoice_number = basename($downloaded_file, '.pdf');
                                        $this->exts->new_invoice($invoice_number, "", "", $downloaded_file);
                                    }
                                }
                            } else {
                                $this->exts->log("Onclick attribute not found");
                            }
                        }
                    }
                }
                if ((int)@$this->exts->document_counter == 0) {
                    $this->exts->no_invoice();
                }
            } else {
                $this->exts->no_invoice();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception process processInvoicePage " . $exception->getMessage());
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent("a[data-target='#zahlungsaufforderungen-panel']", 30);
        $this->exts->click_by_xdotool("a[data-target='#zahlungsaufforderungen-panel']");
        sleep(3);
        $this->exts->waitTillPresent('table#beitragskonto\:rechnungen tbody tr', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table#beitragskonto\:rechnungen tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(2) a', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('td:nth-child(2) a', $row)->getAttribute('href');
                $invoiceName = '';
                $invoiceAmount =  '';
                $invoiceDate =  $this->exts->extract('td:nth-child(1)', $row);

                $downloadBtn = $this->exts->querySelector('td:nth-child(2) a', $row);

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
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            // $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

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


    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {

            if ($this->exts->getElement("ul.nav.nav-bs > li:last-child") != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
