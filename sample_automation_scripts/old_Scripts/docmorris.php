<?php //  migrated and handle empty invoices name

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673830/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // load cookies from file for desktop app
                if (!empty($this->exts->config_array["without_password"])) {
                    $this->exts->loadCookiesFromFile();
                }
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 62553 - Last modified: 19.09.2024 13:53:52 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.docmorris.de/meindocmorris/';
    public $loginUrl = 'https://www.docmorris.de/meindocmorris/anmelden';
    public $invoicePageUrl = 'https://www.docmorris.de/meindocmorris/bestellungen';

    public $username_selector = 'input#username, input[data-testid="username"]';
    public $password_selector = 'input#password, input[data-testid="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"][aria-label="Anmelden"]';

    public $check_login_failed_selector = 'div[data-testid="errorMessageText"]';
    public $check_login_success_selector = 'a[href*="logout"], [data-testid="logout-button"]';

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
        if (
            $this->exts->getElement($this->check_login_success_selector) == null
            || ($this->exts->getElement($this->check_login_success_selector) != null && $this->exts->getElement($this->password_selector) != null)
        ) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->exts->moveToElementAndClick('.usemaxWrapper div[id*="closebtn"]');
            sleep(1);
            $this->exts->moveToElementAndClick('.usemaxWrapper div[id*="closebtn"]');

            $this->check_solve_blocked_page();
            sleep(10);
            $this->checkFillLogin();
            sleep(20);
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->moveToElementAndClick('button[data-testid="close-modal-title-button"]');
            sleep(1);
            $this->processInvoices();
            $this->processInvoices1();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->getInnerTextByJS($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->getInnerTextByJS('div.bg-strong-red')), 'bitte versuchen sie es erneut') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function check_solve_blocked_page()
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

    private function checkFillLogin()
    {
        if ($this->exts->exists('span#cmpwelcomebtnyes a')) {
            $this->exts->moveToElementAndClick('span#cmpwelcomebtnyes a');
            sleep(5);
        }
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
            $this->checkFillRecaptcha();
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->executeSafeScript('
                    if(document.querySelector("[data-callback]") != null){
                        return document.querySelector("[data-callback]").getAttribute("data-callback");
                    }

                    var result = ""; var found = false;
                    function recurse (cur, prop, deep) {
                        if(deep > 5 || found){ return;}console.log(prop);
                        try {
                            if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                            if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                            } else { deep++;
                                for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                            }
                        } catch(ex) { console.log("ERROR in function: " + ex); return; }
                    }

                    recurse(___grecaptcha_cfg.clients[0], "", 0);
                    return found ? "___grecaptcha_cfg.clients[0]." + result : null;
                ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
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

    private function processInvoices()
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div.l-orders-inner table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElements('a[href*="document"]', $tags[5]) != null) {
                $invoiceUrlSelecors = $this->exts->getElements('a[href*="document"]', $tags[5]);
                foreach ($invoiceUrlSelecors as $key => $invoiceUrlSelecor) {
                    $invoiceUrlSelecor_name = trim($this->exts->getElements('a[href*="document"]', $tags[5])[$key]->getAttribute('innerText'));
                    if (strpos($invoiceUrlSelecor_name, 'Rechnung') !== false || strpos($invoiceUrlSelecor_name, 'Invoice') !== false) {
                        $invoiceUrl = $invoiceUrlSelecor->getAttribute("href");
                        break;
                    }
                }
                // $invoiceUrl = $this->exts->getElements('a[href*="document"]', $tags[5])[0]->getAttribute("href");
                $invoiceName = trim(str_replace('#', '', $tags[1]->getText()));
                $invoiceDate = trim($tags[0]->getText());
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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
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

    private function processInvoices1()
    {
        $this->exts->capture("4-invoices-page-1");
        $invoices = [];
        $rows = $this->exts->getElements('[data-testid="mein-docmorris-orders"] section');
        foreach ($rows as $row) {

            $str = $this->exts->extract('header', $row, 'innerText');
            $invoiceDate = explode(PHP_EOL, $str);
            $invoiceName = "";
            if (count($invoiceDate) > 1) {
                $invoiceName = end($invoiceDate);
                $invoiceName = explode(": ", $invoiceName);
                $invoiceName = end($invoiceName);
                $invoiceName = str_replace(")", "", $invoiceName);
                $invoiceDate = array_shift($invoiceDate);
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                $this->exts->log("-------------------");
                $this->exts->log("InvoiceName: " . $invoiceName);
                $this->exts->log("InvoiceDate: " . $invoiceDate);
            }
            $modal_button = $this->exts->getElement('main button', $row);
            if ($modal_button != null) {
                try {
                    $this->exts->log('Click modal_button button');
                    $modal_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click modal_button button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$modal_button]);
                }
                $this->isNoInvoice = false;
            }
            sleep(1);
            // $download_button = $this->getElementByText('#modal-order-documents [data-testid="modal-container"] main button', "Rechnung", null, false);
            $download_button = $this->exts->queryXpath(".//button[.//text()[normalize-space(.)='Rechnung']]");
            if ($download_button != null) {
                try {
                    $this->exts->log('Click download_button button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download_button button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(15);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, "", $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else {
                $this->exts->log("Not Found Download button");
            }

            $this->exts->moveToElementAndClick('#modal-order-documents [data-testid="modal-container"] [data-testid="modal-footer"] button');
            sleep(2);
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'GastroHero', '2673830', 'cmVjaG51bmdlbkBsdXVjLWV2ZW50LmRl', 'I1R1ZXJrZW5zdHJhc3NlMTc=');
$portal->run();
