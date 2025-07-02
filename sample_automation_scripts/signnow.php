<?php //  migrated and updated download code

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

    // Server-Portal-ID: 9416 - Last modified: 04.07.2024 14:13:49 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://snseats.signnow.com/';
    public $loginUrl = 'https://snseats.signnow.com/login';
    public $invoicePageUrl = 'https://snseats.signnow.com/';
    public $username_selector = 'input#login';
    public $password_selector = 'input#pswd';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type*="submit"]';

    public $check_login_failed_selector = '';
    public $check_login_success_selector = 'div.snr-sn-page-header__actions-item.snr-sn-page-header__actions-item--height-lg, div.username-authenticated-caption, [aria-label*="Log out"]';
    public $isNoInvoice = true;
    public $errorMessage = '';
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->disable_extensions();
        $this->exts->log('Begin initPortal ' . $count);
        // Load cookies
        // $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        if ($this->isExists('.snr-sn-page-header__actions > div.snr-sn-page-header__actions-item:last-child')) {
            $this->exts->moveToElementAndClick('.snr-sn-page-header__actions > div.snr-sn-page-header__actions-item:last-child');
            sleep(5);
        }
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null && $this->exts->getElement('//span[contains(text(),"Log Out") or contains(text(),"Déconnecter")]', null, 'xpath') == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
            if ($this->isExists('#captcha-v2 iframe[src*="/recaptcha/api2/anchor?"]')) {
                $this->clearChrome();
                sleep(5);
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
                $this->checkFillLogin();
            }
            if ($this->isExists('#captcha-v2 iframe[src*="/recaptcha/api2/anchor?"]')) {
                $this->clearChrome();
                sleep(5);
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
                $this->checkFillLogin();
            }
            sleep(20);
            $this->checkFillTwoFactor();
            sleep(15);
            if ($this->isExists('.snr-sn-page-header__actions > div.snr-sn-page-header__actions-item:last-child')) {
                $this->exts->moveToElementAndClick('.snr-sn-page-header__actions > div.snr-sn-page-header__actions-item:last-child');
                sleep(5);
            }
        }
        if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->getElement('//span[contains(text(),"Log Out") or contains(text(),"Déconnecter")]', null, 'xpath') != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            // Open invoices url and download invoice
            // $this->exts->moveToElementAndClick('div.snr-sn-sidebar__subnav button.snr-collapse-panel__show-action');
            // if ($this->exts->getElement('div.modal-lg.modal-dialog button[type=submit]') != null) {
            //     $this->exts->moveToElementAndClick('div.modal-lg.modal-dialog button[type=submit]');
            // }
            sleep(5);
            $admin_console_button = $this->exts->getElementByText('button.snr-navgroup-list__control', ['Admin Console', 'Konsole'], null, false);
            sleep(15);
            if ($admin_console_button != null) {
                $admin_console_button->click();
            } else if ($this->isExists('button[data-autotest="sidebar-admin-console-nav-item-btn"]')) {
                $this->exts->moveToElementAndClick('button[data-autotest="sidebar-admin-console-nav-item-btn"]');
            }
            $selector = 'a[href*="billing"]';

            for ($wait = 0; $wait < 5 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for Selectors.....');
                sleep(10);
            }

            if ($this->isExists('button[data-autotest="sidebar-admin-console-nav-item-btn"]')) {
                $this->exts->moveToElementAndClick('button[data-autotest="sidebar-admin-console-nav-item-btn"]');
            }

            for ($wait = 0; $wait < 5 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for Selectors.....');
                sleep(10);
            }

            $this->exts->capture('billing-invoices');
            if ($this->exts->querySelector('a[href*="billing"]') != null) {
                sleep(5);
                $billingUrl = $this->exts->getElement('a[href*="billing"]')->getAttribute("href");
                $this->exts->openUrl($billingUrl);
                $this->processInvoices();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log("Login Failure : " . $this->errorMessage);
            if (
                stripos($this->errorMessage, 'email or password incorrect') !== false
                || stripos($this->errorMessage, 'invalid domain zone') !== false
                || stripos($this->errorMessage, 'der von ihnen eingegebene bBenutzername und das passwort stimmen nicht mit unseren') !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    private function disable_extensions()
    {
        $this->exts->openUrl('chrome://extensions/');
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
            // $this->checkFillRecaptcha(1);
            sleep(5);
            $this->exts->executeSafeScript("document.querySelector('button[type*=\'submit\']').disabled = false;");
            sleep(1);
            $this->exts->executeSafeScript("document.querySelector('button[type*=\"submit\"]').classList.remove('snr-is-disabled');");
            sleep(1);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(2);
            $this->errorMessage = $this->exts->extract(".snr-notifications__item-message", null, 'innerText');
            sleep(10);

            if ($this->isExists($this->password_selector) && $this->isExists('iframe[src*="/recaptcha/api2/anchor?"]')) {
                $this->checkFillRecaptcha(1);
                sleep(5);
                $this->exts->executeSafeScript("document.querySelector('button[type*=\'submit\']').disabled = false;");
                sleep(1);
                $this->exts->executeSafeScript("document.querySelector('button[type*=\"submit\"]').classList.remove('snr-is-disabled');");
                sleep(5);
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(2);
                $this->errorMessage = $this->exts->extract(".snr-notifications__item-message", null, 'innerText');
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
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

    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = '#captcha-v2 iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = '#captcha-v2 textarea[name="g-recaptcha-response"]';
        if ($this->isExists($recaptcha_iframe_selector)) {
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
                            if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                            } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                                for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                            }
                        } catch(ex) { console.log("ERROR in function: " + ex); return; }
                    }
                    recurse(___grecaptcha_cfg.clients[0], "", 0);
                    return found ? "___grecaptcha_cfg.clients[0]." + result : null;
                ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                $gcallbackFunction = $this->exts->executeSafeScript("return " . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[aria-labelledby="verify-code-input"]';
        $two_factor_message_selector = 'p.snr-login-form__subtitle';
        $two_factor_submit_selector = 'form.snr-login-form .snr-login-form__button button[type="submit"]';
        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
                $code_inputs = $this->exts->getElements('input[aria-labelledby="verify-code-input"]');
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));
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
                sleep(15);
                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = "";
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
    private function processInvoices($count = 0)
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->getElements('table > tbody > tr');
        for ($i = 0; $i < count($rows); $i++) {
            $row = $this->exts->getElements('table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 7 && $this->exts->getElement('.button--invite-download', $tags[7]) != null) {
                // $invoiceSelector = $this->exts->getElement('button.invoice-handle-actions-buttons', $tags[6]);
                // $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-".$index."');", [$invoiceSelector]);

                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'M d, Y', 'Y-m-d', 'en');
                $invoiceName = time(); // use custom name in case no name found
                sleep(1);
                $invoiceFileName =  $invoiceName . '.pdf';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' USD';
                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                // $this->exts->getElement('.invoice-handle-actions-buttons', $tags[6])->click();
                $download_button = $this->exts->getElement(".button--invite-download", $tags[7]);
                try {
                    $this->exts->log('Click download invoice');
                    $download_button->click();
                } catch (Exception $e) {
                    $this->exts->log("Click download invoice by javascript ");
                    $this->exts->executeSafeScript('arguments[0].click()', [$download_button]);
                }

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }
            } else if (count($tags) >= 7 && $this->exts->getElement('button.invoice-handle-actions', $tags[6]) != null) {
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'M d, Y', 'Y-m-d', 'en');
                $invoiceName = time();
                sleep(1);
                $invoiceFileName =  $invoiceName . '.pdf';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' USD';

                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $this->exts->execute_javascript("
				document.querySelectorAll('table > tbody > tr')[arguments[0]].querySelectorAll('td')[6].querySelector('button.invoice-handle-actions').click()
			", [$i]);
                $this->exts->moveToElementAndClick(".button--invite-download");
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }
                $this->exts->execute_javascript("
				document.querySelectorAll('table > tbody > tr')[arguments[0]].querySelectorAll('td')[6].querySelector('button.invoice-handle-actions').click()
			", [$i]);
            } else if (count($tags) >= 6 && $this->exts->getElement('a', $tags[5]) != null) {
                $invoiceSelector = $this->exts->getElement('a', $tags[5]);
                $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-" . $i . "');", [$invoiceSelector]);

                $invoiceDate = trim($tags[0]->getText());
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'M d, Y', 'Y-m-d', 'en');
                $invoiceName = '(' . $i . ')' . $invoiceDate;
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getText())) . ' USD';
                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->moveToElementAndClick("a#custom-pdf-download-button-" . $i);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $pagiantionSelector = 'li.VuePagination__pagination-item-next-page:not(.disabled) button';
        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoices($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoices($count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
