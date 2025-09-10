<?php // updated download code added switchtoframe custom function

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

    // Server-Portal-ID: 32212 - Last modified: 10.07.2025 14:47:33 UTC - User: 1

    public $baseUrl = 'https://www.yello.de/mein-yello/anmeldung';
    public $loginUrl = 'https://www.yello.de/mein-yello/anmeldung';
    public $invoicePageUrl = 'https://mein.yello.de';

    public $username_selector = 'form#loginForm input[name="Benutzername"], input#emailinput';
    public $password_selector = 'form#loginForm input[name="Passwort"], input#passwordinput';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form#loginForm button.login-form__anmelden-button, form#loginForm button[type="submit"], button#loginbtn';

    public $check_login_failed_selector = '.prozessError, form#loginForm .validation-message';
    public $check_login_success_selector = 'form[action*="/logout"], a[href="/logout"], dl[class*="vertrag-location"], span.header-user-profile__initials';
    public $download_all_documents = '0';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->download_all_documents = isset($this->exts->config_array["download_all_documents"]) ? (int)@$this->exts->config_array["download_all_documents"] : 0;
        $this->exts->log('download_all_documents: ' . $this->download_all_documents);

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
            $this->exts->moveToElementAndClick('#auth-button-login-button');
            if ($this->exts->exists('a.js_cookie-decline,button#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('a.js_cookie-decline,button#onetrust-accept-btn-handler');
                sleep(15);
            }

            if ($this->exts->exists('a[href="/login?signup=false"], button#login-button')) {
                $this->exts->moveToElementAndClick('a[href="/login?signup=false"], button#login-button');
                sleep(15);
            }

            $this->checkFillLogin();
            sleep(20);
        }

        if ($this->exts->exists('a.js_cookie-decline,button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('a.js_cookie-decline,button#onetrust-accept-btn-handler');
            sleep(15);
        }


        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('button[data-click-id="content.link.nutzerkontoumstellen.anfrage.spaeter"]')) {
                $this->exts->moveToElementAndClick('button[data-click-id="content.link.nutzerkontoumstellen.anfrage.spaeter"]');
                sleep(15);
            }

            $this->invoicePage();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract('div.flying-wrapper__error-message', null, 'innerText')), 'und dein passwort') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElement($this->check_login_failed_selector) != null) {
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
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);
            $this->checkFillRecaptcha();
            sleep(5);
            $this->check_solve_blocked_page();

            $this->exts->capture("2-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
            sleep(5);
            if (!$this->isValidEmail($this->username)) {
                $this->exts->loginFailure(1);
            }
            sleep(15);
            $this->checkFillRecaptcha();
            sleep(2);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    public function isValidEmail($email)
    {
        // Define the email pattern
        $pattern = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

        // Check if the email matches the pattern
        return preg_match($pattern, $email);
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


    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");

        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->check_exist_by_chromedevtool('div.captcha-container > div > div')) {
                $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
                sleep(10);

                $this->exts->click_by_xdotool('div.captcha-container > div > div', 130, 28);
                sleep(15);

                if (!$this->exts->check_exist_by_chromedevtool('div.captcha-container > div > div')) {
                    break;
                }
            } else {
                break;
            }
        }
    }

    private function invoicePage()
    {
        if ($this->exts->exists('a[href="/vertragskontoauswahl"]')) {
            $this->exts->moveToElementAndClick('a[href="/vertragskontoauswahl"]');
            sleep(15);
        }
        if ($this->exts->getElement('li.header-navigation__links-item a[href*="/vertrag"]') != null) {
            $this->exts->moveToElementAndClick('li.header-navigation__links-item a[href*="/vertrag"]');
            sleep(15);
        }

        //$this->exts->openUrl($this->invoicePageUrl);
        sleep(25);

        if ($this->exts->exists('a[href*="vertrag/"][data-testid="product-card"]')) {
            $contracts_sel = $this->exts->getElements('a[href*="vertrag/"][data-testid="product-card"]');
            $contracts = array();

            foreach ($contracts_sel as $contract_sel) {
                $contract_url = $contract_sel->getAttribute('href');
                array_push($contracts, $contract_url);
            }

            foreach ($contracts as $contract_url) {

                $this->exts->openUrl($contract_url);
                // sleep(15);
                $this->processInvoices();

                if ($this->download_all_documents == 1) {
                    $this->exts->moveToElementAndClick('nav#main-nav a[href*="/vertrag/"]');
                    $this->exts->moveToElementAndClick('button.js-accordion_expander[aria-controls="vertragsdokument-container"][aria-expanded="false"]');
                    $this->processAllDocuments();
                }
            }
        } else {
            if ($this->exts->getElement('li.header-navigation__links-item a[href*="/vertrag"]') != null) {
                $this->exts->moveToElementAndClick('li.header-navigation__links-item a[href*="/vertrag"]');
            }

            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();
            if ($this->exts->getElement('li.header-navigation__links-item a[href*="/vertrag"]') != null) {
                $this->exts->moveToElementAndClick('li.header-navigation__links-item a[href*="/vertrag"]');
                sleep(15);
            }

            if ($this->download_all_documents == 1) {
                $this->exts->moveToElementAndClick('nav#main-nav a[href*="/vertrag/"]');
                $this->exts->moveToElementAndClick('button.js-accordion_expander[aria-controls="vertragsdokument-container"][aria-expanded="false"]');
                $this->processAllDocuments();
            }
        }
    }

    private function processInvoices()
    {
        $this->exts->capture("4-invoices-page-processInvoices");
        $invoices = [];

        $rows = $this->exts->getElements('[data-testid="base-download-card-link"]');
        foreach ($rows as $row) {
            if ($row != null) {
                $invoiceUrl = $row->getAttribute("href");
                $invoiceName = trim(end(explode('/YELLO/', $invoiceUrl)));
                $invoiceDate = trim(explode(PHP_EOL, end(explode('vom', $row->getText())))[0]);
                $invoiceAmount = trim(end(explode(PHP_EOL, end(explode('vom', $row->getText())))));

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            // $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
            // $this->exts->log('Date parsed: '.$invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processMonthlyInvoice()
    {
        $this->exts->capture("4-invoices-page-processMonthlyInvoice");
        $invoices = [];

        $rows = $this->exts->getElements('a.rechnung-link');
        foreach ($rows as $row) {
            $invoiceUrl = $row->getAttribute("href");
            $invoiceName = trim(explode('&', end(explode('dokumentId=', $invoiceUrl)))[0]);
            $invoiceDate = trim($this->exts->extract('.rechnung-link__zeitraum', $row));
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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
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

    private function processAllDocuments()
    {
        $this->exts->capture("4-invoices-page-processAllDocuments");
        $invoices = [];
        $this->exts->log('-------------------------- process ALL documents');
        if ($this->exts->exists('iframe[src*="ekp-redirect"]')) {
            $this->switchToFrame('iframe[src*="ekp-redirect"]');
            $rows = $this->exts->getElements('div#vertragsdokument-container a.download-link-wrapper');
            foreach ($rows as $row) {
                $file_type = strtolower($this->exts->extract('.download-link-wrapper__doc-datatype', $row, 'innerHTML'));
                // $this->exts->log('-- File type: '.$file_type);
                if (strpos($file_type, 'pdf') !== false) {
                    $invoiceUrl = $row->getAttribute("href");

                    $parts = parse_url($invoiceUrl);
                    parse_str($parts['query'], $queryParams);

                    $invoiceName = $queryParams['dokumentId'];

                    $acc_number = trim(end(explode('/vertrag/', $this->exts->getUrl())));
                    $invoiceName = trim($acc_number . '_' . $invoiceName, ' ,_');

                    $invoiceDate = trim($this->exts->extract('.download-link-wrapper__date', $row));
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

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
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

            $this->exts->switchToDefault();
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
