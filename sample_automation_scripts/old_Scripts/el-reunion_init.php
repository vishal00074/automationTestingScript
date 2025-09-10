public $baseUrl = 'https://sei-ael-reunion.edf.com/aelEDF/jsp/arc/habilitation/acteur.ZoomerDossierClient.go';
public $loginUrl = 'https://sei-ael-reunion.edf.com/aelEDF/jsp/arc/habilitation/login.jsp';
public $invoicePageUrl = 'https://sei-ael-reunion.edf.com/aelEDF/jsp/arc/habilitation/acteur.ZoomerDossierClient.go';

public $username_selector = 'form[action="habilitation.ActorIdentificationAel.go"] input[name="lg"]';
public $password_selector = 'form[action="habilitation.ActorIdentificationAel.go"] input[name="psw"]';
public $remember_me_selector = '';
public $submit_login_selector = 'a#valider';

public $check_login_failed_selector = '.errorMessage p';
public $check_login_success_selector = 'a#fermerSession';

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
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('div[id="bandeauCookie"] a[id="accederPageChoixCookie"][title="tout accepter"]')) {
            $this->exts->moveToElementAndClick('div[id="bandeauCookie"] a[id="accederPageChoixCookie"][title="tout accepter"]');
        }
        $this->checkFillLogin();
        sleep(30);
    }

    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'nouveau votre login et votre mot de passe') !== false) {
            $this->exts->loginFailure(1);
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

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(10);
        if ($this->exts->exists($this->submit_login_selector) && stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'nouveau votre login et votre mot de passe') === false) {
            $submit_btn = $this->exts->getElement($this->submit_login_selector);
            try {
                $this->exts->log('Click submit button');
                $submit_btn->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click submit button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$submit_btn]);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}