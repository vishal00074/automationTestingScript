<?php // removed commented code added function loadCookiesFromFile and open baseUrl after loadCookiesFromFile
// and uncommented clearCookies function 
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

    // Server-Portal-ID: 241849 - Last modified: 22.08.2025 14:23:24 UTC - User: 1

    public $baseUrl = 'https://espace-client.pro.engie.fr/';
    public $loginUrl = 'https://espace-client.pro.engie.fr/user/auth';
    public $invoicePageUrl = 'https://espace-client.pro.engie.fr/mes-factures';

    public $username_selector = 'form input#okta-signin-username, input#edit-email-login';
    public $password_selector = 'form input#okta-signin-password, input[name="mdp_login"][type="password"]';
    public $submit_login_selector = 'form input#okta-signin-submit, input.engie-login-submit';

    public $check_login_success_selector = 'a[href*="/logout"]';

    public $isNoInvoice = true;
    public $filesCompleted = 0;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_extensions();

        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture('1-init-page');

        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            // die;
            if ($this->exts->exists('div#popin_tc_privacy_container_button button[type="button"]')) {
                $this->exts->moveToElementAndClick('div#popin_tc_privacy_container_button button[type="button"]');
                sleep(3);
            }
            if ($this->exts->querySelector('button#popin_tc_privacy_button_2') != null) {
                $this->exts->moveToElementAndClick('button#popin_tc_privacy_button_2');
                sleep(3);
            }
            $this->exts->click_if_existed('#tc-privacy-wrapper button#accept_all');

            $this->checkFillLogin();
            sleep(10);
            if (
                $this->exts->oneExists([$this->username_selector, $this->password_selector]) &&
                !$this->exts->exists('.login-form-page .form-item--error-message')
            ) {
                sleep(12);
            }
            if (stripos($this->exts->extract('.login-form-page .form-item--error-message', null, 'innerText'), 'Captcha') !== false) {
                $this->checkFillLogin();
                sleep(10);
                if (
                    $this->exts->oneExists([$this->username_selector, $this->password_selector]) &&
                    !$this->exts->exists('.login-form-page .form-item--error-message')
                ) {
                    sleep(12);
                }
            }
            if (stripos($this->exts->extract('.login-form-page .form-item--error-message', null, 'innerText'), 'Captcha') !== false) {
                $this->checkFillLogin();
                sleep(10);
                if (
                    $this->exts->oneExists([$this->username_selector, $this->password_selector]) &&
                    !$this->exts->exists('.login-form-page .form-item--error-message')
                ) {
                    sleep(12);
                }
            }

            $this->checkFillTwoFactor();
        }
        if ($this->exts->querySelector('.contacts-form') != null) {
            $this->exts->moveToElementAndClick('.name-user');
            sleep(2);
            $this->exts->moveToElementAndClick('input#edit-submit');
            sleep(10);
        }
        if ($this->exts->querySelector('form.cgu-form') != null) {
            $this->exts->moveToElementAndClick('input#edit-consent');
            sleep(2);
            $this->exts->moveToElementAndClick('input#edit-submit');
            sleep(1);
        }

        if (!$this->checkLogin()) {
            $this->exts->capture('before-login');
            sleep(50);
        }

        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('a[href*="liste-contacts/set-contact"]')) {
                $this->exts->moveToElementAndClick('a[href*="liste-contacts/set-contact"]');
                sleep(10);
            }

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            if ($this->exts->exists('div.modal-content button.modal-close')) {
                $this->exts->moveToElementAndClick('div.modal-content button.modal-close');
                sleep(3);
            }
            $this->processMultiAccounts();
            $this->exts->log("====== Download Success: " . $this->filesCompleted . " files");
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos($this->exts->extract('.login-form-page .form-item--error-message', null, 'innerText'), 'le mot de passe sont incorrects') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos(strtolower($this->exts->extract('.infobox-error')), 'dresse email inconnue') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos(strtolower($this->exts->extract('.infobox-error')), 'otre compte est bloqu') !== false) {
                $this->exts->loginFailure(1);
            } else if (
                stripos(strtolower($this->exts->extract('p.okta-form-input-error')), 'veuillez saisir une adresse email valide') !== false
                || stripos(strtolower($this->exts->extract('p.okta-form-input-error')), 'please enter a valid email address') !== false
            ) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.message-card-title', null, 'innerText')), 'pas de contrat actif avec cette adresse mail') !== false) {
                $this->exts->account_not_ready();
            } else if (strpos(strtolower($this->exts->extract('div.form-item--error-message', null, 'innerText')), 'otre mot de passe a expir') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('h2.card-big-white-title', null, 'innerText')), 'verrouiller le compte') !== false) {
                $this->exts->account_not_ready();
            } else if (strpos(strtolower($this->exts->extract('div.form-item--error-message', null, 'innerText')), 'euillez saisir une adresse e-mail valide') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.form-item--error-message', null, 'innerText')), 'dresse e-mail inconnue') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            $this->exts->type_key_by_xdotool("ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            $this->exts->type_key_by_xdotool("ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);
            $this->exts->capture("2-login-page-filled");
            $this->checkFillRecaptcha();
            $this->exts->click_by_xdotool($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = '[id*="factor-verify-form"] input[name*="box"]';
        $two_factor_message_selector = '.mfa-code-send-message .body';
        $two_factor_submit_selector = '[id*="factor-verify-form"] #edit-submit';

        $this->exts->waitTillPresent('input[type="radio"][name="factor_id"]', 25);
        if ($this->exts->exists('input[type="radio"][name="factor_id"]')) {
            $this->exts->capture('2fa-mothods');
            $this->exts->moveToElementAndClick('input[type="radio"][name="factor_id"]');
            sleep(3);
            $this->exts->waitTillPresent($two_factor_selector, 25);
            sleep(1);
        }

        if ($this->exts->exists($two_factor_selector)) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->exists($two_factor_message_selector)) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector, null, 'innerText');
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->click_by_xdotool($two_factor_selector);
                sleep(1);
                $this->exts->type_text_by_xdotool($two_factor_code);
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = "iframe[src*='recaptcha/enterprise']";
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
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
                $recaptcha_textareas = $this->exts->querySelectorAll($recaptcha_textarea_selector);
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

    private function checkLogin()
    {
        return $this->exts->querySelector($this->check_login_success_selector) != null || ($this->exts->urlContains('check_logged_in=1') && $this->exts->urlContains('liste-contacts'));
    }

    private function processMultiAccounts()
    {
        // get list acccount url
        sleep(2);
        $account_urls = [];
        $urls = $this->exts->querySelectorAll('.contract_link');
        if (count($urls) > 1) {
            foreach ($urls as $link) {
                $url = $link->getAttribute('href');
                $this->exts->log('url: ' . $url);
                array_push($account_urls, array(
                    'account_url' => $url,
                ));
            }
            foreach ($account_urls as $url) {
                $this->exts->log('account_url: ' . $url['account_url']);
                $this->exts->openUrl($url['account_url']);
                sleep(10);
                if ($this->exts->querySelector('.contacts-form') != null) {
                    $this->exts->moveToElementAndClick('.name-user');
                    sleep(2);
                    $this->exts->moveToElementAndClick('input#edit-submit');
                    sleep(10);
                }
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(10);
                if ($this->exts->exists('div.modal-content button.modal-close')) {
                    $this->exts->moveToElementAndClick('div.modal-content button.modal-close');
                    sleep(3);
                }
                $this->processInvoices();
                sleep(5);
            }
        } else {
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            if ($this->exts->exists('div.modal-content button.modal-close')) {
                $this->exts->moveToElementAndClick('div.modal-content button.modal-close');
                sleep(3);
            }
            $this->processInvoices();
        }
    }

    private function processInvoices()
    {

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div.bills-list div.bill');
        foreach ($rows as $row) {
            $invoice_link = $this->exts->querySelector('div.bill_download a[href*="/facture"]', $row);
            if ($invoice_link != null) {
                $invoice_link = $this->exts->execute_javascript('return arguments[0].href;', [$invoice_link]);
                $invoiceName = explode(
                    '/',
                    array_pop(explode('facture/', $invoice_link))
                )[0];
                if (strpos($invoiceName, 'http') !== false) {
                    $invoiceName = explode(
                        '?',
                        array_pop(explode('/', $invoice_link))
                    )[0];
                }
                $invoiceDate = trim($this->exts->extract('div.bill_year', $row, 'innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', trim($this->exts->extract('span.bill_amount', $row)))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoice_link
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
                $this->filesCompleted++;
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Engie (Espace Client)', '2674761', 'ZmFjdHVyZS1ub3JlcGx5QHBhbGFpc2Rlc3RoZXMuY29t', 'UGFsYWlzZGVzdGhlczIwMjFA');
$portal->run();
