<?php // I have removed unsued  solve_captcha_by_clicking and getCordinated function and click_recaptcha_point function

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

    // Server-Portal-ID: 24847 - Last modified: 16.04.2025 14:47:53 UTC - User: 1

    public $baseUrl = 'https://kunde.edenred.de/page/client-dashboard';
    public $loginUrl = 'https://kunde.edenred.de/page/client-dashboard/';
    public $invoicePageUrl = 'https://www.edenred-one.de/dashboard/history';
    public $username_selector = 'input#Username';
    public $password_selector = 'input#Password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type=submit]';
    public $check_login_failed_selector = 'li#invalid_credentials';
    public $check_login_success_selector = 'a[href*="/logout"]';
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_extensions();
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        $this->exts->waitTillPresent('button[id*=AllowAll]', 10);
        if ($this->exts->exists('button[id*=AllowAll]')) {
            $this->exts->click_element('button[id*=AllowAll]');
        }
        sleep(8);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent('button[id*=AllowAll]', 10);
            if ($this->exts->exists('button[id*=AllowAll]')) {
                $this->exts->click_element('button[id*=AllowAll]');
            }
            $this->checkFillLogin();
            sleep(20);
        }

        // then check user logged in or not
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
            $years = $this->exts->getElementsAttribute('select[ng-model*="yearsCurrent"] option:not([label*="-"])', 'label');

            if ($restrictPages != 0) {
                $years = array_slice($years, 0, $restrictPages);
            }

            foreach ($years as $year) {
                sleep(2);
                $this->exts->click_element('select[ng-model*="yearsCurrent"]');
                sleep(2);
                $this->exts->log('Clicking through year : ' . $year);

                $this->exts->executeSafeScript('  
            let selectBox = document.querySelector(\'select[ng-model*="yearsCurrent"]\');

            if (selectBox) {
                let newValue = ' . json_encode($year) . ';
                let angularScope = angular.element(selectBox).scope();

                angularScope.$apply(() => {
                    angularScope.oh.yearsCurrent = newValue;
                });

                angularScope.$apply(() => {
                    setTimeout(() => {
                        let event = new Event("change");
                        selectBox.dispatchEvent(event);
                    }, 500);
                });
            }
        ');
                sleep(5);
                $this->processInvoices();
            }


            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'unblock') !== false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'entsperren') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function checkFillLogin()
    {
        $this->exts->capture("checkFillLogin2");

        for ($wait = 0; $wait < 10 && $this->exts->executeSafeScript("return !!document.querySelector('input[id=\"Username\"]');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(5);
        }
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType('input[id="Username"]', $this->username);
        sleep(5);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType('input[id="Password"]', $this->password);
        sleep(5);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(5);

        $this->checkFillRecaptcha();
        sleep(5);
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick('button[id="login"]');

        sleep(2);
        $this->checkFillTwoFactor();
    }


    private function disable_extensions()
    {
        $this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
        sleep(2);
        $this->exts->execute_javascript("
        let manager = document.querySelector('extensions-manager');
        if (manager && manager.shadowRoot) {
            let itemList = manager.shadowRoot.querySelector('extensions-item-list');
            if (itemList && itemList.shadowRoot) {
                let items = itemList.shadowRoot.querySelectorAll('extensions-item');
                items.forEach(item => {
                    let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                    if (toggle) toggle.click();
                });
            }
        }
    ");
    }

    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'div#recaptcha iframe[src*="/recaptcha/api2/"][title="reCAPTCHA"]';
        $recaptcha_textarea_selector = 'textarea[id="g-recaptcha-response"]';
        $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
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
                $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                $gcallbackFunction = $this->exts->execute_javascript('
                (function() { 
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
                })();
			');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                $this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            } else {
                // try again if recaptcha expired
                if ($count < 3) {
                    $count++;
                    $this->checkFillRecaptcha($count);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    private function checkFillTwoFactor()
    {
        $this->exts->capture("2.0-two-factor-checking");
        $this->exts->waitTillPresent('input[id="otp"]', 20);
        if ($this->exts->exists('input[id="otp"]')) {
            $two_factor_selector = 'input[id="otp"]';
            $two_factor_message_selector = "div.card-subtitle";
            $two_factor_submit_selector = 'button[id="sendOtp"]';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->notification_uid = "";
            $this->exts->two_factor_attempts++;
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                    $this->exts->moveToElementAndClick('label[for="auth-mfa-remember-device"]');
                }
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(1);
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->exts->exists('[name="transactionApprovalStatus"], #resend-approval-form')) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            $message_selector = '.a-spacing-large .transaction-approval-word-break, #channelDetails, .transaction-approval-word-break, #channelDetailsWithImprovedLayout';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('.transaction-approval-word-break.a-size-medium'));
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n" . trim($this->exts->extract('#channelDetails'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirmation on device";

            $this->exts->notification_uid = "";
            $this->exts->two_factor_attempts++;
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                // Click refresh page if user confirmed
                $this->exts->moveToElementAndClick('a.a-link-normal[href*="/ap/cvf/approval"]');
            }
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('table > tbody > tr[ng-repeat*="history"]', 20);
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
        // 	$this->exts->log('Waiting for invoice...');
        // 	sleep(5);
        // }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table > tbody > tr[ng-repeat*="history"]');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 6 && $this->exts->querySelector('a[href*="DownloadPdf"]', $row) != null) {
                $invoiceBtn = $this->exts->querySelector('a[href*="DownloadPdf"]', $row);
                // $invoiceName = explode('.pdf',
                // 	array_pop(explode('fileName=', $invoiceBtn))
                // )[0];
                $invoiceName = $this->exts->extract('td:nth-child(10)', $row);
                $invoiceDate = trim(str_replace("'", '', trim($tags[1]->getAttribute('innerText')))) . ' ' . trim($this->exts->extract('select[ng-model*="yearsCurrent"] option[selected="selected"]', null, 'innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceBtn' => $invoiceBtn
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
            // $this->exts->log('invoiceBtn: '.$invoice['invoiceBtn']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'm. d Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->click_and_download($invoice['invoiceBtn'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
