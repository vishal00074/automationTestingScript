<?php // migrated and handle empty invoices name added pagination logic updated invoice button selector

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

    // Server-Portal-ID: 26392 - Last modified: 08.02.2024 13:48:40 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'http://kundenportal.berlin-recycling.de/';
    public $loginUrl = 'http://kundenportal.berlin-recycling.de/';
    public $invoicePageUrl = 'URL_url_to_invoice_page';

    public $username_selector = 'form input#Username, form input#userField';
    public $password_selector = 'form input#Password, form input#passwordField';
    public $remember_me_selector = '#PortalRemLogin';
    public $submit_login_selector = 'form #textBtnGo, button#LogBtn';

    public $check_login_failed_selector = '#PortalInfoCaption';
    public $check_login_success_selector = 'a[onclick*="logout"]';

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
        sleep(15);
        if ($this->exts->exists('div.cc-compliance')) {
            $this->exts->moveToElementAndClick('div.cc-compliance');
            sleep(3);
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->checkFillLogin();
            sleep(15);
        }

        // then check user logged in or not
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
        // 	$this->exts->log('Waiting for login...');
        // 	sleep(5);
        // }
        if ($this->exts->exists($this->check_login_success_selector)) {
            sleep(10);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->log('Clicking on div[onclick*="RECHNUNG"] to navigate to invoice page');

            if ($this->exts->querySelector('div.Dashboard div.content > div:nth-child(2) > div > div:nth-child(3) > div.row > div:nth-child(2) h3') != null) {
                $this->exts->execute_javascript('document.querySelector("div.Dashboard div.content > div:nth-child(2) > div > div:nth-child(3) > div.row > div:nth-child(2) h3").click();');
                sleep(10);
            }

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed url: ' . $this->exts->getUrl());

            $this->exts->loginFailure();
        }
    }

    private function checkFillLogin()
    {
        sleep(3);
        $this->exts->log(__FUNCTION__);
        $this->exts->capture(__FUNCTION__);
        if ($this->exts->exists($this->password_selector)) {
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
            $this->exts->waitTillPresent('div.notyf__message');
            if ($this->exts->exists("div.notyf__message")) {
                $this->exts->log("Login Failure : " . $this->exts->extract("div.notyf__message"));
                if (strpos(strtolower($this->exts->extract("div.notyf__message")), 'incorrect login data') !== false || strpos(strtolower($this->exts->extract("div.notyf__message")), '503: service unavailable') !== false) {
                    $this->exts->loginFailure(1);
                }
            }

            sleep(10);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(10);
        for ($wait_count = 1; $wait_count <= 10 && !$this->exts->exists('table#HeadDatasetTables tbody tr, table#HeadDataTables tbody tr'); $wait_count++) {
            $this->exts->log('Waiting for invoice...');
            sleep(5);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        // sorting invoice date desc
        for ($sort = 0; $sort < 3 && $this->exts->exists('table:not([id]) thead th:not([class="sorting_desc"])[class*="sorting"][aria-label*="Belegdatum"][aria-controls], .dataTables_scrollHead th.sorting_desc'); $sort++) {
            $this->exts->moveToElementAndClick('table:not([id]) thead th:not([class="sorting_desc"])[class*="sorting"][aria-label*="Belegdatum"][aria-controls], .dataTables_scrollHead th.sorting:not([class*="control_custom"])');
            sleep(8);
        }
        if ($this->exts->exists('table#HeadDatasetTables tbody tr')) {
            $rows_count = count($this->exts->getElements('table#HeadDatasetTables tbody tr'));
            for ($row_index = 0; $row_index < $rows_count; $row_index++) {
                $row = $this->exts->getElements('table#HeadDatasetTables tbody tr')[$row_index];
                if ($row == null) continue;

                $tags = $this->exts->getElements('td', $row);
                if (count($tags) >= 5 && $this->exts->exists('a.action-button[onclick*="actionClick"]', $tags[4])) {
                    $tempNode = $this->exts->getElement('a.action-button[onclick*="actionClick"]', $tags[4]);
                    $invoiceName = trim($this->exts->getElement('a.control_row_data', $tags[0])->getAttribute('innerText'));
                    $invoiceDate = trim($tags[2]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';
                    $invoiceUrl = '#invoiceUrl_' . $invoiceName;
                    $this->exts->executeSafeScript('arguments[0].setAttribute("id", "invoiceUrl_' . $invoiceName . '")', [$tempNode]);

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

            $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            $date_from = $this->restrictPages == 0 ? strtotime('-2 years') : strtotime('-1 years');
            $this->exts->log("Download invoices from Date:" . date('m', $date_from) . '/' . date('Y', $date_from));

            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $invoiceFilePath = $this->exts->config_array['download_folder'] . $invoiceFileName;
                if (file_exists($invoiceFilePath)) {
                    $this->exts->log('------------Skip download:::File existed--------------');
                    continue;
                }

                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $invoice['invoiceDate'] == '' ? $this->exts->parse_date($invoice['invoiceDate'], 'm/d/Y', 'Y-m-d') : $invoice['invoiceDate'];
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $parsed_date = $invoice['invoiceDate'];
                if ($parsed_date != null && $parsed_date != '') {
                    if (
                        date('Y', $date_from) > date('Y', strtotime($parsed_date))
                        || (date('m', $date_from) > date('m', strtotime($parsed_date))
                            && date('Y', $date_from) == date('Y', strtotime($parsed_date)))
                    ) {
                        $this->exts->log('Invoice files are too old, skipping download');
                        continue;
                    }
                }

                $downloaded_file = $this->exts->click_and_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }

            if ($paging_count < 20 && $this->exts->exists('div.dataTables_paginate a.next:not(.disabled)')) {
                $paging_count++;
                $this->exts->moveToElementAndClick('div.dataTables_paginate a.next:not(.disabled)');
                sleep(5);
                $this->processInvoices($paging_count);
            }
        } else { // page has changed 27/01/2023
            $rows = count($this->exts->getElements('table#HeadDataTables tbody tr'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->getElements('table#HeadDataTables tbody tr')[$i];
                $tags = $this->exts->getElements('td', $row);
                if (count($tags) >= 6 && $this->exts->getElement('a[onclick*="actionClick"][onclick*="PRINT"]', $tags[5]) != null) {
                    $this->isNoInvoice = false;
                    $download_button = $this->exts->getElement('a[onclick*="actionClick"][onclick*="PRINT"]', $tags[5]);
                    $invoiceName = trim($tags[0]->getAttribute('innerText'));
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceDate = trim($tags[2]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EURO';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
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
                        sleep(5);

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



            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

            $pagiantionSelector = 'ul.pagination li.next';
            if ($restrictPages == 0) {
                if ($paging_count < 50 && $this->exts->querySelector($pagiantionSelector) != null && $this->exts->querySelector('ul.pagination li.next.disabled') == null) {
                    $this->exts->click_by_xdotool($pagiantionSelector);
                    sleep(7);
                    $paging_count++;
                    $this->processInvoices($paging_count);
                }
            } else {
                if ($paging_count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null  && $this->exts->querySelector('ul.pagination li.next.disabled') == null) {
                    $this->exts->click_by_xdotool($pagiantionSelector);
                    sleep(7);
                    $paging_count++;
                    $this->processInvoices($paging_count);
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
