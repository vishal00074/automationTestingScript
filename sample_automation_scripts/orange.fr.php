<?php // migrated the script and updated download code trigger no invoice in case if download link is not working
// Server-Portal-ID: 779712 - Last modified: 03.03.2025 11:36:45 UTC - User: 1
public $baseUrl = "https://www.orange.fr/portail";
public $loginUrl = "https://login.orange.fr/?return_url=https://www.orange.fr/portail";
public $homePageUrl = "https://id.orange.fr/auth_user/bin/auth_user.cgi?return_url=http://www.orange.fr";
public $username_selector = "input#login";
public $password_selector = "input#password";
public $submit_button_selector = "button#btnSubmit";
public $login_tryout = 0;
public $twofa_form_selector = "input#smsCode";
public     $month_names_fr = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');

public $captcha_form_selector = 'div[class*="captcha_images"]'; //'[id="captchaRow"] form[name="captcha-form"]';
public $captcha_image_selector = 'div#__next > div > div:nth-child(3) > div:nth-child(2) > ul > li';
public $captcha_submit_btn_selector = 'button.sc-gKseQn'; // 'button.sc-gKsewC';
public $captcha_indications_selector = 'ul.sc-uya65w-0.eatJrZ li';
public $captca_solution_tried = 0;

public $isNoInvoice = true;


/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->exts->openUrl($this->baseUrl);
    sleep(10);

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
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }

        for ($i = 0; $i < 10; $i++) {
            if ($this->exts->urlContains('/error403.html?status=error') || $this->exts->urlContains('error403.html?ref=idme-ssr&status=error')) {
                $this->clearChrome();
                sleep(1);

                $this->exts->openUrl($this->loginUrl);
                sleep(10);

                if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
                    $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
                    sleep(10);
                }
                sleep(20);
            } else break;
        }

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists($this->captcha_form_selector)) {
                $this->clearChrome();
                sleep(1);
                $this->exts->openUrl($this->loginUrl);
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
                $this->clearChrome();
                sleep(1);
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
            } else {
                break;
            }
        }

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists($this->captcha_form_selector)) {
                $this->clearChrome();
                sleep(1);
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
            } else {
                break;
            }
        }

        sleep(20);

        if (!$this->exts->exists($this->username_selector)) {
            $this->exts->openUrl($this->loginUrl);
        }

        $this->fillForm(0);
        sleep(10);
        for ($i = 0; $i < 3 && $this->exts->exists('div#alert-sessionExpired:not([style="display: none;"]) button[data-testid="button-reload"]'); $i++) {
            $this->exts->moveToElementAndClick('div#alert-sessionExpired:not([style="display: none;"]) button[data-testid="button-reload"]');
            $this->clearChrome();
            sleep(1);
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            $this->fillForm(0);
            sleep(10);
        }


        $this->checkFillTwoFactor();

        if ($this->exts->exists('button[data-testid="submit-mc"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="submit-mc"]');
            sleep(3);
            $this->checkFillTwoFactorForMobileAcc();
        }

        if ($this->exts->exists('button[data-testid="link-mc-later"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="link-mc-later"]');
            sleep(20);
        }

        $err_txt1 = "";
        if ($this->exts->getElement("h6#error-msg-box") != null) {
            $err_txt1 = $this->exts->getElement("h6#error-msg-box")->getAttribute('innerText');
        }

        $err_txt2 = "";
        if ($this->exts->getElement("span#default_password_error, label#password-invalid-feedback, label#login-invalid-feedback") != null) {
            $err_txt1 = $this->exts->getElement("span#default_password_error, label#password-invalid-feedback, label#login-invalid-feedback")->getAttribute('innerText');
        }

        if (($err_txt1 != "" && $err_txt1 != null) || ($err_txt2 != "" && $err_txt2 != null)) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        }

        $err_msg1 = $this->exts->extract('div [class="alert-container alert-container-sm alert-danger invalid-feedback"]  label[data-testid="input-password-invalid-feedback"]');
        $lowercase_err_msg = strtolower($err_msg1);
        $substrings = array('vérifiez l’adresse mail et le mot de passe saisis.', 'vérifiez', 'passe');
        foreach ($substrings as $substring) {
            if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                $this->exts->log($err_msg1);
                $this->exts->loginFailure(1);
                break;
            }
        }

        if (strpos($this->exts->getUrl(), '/changePassword') !== false) {
            $this->exts->log('Your current password is not secure enough and needs to be strengthened. ');
            $this->exts->capture('new_password!');
            $this->exts->account_not_ready();
        }

        if ($this->exts->getElement('input#new-password') != null && $this->exts->getElement('input#new-password')->isDisplayed()) {
            $this->exts->log('User must update new password');
            $this->exts->capture('User must update new password');
            $this->exts->account_not_ready();
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->invoicePage();
        } else {
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
        $this->invoicePage();
    }
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

            if ($this->exts->exists($this->username_selector)) {
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

            $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
            sleep(10);

            $this->checkFillTwoFactor();

            if ($this->exts->getElement('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password') != null) {
                $this->exts->moveToElementAndClick('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password');
                sleep(15);
            }

            if ($this->exts->getElement($this->password_selector) != null) {
                if ($this->exts->getElement($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(5);
                }

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
            $temp = $this->exts->extract('h6#error-msg-box-login');
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

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}
function getInnerTextByJS($selector_or_object, $parent = null)
{
    if ($selector_or_object == null) {
        $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
        return;
    }
    $element = $selector_or_object;
    if (is_string($selector_or_object)) {
        $element = $this->exts->getElement($selector_or_object, $parent);
        if ($element == null) {
            $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
        }
        if ($element == null) {
            $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
        }
    }
    if ($element != null) {
        return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
    }
}
function solveClickCaptcha()
{
    $this->exts->log("Start solving click captcha:");
    if ($this->exts->exists($this->captcha_form_selector)) {
        $this->exts->capture("solveClickCaptcha");
        $retry_count = 0;
        while ($retry_count < 5) {
            // $indications = str_replace("?", " ", $this->exts->extract($this->captcha_indications_selector, null, 'innerText'));
            $indicationsArray = array();
            $indications_sel = $this->exts->getElements('ol[class*="timeline-captcha"] li', null, 'css');
            foreach ($indications_sel as $key => $indication_sel) {
                $temp = $this->getInnerTextByJS($indication_sel);
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
            $coordinates = $this->exts->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true); // use $language_code and $captcha_instruction if they changed captcha content
            $call_2captcha_retry = 0;
            while (($coordinates == '' || count($coordinates) != 6) && $call_2captcha_retry < 5) {
                $coordinates = $this->exts->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true);
                $call_2captcha_retry++;
            }
            if ($coordinates != '') {
                $challenge_wraper = $this->exts->getElement($hcaptcha_challenger_wraper_selector);
                if ($challenge_wraper != null) {
                    foreach ($coordinates as $coordinate) {
                        $this->exts->log('Clicking X/Y: ' . $coordinate['x'] . '/' . $coordinate['y']);
                        $this->exts->click_by_xdotool($challenge_wraper, intval($coordinate['x']), intval($coordinate['y']));
                        sleep(2);
                    }
                    $this->exts->capture("After captcha clicked.");
                }
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

function getTranslatedClickCaptchaInstruction($originalInstruction)
{
    $result = null;
    try {
        $this->exts->open_new_window();
        sleep(1);
        $originalInstruction = preg_replace("/\r\n|\r|\n/", '%0A', $originalInstruction);
        $this->exts->log('originalInstruction: ' . $originalInstruction);
        $this->exts->openUrl('https://translate.google.com/?sl=fr&tl=en&text=' . $originalInstruction . '&op=translate');
        sleep(3);

        $acceptBtn = $this->exts->getElementByText('button', ['Agree to the use of cookies', 'Accept all'], null, false);
        if ($acceptBtn != null) {
            $acceptBtn->click();
            sleep(12);
            $this->exts->close_new_window();
            $this->exts->open_new_window();
            sleep(1);
            $this->exts->openUrl('https://translate.google.com/?sl=fr&tl=en&text=' . $originalInstruction . '&op=translate');
            sleep(3);
        }
        // sleep(10);
        $result = $this->getInnerTextByJS($this->exts->getElement('div c-wiz:nth-child(2) span[lang="en"] > span > span'));
        $result = str_replace('%0A', "\n", $result);
        $this->exts->close_new_window();
    } catch (\Exception $ex) {
        $this->exts->log("Failed to get translated instruction");
    }

    return $result;
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
            $this->exts->click_by_xdotool($two_factor_selector);
            $this->exts->type_text_by_xdotool($two_factor_code);
            sleep(1);
            $this->exts->moveToElementAndClick($submit_btn_selector);
            sleep(10);

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
            $this->exts->click_by_xdotool($two_factor_selector);
            $this->exts->type_text_by_xdotool($two_factor_code);

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

/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    sleep(10);
    try {
        if ($this->exts->execute_javascript("document.querySelector('header#o-header elcos-header').shadowRoot.querySelector('button[data-oevent-action=\"espaceclient\"]') != null") == true || $this->exts->exists('a[href*="deconnect"]')) {
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

function invoicePage()
{
    $this->exts->log("invoice Page");

    $this->exts->moveToElementAndClick('[class*="-is-connected"] a[id*="-identityLink"]');
    sleep(1);
    $this->exts->moveToElementAndClick('[data-oevent-action="factures"]');
    sleep(20);

    $currentURL = $this->exts->getUrl();
    if (strpos($currentURL, "pro.orange.fr") !== false) {
        $this->exts->openUrl("https://espaceclientpro.orange.fr/");
        sleep(15);

        if ($this->exts->getElement("#bill-details") != null) {
            $this->exts->moveToElementAndClick("#bill-details");
            sleep(15);
        }

        $str = "var div = document.querySelector('div.usabilla__overlay'); if (div != null) {  div.style.display = \"none\"; }";
        $this->exts->executeSafeScript($str);
        if ($this->exts->exists('button[id*="contract-toggle"] ~ ul li')) {
            $this->processProAccounts();
        } else {
            $this->processInvoicePro();
        }
    } else if (strpos($currentURL, "page=gt-home-page") === false) {
        if ($this->exts->getElement("a.espace-client-left") != null) {
            $this->exts->log(">>>>>[waitForLogin] In 1 condition");
            if ($this->exts->getElement("a.espace-client-left") != null) {
                $this->exts->moveToElementAndClick("a.espace-client-left");
                sleep(15);
            }

            if ($this->exts->getElement("div#fe_popin_content a.btnClose") != null) {
                $this->exts->moveToElementAndClick("div#fe_popin_content a.btnClose");
                sleep(15);
            }

            if ($this->exts->getElement("ul#contractContainer") != null) {
                if ($this->exts->getElement("ul#contractContainer > li > a.ec-carrousel-link-orange") != null) {
                    $this->exts->moveToElementAndClick("ul#contractContainer > li > a.ec-carrousel-link-orange");
                    sleep(15);

                    $this->collectContract_1();
                } else if ($this->exts->getElement("ul#contractContainer li.listeItem a[id*=\"carrousel-\"]") != null) {
                    $this->collectContract_2();
                } else {
                    $this->exts->log('No contract !!!');
                    // $this->exts->no_invoice();
                }
            } else {
                $this->exts->log('intermidiary url to be opened: ' + $this->exts->getUrl());
                $this->collectContract(0);
            }
        } else if ($this->exts->exists('a#o-identityLink')) {
            $prev_url = $this->exts->getUrl();
            $this->exts->moveToElementAndClick('a#o-identityLink');
            sleep(15);

            if (strpos($this->exts->getUrl(), 'login.orange.fr') !== false) {
                $this->exts->openUrl($prev_url);
                sleep(15);
            }

            $this->exts->moveToElementAndClick('div#ecarePopin a.btnClose');
            sleep(3);

            if (strpos($this->exts->getUrl(), "pro.orange.fr") !== false) {
                $this->exts->openUrl("https://espaceclientpro.orange.fr/");
                sleep(15);

                $this->exts->moveToElementAndClick("#bill-details");
                sleep(15);

                $str = "var div = document.querySelector('div.usabilla__overlay'); if (div != null) {  div.style.display = \"none\"; }";
                $this->exts->executeSafeScript($str);

                if ($this->exts->exists('button[id*="contract-toggle"] ~ ul li')) {
                    $this->processProAccounts();
                } else {
                    $this->processInvoicePro();
                }
            } else if ($this->exts->exists('#contractContainer li a')) {
                $this->collectContract_4();
            } else if ($this->exts->getElement('ul#contractContainer > li > a[href*="&idContrat="]') != null) {
                $this->collectContract_1();
            } else if ($this->exts->getElement('a[href="/factures-paiement"]') != null) {
                $this->exts->moveToElementAndClick('a[href="/factures-paiement"]');
                sleep(15);

                if ($this->exts->exists('a[href*="page=factures-historique"]')) {
                    $this->exts->moveToElementAndClick('a[href*="page=factures-historique"]');
                    sleep(15);

                    $account_id = trim(end(explode('idContrat=', $this->exts->getUrl())));

                    $this->processInvoice($account_id);
                } else if ($this->exts->exists('div.contract-item-container a[href*="facture-paiement"]')) {
                    $this->collectContract_3();
                } else if ($this->exts->exists('a[href*="/historique-des-factures"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/historique-des-factures"]');
                    sleep(15);

                    $this->processInvoice3();
                } else if ($this->exts->exists('a[data-e2e="bp-tile-historic"]')) {
                    $this->exts->moveToElementAndClick('a[data-e2e="bp-tile-historic"]');
                    sleep(15);
                    $this->processInvoice3();
                }
            } else if ($this->exts->getElement('div.derniere-facture a[href*="/facturation/factures"]')) {
                $this->exts->moveToElementAndClick('div.derniere-facture a[href*="/facturation/factures"]');
                sleep(15);

                $this->processInvoiceV2();
            } else if ($this->exts->getElement('a[href="/factures"]') != null) {
                $this->exts->log('>>>>>[waitForLogin] In 4 condition');
                $this->exts->moveToElementAndClick("a[href=\"/factures\"]");
                sleep(15);

                $no_invoices = $this->exts->extract('div.orders > p');

                if ($no_invoices != "" && $no_invoices != null) {
                    $this->exts->log("NO invoices !!!!!!");
                    // $this->exts->no_invoice();
                }
            } else if ($this->exts->getElement('div#o-identityLayer a[href*="espaceclientv3.orange.fr"][data-oevent-action="espaceclient"]')) {
                $this->exts->log(">>>>>[waitForLogin] In 3 condition");
                $this->exts->openUrl("https://espaceclientv3.orange.fr");
                sleep(15);

                $this->collectContract();
            } else {
                if ($this->exts->exists('form[name="new_profile"] input#new_profile_firstName')) {
                    $this->exts->log('Update new profile');
                    $this->exts->account_not_ready();
                }

                $this->exts->moveToElementAndClick('a#o-info-perso');
                sleep(15);

                $this->exts->moveToElementAndClick('a#facture');
                sleep(15);

                $this->selectAccount1();
            }
        } else if ($this->exts->getElement('a[href="/factures"]') != null) {
            $this->exts->log('>>>>>[waitForLogin] In 4 condition');
            $this->exts->moveToElementAndClick("a[href=\"/factures\"]");
            sleep(15);

            $no_invoices = $this->exts->extract('div.orders > p');

            if ($no_invoices != "" && $no_invoices != null) {
                $this->exts->log("NO invoices !!!!!!");
                // $this->exts->no_invoice();
            }
        } else if ($this->exts->exists('ul.left-menu a[href*="spaceclient_accueil"]')) {
            $this->exts->moveToElementAndClick('ul.left-menu a[href*="spaceclient_accueil"]');
            sleep(15);

            $this->selectAccount();
        } else {
            $this->exts->log(">>>>>[waitForLogin] In 3 condition");
            $this->exts->openUrl("https://espaceclientv3.orange.fr");
            sleep(15);

            $this->collectContract();
        }
    } else {
        $this->exts->log(">>>>>[waitForLogin] In 5 condition");
        $this->collectContract();
    }

    if ($this->isNoInvoice) {
        $this->exts->openUrl('https://espaceclientpro.orange.fr/contracts');
        sleep(15);

        if ($this->exts->exists('a[href*="/factures-paiement"]')) {
            $this->exts->moveToElementAndClick('a[href*="/factures-paiement"]');
            sleep(15);
        } else if ($this->exts->exists('ul#localNav1 a[href*="/factures-paiement"]')) {
            $this->exts->moveToElementAndClick('ul#localNav1 a[href*="/factures-paiement"]');
            sleep(15);

            $this->exts->moveToElementAndClick('a[href="/factures"]');
            sleep(15);
        }


        if ($this->exts->exists('a[href*="facture-paiement"]')) {
            $acc_urls = $this->exts->getElementsAttribute('a[href*="facture-paiement"]', 'href');
            foreach ($acc_urls as $acc_url) {
                $this->exts->openUrl($acc_url);
                sleep(15);

                $this->exts->moveToElementAndClick('a[data-e2e*="-historic"]');
                sleep(15);

                $this->processFacturePaiement();
            }
        } else {
            $this->exts->moveToElementAndClick('a[data-e2e*="-historic"]');
            sleep(15);

            $this->processFacturePaiement();
        }

        $this->exts->openUrl('https://espaceclientpro.orange.fr/contracts');
        sleep(15);

        if ($this->exts->exists('div.contracts-list.row ul li a')) {
            $contract_url = $this->exts->getUrl();
            $this->exts->log($contract_url);
            $acc_len = count($this->exts->getElements('div.contracts-list.row ul li a'));
            for ($i = 0; $i < $acc_len; $i++) {
                $acc_row =  $this->exts->getElements('div.contracts-list.row ul li a')[$i];

                try {
                    $this->exts->log('Click download button');
                    $acc_row->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$acc_row]);
                }
                sleep(15);

                if ($this->exts->exists('button.bill-details, .bill-summary .bill-details')) {
                    $this->exts->moveToElementAndClick('button.bill-details, .bill-summary .bill-details');
                    sleep(15);

                    $this->processProAccLatestInvoice();
                    $this->selectTabInvoiceYears();

                    $this->exts->capture('after-download-pro-invoice');

                    $this->exts->openUrl($contract_url);

                    // $this->exts->moveToElementAndClick('a[data-track-name="espace_client"], a#o-identityLink.o-identityLink-connected, a#EspaceClientConnected');
                    sleep(15);
                } else if (strpos($this->exts->getUrl(), '/facture-paiement/')) {
                    $this->exts->moveToElementAndClick('a[href*="historique-des-factures"]');
                    sleep(15);

                    $this->processFacturePaiement();

                    $this->exts->openUrl('https://espaceclientpro.orange.fr/contracts');
                    sleep(15);
                } else {
                    $this->exts->moveToElementAndClick('a[href="/factures-paiement"]');
                    sleep(15);

                    $facture_paiements_url = $this->exts->getElementsAttribute('a[href*="facture-paiement/"]', 'href');
                    foreach ($facture_paiements_url as $facture_paiement_url) {
                        $this->exts->openUrl($facture_paiement_url);
                        sleep(15);

                        $this->exts->moveToElementAndClick('a[href*="historique-des-factures"]');
                        sleep(15);

                        $this->processFacturePaiement();
                    }

                    $this->exts->openUrl('https://espaceclientpro.orange.fr/contracts');
                    sleep(15);
                }

                sleep(20);
            }
        } else {
            $this->exts->moveToElementAndClick('a[data-track-name="espace_client"]');
            sleep(15);

            $this->exts->moveToElementAndClick('button.bill-details, .bill-summary .bill-details');
            sleep(15);

            $this->processProAccLatestInvoice();
            $this->selectTabInvoiceYears();
        }

        $this->exts->moveToElementAndClick('div[id*="header-desktop"] .left-menu a[href*="r.orange.fr"][href*="mon-profil"]');
        sleep(15);

        $this->exts->moveToElementAndClick('#tab-facture > a');
        sleep(5);

        $this->exts->moveToElementAndClick('#tab-facture a[href="factures"]');
        sleep(15);

        $this->processRInvoices();
    }

    if ($this->isNoInvoice) {
        $this->exts->no_invoice();
    }

    $this->exts->success();
}

public $collectAccUrl = '';
function selectAccount()
{
    $this->exts->log("Select Account");
    $this->collectContract = $this->exts->getUrl();
    $this->exts->log('collectContract' . $this->collectContract);
    $this->exts->moveToElementAndClick('button#bill-details');
    sleep(15);
    $this->selectYear1();

    if ($this->exts->exists('button[id*="contract-toggle"] + ul li')) {
        $count_acc = count($this->exts->getElements('button[id*="contract-toggle"] + ul li'));

        for ($i = 0; $i < $count_acc; $i++) {
            $this->exts->openUrl($this->collectAccUrl);
            sleep(15);

            $this->exts->moveToElementAndClick('button[id*="contract-toggle"]');
            sleep(5);

            $acc_sel = 'button[id*="contract-toggle"] + ul li:nth-child(' . ($i + 1) . ')';
            $this->exts->moveToElementAndClick($acc_sel);
            sleep(15);

            $this->exts->moveToElementAndClick('button#bill-details');
            sleep(15);

            $this->selectYear1();
        }
    }
}

function selectAccount1()
{
    $this->exts->log("Select Account 1");
    if ($this->exts->exists('a[href*="?contract="]')) {
        $accounts = $this->exts->getElements('a[href*="?contract="]');
        $accounts_array = array();

        foreach ($accounts as $account) {
            $account_url = $account->getAttribute('href');
            $account_number = trim(explode('&', end(explode('?contract=', $account_url)))[0]);

            $acc = array(
                'account_url' => $account_url,
                'account_number' => $account_number
            );

            array_push($accounts_array, $acc);
        }

        $this->exts->log('Number of account: ' . count($accounts_array));

        foreach ($accounts_array as $account) {
            $this->exts->openUrl($account['account_url']);
            sleep(15);

            $this->exts->moveToElementAndClick('a[href*="page=factures-historique"]');
            sleep(15);

            $this->processInvoice1($account['account_number']);
        }
    } else if ($this->exts->getElement('#bill-details') != null) {
        $this->exts->moveToElementAndClick('#bill-details');
        sleep(15);

        $str = "var div = document.querySelector('div.usabilla__overlay'); if (div != null) {  div.style.display = \"none\"; }";
        $this->exts->executeSafeScript($str);
        sleep(2);

        $this->selectYear();
    } else if ($this->exts->getElement('ul#contractContainer > li > a[href*="&idContrat="]') != null) {
        $this->collectContract_1();
    }

    $this->exts->moveToElementAndClick('a#o-identityLink');
    sleep(15);

    if ($this->exts->exists('input[name="idContrat"]')) {
        $accounts = $this->exts->getElements('input[name="idContrat"]');
        $accounts_array = array();

        foreach ($accounts as $account) {
            $account_number = trim($account->getAttribute('value'));
            $account_url = 'https://espace-client.orange.fr/facture-paiement/' . $account_number;

            $acc = array(
                'account_url' => $account_url,
                'account_number' => $account_number
            );

            array_push($accounts_array, $acc);
        }

        $this->exts->log('Number of account: ' . count($accounts_array));

        foreach ($accounts_array as $account) {
            $this->exts->openUrl($account['account_url']);
            sleep(15);

            $this->exts->moveToElementAndClick('a[href*="/facture-paiement/"][href*="historique-des-factures"]');
            sleep(15);

            $this->processInvoice3($account['account_number']);
        }
    }

    if ($this->totalFiles == 0) {
        $this->exts->moveToElementAndClick('a#o-identityLink');
        sleep(15);

        $this->collectContract_1();
    }
}

function processInvoice1($contractId)
{
    $this->exts->log("Begin processInvoice 1");

    $currentUrl = $this->exts->getUrl();

    try {
        if ($this->exts->getElement('div[class*="BillHistory"] table > tbody > tr') != null) {
            $invoices = array();
            $receipts = $this->exts->getElements('div[class*="BillHistory"] table > tbody > tr');
            foreach ($receipts as $receipt) {
                $this->exts->log("each record");
                $tags = $this->exts->getElements('td', $receipt);
                if ($tags >= 2 && $this->exts->getElement('td[headers="ec-downloadCol"] a', $receipt) != null) {
                    $receiptDate = $tags[0]->getAttribute('innerText');
                    $receiptUrl = $this->exts->extract('td[headers="ec-downloadCol"] a', $receipt, 'href');
                    $receiptName = $this->exts->extract('td a[href*="&idFacture="] span.ec_visually_hidden', $receipt);
                    $receiptName = trim(explode("(", end(explode(" du ", $receiptName)))[0]);
                    $receiptName = $contractId . '_' . str_replace('/', '', $receiptName);
                    $receiptFileName = $receiptName . '.pdf';
                    $parsed_date = $this->exts->parse_date($receiptDate, 'j F Y', 'Y-m-d');
                    $receiptAmount = $tags[1]->getAttribute('innerText');
                    $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';

                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice URL: " . $receiptUrl);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $this->isNoInvoice = false;
                }
            }

            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                if ($this->totalFiles % 5 == 0) {
                    $this->exts->openUrl($currentUrl);
                    sleep(15);
                }
                $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->log("create file");
                    $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                } else {
                    $this->exts->openUrl($currentUrl);
                    sleep(15);
                    $this->downloadDirectAgain($invoice, 0);
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

function processInvoice3()
{
    $this->exts->log("Begin processInvoice3");

    $this->exts->capture('4-List-invoice-processInvoice3');
    $current_url = $this->exts->getUrl();

    $rows_len = count($this->exts->getElements('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr')[$i];
        $tags = $this->exts->getElements('td', $row);

        if (count($tags) >= 4 && $this->exts->getElement('a[class*="downloadIcon"]', $row) != null) {
            $download_button = $this->exts->getElement('a[class*="downloadIcon"]', $row);
            $invoiceName = '';
            $invoiceDate = trim($this->getInnerTextByJS($tags[1]));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[2]))) . ' EUR';

            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);

            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd F Y', 'Y-m-d', 'fr');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            try {
                $this->exts->log('Click download button');
                $download_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
            }

            $this->exts->moveToElementAndClick('button[data-e2e="download-link"]');
            // Trigger No permission in case if pdf not downloading
            sleep(5);
            $this->exts->capture('pdf-no-permission-0');

            $this->exts->waitTillPresent('div.alert-warning');

            if ($this->exts->exists('div.alert-warning')) {
                $this->exts->no_permission();
            } else {
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceFileName = basename($downloaded_file);
                    $invoiceName = explode('.pdf', $invoiceFileName)[0];
                    $invoiceName = explode('(', $invoiceName)[0];
                    $invoiceName = str_replace(' ', '', $invoiceName);
                    $this->exts->log('Final invoice name: ' . $invoiceName);
                    $invoiceFileName = $invoiceName . '.pdf';
                    @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    // $this->exts->log(__FUNCTION__.'::No download '.$invoiceName);
                    if ($this->exts->exists('button[data-e2e="download-link"]')) {

                        $this->exts->moveToElementAndClick('button[data-e2e="download-link"]');
                        // Trigger No permission in case if pdf not downloading
                        sleep(5);
                        $this->exts->capture('pdf-no-permission-0');

                        $this->exts->waitTillPresent('div.alert-warning');

                        if ($this->exts->exists('div.alert-warning')) {
                            $this->exts->no_permission();
                        } else {

                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf');

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $invoiceFileName = basename($downloaded_file);
                                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                                $invoiceName = explode('(', $invoiceName)[0];
                                $invoiceName = str_replace(' ', '', $invoiceName);
                                $this->exts->log('Final invoice name: ' . $invoiceName);
                                $invoiceFileName = $invoiceName . '.pdf';
                                @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                                if ($this->exts->invoice_exists($invoiceName)) {
                                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                                } else {
                                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                                    sleep(1);
                                }
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                            }
                        }
                    }

                    if ($this->exts->exists('button[data-e2e="pdf-cancel-popup"]')) {
                        $this->exts->moveToElementAndClick('button[data-e2e="pdf-cancel-popup"]');
                        sleep(5);
                    }

                    $this->exts->executeSafeScript('history.back();');
                    sleep(15);
                }
            }

            if (strpos($this->exts->getUrl(), 'voir-la-facture/true') !== false) {
                $this->exts->openUrl($current_url);
            }
        }
    }
}

function processInvoice4($contractId)
{
    $this->exts->log("Begin processInvoice4");

    $this->exts->capture('4-List-invoice-processInvoice4');

    try {
        if ($this->exts->getElement('table[aria-labelledby*="billsHistoryTitle"] > tbody > tr') != null) {
            $receipts = $this->exts->getElements('table[aria-labelledby*="billsHistoryTitle"] > tbody > tr');
            $invoices = array();
            foreach ($receipts as $i => $receipt) {
                $tags = $this->exts->getElements('td', $receipt);
                if (count($tags) >= 4 && $this->exts->getElement('td a[class*="downloadIcon"]', $receipt) != null) {
                    $receiptDate = $this->exts->extract('td[class*="dateColumn"] strong', $receipt);
                    $receiptDate = $this->translate_date_abbr(strtolower($receiptDate));
                    $receiptUrl = $this->exts->getElement('td a[class*="downloadIcon"]', $receipt);
                    $this->exts->executeSafeScript(
                        "arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
                        array($receiptUrl, $i)
                    );

                    $receiptUrl = 'a#invoice' . $i;
                    $receiptName = str_replace(" ", "", $receiptDate);
                    if ($account_number != '') {
                        $receiptName = $contractId . '_' . $receiptName;
                    }
                    $receiptFileName = $receiptName . '.pdf';
                    $parsed_date = $this->exts->parse_date($receiptDate, 'M Y', 'Y-m-d');
                    $receiptAmount = $this->exts->extract('td[class*="amountColumn"] strong', $receipt);
                    $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';

                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice URL: " . $receiptUrl);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'receiptUrl' => $receiptUrl,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName
                    );
                    array_push($invoices, $invoice);
                    $this->isNoInvoice = false;
                }
            }

            $this->exts->log("Invoice found: " . count($invoices));

            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                if ($this->exts->getElement($invoice['receiptUrl']) != null) {
                    if ($this->exts->document_exists($invoice['receiptFileName'])) {
                        continue;
                    }

                    $this->exts->moveToElementAndClick($invoice['receiptUrl']);

                    $this->exts->wait_and_check_download('pdf');

                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                    sleep(1);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->log("create file");
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    }
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

function selectYear1()
{
    if ($this->exts->getElement("div#bill-archive ul.nav-tabs li span") != null) {
        $count_years = count($this->exts->getElements('div#bill-archive ul.nav-tabs li span'));

        for ($i = 0; $i < $count_years; $i++) {
            $sel_y = "div#bill-archive ul.nav-tabs li:nth-child(" . ($i + 1) . ") span";
            if ($this->exts->getElement($sel_y) != null) {
                $this->exts->moveToElementAndClick($sel_y);
                sleep(5);
                $this->downloadInvoiceV1();
            }
        }
    } else {
        $this->downloadInvoiceV1();
    }
}

function processProAccounts()
{
    $this->exts->log('process multi pro accounts');
    $this->processInvoicePro();
    $count_acc_sels = count($this->exts->getElements('button[id*="contract-toggle"] ~ ul li:not(.attach-contract)'));
    for ($i = 0; $i < $count_acc_sels; $i++) {
        $this->exts->openUrl("https://espaceclientpro.orange.fr/");
        sleep(15);

        $acc_sel = $this->exts->getElements('button[id*="contract-toggle"] ~ ul li:not(.attach-contract)')[$i];
        try {
            $this->exts->log('Click download button');
            $acc_sel->click();
        } catch (\Exception $exception) {
            $this->exts->log('Click download button by javascript');
            $this->exts->executeSafeScript("arguments[0].click()", [$acc_sel]);
        }
        sleep(15);

        $this->exts->moveToElementAndClick("#bill-details");
        sleep(15);

        $str = "var div = document.querySelector('div.usabilla__overlay'); if (div != null) {  div.style.display = \"none\"; }";
        $this->exts->executeSafeScript($str);

        $this->processInvoicePro();
    }
}

function processInvoicePro()
{
    $this->exts->log("Begin downlaod invoice pro");

    sleep(15);

    try {
        if ($this->exts->getElement("#historical-bills-container .bill-separation.row") != null) {
            $invoices = array();
            $receipts = $this->exts->getElements('#historical-bills-container .bill-separation.row');
            $this->exts->log(count($receipts));
            $count = 0;
            $href = $this->exts->extract('#historical-bills-container .bill-separation.row:nth-child(1) a.bill-link', null, 'href');

            if ($href == "" || $href == null) {
                $this->exts->log("processInvoiceV1");
                $this->selectYear();
            } else {
                foreach ($receipts as $key => $receipt) {
                    $this->exts->log("each record");
                    if ($this->exts->getElement('', $receipt) != null) {
                        $receiptDate = $this->exts->extract('a.bill-link', $receipt, 'href');
                        $receiptDate = trim(explode("T", end(explode("billDate=", $receiptDate)))[0]);

                        $receiptUrl = $this->exts->extract('a.bill-link', $receipt, 'href');
                        $idContrat = trim(explode("&", end(explode("idContrat=", $receiptUrl)))[0]);
                        $receiptName = $idContrat . '_' . str_replace(",", "", $receiptDate);
                        $receiptFileName = $receiptName . '.pdf';
                        $parsed_date = $this->exts->parse_date($receiptDate, 'Y-m-d', 'Y-m-d');
                        $receiptAmount = $this->exts->extract('.bill-amount', $receipt);
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';

                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice URL: " . $receiptUrl);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("Invoice parsed_date: " . $parsed_date);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName,
                            'receiptUrl' => $receiptUrl,
                        );

                        array_push($invoices, $invoice);
                        $this->isNoInvoice = false;
                    }
                }

                $this->exts->log("Invoice found: " . count($invoices));
                foreach ($invoices as $invoice) {
                    $this->totalFiles += 1;
                    $this->pDownloadInvoiceV2($invoice, 0);
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

function pDownloadInvoiceV2($invoice, $count)
{
    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
    // $downloaded_file = $this->exts->download_current($receiptFileName);
    $this->exts->log("downloaded file");
    sleep(5);
    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
        $this->exts->log("create file");
        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
        sleep(5);
    } else {
        $count++;
        if ($count < 10) {
            $this->pDownloadInvoiceV2($invoice, $count);
        }
    }
}

function collectContract()
{
    $this->exts->log("collectContract");
    if ($this->exts->getElement(".facture a[href*=\"factures-accueil\"][href*=\"idContrat=\"]") != null) {
        $contracts = array();
        $trackerIds = "";
        $conts = $this->exts->getElements(".facture a[href*=\"factures-accueil\"][href*=\"idContrat=\"]");
        $this->exts->log(count($conts));
        foreach ($conts as $cont) {
            $url = $cont->getAttribute('href');
            array_push($contracts, $url);
        }

        foreach ($contracts as $url) {
            // $url = $cont->getAttribute('href');
            if (strpos($url, "idContrat=") !== false) {
                $contractId = trim(end(explode('idContrat=', $url)));
            } else {
                $contractId = trim(end(explode('idContract=', $url)));
            }

            if (strpos($trackerIds, $contractId) === false) {
                $trackerIds = $trackerIds . "__" . $contractId;

                $this->exts->log('Goto contract url: ' . $url);
                $this->exts->openUrl($url);
                sleep(15);

                $sms_otp_sel = "div#OTP_push_" . $contractId . " ul li#AuthSMS";
                $sms_otp_next = "form#formPopinOTP_" . $contractId . " button[type=\"submit\"]";
                $num_client_otp_sel = "div#OTP_push_" . $contractId . " ul li#AuthNumClient";
                if ($this->exts->getElement($sms_otp_sel) != null && $this->exts->getElement($sms_otp_sel)->isDisplayed()) {
                    $this->exts->moveToElementAndClick($sms_otp_sel);
                    sleep(5);

                    if ($this->exts->getElement($sms_otp_next) != null) {
                        $this->exts->moveToElementAndClick($sms_otp_next);
                        sleep(5);
                    }

                    $this->processTFA_SMS();
                } else if ($this->exts->getElement($num_client_otp_sel) != null && $this->exts->getElement($num_client_otp_sel)->isDisplayed()) {
                    $this->exts->moveToElementAndClick($num_client_otp_sel);
                    sleep(5);

                    $this->processTFA_NUM($contractId);
                }

                if ($this->exts->getElement("a[href*=\"page=factures-historique\"]") != null) {
                    $this->exts->moveToElementAndClick("a[href*=\"page=factures-historique\"]");
                    sleep(15);

                    $this->processInvoice($contractId);
                } else {
                    $this->exts->log("No invoices for:" . $contractId);
                }
            }
        }
    } else {
        if (strpos($this->exts->getUrl(), "page=gt-home-page") !== false) {
            $this->exts->log("No contracts!!!");
            // $this->exts->no_invoice();
        } else if ($this->exts->getElement("ul#contractContainer > li > a[href*=\"&idContrat=\"]") != null) {
            $this->collectContract_1();
        } else if ($this->exts->getElement("button#bill-details") != null) {
            $this->exts->log(">>>>>[waitForLogin] In 2 condition");
            $this->exts->moveToElementAndClick("button#bill-details");
            sleep(15);
            $this->selectYear();
        } else {
            $this->exts->openUrl("https://espaceclient.caraibe.orange.fr/group/espace-client/facturation/factures?p_p_id=billing_WAR_webcareportlets&p_p_lifecycle=0&p_p_state=normal&p_p_mode=view&p_p_col_id=column-1&p_p_col_pos=1&p_p_col_count=4&_billing_WAR_webcareportlets_action=allInvoices");
            sleep(15);

            if ($this->exts->getElement('a[href="/factures-paiement"]') != null) {
                $this->exts->moveToElementAndClick('a[href="/factures-paiement"]');
                sleep(15);

                $this->exts->moveToElementAndClick('a[href*="page=factures-historique"]');
                sleep(15);

                $this->processInvoiceV4();
            } else {
                $this->processInvoiceV2();
            }
        }
    }
}

function processInvoiceV4()
{
    $this->exts->log('Start downloadInvoice 1');

    $this->exts->capture('4-List-invoices');

    $allFiles = 0;

    if ($this->exts->getElement('div[class*="tableBillHistory"] table tbody tr') != null) {
        $receipts = $this->exts->getElements('div[class*="tableBillHistory"] table tbody tr');
        $invoices = array();
        foreach ($receipts as $receipt) {
            if ($this->exts->getElement('td[headers="ec-downloadCol"] a[href*="page=facture-telecharger"]', $receipt) != null) {
                $receiptDate = $this->exts->getElement('td[headers="ec-dateCol"]', $receipt)->getAttribute('innerText');
                $receiptUrl = $this->exts->getElement('td[headers="ec-downloadCol"] a[href*="page=facture-telecharger"]', $receipt)->getAttribute('href');
                $receiptName = str_replace(',', '', $receiptDate);
                $receiptFileName = $receiptName . '.pdf';
                $parsed_date = $this->exts->parse_date($receiptDate, 'd F Y', 'Y-m-d');
                $receiptAmount = $this->exts->getElement('td[headers="ec-amountCol"]', $receipt)->getAttribute('innerText');
                $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' USD';

                $this->exts->log("Invoice Date: " . $receiptDate);
                $this->exts->log("Invoice URL: " . $receiptUrl);
                $this->exts->log("Invoice Name: " . $receiptName);
                $this->exts->log("Invoice FileName: " . $receiptFileName);
                $this->exts->log("Invoice parsed_date: " . $parsed_date);
                $this->exts->log("Invoice Amount: " . $receiptAmount);
                $invoice = array(
                    'receiptName' => $receiptName,
                    'receiptUrl' => $receiptUrl,
                    'parsed_date' => $parsed_date,
                    'receiptAmount' => $receiptAmount,
                    'receiptFileName' => $receiptFileName
                );
                array_push($invoices, $invoice);
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log("Count Invoices: " . count($invoices));

        foreach ($invoices as $invoice) {
            $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
            $this->exts->log("downloaded file");
            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                $this->exts->log("create file");
                $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
            }

            $allFiles += 1;
        }
    }
}

function collectContract_1()
{
    $this->exts->log("collectContract 1");
    if ($this->exts->getElement("ul#contractContainer > li > a[href*=\"&idContra\"]") != null) {
        $contracts = array();
        $trackerIds = "";
        $conts = $this->exts->getElements('ul#contractContainer > li > a[href*="&idContra"]');
        $this->exts->log(count($conts));
        foreach ($conts as $cont) {
            $url = $cont->getAttribute('href');
            array_push($contracts, $url);
        }

        foreach ($contracts as $url) {
            // $url = $cont->getAttribute('href');
            if (strpos($url, "idContrat=") !== false) {
                $contractId = trim(end(explode('idContrat=', $url)));
            } else {
                $contractId = trim(end(explode('idContract=', $url)));
            }
            if (strpos($trackerIds, $contractId) === false) {
                $trackerIds = $trackerIds . "__" . $contractId;

                $this->exts->log('Goto contract url: ' . $url);
                $this->exts->openUrl($url);
                sleep(15);

                $sms_otp_sel = "div#OTP_push_" . $contractId . " ul li#AuthSMS";
                $sms_otp_next = "form#formPopinOTP_" . $contractId . " button[type=\"submit\"]";
                $num_client_otp_sel = "div#OTP_push_" . $contractId . " ul li#AuthNumClient";
                if ($this->exts->getElement($sms_otp_sel) != null && $this->exts->getElement($sms_otp_sel)->isDisplayed()) {
                    $this->exts->moveToElementAndClick($sms_otp_sel);
                    sleep(5);

                    if ($this->exts->getElement($sms_otp_next) != null) {
                        $this->exts->moveToElementAndClick($sms_otp_next);
                        sleep(5);
                    }

                    $this->processTFA_SMS();
                } else if ($this->exts->getElement($num_client_otp_sel) != null && $this->exts->getElement($num_client_otp_sel)->isDisplayed()) {
                    $this->exts->moveToElementAndClick($num_client_otp_sel);
                    sleep(5);

                    $this->processTFA_NUM($contractId);
                }

                if ($this->exts->getElement("a[href*=\"?page=factures-accueil&idContra\"]") != null) {
                    $this->exts->moveToElementAndClick("a[href*=\"?page=factures-accueil&idContra\"]");
                    sleep(15);

                    if ($this->exts->getElement("a[href*=\"page=factures-historique\"]") != null) {
                        $this->exts->moveToElementAndClick("a[href*=\"page=factures-historique\"]");
                        sleep(15);
                        $this->processInvoice($contractId);
                    } else {
                        $this->exts->log("No invoices for:" . $contractId);
                    }
                } else if ($this->exts->getElement('div.facture') != null) {
                    $this->exts->moveToElementAndClick('div.facture');
                    sleep(15);

                    if ($this->exts->getElement("a[href*=\"page=factures-historique\"]") != null) {
                        $this->exts->moveToElementAndClick("a[href*=\"page=factures-historique\"]");
                        sleep(15);
                        $this->processInvoice($contractId);
                    } else {
                        $this->exts->log("No invoices for:" . $contractId);
                    }
                } else {
                    $this->exts->log("No invoices for:" . $contractId);
                }
            }
        }
    } else {
        if (strpos($this->exts->getUrl(), "page=gt-home-page") !== false) {
            $this->exts->log("No contracts!!!");
            // $this->exts->no_invoice();
        } else if ($this->exts->getElement("button#bill-details") != null) {
            $this->exts->log(">>>>>[waitForLogin] In 2 condition");
            $this->exts->moveToElementAndClick("button#bill-details");
            sleep(15);
            $this->selectYear();
        } else {
            $this->exts->openUrl("https://espaceclient.caraibe.orange.fr/group/espace-client/facturation/factures?p_p_id=billing_WAR_webcareportlets&p_p_lifecycle=0&p_p_state=normal&p_p_mode=view&p_p_col_id=column-1&p_p_col_pos=1&p_p_col_count=4&_billing_WAR_webcareportlets_action=allInvoices");
            sleep(15);
            $this->processInvoiceV2();
        }
    }
}

function collectContract_2()
{
    $this->exts->log("collectContract 2");
    if ($this->exts->getElement("ul#contractContainer li.listeItem a[id*=\"carrousel-\"]") != null) {
        $contracts = array();
        $trackerIds = "";
        $conts = $this->exts->getElements("ul#contractContainer li.listeItem a[id*=\"carrousel-\"]");
        $this->exts->log(count($conts));

        foreach ($conts as $cont) {
            $url = $cont->getAttribute('href');
            array_push($contracts, $url);
        }

        foreach ($contracts as $url) {
            // $url = $cont->getAttribute('href');
            if (strpos($url, "idContrat=") !== false) {
                $contractId = trim(end(explode('idContrat=', $url)));
            } else {
                $contractId = trim(end(explode('idContract=', $url)));
            }
            if (strpos($trackerIds, $contractId) === false) {
                $trackerIds = $trackerIds . "__" . $contractId;

                $this->exts->log('Goto contract url: ' . $url);
                $this->exts->openUrl($url);
                sleep(15);

                $sms_otp_sel = "div#OTP_push_" . $contractId . " ul li#AuthSMS";
                $sms_otp_next = "form#formPopinOTP_" . $contractId . " button[type=\"submit\"]";
                $num_client_otp_sel = "div#OTP_push_" . $contractId . " ul li#AuthNumClient";
                if ($this->exts->getElement($sms_otp_sel) != null && $this->exts->getElement($sms_otp_sel)->isDisplayed()) {
                    $this->exts->moveToElementAndClick($sms_otp_sel);
                    sleep(5);

                    if ($this->exts->getElement($sms_otp_next) != null) {
                        $this->exts->moveToElementAndClick($sms_otp_next);
                        sleep(5);
                    }

                    $this->processTFA_SMS();
                } else if ($this->exts->getElement($num_client_otp_sel) != null && $this->exts->getElement($num_client_otp_sel)->isDisplayed()) {
                    $this->exts->moveToElementAndClick($num_client_otp_sel);
                    sleep(5);

                    $this->processTFA_NUM($contractId);
                }

                if ($this->exts->getElement("a[href*=\"?page=factures-accueil&idContrat=\"]") != null) {
                    $this->exts->moveToElementAndClick("a[href*=\"?page=factures-accueil&idContrat=\"]");
                    sleep(15);

                    if ($this->exts->getElement("a[href*=\"page=factures-historique\"]") != null) {
                        $this->exts->moveToElementAndClick("a[href*=\"page=factures-historique\"]");
                        sleep(15);
                        $this->processInvoice($contractId);
                    } else {
                        $this->exts->log("No invoices for:" . $contractId);
                    }
                } else if ($this->exts->getElement("div.facture a") != null) {
                    $this->exts->moveToElementAndClick("div.facture a");
                    sleep(15);

                    if ($this->exts->getElement("a[href*=\"page=factures-historique\"]") != null) {
                        $this->exts->moveToElementAndClick("a[href*=\"page=factures-historique\"]");
                        sleep(15);
                        $this->processInvoice($contractId);
                    } else {
                        $this->exts->log("No invoices for:" . $contractId);
                    }
                } else {
                    $this->exts->log("No invoices for:" . $contractId);
                }
            }
        }
    } else {
        if (strpos($this->exts->getUrl(), "page=gt-home-page") !== false) {
            $this->exts->log("No contracts!!!");
            // $this->exts->no_invoice();
        } else if ($this->exts->getElement("button#bill-details") != null) {
            $this->exts->log(">>>>>[waitForLogin] In 2 condition");
            $this->exts->moveToElementAndClick("button#bill-details");
            sleep(15);
            $this->selectYear();
        } else {
            $this->exts->openUrl("https://espaceclient.caraibe.orange.fr/group/espace-client/facturation/factures?p_p_id=billing_WAR_webcareportlets&p_p_lifecycle=0&p_p_state=normal&p_p_mode=view&p_p_col_id=column-1&p_p_col_pos=1&p_p_col_count=4&_billing_WAR_webcareportlets_action=allInvoices");
            sleep(15);
            $this->processInvoiceV2();
        }
    }
}

function collectContract_3()
{
    $this->exts->log("collectContract 3");
    if ($this->exts->exists('div.contract-item-container a[href*="facture-paiement"]')) {
        $accounts = $this->exts->getElements('div.contract-item-container a[href*="facture-paiement"]');
        $accounts_array = array();

        foreach ($accounts as $account) {
            $account_url = $account->getAttribute('href');
            $account_number = trim(end(explode('/', $account_url)));

            $acc = array(
                'account_url' => $account_url,
                'account_number' => $account_number
            );

            array_push($accounts_array, $acc);
        }

        $this->exts->log('Number of account: ' . count($accounts_array));

        foreach ($accounts_array as $account) {
            $this->exts->openUrl($account['account_url']);
            sleep(15);

            $this->exts->moveToElementAndClick('a[href*="page=factures-historique"]');
            sleep(15);

            $this->processInvoice($account['account_number']);
        }
    }
}

function collectContract_4()
{
    $this->exts->log("collectContract 4");
    if ($this->exts->exists('#contractContainer li a')) {
        $accounts = $this->exts->getElements('#contractContainer li a');
        $accounts_array = array();

        foreach ($accounts as $account) {
            $account_url = $account->getAttribute('href');
            $account_url = trim(explode('#', $account_url)[0]);

            if ($account_url == "" || strpos($account_url, 'http') === false || strpos($account_url, 'boutique.orange.fr') !== false || strpos($account_url, 'cont=AJOUT') !== false) {
                continue;
            }

            if (strpos($account_url, "idContrat=") !== false) {
                $account_number = trim(end(explode('idContrat=', $account_url)));
            } else if (strpos($account_url, "idContract=") !== false) {
                $account_number = trim(end(explode('idContract=', $account_url)));
            } else {
                $account_number = trim(end(explode('/', $account_url)));
            }

            $this->exts->log("account_url: " . $account_url);
            $this->exts->log("account_number: " . $account_number);

            $acc = array(
                'account_url' => $account_url,
                'account_number' => $account_number
            );

            array_push($accounts_array, $acc);
        }

        $this->exts->log('Number of account: ' . count($accounts_array));

        foreach ($accounts_array as $account) {
            $this->exts->openUrl($account['account_url']);
            sleep(15);

            if (strpos($this->exts->getUrl(), "pro.orange.fr") !== false) {
                $this->exts->moveToElementAndClick("#bill-details");
                sleep(15);

                $str = "var div = document.querySelector('div.usabilla__overlay'); if (div != null) {  div.style.display = \"none\"; }";
                $this->exts->executeSafeScript($str);

                $this->processInvoicePro();
            } else if (strpos($account['account_url'], '&idContra') !== false) {
                $sms_otp_sel = "div#OTP_push_" . $account['account_number'] . " ul li#AuthSMS";
                $sms_otp_next = "form#formPopinOTP_" . $account['account_number'] . " button[type=\"submit\"]";
                $num_client_otp_sel = "div#OTP_push_" . $account['account_number'] . " ul li#AuthNumClient";
                if ($this->exts->getElement($sms_otp_sel) != null && $this->exts->getElement($sms_otp_sel)->isDisplayed()) {
                    $this->exts->moveToElementAndClick($sms_otp_sel);
                    sleep(5);

                    if ($this->exts->getElement($sms_otp_next) != null) {
                        $this->exts->moveToElementAndClick($sms_otp_next);
                        sleep(5);
                    }

                    $this->processTFA_SMS();
                } else if ($this->exts->getElement($num_client_otp_sel) != null && $this->exts->getElement($num_client_otp_sel)->isDisplayed()) {
                    $this->exts->moveToElementAndClick($num_client_otp_sel);
                    sleep(5);

                    $this->processTFA_NUM($account['account_number']);
                }

                if ($this->exts->getElement('a[href*="?page=factures-accueil&idContra="]') != null) {
                    $this->exts->moveToElementAndClick('a[href*="?page=factures-accueil&idContra="]');
                    sleep(15);
                } else if ($this->exts->getElement("div.facture a") != null) {
                    $this->exts->moveToElementAndClick("div.facture a");
                    sleep(15);
                }

                if ($this->exts->getElement('a[href*="page=factures-historique"]') != null) {
                    $this->exts->moveToElementAndClick('a[href*="page=factures-historique"]');
                    sleep(15);
                    $this->processInvoice($account['account_number']);
                } else if ($this->exts->getElement('a[href*="page=factures-accueil"]')) {
                    $this->exts->moveToElementAndClick('a[href*="page=factures-accueil"]');
                    sleep(15);
                    $this->exts->moveToElementAndClick('a[href*="/historique-des-factures"]');
                    sleep(15);
                    $this->processInvoice4($account['account_number']);
                } else {
                    $this->exts->log("No invoices for:" . $account['account_number']);
                }
            } else if ($this->exts->exists('#contractContainer li a')) {
                $this->collectContract_1();
            } else {
                $this->exts->moveToElementAndClick('a[href*="page=factures-historique"]');
                sleep(15);

                $this->processInvoice($account['account_number']);
            }
        }
    }
}

function processInvoice($contractId)
{
    $this->exts->log("Begin processInvoice");
    $currentUrl = $this->exts->getUrl();
    try {
        if ($this->exts->getElement('div[class*="BillHistory"] table > tbody > tr') != null) {
            $invoices = array();
            $receipts = $this->exts->getElements('div[class*="BillHistory"] table > tbody > tr');
            foreach ($receipts as $receipt) {
                $this->exts->log("each record");
                $tags = $this->exts->getElements('td', $receipt);
                if ($tags >= 2 && $this->exts->getElement('td[headers="ec-downloadCol"] a', $receipt) != null) {
                    $receiptDate = $tags[0]->getAttribute('innerText');
                    $receiptUrl = $this->exts->extract('td[headers="ec-downloadCol"] a', $receipt, 'href');
                    $receiptName = $this->exts->extract('td a[href*="&idFacture="] span.ec_visually_hidden', $receipt);
                    $receiptName = trim(explode("(", end(explode(" du ", $receiptName)))[0]);
                    $receiptName = $contractId . '_' . str_replace('/', '', $receiptName);
                    $receiptFileName = $receiptName . '.pdf';
                    $parsed_date = $this->exts->parse_date($receiptDate, 'j F Y', 'Y-m-d');
                    $receiptAmount = $tags[1]->getAttribute('innerText');
                    $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';

                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice URL: " . $receiptUrl);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName,
                        'receiptUrl' => $receiptUrl,
                    );

                    array_push($invoices, $invoice);
                    $this->isNoInvoice = false;
                }
            }

            $this->exts->log("Invoice found: " . count($invoices));
            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                if ($this->totalFiles % 5 == 0) {
                    $this->exts->openUrl($currentUrl);
                    sleep(15);
                }
                $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->log("create file");
                    $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                } else {
                    $this->exts->openUrl($currentUrl);
                    sleep(15);
                    $this->downloadDirectAgain($invoice, 0);
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

function downloadDirectAgain($invoice, $count)
{
    $this->exts->log("Download again invoice: " . $invoice['receiptName']);

    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
        $this->exts->log("create file");
        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
    } else {
        $count += 1;
        if ($count < 10) {
            $this->downloadDirectAgain($invoice, $count);
        }
    }
}

function processInvoiceV2()
{
    $this->exts->log("Begin processInvoiceV2");
    $currentUrl = $this->exts->getUrl();

    try {
        if ($this->exts->getElement(".last-invoice-tab div") != null) {
            $div_len = count($this->exts->getElements('.last-invoice-tab div'));
            $span_date_len = count($this->exts->getElements('.last-invoice-tab div span.date'));
            if ($div_len == 1 && $span_date_len > 1) {
                $this->processInvoiceV3();
            } else {
                $invoices = array();
                $receipts = $this->exts->getElements('.last-invoice-tab div');
                foreach ($receipts as $receipt) {
                    if ($this->exts->getElement('.lien-chevron a', $receipt) != null) {
                        $receiptDate = $this->exts->extract('.date', $receipt);
                        $this->exts->log("invoice date: " . $receiptDate);
                        $receiptUrl = $this->exts->extract('.lien-chevron a', $receipt, 'href');
                        $receiptName = $this->exts->extract('.lien-chevron', $receipt, 'id');
                        $receiptName = trim(end(explode("_", $receiptName)));
                        $receiptFileName = $receiptName . '.pdf';
                        $this->exts->log("Inovice name: " . $receiptName);
                        $this->exts->log("Invoice file name: " . $receiptFileName);
                        $this->exts->log("Invoice url: " . $receiptUrl);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptAmount = $this->exts->extract('.prix', $receipt);
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName,
                            'receiptUrl' => $receiptUrl,
                        );

                        array_push($invoices, $invoice);
                        $this->isNoInvoice = false;
                    }
                }

                $this->exts->log("Invoice found: " . count($invoices));
                foreach ($invoices as $c => $invoice) {
                    if ($c % 5 == 0) {
                        $this->exts->openUrl($currentUrl);
                        sleep(15);
                    }

                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("downloaded file");
                    sleep(1);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->log("create file");
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    } else {
                        $this->exts->openUrl($currentUrl);
                        sleep(15);
                        $this->downloadDirectAgain($invoice, 0);
                    }
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

function processInvoiceV3()
{
    $this->exts->log("Begin processInvoiceV3");

    $currentUrl = $this->exts->getUrl();

    $allFiles = 0;

    sleep(15);

    try {
        if ($this->exts->getElement(".last-invoice-tab div") != null) {
            $invoices = array();
            $dates = $this->exts->getElements('.last-invoice-tab div span.date');
            $amounts = $this->exts->getElements('.last-invoice-tab div span.prix');
            $URLs = $this->exts->getElements('.last-invoice-tab div a[id*="id_lnk_facturation_facture"]');
            $this->exts->log(count($receipts));

            for ($i = 0; $i < count($dates); $i++) {
                $receiptDate = $dates[$i]->getAttribute('innerText');
                $this->exts->log('Receipt Date: ' . $receiptDate);
                $receiptUrl = $URLs[$i]->getAttribute('href');
                $this->exts->log('Receipt URL:' . $receiptUrl);
                $receiptName = $URLs[$i]->getAttribute('id');
                $receiptName = trim(end(explode("_", $receiptName)));
                $receiptFileName = $receiptName . '.pdf';
                $this->exts->log('Receipt Name: ' . $receiptName);
                $this->exts->log('Receipt Filename: ' . $receiptFileName);
                $parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
                $this->exts->log($parsed_date);
                $receiptAmount = $amounts[$i]->getAttribute('innerText');
                $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';
                $this->exts->log($receiptAmount);
                $invoice = array(
                    'receiptName' => $receiptName,
                    'parsed_date' => $parsed_date,
                    'receiptAmount' => $receiptAmount,
                    'receiptFileName' => $receiptFileName,
                    'receiptUrl' => $receiptUrl,
                );

                array_push($invoices, $invoice);
                $this->isNoInvoice = false;
            }

            $this->exts->log('Invoice found: ' . count($invoices));
            foreach ($invoices as $c => $invoice) {
                if ($c % 5 == 0) {
                    $this->exts->openUrl($currentUrl);
                    sleep(15);
                }
                $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                $this->exts->log("downloaded file");
                sleep(1);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->log("create file");
                    $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                } else {
                    $this->exts->openUrl($currentUrl);
                    sleep(15);
                    $this->downloadDirectAgain($invoice, 0);
                }

                $allFiles += 1;
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

public $totalFiles = 0;
function selectYear()
{
    if ($this->exts->getElement("div#bill-archive ul.nav-tabs li span") != null) {
        $count_years = count($this->exts->getElements('div#bill-archive ul.nav-tabs li span'));

        for ($i = 0; $i < $count_years; $i++) {
            $sel_y = "div#bill-archive ul.nav-tabs li:nth-child(" . ($i + 1) . ") span";
            if ($this->exts->getElement($sel_y) != null) {
                $this->exts->moveToElementAndClick($sel_y);
                sleep(5);
                $this->downloadInvoiceV1();
            }
        }
    } else {
        $this->downloadInvoiceV1();
    }
}

public function translate_date_abbr($date_str)
{
    $this->month_names_fr = array('janvier', 'vrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'ao', 'septembre', 'octobre', 'novembre', 'cembre');

    for ($i = 0; $i < count($this->month_names_fr); $i++) {
        if (stripos($date_str, $this->month_names_fr[$i]) !== FALSE) {
            $date_str = $this->exts->month_abbr_en[$i] . ' ' . trim(end(explode(' ', $date_str)));
            break;
        }
    }
    return $date_str;
}

function downloadInvoiceV1()
{
    $this->exts->log("Begin downlaod invoice 1");

    $currentURL = $this->exts->getUrl();

    try {
        if ($this->exts->getElement('div.latest-bill') != null) {
            $this->isNoInvoice = false;
            $idContrat = trim(explode("/", end(explode("/contract/", $currentURL)))[0]);
            $receiptDate = $this->exts->extract('div.latest-bill span p.latest-bill-title');
            $receiptDate = $this->translate_date_abbr(strtolower($receiptDate));
            $receiptUrl = 'div.latest-bill div#latest-bill-document a';
            $receiptName = $idContrat . "_" . str_replace(" ", "", $receiptDate);
            $receiptFileName = $receiptName . '.pdf';
            $parsed_date = $this->exts->parse_date($receiptDate, 'M Y', 'Y-m-d');
            $receiptAmount = $this->exts->extract('div.latest-bill div#latest-bill-document a span.large-bill');
            $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';

            $this->exts->log("Invoice Date: " . $receiptDate);
            $this->exts->log("Invoice URL: " . $receiptUrl);
            $this->exts->log("Invoice Name: " . $receiptName);
            $this->exts->log("Invoice FileName: " . $receiptFileName);
            $this->exts->log("Invoice parsed_date: " . $parsed_date);
            $this->exts->log("Invoice Amount: " . $receiptAmount);

            $invoice = array(
                'receiptName' => $receiptName,
                'parsed_date' => $parsed_date,
                'receiptAmount' => $receiptAmount,
                'receiptFileName' => $receiptFileName,
                'receiptUrl' => $receiptUrl,
            );

            $this->totalFiles += 1;
            if (!$this->exts->document_exists($invoice['receiptFileName'])) {
                $this->pDownloadInvoiceV1($invoice, 1);
            }
        }

        if ($this->exts->getElement("div#bill-archive div#historical-bills-container div.bill-separation") != null) {
            $invoices = array();
            $receipts = $this->exts->getElements('div#bill-archive div#historical-bills-container div.bill-separation.row');
            $this->exts->log(count($receipts));
            foreach ($receipts as $i => $receipt) {
                $this->exts->log("each record");
                if ($this->exts->getElement('div a.bill-link', $receipt) != null) {
                    $receiptDate = $this->exts->extract('span.capitalize', $receipt);
                    $receiptDate = $this->translate_date_abbr(strtolower($receiptDate));
                    $this->exts->log($receiptDate);
                    $receiptUrl = $this->exts->getElement('div a.bill-link', $receipt);
                    $this->exts->executeSafeScript(
                        "arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
                        array($receiptUrl, $i)
                    );

                    $receiptUrl = "div#bill-archive div#historical-bills-container div.bill-separation.row div a.bill-link#invoice" . $i;
                    $idContrat = trim(explode("/", end(explode("/contract/", $currentURL)))[0]);
                    $receiptName = $idContrat . "_" . str_replace(" ", "", $receiptDate);
                    $receiptFileName = $receiptName . '.pdf';
                    $this->exts->log($receiptName);
                    $this->exts->log($receiptFileName);
                    $this->exts->log($receiptUrl);
                    $parsed_date = $this->exts->parse_date($receiptDate, 'M Y', 'Y-m-d');
                    $this->exts->log($parsed_date);
                    $receiptAmount = $this->exts->extract('span.bill-amount', $receipt);
                    $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';
                    $this->exts->log($receiptAmount);
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName,
                        'receiptUrl' => $receiptUrl,
                    );

                    array_push($invoices, $invoice);
                    $this->isNoInvoice = false;
                }
            }

            $this->exts->log("Invoice found: " . count($invoices));

            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                if (!$this->exts->document_exists($invoice['receiptFileName'])) {
                    $this->pDownloadInvoiceV1($invoice, 1);
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

function pDownloadInvoiceV1($invoice, $count)
{
    $downloaded_file = $this->exts->click_and_print($invoice['receiptUrl'], $invoice['receiptFileName']);
    $this->exts->log("downloaded file");
    sleep(10);
    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
        $this->exts->log("create file");
        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
        sleep(5);
    } else {
        $count++;
        if ($count < 5) {
            $this->pDownloadInvoiceV1($invoice, $count);
        }
    }
}

// Apply new cases
private function processFacturePaiement()
{
    $this->exts->capture("4-invoices-page-FacturePaiement");
    $invoices = [];

    $current_url = $this->exts->getUrl();

    $rows_len = count($this->exts->getElements('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr, [aria-labelledby*="billsHistoryTitle"] table tbody tr'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr, [aria-labelledby*="billsHistoryTitle"] table tbody tr')[$i];
        $tags = $this->exts->getElements('td', $row);

        if (count($tags) >= 4 && $this->exts->getElement('a[class*="downloadIcon"]', $row) != null) {
            $download_button = $this->exts->getElement('a[class*="downloadIcon"]', $row);
            $invoiceName = '';
            $invoiceDate = trim($this->getInnerTextByJS($tags[1]));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[2]))) . ' EUR';

            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);

            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd F Y', 'Y-m-d', 'fr');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            try {
                $this->exts->log('Click download button');
                $download_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
            }

            $this->exts->moveToElementAndClick('button[data-e2e="download-link"]');
            // Trigger No permission in case if pdf not downloading
            sleep(5);
            $this->exts->capture('pdf-no-permission-0');

            $this->exts->waitTillPresent('div.alert-warning');

            if ($this->exts->exists('div.alert-warning')) {
                $this->exts->no_permission();
            } else {
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceFileName = basename($downloaded_file);
                    $invoiceName = explode('.pdf', $invoiceFileName)[0];
                    $invoiceName = explode('(', $invoiceName)[0];
                    $invoiceName = str_replace(' ', '', $invoiceName);
                    $this->exts->log('Final invoice name: ' . $invoiceName);
                    $invoiceFileName = $invoiceName . '.pdf';
                    @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    // $this->exts->log(__FUNCTION__.'::No download '.$invoiceName);
                    if ($this->exts->exists('button[data-e2e="download-link"]')) {
                        $this->exts->moveToElementAndClick('button[data-e2e="download-link"]');
                        // Trigger No permission in case if pdf not downloading
                        sleep(5);
                        $this->exts->capture('pdf-no-permission-0');

                        $this->exts->waitTillPresent('div.alert-warning');

                        if ($this->exts->exists('div.alert-warning')) {
                            $this->exts->no_permission();
                        } else {

                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf');

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $invoiceFileName = basename($downloaded_file);
                                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                                $invoiceName = explode('(', $invoiceName)[0];
                                $invoiceName = str_replace(' ', '', $invoiceName);
                                $this->exts->log('Final invoice name: ' . $invoiceName);
                                $invoiceFileName = $invoiceName . '.pdf';
                                @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                                if ($this->exts->invoice_exists($invoiceName)) {
                                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                                } else {
                                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                                    sleep(1);
                                }
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                            }
                        }
                    }

                    if ($this->exts->exists('button[data-e2e="pdf-cancel-popup"]')) {
                        $this->exts->moveToElementAndClick('button[data-e2e="pdf-cancel-popup"]');
                        sleep(5);
                    }

                    $this->exts->executeSafeScript('history.back();');
                    sleep(15);
                }
            }



            if (strpos($this->exts->getUrl(), 'voir-la-facture/true') !== false) {
                $this->exts->openUrl($current_url);
            }
        }
    }
}

private function processRInvoices()
{
    $this->exts->capture("4-Rinvoices-page");
    $invoices = [];

    $rows = $this->exts->getElements('table#table-bills tbody tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td, th', $row);
        if (count($tags) >= 3 && $this->exts->getElement('a[href*="factureId="]', $row) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="factureId="]', $row)->getAttribute("href");
            $invoiceName = explode(
                '&',
                array_pop(explode('factureId=', $invoiceUrl))
            )[0];
            $invoiceDate = trim($this->getInnerTextByJS($tags[0]));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[1]))) . ' EUR';

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

        $invoiceFileName = $invoice['invoiceName'] . '.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F d, Y', 'Y-m-d');
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

private function selectTabInvoiceYears()
{
    $this->exts->capture('3-tab-year');
    $year_len = count($this->exts->getElements('div#bill-archive nav ul li a'));
    $this->exts->log('year_len' . $year_len);
    for ($i = 0; $i < $year_len; $i++) {
        $year_button = $this->exts->getElements('div#bill-archive nav ul li a')[$i];
        try {
            $this->exts->log('Click year_button button');
            $year_button->click();
        } catch (\Exception $exception) {
            $this->exts->log('Click year_button by javascript');
            $this->exts->executeSafeScript("arguments[0].click()", [$year_button]);
        }
        sleep(15);

        $this->processProAccInvoice();
    }
}

private function processProAccLatestInvoice()
{
    if ($this->exts->exists('div.latest-bill span.icon-pdf-file')) {
        $this->isNoInvoice = false;
        $invoiceDate = trim($this->getInnerTextByJS('div.latest-bill span.bill-date, div.latest-bill a div.item-text'));
        $invoiceDate = $this->translate_date_abbr(strtolower($invoiceDate));
        $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS('div.latest-bill span.bill-price, div.latest-bill a div.font-weight-bold'))) . ' EUR';
        $invoiceFileName = $invoiceName . '.pdf';
        $this->isNoInvoice = false;

        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoiceName);
        $this->exts->log('invoiceDate: ' . $invoiceDate);
        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
        $this->exts->log('invoiceFileName: ' . $invoiceFileName);

        $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
        $this->exts->log('Date parsed: ' . $invoiceDate);

        $this->exts->moveToElementAndClick('div.latest-bill span.icon-pdf-file');

        $this->exts->wait_and_check_download('pdf');
        $this->exts->wait_and_check_download('pdf');
        $this->exts->wait_and_check_download('pdf');
        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
        }
    }
}

private function processProAccInvoice()
{
    $this->exts->capture("4-invoices-page-ProAccInvoice");
    $invoices = [];

    $rows_len = count($this->exts->getElements('#historical-bills-container div.row'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('#historical-bills-container div.row')[$i];
        if ($this->exts->getElement('a.bill-link', $row) != null) {
            $download_button = $this->exts->getElement('a.bill-link', $row);
            $invoiceDate = trim($this->getInnerTextByJS('span.capitalize:not(.bill-amount)', $row));
            $invoiceDate = $this->translate_date_abbr(strtolower($invoiceDate));
            $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS('.bill-amount', $row))) . ' EUR';
            $invoiceFileName = $invoiceName . '.pdf';
            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceFileName: ' . $invoiceFileName);

            $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            try {
                $this->exts->log('Click download button');
                $download_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
            }

            $this->exts->wait_and_check_download('pdf');
            $this->exts->wait_and_check_download('pdf');
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
            }
        }
    }

    $rows_len = count($this->exts->getElements('#bill-archive ul.items-list li a div.item-container div.item-text span'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('#bill-archive ul.items-list li')[$i];
        if ($this->exts->getElement('a', $row) != null) {
            $download_button = $this->exts->getElement('a', $row);
            $invoiceDate = trim($this->getInnerTextByJS('a div.item-container div.item-text span', $row));
            $invoiceDate = $this->translate_date_abbr(strtolower($invoiceDate));
            $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS('a div.item-container div.amount', $row))) . ' EUR';
            $invoiceFileName = $invoiceName . '.pdf';
            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceFileName: ' . $invoiceFileName);

            $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            try {
                $this->exts->log('Click download button');
                $download_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
            }

            $this->exts->wait_and_check_download('pdf');
            $this->exts->wait_and_check_download('pdf');
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
            }
        }
    }
}

