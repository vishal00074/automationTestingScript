public $baseUrl = "https://www.orange.fr/portail";
public $loginUrl = "https://login.orange.fr/?return_url=https://www.orange.fr/portail";
public $homePageUrl = "https://espaceclientv3.orange.fr/?page=factures-accueil";
public $username_selector = "input#login";
public $password_selector = "input#password";
public $submit_button_selector = "button#btnSubmit";
public $login_tryout = 0;
public $month_names_fr = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
public $captcha_form_selector = 'div[class*="captcha_images"]'; //'[id="captchaRow"] form[name="captcha-form"]';
public $captcha_image_selector = 'ul.uya65w-4 li';
public $captcha_image_selector_1 =  'ul#captcha-images li';
public $captcha_submit_btn_selector = 'button.sc-gKsewC, button#login-submit-button';
public $captcha_indications_selector = 'ul.uya65w-0.eCJhHZ li';
public $lang_code = 'fr';
public $isNoInvoice = true;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->exts->openUrl($this->baseUrl);
    sleep(15);

    $user_agent = $this->exts->executeSafeScript('return navigator.userAgent;');
    $this->exts->log('user_agent: ' . $user_agent);

    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    //This is not needed because profile get loaded without calling any function

    if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
        $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
        sleep(10);
    }

    if ($this->checkLogin()) {
        $isCookieLoginSuccess = true;
    } else {
        $this->clearChrome();
        // sleep(5);
        $this->exts->openUrl($this->loginUrl);
        sleep(15);

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists($this->captcha_form_selector)) {
                $this->solveClickCaptcha();
                sleep(10);
            } else {
                break;
            }
        }

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }
    }

    $this->exts->capture('before-fill-form');

    if (!$isCookieLoginSuccess) {
        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists($this->captcha_form_selector)) {
                $this->solveClickCaptcha();
                sleep(10);
            } else {
                break;
            }
        }

        if (!$this->exts->exists($this->username_selector)) {
            $this->exts->openUrl($this->loginUrl);
            sleep(10);

            if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
                $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
                sleep(10);
            }

            for ($i = 0; $i < 3; $i++) {
                if ($this->exts->exists($this->captcha_form_selector)) {
                    $this->solveClickCaptcha();
                    sleep(10);
                } else {
                    break;
                }
            }
        }

        // $this->exts->capture("after-login-clicked");

        if (!$this->exts->exists($this->username_selector) && $this->exts->exists('div p#accountLogin') && $this->exts->exists('a.link-action-password')) {
            $this->exts->moveToElementAndClick("a.link-action-password");
            sleep(15);
        }

        if ($this->exts->exists('a[id*="choose-account"]')) {
            $this->exts->moveToElementAndClick('a[id*="choose-account"]');
            sleep(15);
        }

        $this->fillForm(0);
        sleep(10);
        for ($i = 0; $i < 3 && $this->exts->exists('div#alert-sessionExpired:not([style="display: none;"]) button[data-testid="button-reload"]'); $i++) {
            $this->exts->moveToElementAndClick('div#alert-sessionExpired:not([style="display: none;"]) button[data-testid="button-reload"]');
            sleep(10);
            $this->fillForm(0);
            sleep(10);
        }

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }

        if ($this->exts->getElement("button#o-cookie-ok") != null) {
            $this->exts->moveToElementAndClick('button#o-cookie-ok');
        }

        if ($this->exts->getElement("#o-cookie-consent-ok") != null) {
            $this->exts->moveToElementAndClick('#o-cookie-consent-ok');
        }

        $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
        sleep(10);


        $this->checkFillTwoFactor();

        if ($this->exts->exists('button[data-testid="link-mc-later')) {
            $this->exts->moveToElementAndClick('button[data-testid="link-mc-later');
            sleep(20);
        }

        if ($this->exts->exists('button[data-oevent-action="clic_lien_plus_tard"]')) {
            $this->exts->moveToElementAndClick('button[data-oevent-action="clic_lien_plus_tard"]');
            sleep(20);
        }

        $err_txt1 = "";
        if ($this->exts->getElement("h6#error-msg-box") != null) {
            $err_txt1 = $this->exts->getElement("h6#error-msg-box")->getAttribute('innerText');
        }

        $err_txt2 = "";
        if ($this->exts->getElement("span#default_password_error, label#password-invalid-feedback") != null) {
            $err_txt1 = $this->exts->getElement("span#default_password_error, label#password-invalid-feedback")->getAttribute('innerText');
        }

        if (($err_txt1 != "" && $err_txt1 != null) || ($err_txt2 != "" && $err_txt2 != null)) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        }

        if (strpos($this->exts->getUrl(), '/changePassword') !== false) {
            $this->exts->log('Your current password is not secure enough and needs to be strengthened. ');
            $this->exts->capture('new_password!');
            $this->exts->account_not_ready();
        }

        if ($this->exts->getElement('input#new-password') != null && $this->exts->getElement('input#new-password') != null) {
            $this->exts->log('User must update new password');
            $this->exts->capture('User must update new password');
            $this->exts->account_not_ready();
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            if ($this->exts->urlContains('recovery/error')) {
                $this->exts->account_not_ready();
            }
            if ($this->exts->exists('p#password-error-title-error')) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->urlContains('/renforcer-mot-de-passe')) {
                $this->exts->account_not_ready();
            } elseif ($this->exts->urlContains('mdp/choice/default')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
        }
            
        $this->exts->success();
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
    sleep(1);
    $this->exts->capture("clear-page");
    $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearButton").click();');
    sleep(15);
    $this->exts->capture("after-clear");
}

public $isLoginByCookie = false;
function fillForm($count)
{
    $this->exts->capture("1-pre-login");
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(5);
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;

            if ($this->exts->getElement($this->username_selector) != null) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
            }

            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(10);

            if ($this->exts->getElementByText('div#login-error', ['cette adresse e-mail', 'Cette adresse mail ou ce numéro de mobile n’est pas valide. Vérifiez votre saisie'], null, false) != null) {
                $this->exts->loginFailure(1);
            }

            if ($this->exts->exists('a[data-testid="footerlink-authent-pwd"], button[data-testid="footerlink-authent-pwd"]')) {
                $this->exts->moveToElementAndClick('a[data-testid="footerlink-authent-pwd"], button[data-testid="footerlink-authent-pwd"]');
                sleep(16);
            }

            if ($this->exts->exists('button[data-testid="submit-mc"]')) {
                $this->exts->moveToElementAndClick('button[data-testid="submit-mc"]');
                sleep(3);
                $this->checkFillTwoFactorForMobileAcc();
            }

            $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
            sleep(10);

            $this->checkFillTwoFactor();

            if ($this->exts->getElement('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password') != null) {
                $this->exts->moveToElementAndClick('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password');
                sleep(15);
            }

            if ($this->exts->getElement($this->password_selector) != null) {
                if ($this->exts->getElement($this->password_selector) != null && $this->exts->getElement($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(5);
                }

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(10);

                if ($this->checkIfExists($this->password_selector)) {
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);

                    $this->exts->moveToElementAndClick($this->submit_button_selector);

                    sleep(10);
                }
            } else if ($this->exts->getElement("button#btnSubmit") && strpos($this->exts->getUrl(), "/keep-connected") !== false) {
                $this->isLoginByCookie = true;
                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
            } else {
                $temp = "";
                if ($this->exts->getElement("h6#error-msg-box-login") != null) {
                    $temp = $this->exts->getElement("h6#error-msg-box-login")->getAttribute('innerText');
                }

                if ($temp != "" && $temp != null) {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure(1);
                }
            }
        } else if ($this->exts->exists($this->password_selector)) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->moveToElementAndClick($this->submit_button_selector);

            sleep(10);

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
            }
        } else if ($this->exts->getElement("button#btnSubmit") && strpos($this->exts->getUrl(), "/keep-connected") !== false) {
            $this->isLoginByCookie = true;
            $this->exts->moveToElementAndClick($this->submit_button_selector);

            sleep(10);
        } else if ($this->exts->exists('a[data-testid="footerlink-authent-pwd"]')) {
            $this->exts->moveToElementAndClick('a[data-testid="footerlink-authent-pwd"]');
            sleep(15);

            $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
            sleep(10);

            $this->checkFillTwoFactor();

            if ($this->exts->getElement('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password') != null) {
                $this->exts->moveToElementAndClick('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password');
                sleep(15);
            }

            $this->exts->log('enter password');
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->moveToElementAndClick($this->submit_button_selector);

            sleep(10);

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
            }
        } else {
            $temp = $this->exts->extract('h6#error-msg-box-login', null, 'innerText');
            if ($temp != "" && $temp != null) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure(1);
            }
        }

        sleep(10);

        if ($this->exts->exists('div.promoteMC-container a#btnLater')) {
            $this->exts->moveToElementAndClick('div.promoteMC-container a#btnLater');
            sleep(15);
        }

        if ($this->exts->exists('a[data-oevent-action="clic_lien_plus_tard"]')) {
            $this->exts->moveToElementAndClick('a[data-oevent-action="clic_lien_plus_tard"]');
            sleep(14);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkIfExists($selector)
{
    return $this->exts->execute_javascript("document.body.innerHTML.includes('$selector');");
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#otc-input, input#otc';
    $two_factor_message_selector = '#otc-form #otcLabel, #otc-form #helpCard, form h3 + p';
    $two_factor_submit_selector = '#otc-form #btnSubmit, button[data-testid="submit-otc"]';

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

private function checkFillTwoFactorForMobileAcc()
{
    $this->exts->log('start checkFillTwoFactorForMobileAcc');
    $two_factor_selector = '';
    $two_factor_message_selector = 'span.icon-Internet-security-mobile + div';
    $two_factor_submit_selector = '';

    if ($this->exts->getElement($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
        if (!empty($two_factor_code) && trim($two_factor_code) == 'ok') {
            $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            sleep(15);
            if ($this->exts->getElement($two_factor_message_selector) == null && !$this->exts->exists('button[data-testid="btn-mc-error"]')) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                if ($this->exts->exists('button[data-testid="btn-mc-error"]')) {
                    $this->exts->moveToElementAndClick('button[data-testid="btn-mc-error"]');
                    sleep(3);
                }
                $this->checkFillTwoFactorForMobileAcc();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

function processTFA_SMS()
{
    try {
        $this->exts->log("Current URL - " . $this->exts->getUrl());

        if ($this->exts->getElement($this->twofa_form_selector) != null) {
            $this->handleTwoFactorCode($this->twofa_form_selector, "form#otpForm button[type=\"submit\"]");
            sleep(5);
        }
        if ($this->exts->getElement($this->twofa_form_selector) != null) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception process TFA " . $exception->getMessage());
    }
}

function processTFA_NUM($contractId)
{
    try {
        $this->exts->log("Current URL - " . $this->exts->getUrl());

        $two_factor_selector = "div.contratCalcule_" . $contractId . " input[name=\"clientReference\"]";
        $submit_btn_selector = "div[class=\"buttons contratCalcule contratCalcule_" . $contractId . "\"] button[type=\"submit\"]";
        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->handleTwoFactorCode1($two_factor_selector, $submit_btn_selector, $contractId);
            sleep(5);
        }
        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception process TFA " . $exception->getMessage());
    }
}

function handleTwoFactorCode1($two_factor_selector, $submit_btn_selector, $contractId)
{
    if ($this->exts->two_factor_attempts == 2) {
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
    }

    if ($this->exts->two_factor_attempts == 1) {
        if ($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]") != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]")->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = trim($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]")->getAttribute('innerText'));
        }
    }

    $this->exts->log($this->exts->two_factor_notif_msg_en);

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
        try {
            $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
            if ($this->exts->getElement($submit_btn_selector) != null && $this->exts->getElement($submit_btn_selector)->isEnabled()) {
                $this->exts->getElement($submit_btn_selector)->click();
                sleep(10);
            } else {
                sleep(5);
                $this->exts->getElement($submit_btn_selector)->click();
                sleep(10);
            }

            if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->handleTwoFactorCode($two_factor_selector, $submit_btn_selector);
            }
        } catch (\Exception $exception) {
            $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
        }
    }
}

function handleTwoFactorCode($two_factor_selector, $submit_btn_selector)
{
    if ($this->exts->two_factor_attempts == 2) {
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
    }

    if ($this->exts->two_factor_attempts == 1) {
        if ($this->exts->getElement("div.addContractFormContent p.ec_form_line + div") != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement("div.addContractFormContent p.ec_form_line + div")->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = trim($this->exts->getElement("div.addContractFormContent p.ec_form_line + div")->getAttribute('innerText'));
        }

        if ($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]") != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . trim($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]")->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . trim($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]")->getAttribute('innerText'));
        }
    }

    $this->exts->log($this->exts->two_factor_notif_msg_en);

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
        try {
            $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
            if ($this->exts->getElement($submit_btn_selector) != null && $this->exts->getElement($submit_btn_selector)->isEnabled()) {
                $this->exts->getElement($submit_btn_selector)->click();
                sleep(10);
            } else {
                sleep(5);
                $this->exts->getElement($submit_btn_selector)->click();
                sleep(10);
            }

            if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->handleTwoFactorCode($two_factor_selector, $submit_btn_selector);
            }
        } catch (\Exception $exception) {
            $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
        }
    }
}

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    sleep(10);
    try {
        if ($this->exts->execute_javascript("document.querySelector('header#o-header elcos-header').shadowRoot.querySelector('button[data-oevent-action=\"espaceclient\"]') != null") == true) {
            $isLoggedIn = true;
        } else if ($this->exts->querySelector('a[href="/compte?sosh="]') != null) {
            $isLoggedIn = true;
        } else if ($this->exts->execute_javascript("document.querySelector('header#o-header elcos-header').shadowRoot.querySelector('button span.display-name') != null") == true) {
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

public $captca_solution_tried = 0;
function solveClickCaptcha()
{
    $this->exts->log("Start solving click captcha:");
    if ($this->exts->exists($this->captcha_form_selector)) {
        $this->exts->capture("solveClickCaptcha");
        $retry_count = 0;
        while ($retry_count < 5) {
            // $indications = str_replace("?", " ", $this->exts->extract($this->captcha_indications_selector, null, 'innerText'));
            $indicationsArray = array();
            $indications_sel = $this->exts->getElements('ol[class*="timeline-captcha"] li');
            foreach ($indications_sel as $key => $indication_sel) {
                $temp = $indication_sel->getAttribute('innerText');
                $temp = trim($temp);
                $this->exts->log($temp);
                array_push($indicationsArray, $temp);
            }
            $hcaptcha_challenger_wraper_selector = 'div[class*="captcha_images"]';
            $translatedIndication = "";
            foreach ($indicationsArray as $key => $indication) {
                $translatedIndication = $translatedIndication . ($key + 1) . '-' . $this->getTranslatedClickCaptchaInstruction($indication) . '.';
            }
            $this->exts->log("translatedIndications " . $translatedIndication);
            $captcha_instruction = "Click on the image in this order." . $translatedIndication;
            $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true); // use $language_code and $captcha_instruction if they changed captcha content
            $call_2captcha_retry = 0;
            while (($coordinates == '' || count($coordinates) != 6) && $call_2captcha_retry < 5) {
                $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true);
                $call_2captcha_retry++;
            }
            if ($coordinates != '') {
                foreach ($coordinates as $coordinate) {
                    $this->exts->click_by_xdotool($hcaptcha_challenger_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                }
                $this->exts->capture("After captcha clicked.");
            }
            $retry_count++;
            $this->captca_solution_tried++;

            $this->exts->capture('after-click-all-images');

            if ($this->exts->exists('div.justify-content-sm-start button[type="button"]')) {
                $this->exts->moveToElementAndClick('div.justify-content-sm-start button[type="button"]');
                sleep(15);
            }

            $this->exts->capture('after-solve-clickcaptcha');
            if (!$this->exts->exists($this->captcha_form_selector)) {
                $this->exts->log("Captcha solved!!!!!! About to continue process...");
                break;
            } else {
                $this->exts->log("Captcha not solved!!!!!! Refresh to retry...");
                $this->exts->refresh();
                sleep(10);
                if (!$this->exts->exists($this->captcha_form_selector)) {
                    break;
                }
            }
        }
    } else {
        $this->exts->log("Captcha not found!!!!!!");
    }
}
private function processClickCaptcha(
    $captcha_image_selector,
    $instruction = '',
    $lang_code = '',
    $json_result = false,
    $image_dpi = 75
) {
    $this->exts->log("--GET Coordinates By 2CAPTCHA--");
    $response = '';
    $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
    $source_image = imagecreatefrompng($image_path);
    imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

    $cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
    $this->exts->log('Executing command : ' . $cmd);
    exec($cmd, $output, $return_var);
    $this->exts->log('Command Result : ' . print_r($output, true));

    if (!empty($output)) {
        $output = trim($output[0]);
        if ($json_result) {
            if (strpos($output, '"status":1') !== false) {
                $response = json_decode($output, true);
                $response = $response['request'];
            }
        } else {
            if (strpos($output, 'coordinates:') !== false) {
                $array = explode("coordinates:", $output);
                $response = trim(end($array));
                $coordinates = [];
                $pairs = explode(';', $response);
                foreach ($pairs as $pair) {
                    preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
                    if (!empty($matches)) {
                        $coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
                    }
                }
                $this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
                $this->exts->log(print_r($coordinates, true));
                return $coordinates;
            }
        }
    }

    if ($response == '') {
        $this->exts->log("Can not get result from API");
    }
    return $response;
}

function getTranslatedClickCaptchaInstruction($originalInstruction)
{
    $result = null;
    try {
        $this->exts->openNewTab();
        sleep(1);
        $originalInstruction = preg_replace("/\r\n|\r|\n/", '%0A', $originalInstruction);
        $this->exts->log('originalInstruction: ' . $originalInstruction);
        $this->exts->openUrl('https://translate.google.com/?sl=fr&tl=en&text=' . $originalInstruction . '&op=translate');
        sleep(3);

        $acceptBtn = $this->exts->getElementByText('button', ['Agree to the use of cookies', 'Accept all'], null, false);
        if ($acceptBtn != null) {
            $acceptBtn->click();
            sleep(12);
            $this->exts->switchToInitTab();
            $this->exts->closeAllTabsButThis();
            $this->exts->openNewTab();
            sleep(1);
            $this->exts->openUrl('https://translate.google.com/?sl=fr&tl=en&text=' . $originalInstruction . '&op=translate');
            sleep(3);
        }
        // sleep(10);
        $result = $this->exts->extract('div c-wiz:nth-child(2) span[lang="en"] > span > span', null, 'innerText');
        $result = str_replace('%0A', "\n", $result);
        $this->exts->switchToInitTab();
        $this->exts->closeAllTabsButThis();
    } catch (\Exception $ex) {
        $this->exts->log("Failed to get translated instruction");
    }

    return $result;
}