<?php // replace exists to isExists function 

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

    // Server-Portal-ID: 33469 - Last modified: 26.06.2025 14:37:59 UTC - User: 1

    public $baseUrl = 'https://login.smoobu.com/login';
    public $loginUrl = 'https://login.smoobu.com/';
    public $invoicePageUrl = 'https://login.smoobu.com/billing/invoices';
    public $outgoingInvoicePageUrl = 'https://login.smoobu.com/de/payment/invoice-overview';

    public $username_selector = 'form input#user_email, form [name="email"]';
    public $password_selector = 'form input#user_password, form [name="password"]';
    public $submit_login_selector = 'form button[type="submit"]';

    public $check_login_failed_selector = 'form div.alert-danger, [role="alert"]';
    public $check_login_success_selector = '#topNavigation button:has(svg), #navbar-top li.user-dropdown';

    public $isNoInvoice = true;
    public $outgoingInvoice = 0;
    public $restrictPages = 3;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->outgoingInvoice = isset($this->exts->config_array["outgoing_invoice"]) ? (int)@$this->exts->config_array["outgoing_invoice"] : 0;

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(3);
        // $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
        for ($i = 0; $i < 20 && $this->exts->getElement($this->username_selector) == null && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
            sleep(1);
        }
        if ($this->isExists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->log('Closed cookie cookies notice');
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(2);
        }
        $this->exts->capture('1-init-page');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
            // $this->exts->waitTillAnyPresent(['div.MuiAlert-message', $this->check_login_success_selector, 'input[id*="code"]', 'form[name="user"] .MuiAlert-message']);
            for ($i = 0; $i < 20 && $this->exts->getElements('div.MuiAlert-message, input[id*="code"], form[name="user"] .MuiAlert-message') == null && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
                sleep(1);
            }
            // error recaptcha, retry
            if (stripos($this->exts->extract('div.MuiAlert-message'), 'Google Recaptcha failed') !== false) {
                $this->exts->openUrl($this->loginUrl);
                // $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
                for ($i = 0; $i < 20 && $this->exts->getElement($this->username_selector) == null && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
                    sleep(1);
                }
                $this->checkFillLogin();
                // $this->exts->waitTillAnyPresent(['div.MuiAlert-message', $this->check_login_success_selector, 'input[id*="code"]', 'form[name="user"] .MuiAlert-message']);
                for ($i = 0; $i < 20 && $this->exts->getElements('div.MuiAlert-message, input[id*="code"], form[name="user"] .MuiAlert-message') == null && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
                    sleep(1);
                }
            }
            $this->checkFill2FAConfirmLogin();
            $this->checkFillTwoFactor();
            // $this->exts->waitTillPresent($this->check_login_success_selector);
            for ($i = 0; $i < 20 && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
                sleep(1);
            }
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // If outgoing_invoice == 1, download Outgoing_invoice
            if ($this->outgoingInvoice == 1) {
                $this->exts->openUrl($this->outgoingInvoicePageUrl);
                $this->setDateSelector();
                sleep(5);
                $this->processOutgoingInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos($this->exts->extract($this->check_login_failed_selector), 'password') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        for ($i = 0; $i < 20 && $this->exts->getElement($this->password_selector) == null; $i++) {
            sleep(1);
        }
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->checkFillRecaptcha();
            sleep(3);
            $this->exts->capture("2-login-page-filled");
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
        if ($this->isExists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->processRecaptcha(trim($this->exts->getUrl()), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
                    if(document.querySelector("[data-callback]") != null){
                        document.querySelector("[data-callback]").getAttribute("data-callback");
                    }

                    var result = ""; var found = false;
                    function recurse (cur, prop, deep) {
                        if(deep > 5 || found){ }console.log(prop);
                        try {
                            if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ }
                            if(prop.indexOf(".callback") > -1){result = prop; found = true; 
                            } else { deep++;
                                for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                            }
                        } catch(ex) { console.log("ERROR in function: " + ex); }
                    }

                    recurse(___grecaptcha_cfg.clients[0], "", 0);
                    found ? "___grecaptcha_cfg.clients[0]." + result : null;
                ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    public function processRecaptcha($base_url, $google_key = '', $fill_answer = true)
    {
        $this->exts->recaptcha_answer = '';
        $this->exts->log("--Google Re-Captcha--");
        if (!empty($this->exts->config_array['recaptcha_shell_script'])) {
            $cmd = $this->exts->config_array['recaptcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --GOOGLE_KEY::" . urlencode($google_key) . " --BASE_URL::" . urlencode($base_url);
            $this->exts->log('Executing command : ' . $cmd);
            exec($cmd, $output, $return_var);
            $this->exts->log('Command Result : ' . print_r($output, true));

            if (!empty($output)) {
                $recaptcha_answer = '';
                foreach ($output as $line) {
                    if (stripos($line, "RECAPTCHA_ANSWER") !== false) {
                        $result_codes = explode("RECAPTCHA_ANSWER:", $line);
                        $recaptcha_answer = $result_codes[1];
                        break;
                    }
                }

                if (!empty($recaptcha_answer)) {
                    if ($fill_answer) {
                        $answer_filled = $this->exts->execute_javascript(
                            "document.getElementById(\"g-recaptcha-response\").innerHTML = arguments[0];return document.getElementById(\"g-recaptcha-response\").innerHTML;",
                            [$recaptcha_answer]
                        );
                        $this->exts->log("recaptcha answer filled - " . $answer_filled);
                    }

                    $this->exts->recaptcha_answer = $recaptcha_answer;

                    return true;
                }
            }
        }

        return false;
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[id*="code"]';
        $two_factor_message_selector = '#twoFactorForm form > div >div > p';
        $two_factor_submit_selector = 'button[type="submit"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->getElements($two_factor_selector);
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));
                        // $code_input->sendKeys($resultCodes[$key]);
                        $this->exts->moveToElementAndType($code_input, $resultCodes[$key]);
                        $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                    } else {
                        $this->exts->log('"checkFillTwoFactor: Have no char for input #' . $code_input->getAttribute('id'));
                    }
                }
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(5);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkFill2FAConfirmLogin()
    {
        $two_factor_message_selector = 'form[name="user"] .MuiAlert-message';
        if ($this->exts->getElement($two_factor_message_selector) != null && strpos(strtolower($this->exts->extract($two_factor_message_selector)), 'confirm your login') !== false) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = str_replace('Please check your inbox and click the link to log in', '', $this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' Pls copy that link then paste here';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Open url: ." . $two_factor_code);
                $this->exts->openUrl($two_factor_code);
                sleep(25);
                $this->exts->capture("after-open-url-two-factor");
            } else {
                $this->exts->log("Not received two factor code");
            }
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
        // $this->exts->waitTillPresent('table > tbody > tr a[href*="/cb-downloads"]');
        for ($i = 0; $i < 20 && $this->exts->getElements('table > tbody > tr a[href*="/cb-downloads"]') == null; $i++) {
            sleep(1);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 2 && $this->exts->getElement('a[href*="/cb-downloads"]', $tags[0]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/cb-downloads"]', $tags[0])->getAttribute("href");
                $invoiceName = explode(
                    '.pdf',
                    array_pop(explode('invoice/', $invoiceUrl))
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
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function setDateSelector()
    {
        // $this->exts->waitTillPresent('div[id="invoiceTable_filter"] select[id="from"]');
        for ($i = 0; $i < 20 && $this->exts->getElement('div[id="invoiceTable_filter"] select[id="from"]') == null; $i++) {
            sleep(1);
        }
        $currentYear = date('Y');
        $currentMonth = date('m');
        $restrictPages  = 0;

        if ($restrictPages == 0) {
            // If restrictPages is 0, download data for 2 years
            $from_date = $currentMonth . "/" . ($currentYear - 2);
            $to_date = $currentMonth . "/" . $currentYear;
            $this->exts->log("------------ " . $from_date);
        } else {
            // If restrictPages is not 0, download data for the last 6 months
            $from_date = date('m/Y', strtotime('-6 months'));
            $to_date = date('m/Y');
        }

        $from_date_dropdown = $this->exts->getElement('div[id="invoiceTable_filter"] select[id="from"]');
        $this->exts->log('from_date_dropdown: ' . $from_date);
        $this->exts->execute_javascript('
            var select = arguments[0];
            var option = select.querySelector("option[value=\'' . $from_date . '\']");
            if (option) {
                option.selected = true;
                select.dispatchEvent(new Event("change"));
            } else {
                console.log("Option with value ' . $from_date . ' not found.");
            }
        ', [$from_date_dropdown]);
        sleep(2);

        $to_date_dropdown = $this->exts->getElement('div[id="invoiceTable_filter"] select[id="to"]');
        $this->exts->log('to_date_dropdown: ' . $to_date);
        $this->exts->execute_javascript('
            var select = arguments[0];
            var option = select.querySelector("option[value=\'' . $to_date . '\']");
            if (option) {
                option.selected = true;
                select.dispatchEvent(new Event("change"));
            } else {
                console.log("Option with value ' . $to_date . ' not found.");
            }
        ', [$to_date_dropdown]);
        sleep(2);
    }

    private function processOutgoingInvoices($paging_count = 1)
    {
        // $this->exts->waitTillPresent('table > tbody > tr a[href*="/downloadInvoice"]');
        for ($i = 0; $i < 20 && $this->exts->getElement('table > tbody > tr a[href*="/downloadInvoice"]') == null; $i++) {
            sleep(1);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        sleep(2);
        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            $this->exts->log('tag count: ' . count($tags));
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="/downloadInvoice"]', $tags[6]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/downloadInvoice"]', $tags[6])->getAttribute("href");
                $invoiceName = trim($tags[0]->getText());
                $invoiceDate = trim($tags[3]->getText());
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getText())) . ' EUR';

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

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        if (
            $this->restrictPages == 0 && $paging_count < 50 &&
            $this->exts->getElement('a#invoiceTable_next:not(.disabled)') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('a#invoiceTable_next:not(.disabled)');
            sleep(5);
            $this->processOutgoingInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
