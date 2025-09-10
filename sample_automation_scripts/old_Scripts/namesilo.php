<?php // migrated

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

    // Server-Portal-ID: 28527 - Last modified: 20.08.2024 09:29:29 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.namesilo.com/';
    public $loginUrl = 'https://www.namesilo.com/login.php';
    public $invoicePageUrl = 'https://www.namesilo.com/account/orders';

    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div.modal-dialogue__message';
    public $check_login_success_selector = 'a[href*="/logout.php"], a[href="/logout"], a#logout, a[href="/account/settings"]';

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
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->check_solve_blocked_page();
            $this->checkFillHcaptcha();
            $this->exts->capture('solve-hcaptcha-1');
            $this->checkFillLogin();
            sleep(15);
            $this->checkFillHcaptcha();
            $this->exts->capture('solve-hcaptcha-4');
            if ($this->exts->exists($this->password_selector)) {
                $this->checkFillLogin();
                sleep(15);
                $this->checkFillHcaptcha();
                $this->exts->capture('solve-hcaptcha-7');
                $this->checkFillRecaptcha();
            }
            $this->checkFillTwoFactor();
        }


        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            $this->checkFillHcaptcha();
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $mesg_error = '';
                $mesg_error_els = $this->exts->getElements($this->check_login_failed_selector);
                foreach ($mesg_error_els as $key => $mesg_error_el) {
                    $mesg_error .= trim($mesg_error_el->getAttribute('innerText'));
                }

                $this->exts->log('mesg_error: ' . $mesg_error);

                if (stripos($mesg_error, 'passwor') !== false) {
                    $this->exts->loginFailure(1);
                }
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

            $this->checkFillRecaptcha();

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);

            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $mesg_error = '';
                $mesg_error_els = $this->exts->getElements($this->check_login_failed_selector);
                foreach ($mesg_error_els as $key => $mesg_error_el) {
                    $mesg_error .= trim($mesg_error_el->getAttribute('innerText'));
                }

                $this->exts->log('mesg_error: ' . $mesg_error);

                if (strpos($mesg_error, 'passwor') !== false) {
                    $this->exts->loginFailure(1);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillHcaptcha($count = 0)
    {
        $hcaptcha_iframe_selector = 'iframe[src*="hcaptcha"]';
        if ($this->exts->exists($hcaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
            $data_siteKey =  end(explode("&sitekey=", $iframeUrl));
            $jsonRes = $this->exts->processHumanCaptcha("", $data_siteKey, $this->exts->getUrl(), false);

            if (!empty($jsonRes) && trim($jsonRes) != '') {
                $captchaScript = '
					function submitToken(token) {
						document.querySelector("[name=h-captcha-response]").innerText = token;
						document.querySelector("form.challenge-form").submit();
					}
					submitToken(arguments[0]);
				';
                $params = array($jsonRes);
                $this->exts->executeSafeScript($captchaScript, $params);
            }

            sleep(15);
            if ($this->exts->exists($hcaptcha_iframe_selector) && $count < 5) {
                $count++;
                $this->exts->refresh();
                sleep(15);
                $this->checkFillHcaptcha($count);
            }
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

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#inputPassword, input[placeholder*="code"]';
        $two_factor_message_selector = 'div.fs16.fs18-M.fw4.mb36.lh13';
        $two_factor_submit_selector = 'main button[type="submit"], .section form button[type="submit"]';

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
                $this->exts->moveToElementAndType($two_factor_selector, '');
                $this->exts->click_by_xdotool($two_factor_selector);
                sleep(4);
                $this->exts->type_text_by_xdotool($two_factor_code);

                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->moveToElementAndClick('input#customCheck1');

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

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
        } else if ($this->exts->getElement('//form[contains(@class, "login-form")]//span[contains(text(),"Factor Code")]/following-sibling::input[contains(@type, "password")]', null, 'xpath') != null) {
            $two_factor_selector = 'input[type="password"]';
            $two_factor_message_selector = '//div[contains(text(), "enter the code")]';
            $two_factor_submit_selector = 'form.login-form button[type="submit"]';

            if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->log("Two factor page found.");
                $this->exts->capture("2.1-two-factor");

                if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                    $this->exts->two_factor_notif_msg_en = "";
                    for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
                        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getText() . "\n";
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
                    $this->exts->moveToElementAndType($two_factor_selector, '');
                    $this->exts->click_by_xdotool($two_factor_selector);
                    sleep(4);
                    $this->exts->type_text_by_xdotool($two_factor_code);

                    $this->exts->moveToElementAndClick('input#customCheck1');

                    $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                    sleep(3);
                    $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                    sleep(15);

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
    }

    private function check_solve_blocked_page()
    {
        sleep(7);
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
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 180, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 180, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 180, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }

    private function processInvoices()
    {
        sleep(25);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a[href*="order_invoice"]', $tags[4]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="order_invoice"]', $tags[4])->getAttribute("href");
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' USD';

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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Y-m-d h:i:s', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->download_capture($invoice['invoiceUrl'], $invoiceFileName, 5);
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
