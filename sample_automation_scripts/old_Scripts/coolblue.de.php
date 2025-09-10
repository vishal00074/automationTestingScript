<?php

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

    // Server-Portal-ID: 779721 - Last modified: 10.07.2025 14:31:31 UTC - User: 1

    public $baseUrl = 'https://www.coolblue.de/mein-coolblue-konto';
    public $loginUrl = 'https://www.coolblue.de/anmelden';
    public $invoicePageUrl = 'https://www.coolblue.de/mein-coolblue-konto/orderuebersicht';

    public $username_selector = 'input[type=email]';
    public $password_selector = 'cb-login-password';
    public $confirm_password_selector = 'input#cb-login-password';
    public $remember_me_selector = 'input#cb-login-password-confirm';
    public $submit_login_selector = 'button[type=submit][class=button], cb-login form div > button[type="submit"]';

    public $check_login_failed_selector = 'div.notice';
    public $check_login_success_selector = 'li a[href*=abmelden]';

    public $isNoInvoice = true;

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        $this->checkFillRecaptcha();
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillRecaptcha();
            $this->fillForm(0);
            $this->checkFillTwoFactor();

            if ($this->exts->exists('button[name = "accept_cookie"]')) {
                $this->exts->click_by_xdotool('button[name = "accept_cookie"]');
                sleep(5);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);
            if ($this->exts->exists('button[value=all_categories]')) {
                $this->exts->click_element('button[value=all_categories]');
            }
            sleep(5);
            $this->exts->openUrl($this->invoicePageUrl);

            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElement('input#cb-login-password-confirm')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                $this->exts->click_element("form button");
                sleep(5);
                $this->checkFillRecaptcha();
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->confirm_password_selector, $this->password);


                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->checkFillRecaptcha();
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }
    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'div.j-form__re-captcha-fallback iframe[src*="/recaptcha/api2/anchor?"]';
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
                $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                $gcallbackFunction = $this->exts->execute_javascript('
					(function() {
						if(document.querySelector("[data-callback]") != null){
							document.querySelector("[data-callback]").getAttribute("data-callback");
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
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }
    private function checkFillTwoFactor()
    {
        $two_factor_selector = '[autocomplete="one-time-code"]';
        $two_factor_message_selector = '[data-placeholder="notice__content"]';
        $two_factor_submit_selector = 'form [trackingname="confirm"] button';

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
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);

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
    private function processInvoices($paging_count = 1)
    {
        sleep(5);
        $invoice_tab = $this->exts->findTabMatchedUrl(['orderuebersicht']);
        if ($invoice_tab == null) {
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(7);
        }

        $this->exts->capture("4-invoices-page");
        $invoiceUrls = [];

        $rows = $this->exts->querySelectorAll('a[href*="orderdetails"][aria-label], a[href*="orderuebersicht/"][aria-label]');
        foreach ($rows as $key =>  $row) {
            $extractInvoiceUrl = $this->exts->getElements('a[href*="orderdetails"][aria-label], a[href*="orderuebersicht/"][aria-label]')[$key];
            $urlInvoice = $extractInvoiceUrl->getAttribute('href');
            if (!in_array($urlInvoice, $invoiceUrls)) {
                $invoiceUrls[] = $urlInvoice;
            }
        }
        $this->exts->log('Invoices found: ' . count($invoiceUrls));
        foreach ($invoiceUrls as $url) {
            $this->exts->openUrl($url);
            sleep(10);
            $downloadBtn = $this->exts->getElement('a[download]');
            if ($downloadBtn != null) {
                $invoiceName = $downloadBtn->getAttribute('download');
                $invoiceName = array_shift(explode('.', $invoiceName));
                $invoiceAmount =  '';
                $invoiceDate =  '';

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf': '';
                $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
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
