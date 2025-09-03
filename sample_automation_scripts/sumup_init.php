public $baseUrl = 'https://me.sumup.com/de-de/overview';
public $loginUrl = 'https://me.sumup.com/de-de/login';
public $invoicePageUrl = 'https://me.sumup.com/de-de/sales';

public $username_selector = 'input#username, input[name="email"], input[name="username"]';
public $password_selector = 'input#password, input[name="password"]';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'form[action*="/login"]';
public $check_login_success_selector = 'span[class*="merchantInfo"],a[data-selector="SALES_OVERVIEW.REPORTS_BUTTON"] , div[class*="CompanyName"] ,a[href*="/referrals"], a[href="/de-de/account"], a[href="/de-de/settings"],a[href="/en-us/settings"], button[data-selector="EXPORT_BUTTON"], button[data-selector="CALENDAR_BUTTON"]';

public $download_payouts = 0;
public $isNoInvoice = true;
public $MAX_INVOICE_LIMIT = 1500;
public $download_monthly_payouts = 0;
public $credit_notes = 0;
public $ar_invoices = 0;
public $restrictPages = 3;
public $totalFiles = 0;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    // $this->fake_user_agent('Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari\/537.36');
    $this->download_payouts = isset($this->exts->config_array["download_payouts"]) ? (int) @$this->exts->config_array["download_payouts"] : 0;
    $this->download_monthly_payouts = isset($this->exts->config_array["download_monthly_payouts"]) ? (int) @$this->exts->config_array["download_monthly_payouts"] : 0;

    $this->ar_invoices = isset($this->exts->config_array["ar_invoices"]) ? (int) $this->exts->config_array["ar_invoices"] : 0;
    $this->credit_notes = isset($this->exts->config_array["credit_notes"]) ? (int) $this->exts->config_array["credit_notes"] : 0;


    // Load cookies
    $this->exts->openUrl($this->baseUrl);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    sleep(10);
    $this->check_solve_blocked_page();
    if ($this->exts->check_exist_by_chromedevtool('button#onetrust-accept-btn-handler')) {
        $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
        sleep(5);
    }
    $this->exts->capture_by_chromedevtool('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->exts->check_exist_by_chromedevtool($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        //bypass browser rejected
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_blocked_page();

        $this->checkFillLogin();
        sleep(10);
        $this->check_solve_blocked_page();
        sleep(5);
        if ($this->isExists('h1[class*="cui-headline-sagu"]') && stripos($this->exts->extract('h1[class*="cui-headline-sagu"]'), 'Passkey or security key') !== false) {
            $this->exts->type_key_by_xdotool('Escape');
            sleep(2);
            $this->exts->moveToElementAndClick('a[href="/flows/authentication"] > span[class*="content"]');
            sleep(2);
            $this->exts->moveToElementAndClick('ul[class="cui-listitemgroup-items-rktu"] a[href="/flows/authentication/totp"], ul[class="cui-listitemgroup-items-rktu"] li:nth-child(1)');
            sleep(2);
        }

        $this->checkFillTwoFactor();
    }
    sleep(5);
    if ($this->isExists('button[class*="cui-dialog-close"]')) {
        $this->exts->moveToElementAndClick('button[class*="cui-dialog-close"]');
    }
    sleep(5);

    if ($this->isExists('div[class*="styles_buttonGroup"] > div[class*="cui-buttongroup-axeq cui-buttongroup-right-pmp3"] > button[class*="cui-button-ylou cui-button-secondary"]')) {
        $this->exts->log('Click on Update Later Button');
        $this->exts->moveToElementAndClick('div[class*="styles_buttonGroup"] > div[class*="cui-buttongroup-axeq cui-buttongroup-right-pmp3"] > button[class*="cui-button-ylou cui-button-secondary"]');
        sleep(5);
    }


    $this->exts->waitTillPresent($this->check_login_success_selector, 30);
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');

        if ($this->isExists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
        }
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->loginFailure(1);
        }

        $isTwoFAIncorrect = $this->exts->execute_javascript('document.body.innerHTML.includes("Please enter the correct code")');
        $this->exts->log('isTwoFAIncorrect: ' . $isTwoFAIncorrect);
        if (
            stripos($this->exts->extract($this->check_login_failed_selector), 'incorrect email address or password') !== false ||
            stripos($this->exts->extract($this->check_login_failed_selector), 'passwort falsch') !== false ||
            stripos($this->exts->extract($this->check_login_failed_selector), 'passe incorrect') !== false ||
            stripos($this->exts->extract('div.cui-notificationinline-danger-eeh7 p'), 'email address and/or password') !== false ||
            stripos($this->exts->extract($this->check_login_failed_selector), 'email address and/or password') !== false || $this->exts->getElement('div.cui-notificationinline-danger-eeh7 p') != null

        ) {
            $this->exts->loginFailure(1);
        } elseif ($isTwoFAIncorrect) {
            $this->exts->log('Please enter the correct code');
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector, 20);
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}


private function checkFillTwoFactor()
{
    $two_factor_selector = 'form > div > div input[id*="otp_code_input"]';
    $two_factor_message_selector = 'h1 + p';
    $two_factor_submit_selector = 'form button[type="submit"]';
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

            $this->exts->click_by_xdotool($two_factor_selector);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->isExists('input[type="checkbox"]')) {
                $this->exts->click_by_xdotool('input[type="checkbox"]');
                sleep(1);
            }

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

private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            sleep(10);

            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}
// end function to bypass hcaptcha

private function changeSelectbox($select_box = '', $option_value = '')
{
    $this->exts->waitTillPresent($select_box, 10);
    if ($this->isExists($select_box)) {
        $option = $option_value;
        $this->exts->click_by_xdotool($select_box);
        sleep(2);
        $optionIndex = $this->exts->executeSafeScript('
        const selectBox = document.querySelector("' . $select_box . '");
        const targetValue = "' . $option_value . '";
        const optionIndex = [...selectBox.options].findIndex(option => option.value === targetValue);
        return optionIndex;
    ');
        $this->exts->log($optionIndex);
        sleep(1);
        for ($i = 0; $i < $optionIndex; $i++) {
            $this->exts->log('>>>>>>>>>>>>>>>>>> Down');
            // Simulate pressing the down arrow key
            $this->exts->type_key_by_xdotool('Down');
            sleep(1);
        }
        $this->exts->type_key_by_xdotool('Return');
    } else {
        $this->exts->log('Select box does not exist');
    }
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