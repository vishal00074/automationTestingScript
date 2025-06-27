public $baseUrl = "https://de.ingrammicro.com/cep/app/my/dashboard";
public $invoicePageUrls = "https://de.ingrammicro.com/cep/app/invoice/InvoiceList/Invoice";
public $invoicePageUrl = "https://de.ingrammicro.com/cep/app/invoice/InvoiceList";
public $homePageUrl = "https://de.ingrammicro.com";
public $username_selector = "#ctl00_PlaceHolderMain_txtUserEmail, input[name*='username']";
public $password_selector = "#ctl00_PlaceHolderMain_txtPassword, input[name*='password']";
public $login_button_selector = "#ctl00_PlaceHolderMain_btnLogin, input[id*='submit']";
public $login_confirm_selector = 'button[data-testid="header-avatarBtn"]';
public $billingPageUrl = "https://my.t-mobile.com/billing/summary.html";
public $remember_me = "input[name=\"remember_me\"]";
public $submit_button_selector = "input[type='submit']";
public $dropdown_selector = "#img_DropDownIcon";
public $dropdown_item_selector = "#di_billCycleDropDown";
public $more_bill_selector = ".view-more-bills-btn";
public $login_tryout = 0;
public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $isCookieLoginSuccess = false;

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->homePageUrl);
    $this->exts->capture('1-init-page');

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->waitTillPresent('button[data-testid="btn_TopCornerLogin"]');
        $this->exts->moveToElementAndClick('button[data-testid="btn_TopCornerLogin"]');
        sleep(2);
        $this->exts->capture("after-login-clicked");

        $this->fillForm(0);

        sleep(15);

        if ($this->exts->getElement('#ctl00_PlaceHolderMain_chkTermsOfSale') != null && $this->exts->getElement('#ctl00_PlaceHolderMain_btnSubmit') != null) {
            $this->exts->moveToElementAndClick('#ctl00_PlaceHolderMain_chkTermsOfSale');
            sleep(1);
            $this->exts->moveToElementAndClick('#ctl00_PlaceHolderMain_btnSubmit');
            sleep(10);
        }

        if ($this->isExists('form[data-se="factor-email"]')) {
            sleep(1);
            $this->exts->moveToElementAndClick('form[data-se="factor-email"] input[type="submit"]');
            sleep(10);
        }

        $this->checkFillTwoFactor();
        sleep(15);
    }

    if ($this->exts->urlContains('/terms-and-conditions')) {
        $this->exts->moveToElementAndClick('input.PrivateSwitchBase-input');
        sleep(1);
        $this->exts->moveToElementAndClick('button.MuiButton-contained');
    }

    if ($this->exts->urlContains('/welcome-video')) {
        $this->exts->openUrl($this->homePageUrl);
        sleep(5);
    }

    if ($this->isExists('div[id*="walkme-visual-design"]')) {
        $this->exts->moveToElementAndClick('div[id*="walkme-visual-design"] button > div > .wm-ignore-css-reset');
        sleep(3);
    }

    if ($this->isExists('.cc_btn_accept_all, #onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('.cc_btn_accept_all, #onetrust-accept-btn-handler');
        sleep(3);
    }

    $this->exts->capture('1.1-before-check-login');

    if ($this->checkLogin()) {

        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log("Login failed " . $this->exts->getUrl());
        if ($this->exts->getElement('input[name*="oldPassword"]') != null && $this->exts->getElement('input[name*="newPassword"] ') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>account_not_ready***************!!!!");
            $this->exts->capture("account_not_ready");
            $this->exts->account_not_ready();
        }
        if ($this->exts->urlContains('/PasswordUpdate')) {
            $this->exts->account_not_ready();
        }

        if (strpos($this->exts->extract('.o-form-has-errors .infobox-error'), 'Ihr Benutzername und Ihr Passwort') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->getElement('//*[contains(text(),"bitte versuchen Sie es mit einer anderen URL")]', null, 'xpath') != null) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('.o-form-has-errors .infobox-error'), 'Authentifizierung fehlgeschlagen') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('.o-form-has-errors .infobox-error'), 'User is not assigned to the client application.') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(2);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->login_button_selector);
            sleep(10);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->login_confirm_selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for login.....');
        sleep(10);
    }
    $isloggedIn = false;
    if ($this->exts->querySelector($this->login_confirm_selector) != null && !$this->exts->urlContains('/PasswordUpdate')) {
        $isloggedIn = true;
    }

    return $isloggedIn;
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

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form[data-se="factor-email"] input[name="answer"]';
    $two_factor_message_selector = 'form[data-se="factor-email"] .mfa-email-sent-content';
    $two_factor_submit_selector = 'form[data-se="factor-email"] [type="submit"]';

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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            if ($this->isExists('form[data-se="factor-email"] [data-se-for-name="rememberDevice"]')) {
                $this->exts->moveToElementAndClick('form[data-se="factor-email"] [data-se-for-name="rememberDevice"]');
                sleep(3);
            }
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
