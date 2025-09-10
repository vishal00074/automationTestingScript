<?php //  updated login code.

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

    // Server-Portal-ID: 1278555 - Last modified: 10.06.2025 15:47:08 UTC - User: 1
    /*Define constants used in script*/

    public $baseUrl = 'https://fleet.aral.com/de/login';
    public $loginUrl = 'https://fleet.aral.com/de/login';
    public $invoicePageUrl = '';

    public $username_selector = 'input[type="text"]';
    public $password_selector = 'input#password,input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[data-testid="btn-anmelden"]';

    public $check_login_failed_selector = 'div.alert-error';
    public $check_login_success_selector = '';

    public $isNoInvoice = true;

    /**
  
     * Entry Method thats called for a portal
  
     * @param Integer $count Number of times portal is retried.
  
     */
    private function initPortal($count)
    {
        $this->exts->temp_keep_useragent = $this->exts->send_websocket_event(
            $this->exts->current_context->webSocketDebuggerUrl,
            "Network.setUserAgentOverride",
            '',
            ["userAgent" => "Mozilla/5.0 (Windows NT 11.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.6998.166 Safari/537.36"]
        );

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent('button[data-testid="button-accept-cookies"]', 10);
            $this->exts->click_element('button[data-testid="button-accept-cookies"]');
            $this->exts->waitTillPresent('button[data-testid="button-login"]', 10);
            $this->exts->click_element('button[data-testid="button-login"]');
            $this->checkFillRecaptcha();
            $this->fillForm(0);
            sleep(5);
            $this->checkFillRecaptcha();

            if ($this->exts->exists('button[data-testid*="linkpere-mailsenden"]')) {
                $this->exts->click_element('button[data-testid*="linkpere-mailsenden"]');
            } elseif ($this->exts->exists('button[type=button]')) {
                $this->exts->click_element('button[type=button]');
            }
            sleep(3);
            $this->checkFillTwoFactor();
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 15);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->click_by_xdotool($this->username_selector);
                $this->exts->type_key_by_xdotool('Ctrl+a');
                $this->exts->type_key_by_xdotool('Delete');
                $this->exts->type_text_by_xdotool($this->username);
                sleep(4);
                $this->checkFillRecaptcha();
                $this->exts->click_element('button[type="submit"]');
                sleep(7);
                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                $this->exts->type_key_by_xdotool('Ctrl+a');
                $this->exts->type_key_by_xdotool('Delete');
                $this->exts->type_text_by_xdotool($this->password);
                sleep(4);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }

                sleep(10);

                $error_text = strtolower($this->exts->extract('div > h1'));

                $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
                if (stripos($error_text, strtolower('400')) !== false) {
                    $this->exts->moveToElementAndClick('button[class="btn btn-primary"][type="submit"]');
                    sleep(10);
                    $this->fillFormUndetected();
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function fillFormUndetected()
    {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("2-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(2);
            $this->checkFillRecaptcha();
            $this->exts->click_by_xdotool('button[type="submit"]');
            sleep(7);
            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(2);

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(2);
            }

            $error_text = strtolower($this->exts->extract('div.alert-error  > div'));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('Es wurde kein Konto gefunden')) !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            }
        }
    }

    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
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
        $two_factor_selector = 'form input[type=text]';
        $two_factor_message_selector = 'div#message';
        $two_factor_submit_selector = 'button[type="submit"]';
        $this->exts->waitTillPresent($two_factor_selector, 10);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(2);
                $this->exts->click_element($two_factor_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);
                if ($this->exts->querySelector($two_factor_selector) == null) {
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

    /**
  
     * Method to Check where user is logged in or not
  
     * return boolean true/false
  
     */
    public function checkLogin()
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
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
