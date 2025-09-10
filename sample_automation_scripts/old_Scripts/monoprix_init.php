public $baseUrl = 'http://client.monoprix.fr/monoprix-shopping/commandes';
public $loginUrl = 'https://www.monoprix.fr/login';
public $invoicePageUrl = 'http://client.monoprix.fr/monoprix-shopping/commandes';

public $username_selector = '.login-form input[name="email"], input[name="email"]';
public $password_selector = '.login-form input[name="password"], input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = '.login-form button[type="submit"], button[type="submit"]';

public $check_login_failed_selector = 'span#password-error-description';
public $check_login_success_selector = 'button.user-menu__logout-button, li.ProfileNavSide_nav-elem__iyH1l'; //code update

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
    if ($this->exts->exists('a[href="/mon-profil"],, a[href="/monoprix-shopping/home"]')) {
        $this->exts->moveToElementAndClick('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]');
        sleep(15);
    }
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
        }
        $this->checkFillLogin();
        sleep(20);
        if ($this->exts->exists('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]')) {
            $this->exts->moveToElementAndClick('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]');
            sleep(15);
        }
        if ($this->exts->exists('.error.continue-shopping')) {
            $this->exts->moveToElementAndClick('.error.continue-shopping');
            sleep(15);
        }
        if ($this->exts->exists('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]')) {
            $this->exts->moveToElementAndClick('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]');
            sleep(15);
        }
    }
    if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->getElement('a[href="/monoprix-shopping/commandes"]') != null) { //code update
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'mot de passe invalide') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(6);
        for ($i = 0; $i < 8 && !$this->exts->exists($this->password_selector); $i++) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(6);
        }
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}