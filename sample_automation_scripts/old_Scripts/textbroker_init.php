public $baseUrl = 'https://intern.textbroker.de/client/home';
public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $submit_login_selector = 'form[action="/login/login"] button[type="submit"]';
public $check_login_success_selector = 'li.logout, a[href*="/logout"]';
public $isNoInvoice = true;
public $totalFiles = 0;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // Load cookies
    
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    if ($this->exts->exists('div.c-side-nav__menu [title="Mein Konto"] [aria-haspopup="menu"]')) {
        $this->exts->moveToElementAndClick('div.c-side-nav__menu [title="Mein Konto"] [aria-haspopup="menu"]');
        sleep(5);
    }
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLoggedIn()) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $this->exts->moveToElementAndClick('[aria-describedby="cookieconsent:desc"] a.cc-allow');
        sleep(2);
        $this->exts->moveToElementAndClick('button.cm__btn[data-role="all"]');
        sleep(5);
        $this->checkFillLogin();
        sleep(5);
        if (strpos(strtolower($this->exts->extract('.alert.fadeInDown')), 'recaptcha') !== false) {
            // $this->exts->moveToElementAndClick('.alert.fadeInDown [data-notify="dismiss"]');
            $this->checkFillRecaptcha();
            sleep(5);
            $this->exts->moveToElementAndClick('.alert.fadeInDown [data-notify="dismiss"]');
            sleep(30);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
        }

        if (strpos(strtolower($this->exts->extract('.alert.fadeInDown')), 'recaptcha') !== false) {
            // $this->clearChrome();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->exts->moveToElementAndClick('button.cm__btn[data-role="all"]');
            $this->checkFillLogin();
        }

        $this->checkFillTwoFactor();

        sleep(10);
        if ($this->exts->exists('tb-side-nav-menu.ng-star-inserted div.mat-menu-trigger, button.c-toolbar__menu-button')) {
            $this->exts->moveToElementAndClick('tb-side-nav-menu.ng-star-inserted div.mat-menu-trigger,button.c-toolbar__menu-button');
            sleep(5);
        }
        if ($this->exts->exists('div.c-side-nav__menu [title="Mein Konto"] [aria-haspopup="menu"]')) {
            $this->exts->moveToElementAndClick('div.c-side-nav__menu [title="Mein Konto"] [aria-haspopup="menu"]');
            sleep(5);
        }

        sleep(8);
        if ($this->exts->exists('div[class="cm__btn-group"] button[data-role="all"]')) {
            $this->exts->moveToElementAndClick('div[class="cm__btn-group"] button[data-role="all"]');
            sleep(5);
        }
    }

    // then check user logged in or not
    if ($this->checkLoggedIn()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (strpos(strtolower($this->exts->extract('.alert.fadeInDown')), 'passwor') !== false || strpos(strtolower($this->exts->extract('.alert.fadeInDown')), strtolower('Die Benutzerdaten sind nicht korrekt'))) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->urlContains('/loginFailBlock')) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
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
            $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->execute_javascript('
            if(document.querySelector("[data-callback]") != null){
                document.querySelector("[data-callback]").getAttribute("data-callback");
            } else {
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
                found ? "___grecaptcha_cfg.clients[0]." + result : null;
            }
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
private function checkFillTwoFactor()
{
    $two_factor_selector = '.c-two-factor-code-verification [formcontrolname="code"] input';
    $two_factor_message_selector = 'tb-two-factor-authenticate-view h1 + div > div:first-child';
    $two_factor_submit_selector = 'button[type="submit"]';

    if ($this->exts->getElement($two_factor_selector) != null) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->moveToElementAndClick('[formcontrolname="trustedDevice"]');
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}
private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");
        $this->exts->moveToElementAndClick('label[for="userTypeClient"]');

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(10);
        $this->checkFillRecaptcha();
        $this->exts->capture("2-login-page-filled");
        sleep(5);
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkLoggedIn()
{
    $isLoggedIn = false;
    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    if ($this->exts->exists($this->check_login_success_selector)) {
        $isLoggedIn = true;
    }
    return $isLoggedIn;
}
