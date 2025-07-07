<?php // updated login success selector and download code. added date pagination logic

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

    // Server-Portal-ID: 65486 - Last modified: 23.01.2024 14:11:26 UTC - User: 1

    public $baseUrl = 'https://rf24.de/';
    public $loginUrl = 'https://rf24.de/login';
    public $invoicePageUrl = 'https://rf24.de/mein-konto/vorgaenge';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[data-qa="login-button"]';

    public $check_login_failed_selector = 'div[data-qa="login-form-error-message"] span.loginForm__errorMessageLabel';
    public $check_login_success_selector = 'a[href="abmelden.htm"], a[href*="/mein-konto/"]';

    public $isNoInvoice = true;
    public $restrictPages = 3;
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

        if ($this->exts->getElement('button[data-qa="cookie-banner-accept-all"]') != null) {
            $this->exts->moveToElementAndClick('button[data-qa="cookie-banner-accept-all"]');
            sleep(2);
        }

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->getElement('button[data-qa="cookie-banner-accept-all"]') != null) {
                $this->exts->moveToElementAndClick('button[data-qa="cookie-banner-accept-all"]');
                sleep(2);
            }
            $this->checkFillLogin();
            sleep(20);
        }

        // then check user logged in or not
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
        // 	$this->exts->log('Waiting for login...');
        // 	sleep(5);
        // }
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            $this->exts->moveToElementAndClick('.modal__closeBtn');
            sleep(2);
            // Open invoices url and download invoice
            $this->exts->moveToElementAndClick('a[href="belege.htm"]');
            sleep(14);

            $this->changeSelectbox('select[name="belegart"]', '2');
            sleep(15);


            $this->exts->moveToElementAndClick('input#sucheBtn');
            sleep(14);

            $this->switchToFrame('[src="belege_liste.htm"]');
            sleep(3);

            $this->processBills();

            $this->exts->switchToDefault();
            sleep(2);

            $this->changeSelectbox('select[name="belegart"]', '4');
            sleep(15);


            $this->exts->moveToElementAndClick('input#sucheBtn');
            sleep(14);

            $this->switchToFrame('[src="belege_liste.htm"]');
            sleep(3);

            $this->processCredits();

            $this->exts->switchToDefault();
            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
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

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->exts->waitTillPresent($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $option = $option_value;
            $this->exts->click_by_xdotool($select_box);
            sleep(2);
            $optionIndex = $this->exts->executeSafeScript('
			const selectBox = document.querySelector("' . $select_box . '");
			const targetValue = "' . $option_value . '";
			const optionIndex = [...selectBox.options].findIndex(option => option.value === targetValue);
			return optionIndex;
		');
            $this->exts->log($optionIndex);
            sleep(1);
            for ($i = 0; $i < $optionIndex; $i++) {
                $this->exts->log('>>>>>>>>>>>>>>>>>> Down');
                // Simulate pressing the down arrow key
                $this->exts->type_key_by_xdotool('Down');
                sleep(1);
            }
            $this->exts->type_key_by_xdotool('Return');
        } else {
            $this->exts->log('Select box does not exist');
        }
    }

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    private function processBills()
    {
        $this->exts->capture("4-bills-page");
        $invoices = [];

        $rows_len = count($this->exts->getElements('div#liste_breit table.tb_sg_body_rf tbody tr'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('div#liste_breit table.tb_sg_body_rf tbody tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 4 && $this->exts->getElement('a[href*="getpos"]', $tags[0]) != null) {
                $download_button = $this->exts->getElement('a[href*="getpos"]', $tags[0]);
                $invoiceName = trim($this->getInnerTextByJS('a[href*="getpos"]', $tags[0]));
                $invoiceDate = trim($this->getInnerTextByJS($tags[2]));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[3]))) . ' EUR';

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(9);

                $this->exts->switchToDefault();
                sleep(2);

                $handle = $this->exts->current_chrome_tab;

                $this->exts->moveToElementAndClick('div#div_pdf');
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }

                $tab = $this->exts->switchToTab(end($this->exts->get_all_tabs()));
                sleep(2);

                $this->exts->closeTab($tab);
                sleep(2);

                $this->exts->switchToTab($handle);
                sleep(2);

                $this->exts->moveToElementAndClick('input#sucheBtn');
                sleep(7);

                $this->switchToFrame('[src="belege_liste.htm"]');
                sleep(1);
            }
        }
    }

    private function processCredits()
    {
        $this->exts->capture("4-credits-page");
        $invoices = [];

        $rows_len = count($this->exts->getElements('div#liste_breit table.tb_sg_body_rf tbody tr'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('div#liste_breit table.tb_sg_body_rf tbody tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 4 && $this->exts->getElement('a[href*="getpos"]', $tags[0]) != null) {
                $download_button = $this->exts->getElement('a[href*="getpos"]', $tags[0]);
                $invoiceName = trim($this->getInnerTextByJS('a[href*="getpos"]', $tags[0]));
                $invoiceDate = trim($this->getInnerTextByJS($tags[2]));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[3]))) . ' EUR';

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(9);

                $this->exts->switchToDefault();
                sleep(2);

                $handle = $this->exts->current_chrome_tab;

                $this->exts->moveToElementAndClick('div#div_pdf');
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }

                $tab = $this->exts->switchToTab(end($this->exts->get_all_tabs()));
                sleep(2);

                $this->exts->closeTab($tab);
                sleep(2);

                $this->exts->switchToTab($handle);
                sleep(2);

                $this->exts->moveToElementAndClick('input#sucheBtn');
                sleep(7);

                $this->switchToFrame('[src="belege_liste.htm"]');
                sleep(1);
            }
        }
    }

    private function dateRange()
    {
        $this->exts->waitTillPresent('input[name="created_after"]');
        $this->exts->capture('select-date-range');

        $selectDate = new DateTime();
        $currentDate = $selectDate->format('Y-m-d');

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = $selectDate->format('Y-m-d');
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('Y-m-d');
            $this->exts->capture('date-range-3-months');
        }

        // Proper JavaScript string escaping
        $url = 'https://rf24.de/mein-konto/vorgaenge?creationDate=>' . $formattedDate . ';<' . $currentDate . '';
        $this->exts->openUrl($url);
        sleep(10);
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function processInvoices($count = 1)
    {
        sleep(20);
        $this->dateRange();
        $this->exts->moveToElementAndClick('.modal__closeBtn');
        sleep(2);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $paths = explode('/', $this->exts->getUrl());
        $currentDomainUrl = $paths[0] . '//' . $paths[2];
        $rows = $this->exts->getElements('[data-qa="transactions-table"] table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 11 && $this->exts->getElement('a[href*="/mein-konto/vorgang"]', $tags[10]) != null) {
                if (stripos($this->getInnerTextByJS($tags[1]), "Rechnung") === false) {
                    continue;
                }
                $invoiceUrl = $this->exts->getElement('a[href*="/mein-konto/vorgang"]', $tags[10])->getAttribute("href");
                $invoiceName = trim($this->getInnerTextByJS($tags[0]));

                if (strpos($invoiceUrl, $currentDomainUrl) === false && strpos($invoiceUrl, 'http') === false) {
                    $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                }

                $invoiceDate = trim($this->getInnerTextByJS($tags[3]));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[9]))) . ' EUR';

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
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoice['invoiceName']);
            } else {
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $tab =  $this->exts->openNewTab($invoice['invoiceUrl']);
                sleep(2);
                $this->waitFor('button[data-qa="document-print"]');

                $downloaded_file = $this->exts->click_and_print('button[data-qa="document-print"]', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->exts->closeTab($tab);
                sleep(2);
            }
        }
        sleep(5);
        if ($this->exts->querySelector('button[data-qa="pagination-next-page"]:not(:disabled)') != null && $count < 50) {
            $this->exts->moveToElementAndClick('button[data-qa="pagination-next-page"]:not(:disabled)');
            sleep(5);
            $this->processInvoices();
            $count++;
        }
    }

    function getInnerTextByJS($selector_or_object, $parent = null)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
            return;
        }
        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $element = $this->exts->getElement($selector_or_object, $parent);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
