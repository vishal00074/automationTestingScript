public $baseUrl = 'https://pro.packlink.de';
public $loginUrl = 'https://pro.packlink.de/login';
public $invoicePageUrl = 'https://pro.packlink.de/private/settings/billing/invoices';

public $username_selector = 'input#login-email, input[name="email"]';
public $password_selector = 'input#login-password, input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form#login-form button#login-submit , button[type="submit"]';

public $check_login_success_selector = 'a[href*="logout"], .app-header-menu a.app-header-menu__item [href*="cog--light"], .app-header-menu a.app-header-menu__item[role="button"][title="Einstellungen"], .app-header-menu a.app-header-menu__item [class*="COG_LIGHT"], span[data-id="ICON-SETTINGS"]';

public $isNoInvoice = true;
public $restrictPages = 3;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        // $this->exts->openUrl($this->loginUrl);
        if ($this->exts->exists('#navbarSupportedContent a[href*="login"]')) {
            $this->exts->moveToElementAndClick('#navbarSupportedContent a[href*="login"]');
        } else if ($this->exts->exists('a[href="https://pro.packlink.de/private"]')) {
            $this->exts->moveToElementAndClick('a[href="https://pro.packlink.de/private"]');
        } else {
            $this->exts->openUrl($this->loginUrl);
        }
        sleep(20);
        if (!$this->exts->exists('div#gatsby-focus-wrapper [role="main"]') && !$this->exts->exists($this->password_selector)) {
            $this->clearChrome();
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
            if ($this->exts->exists('#navbarSupportedContent a[href*="login"]')) {
                $this->exts->moveToElementAndClick('#navbarSupportedContent a[href*="login"]');
            } else if ($this->exts->exists('a[href="https://pro.packlink.de/private"]')) {
                $this->exts->moveToElementAndClick('a[href="https://pro.packlink.de/private"]');
            } else {
                $this->exts->openUrl($this->loginUrl);
            }
        }
        // click cookies button
        if ($this->exts->exists('button#didomi-notice-agree-button')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
            sleep(5);
        }
        $this->checkFillLogin();
        sleep(40);
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->exists('.shipment-list-sidebar__inboxes li[data-inbox="ALL"]')) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (
            strpos($this->exts->extract('.authentication form .notification--error'), 'Falsche Anmeldedaten') !== false ||
            strpos($this->exts->extract('article h3'), 'Falsche Anmeldedaten') !== false
        ) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    sleep(10);
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
        $this->exts->executeSafeScript('window.alert = null;window.confirm = null;');
        $this->exts->moveToElementAndClick($this->submit_login_selector);
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
    for ($i = 0; $i < 6; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}