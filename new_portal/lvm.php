<?php // 
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

    // Server-Portal-ID: 15179 - Last modified: 08.07.2025 14:55:18 UTC - User: 1

    // Script here
    public $baseUrl = 'https://www.lvm.de';
    public $loginUrl = 'https://www.lvm.de/account/app/ui/protected/uebersicht';
    public $invoicePageUrl = 'https://www.lvm.de/document/app/ui/protected/dokumente/uebersicht?continue=&selectedTab=tab-filter&tabPaneOpen=true';

    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div#error-message-login,span.error, form#kc-form-login div#input-error';
    public $check_login_success_selector = 'label[for=online-services-anmelden-tab],a[href*="logout"], a[onclick*="frameLogout()"], div#loginSubView-alert-danger-onlinedienst_logout';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->moveToElementAndClick('div.cookie-consent__container button[onclick*="onAllowAllTracking()"]');
        sleep(5);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            $this->checkFillLogin();
            sleep(5);

            $this->check_solve_cloudflare_page();
            $this->exts->waitTillAnyPresent(['input#code', 'input#phonenumber', $this->check_login_success_selector]);
            $this->checkFillPhoneNumber();

            $this->exts->waitTillAnyPresent(['input#code', $this->check_login_success_selector]);
            $this->checkFillTwoFactor();

            $this->exts->waitTillPresent('div.cookie-consent__container button[onclick*="onAllowAllTracking()"]');
            $this->exts->moveToElementAndClick('div.cookie-consent__container button[onclick*="onAllowAllTracking()"]');
            sleep(5);
        }


        $this->exts->moveToElementAndClick('form#kc-form-nutzungsbedingungen button[onclick="acceptNb()"]');
        $this->exts->waitTillPresent('button[onclick*="acceptEinwilligung"]');

        if ($this->exts->exists('button[onclick*="acceptEinwilligung"]')) {
            $this->exts->moveToElementAndClick('button[onclick*="acceptEinwilligung"]');
            sleep(15);
        }
        // then check user logged in or not
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            $cookie_element = $this->exts->executeSafeScript('return document.querySelector("#cmpwrapper").shadowRoot.querySelector("#cmpbox");');

            if ($cookie_element !== null) {
                $this->exts->executeSafeScript('document.querySelector("#cmpwrapper").shadowRoot.querySelector(".cmpboxbtn.cmpboxbtnyes.cmptxt_btn_yes").click();');
                sleep(3);
            }
            $this->exts->moveToElementAndClick('a[href*="alledokumente"], a[href*="/dokumente/uebersicht"]');
            sleep(15);

            $search_el = $this->exts->getElement('//a[contains(@id,"varied-ctrl-tabs_control-item")]/span[contains(text(),"Suche")]', null, 'xpath');
            if ($search_el != null) {
                try {
                    $this->exts->log('Click search_el');
                    $search_el->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click search_el by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$search_el]);
                }
                sleep(15);
            }

            if ($this->exts->exists('#dateinamsuche')) {
                $this->exts->moveToElementAndType('#dateinamsuche', 'Rechnung');
            } else {
                $this->exts->moveToElementAndType('#searchText', 'Rechnung');
            }

            if ($this->exts->exists('button#tab-filter')) {
                if ($this->restrictPages == 0) {
                    $startDate = date('d-m-Y', strtotime('-3 years'));
                } else {
                    $startDate = date('d-m-Y', strtotime('-3 months'));
                }
                $currentDate = date('d-m-Y');
                $this->exts->click_by_xdotool('#selectedStart');
                sleep(1);
                $this->exts->type_text_by_xdotool($startDate);
                $this->exts->click_by_xdotool('#selectedEnd');
                sleep(1);
                $this->exts->type_text_by_xdotool($currentDate);
                sleep(2);
                $this->exts->click_element('button#submitFilterSettings');
            }

            sleep(1);

            $this->exts->moveToElementAndClick('#submitSearch');
            sleep(15);

            $this->processInvoices(1);
            $this->processRechnungDocuments(1);

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->querySelector($this->password_selector) != null) {
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
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'kennwort') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }

    private function checkFillPhoneNumber(): void
    {
        $selector = 'input#phonenumber';
        $message_selector = 'input#phonenumber + div span';
        $submit_selector = 'button#kc-login';

        while ($this->exts->getElement($selector) !== null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            // Collect and log the 2FA instruction messages
            $this->exts->two_factor_notif_msg_en = "Please enter your phone number. ";
            $messages = $this->exts->getElements($message_selector);
            foreach ($messages as $msg) {
                $this->exts->two_factor_notif_msg_en .= $msg->getAttribute('innerText') . "\n";
            }

            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            // Add retry message if this is the final attempt
            if ($this->exts->two_factor_attempts === 2) {
                $this->exts->two_factor_notif_msg_en .= ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de .= ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $code = trim($this->exts->fetchTwoFactorCode());
            if ($code === '') {
                $this->exts->log("2FA code not received");
                break;
            }

            $this->exts->log("checkFillTwoFactor: Entering 2FA code: " . $code);
            $this->exts->click_by_xdotool($selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($code);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($submit_selector);
            sleep(5); // Added: Ensure time for 2FA processing

            if ($this->exts->getElement($selector) === null) {
                $this->exts->log("Two factor solved");
                break;
            }

            $this->exts->two_factor_attempts++;
        }

        if ($this->exts->two_factor_attempts >= 3) {
            $this->exts->log("Two factor could not be solved after 3 attempts");
        }
    }

    private function checkFillTwoFactor(): void
    {
        $selector = 'input#code';
        $message_selector = 'span#alert-message-info + span';
        $submit_selector = 'button#kc-login';

        while ($this->exts->getElement($selector) !== null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            // Collect and log the 2FA instruction messages
            $this->exts->two_factor_notif_msg_en = "";
            $messages = $this->exts->getElements($message_selector);
            foreach ($messages as $msg) {
                $this->exts->two_factor_notif_msg_en .= $msg->getAttribute('innerText') . "\n";
            }

            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            // Add retry message if this is the final attempt
            if ($this->exts->two_factor_attempts === 2) {
                $this->exts->two_factor_notif_msg_en .= ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de .= ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $code = trim($this->exts->fetchTwoFactorCode());
            if ($code === '') {
                $this->exts->log("2FA code not received");
                break;
            }

            $this->exts->log("checkFillTwoFactor: Entering 2FA code: " . $two_factor_code);
            $this->exts->click_by_xdotool($selector);
            $this->exts->type_text_by_xdotool($code);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($submit_selector);
            sleep(5); // Added: Ensure time for 2FA processing

            if ($this->exts->getElement($selector) === null) {
                $this->exts->log("Two factor solved");
                break;
            }

            $this->exts->two_factor_attempts++;
        }

        if ($this->exts->two_factor_attempts >= 3) {
            $this->exts->log("Two factor could not be solved after 3 attempts");
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(25);
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
        // 	$this->exts->log('Waiting for invoice...');
        // 	sleep(5);
        // }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('#versicherungsdokumenteContent ul.teaser-list li.teaser-list--item');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('div.is--inline-item', $row);
            if (count($tags) >= 3 && $this->exts->querySelector('a[href*="handleOpenBuendelDetails"]', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="handleOpenBuendelDetails"]', $row)->getAttribute("href");
                $invoiceName = trim(explode('.pdf', $this->exts->querySelector('a[href*="handleOpenBuendelDetails"]', $row)->getAttribute("innerText"))[0]) . '_' . trim($tags[2]->getAttribute('innerText'));;
                // explode('&',
                // 	array_pop(explode('invoiceId=', $invoiceUrl))
                // )[0];
                $invoiceDate = trim($tags[2]->getAttribute('innerText'));
                $invoiceAmount = '';

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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);

            if ($this->exts->exists('a[href*="NJdownloadBuendelAsArchive"]')) {
                $pdfUrl = $this->exts->extract('a[href*="NJdownloadBuendelAsArchive"]', null, 'href');
                if ($this->exts->exists('a[href*="vertragsdetails"]')) {
                    $vertrags = $this->exts->extract('a[href*="selectedVsnr!"]', null, 'innerText');
                    $vertrags = trim(str_replace('.', '', explode(
                        'anzeigen',
                        array_pop(explode('Vertrag', $vertrags))
                    )[0]));
                    $invoice['invoiceName'] = $invoice['invoiceName'] . $vertrags;
                }
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

                $invoiceFileName = $invoice['invoiceName'] . '.zip';

                $downloaded_file = $this->exts->direct_download($pdfUrl, 'zip', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->log($downloaded_file);
                    // rename($downloaded_file, explode('.zip', $downloaded_file)[0] . '.pdf');
                    // $invoiceFileName = $invoice['invoiceName'].'.pdf';
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            count($rows) > 0 && $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->querySelector('.page-pagination .button-link:last-child a') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('.page-pagination .button-link:last-child a');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }

    private function processRechnungDocuments($paging_count = 1)
    {
        sleep(25);
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
        // 	$this->exts->log('Waiting for invoice...');
        // 	sleep(5);
        // }
        $this->exts->capture("4-processRechnungDocuments-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('a[href*="/dokumente/details"]');
        foreach ($rows as $row) {
            $invoiceUrl = $row->getAttribute("href");
            $invoiceName = explode(
                '&',
                array_pop(explode('documentId=', $invoiceUrl))
            )[0];
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


            // $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
            // $this->exts->log('Date parsed: '.$invoice['invoiceDate']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);

            if ($this->exts->exists('a[href*="/dokumente/details/part/download"]')) {
                $pdfUrl = $this->exts->extract('a[href*="/dokumente/details/part/download"]', null, 'href');
                if ($this->exts->exists('a[href*="vertragsdetails"]')) {
                    $vertrags = $this->exts->extract('a[href*="vertragsdetails"]', null, 'innerText');
                    $vertrags = trim(str_replace('.', '', explode(
                        'anzeigen',
                        array_pop(explode('Vertrag', $vertrags))
                    )[0]));
                    $invoice['invoiceName'] = $invoice['invoiceName'] . $vertrags;
                }
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

                $invoiceFileName = $invoice['invoiceName'] . '.pdf';

                $downloaded_file = $this->exts->direct_download($pdfUrl, 'pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->log($downloaded_file);
                    // rename($downloaded_file, explode('.zip', $downloaded_file)[0] . '.pdf');
                    // $invoiceFileName = $invoice['invoiceName'].'.pdf';
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->querySelector('.page-pagination .button-link:last-child a') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('.page-pagination .button-link:last-child a');
            sleep(5);
            $this->processRechnungDocuments($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
