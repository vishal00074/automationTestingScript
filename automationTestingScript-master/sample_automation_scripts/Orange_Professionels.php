<?php
// Server-Portal-ID: 238945 - Last modified: 26.09.2024 13:47:17 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://pro.orange.fr/';
public $loginUrl = 'https://pro.orange.fr/';
public $invoicePageUrl = 'https://pro.orange.fr/';

public $username_selector = 'input#login, input#username';
public $password_selector = 'input#password, input#currentPassword';
public $remember_me_selector = '';
public $submit_login_selector = 'button#btnSubmit, button#submit-button';

public $check_login_failed_selector = 'h6#error-msg-box, span#default_password_error, h6#error-msg-box-login, p#password-error-title-error, form#authenValidation input#currentPassword.has-error';
public $check_login_success_selector = 'a[data-tag*="_deconnecter"], a[href*="/Ofermersession"], [data-oevent-action="sedeconnecter"], a[href*="_deconnexion"], a[href*="orange.fr/deconnect"], a[href*="Oid_fermersession"], a#o-identityLink, a[href*="/deconnect"]';

public $captcha_form_selector = 'div[class*="captcha_images"]'; //'[id="captchaRow"] form[name="captcha-form"]';
public $captcha_image_selector = 'div#__next > div > div:nth-child(3) > div:nth-child(2) > ul > li';
public $captcha_submit_btn_selector = 'div#__next div > button';
public $captcha_indications_selector = 'ul.uya65w-0.eCJhHZ li';
public $restrictPages = 3;
public $isNoInvoice = true;


/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);

    if ($this->exts->exists('#o-cookie-ok, #didomi-notice-agree-button')) {
        $this->exts->click_by_xdotool('#o-cookie-ok, #didomi-notice-agree-button');
        sleep(3);
    }

    $this->solveClickCaptcha();
    sleep(15);

    $this->exts->click_by_xdotool('div.welcomePanel a.btn-inverse');
    sleep(5);

    if ($this->exts->exists('span[ng-if*="control.cancel"] a')) {
        $this->exts->click_by_xdotool('span[ng-if*="control.cancel"] a');
        sleep(10);
    }

    $this->exts->capture_by_chromedevtool('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->exts->exists($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('a#close')) {
            $this->exts->click_by_xdotool('a#close');
            sleep(10);
        }
        if ($this->exts->exists('#o-cookie-ok, #didomi-notice-agree-button')) {
            $this->exts->click_by_xdotool('#o-cookie-ok, #didomi-notice-agree-button');
            sleep(10);
        }
        if ($this->exts->exists($this->captcha_form_selector)) {
            $this->solveClickCaptcha();
            sleep(15);
        }

        $this->exts->click_by_xdotool('div.welcomePanel a.btn-inverse');
        sleep(5);

        if ($this->exts->exists('iframe[src*="cloudfront"]')) {
            $this->exts->execute_javascript('document.querySelector(\'iframe[src*="cloudfront"]\').remove();');
            $this->exts->execute_javascript('document.querySelector("div.usabilla__overlay").remove();');
            sleep(2);
        }

        $this->exts->click_by_xdotool('div#IdentityNotConnectedProspect > a');
        sleep(10);

        if ($this->exts->exists($this->captcha_form_selector)) {
            $this->solveClickCaptcha();
            sleep(15);
        }



        $this->exts->capture_by_chromedevtool('1-init-page-2');

        $this->checkFillLogin();
        sleep(20);

        for ($i = 0; $i < 10; $i++) {
            if ($this->exts->urlContains('/error403.html?status=error') || $this->exts->urlContains('error403.html?ref=idme-ssr&status=error')) {
                $this->exts->clearCookies();
                sleep(1);

                $this->exts->getUrl($this->baseUrl);
                sleep(20);

                if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
                    $this->exts->click_by_xdotool('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
                    sleep(10);
                }
                if ($this->exts->exists('iframe[src*="cloudfront"]')) {
                    $this->exts->execute_javascript('document.querySelector(\'iframe[src*="cloudfront"]\').remove();');
                    $this->exts->execute_javascript('document.querySelector("div.usabilla__overlay").remove();');
                    sleep(2);
                }
                $this->exts->click_by_xdotool('div#IdentityNotConnectedProspect > a');
                sleep(20);
                if ($this->exts->exists($this->captcha_form_selector)) {
                    $this->solveClickCaptcha();
                    sleep(15);
                }
                $this->checkFillLogin();
                sleep(20);
            } else break;
        }

        if ($this->exts->exists('button[data-testid="submit-mc"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="submit-mc"]');
            sleep(3);
            $this->checkFillTwoFactorForMobileAcc();
        }

        $this->checkFillTwoFactor();

        $this->exts->capture('after-submit-login');


        if ($this->exts->exists('a#btnLater')) {
            $this->exts->moveToElementAndClick('a#btnLater');
            sleep(15);
        }
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        // Open invoices url and download invoice
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(25);

        $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
        sleep(20);

        if ($this->exts->exists($this->username_selector)) {
            $this->checkFillLogin();
            sleep(20);

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(25);

            $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
            sleep(20);
        }

        $contracts_len = count($this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker'));

        // $contracts = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker');
        $this->exts->log('Totoal Contracts - ' . $contracts_len);
        if ($contracts_len > 0) {
            $contract_url = $this->exts->getUrl(); // https://espaceclientpro.orange.fr/contracts
            for ($i = 0; $i < $contracts_len; $i++) {
                $contractBtn = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker')[$i];
                // if ($contractBtn == null) continue;
                try {
                    $contractBtn->click();
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript('arguments[0].click()', [$contractBtn]);
                }
                sleep(15);

                if ($this->exts->exists($this->username_selector)) {
                    $this->checkFillLogin();
                    sleep(20);

                    // Open invoices url and download invoice
                    $this->exts->openUrl($this->invoicePageUrl);
                    sleep(25);

                    $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
                    sleep(20);

                    $contractBtn = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker')[$i];
                    // if ($contractBtn == null) continue;
                    try {
                        $contractBtn->click();
                    } catch (\Exception $exception) {
                        $this->exts->execute_javascript('arguments[0].click()', [$contractBtn]);
                    }
                    sleep(15);
                }

                if ($this->exts->exists('a[href*="/factures"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/factures"]');
                    sleep(15);

                    if ($this->exts->exists($this->username_selector)) {
                        $this->checkFillLogin();
                        sleep(20);

                        // Open invoices url and download invoice
                        $this->exts->openUrl($this->invoicePageUrl);
                        sleep(25);

                        $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
                        sleep(20);

                        $contractBtn = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker')[$i];
                        // if ($contractBtn == null) continue;
                        try {
                            $contractBtn->click();
                        } catch (\Exception $exception) {
                            $this->exts->execute_javascript('arguments[0].click()', [$contractBtn]);
                        }
                        sleep(15);

                        $this->exts->moveToElementAndClick('a[href*="/factures"]');
                        sleep(15);
                    }
                } else if ($this->exts->exists('.bill-summary .bill-details')) {
                    $this->exts->moveToElementAndClick('.bill-summary .bill-details');
                    sleep(15);

                    if ($this->exts->exists($this->username_selector)) {
                        $this->checkFillLogin();
                        sleep(20);

                        // Open invoices url and download invoice
                        $this->exts->openUrl($this->invoicePageUrl);
                        sleep(25);

                        $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
                        sleep(20);

                        $contractBtn = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker')[$i];
                        // if ($contractBtn == null) continue;
                        try {
                            $contractBtn->click();
                        } catch (\Exception $exception) {
                            $this->exts->execute_javascript('arguments[0].click()', [$contractBtn]);
                        }
                        sleep(15);

                        $this->exts->moveToElementAndClick('a[href*="/factures"]');
                        sleep(15);
                    }
                }

                $this->processProAccLatestInvoice();

                $this->selectTabInvoiceYears();

                $this->exts->moveToElementAndClick('#contract-summary #contract-switcher-block button#access-hub');
                sleep(10);
                $this->exts->openUrl($contract_url);
                sleep(30);
            }
        } else {
            if ($this->exts->exists('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]')) {
                $this->exts->moveToElementAndClick('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]');
                sleep(15);
            } else if ($this->exts->exists('.bill-summary .bill-details')) {
                $this->exts->moveToElementAndClick('.bill-summary .bill-details');
                sleep(15);
            }

            if ($this->exts->exists($this->username_selector)) {
                $this->checkFillLogin();
                sleep(20);

                $this->exts->openUrl($this->invoicePageUrl);
                sleep(25);

                $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
                sleep(20);

                if ($this->exts->exists('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]');
                    sleep(15);
                } else if ($this->exts->exists('.bill-summary .bill-details')) {
                    $this->exts->moveToElementAndClick('.bill-summary .bill-details');
                    sleep(15);
                }
            }

            $this->processProAccLatestInvoice();

            $this->selectTabInvoiceYears();
        }

        if ($this->isNoInvoice) {
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(25);

            $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
            sleep(20);

            $contracts_len = count($this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker'));

            // $contracts = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker');
            $this->exts->log('Totoal Contracts - ' . $contracts_len);
            if ($contracts_len > 0) {
                $contract_url = $this->exts->getUrl(); // https://espaceclientpro.orange.fr/contracts
                for ($i = 0; $i < $contracts_len; $i++) {
                    $contractBtn = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker')[$i];
                    // if ($contractBtn == null) continue;
                    try {
                        $contractBtn->click();
                    } catch (\Exception $exception) {
                        $this->exts->execute_javascript('arguments[0].click()', [$contractBtn]);
                    }
                    sleep(15);

                    if ($this->exts->exists('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]')) {
                        $this->exts->moveToElementAndClick('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]');
                        sleep(15);

                        if ($this->exts->exists($this->username_selector)) {
                            $this->checkFillLogin();
                            sleep(20);

                            // Open invoices url and download invoice
                            $this->exts->openUrl($this->invoicePageUrl);
                            sleep(25);

                            $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
                            sleep(20);

                            $contractBtn = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker')[$i];
                            // if ($contractBtn == null) continue;
                            try {
                                $contractBtn->click();
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click()', [$contractBtn]);
                            }
                            sleep(15);

                            $this->exts->moveToElementAndClick('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]');
                            sleep(15);
                        }
                    } else if ($this->exts->exists('.bill-summary .bill-details')) {
                        $this->exts->moveToElementAndClick('.bill-summary .bill-details');
                        sleep(15);

                        if ($this->exts->exists($this->username_selector)) {
                            $this->checkFillLogin();
                            sleep(20);

                            // Open invoices url and download invoice
                            $this->exts->openUrl($this->invoicePageUrl);
                            sleep(25);

                            $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
                            sleep(20);

                            $contractBtn = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker')[$i];
                            // if ($contractBtn == null) continue;
                            try {
                                $contractBtn->click();
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click()', [$contractBtn]);
                            }
                            sleep(15);

                            $this->exts->moveToElementAndClick('.bill-summary .bill-details');
                            sleep(15);
                        }
                    }

                    $this->exts->moveToElementAndClick('a[href*="/historique-des-factures"]');
                    sleep(17);

                    $this->processFacturePaiement();


                    $this->exts->moveToElementAndClick('#contract-summary #contract-switcher-block button#access-hub');
                    sleep(10);
                    $this->exts->openUrl($contract_url);
                    sleep(30);
                }
            } else {
                if ($this->exts->exists('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]');
                    sleep(15);
                } else if ($this->exts->exists('.bill-summary .bill-details')) {
                    $this->exts->moveToElementAndClick('.bill-summary .bill-details');
                    sleep(15);
                }

                $this->exts->moveToElementAndClick('a[href*="/historique-des-factures"]');
                sleep(17);

                if ($this->exts->exists($this->username_selector)) {
                    $this->checkFillLogin();
                    sleep(20);

                    $this->exts->openUrl($this->invoicePageUrl);
                    sleep(25);

                    $this->exts->moveToElementAndClick('[data-track-name="espace_client"], a#EspaceClientConnected');
                    sleep(20);

                    if ($this->exts->exists('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]')) {
                        $this->exts->moveToElementAndClick('a[href*="/factures"], [data-e2e="dashboardsection"] a[href*="facture-paiement"]');
                        sleep(15);
                    } else if ($this->exts->exists('.bill-summary .bill-details')) {
                        $this->exts->moveToElementAndClick('.bill-summary .bill-details');
                        sleep(15);
                    }
                }

                $this->processFacturePaiement();
            }
        }

        if ($this->exts->getElementByText('h3.feedback-title', ['L’espace client est indisponible pour le moment'], null, false) != null) {
            $this->exts->no_permission();
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log('::login failed url:: ' . $this->exts->getUrl());
        $this->exts->capture('login-fail');
        if ($this->exts->urlContains('recovery/error/livebox')) {
            $this->exts->account_not_ready();
        }
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('input#login ~ label', null, 'innerText')), 'adresse e-mail ou') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->urlContains('/renforcer-mot-de-passe')) {
            $this->exts->account_not_ready();
        } elseif ($this->exts->urlContains('mdp/choice/default')) {
            $this->exts->account_not_ready();
        } elseif ($this->exts->exists('label#password-invalid-feedback')) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


private function checkFillLogin()
{
    if ($this->exts->exists($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture_by_chromedevtool("2-login-page");

        $this->exts->log("Enter Username");

        $this->exts->moveToElementAndType($this->username_selector, $this->username);

        $this->exts->type_key_by_xdotool("Delete");
        sleep(1);
        $this->exts->capture("2-username-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(15);

        if ($this->exts->exists($this->captcha_form_selector)) {
            $this->solveClickCaptcha();
            sleep(15);
        }

        if ($this->exts->exists('a[data-testid="footerlink-authent-pwd"], button[data-testid="footerlink-authent-pwd"]')) {
            $this->exts->click_by_xdotool('a[data-testid="footerlink-authent-pwd"], button[data-testid="footerlink-authent-pwd"]');
            sleep(14);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(15);
        } else if ($this->exts->exists('button#btnSubmit[data-oevent-action="clic_sidentifier_avec_mc"], button[data-testid="submit-mc"]')) {
            $this->exts->moveToElementAndClick('button#btnSubmit[data-oevent-action="clic_sidentifier_avec_mc"], button[data-testid="submit-mc"]');
            sleep(15);

            if ($this->exts->exists('button.btn-action-password')) {
                $this->exts->moveToElementAndClick('button.btn-action-password');
                sleep(15);
            }

            $this->checkFillTwoFactorForMobileAcc();
            sleep(15);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(15);
        } else {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(15);
        }

        if ($this->exts->exists('div.promoteMC-container a#btnLater')) {
            $this->exts->moveToElementAndClick('div.promoteMC-container a#btnLater');
            sleep(15);
        }

        if ($this->exts->exists('a[data-oevent-action="clic_lien_plus_tard"]')) {
            $this->exts->moveToElementAndClick('a[data-oevent-action="clic_lien_plus_tard"]');
            sleep(14);
        }

        if ($this->exts->exists('button[data-testid="link-mc-later"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="link-mc-later"]');
            sleep(15);
        }
        if($this->exts->exists('button[id="submit-button"]')){
            $this->exts->moveToElementAndClick('button#submit-button');
            sleep(15);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function selectTabInvoiceYears()
{
    $this->exts->capture('3-tab-year');
    $year_len = count($this->exts->getElements('div#bill-archive nav ul li'));
    if ($this->restrictPages == 0) {
        for ($i = 0; $i < $year_len; $i++) {
            $year_button = $this->exts->getElements('div#bill-archive nav ul li')[$i];
            try {
                $this->exts->log('Click download button');
                $year_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click year_button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$year_button]);
            }
            sleep(15);

            $this->processProAccInvoice();
        }
    } else {
        $this->processProAccInvoice();
    }
}

private function processProAccLatestInvoice()
{
    $this->exts->log(__FUNCTION__);
    $this->exts->capture(__FUNCTION__);
    if ($this->exts->exists('div.latest-bill span.icon-pdf-file')) {
        $this->isNoInvoice = false;
        if ($this->exts->exists('div.latest-bill span.bill-date')) {
            $invoiceDate = trim($this->exts->extract('div.latest-bill span.bill-date', null, 'innerText'));
        } else {
            $invoiceDate = trim($this->exts->extract('div.latest-bill .item-container span:first-child,div.latest-bill .item-text span', null, 'innerText'));
        }
        $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
        if (trim($invoiceDate) == '' || $invoiceDate == null) {
            $invoiceDate = date('F Y');
        }
        $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.latest-bill span.bill-price', null, 'innerText'))) . ' EUR';
        $invoiceFileName = $invoiceName . '.pdf';
        $this->isNoInvoice = false;

        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoiceName);
        $this->exts->log('invoiceDate: ' . $invoiceDate);
        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
        $this->exts->log('invoiceFileName: ' . $invoiceFileName);

        $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
        $this->exts->log('Date parsed: ' . $invoiceDate);

        if (!$this->exts->invoice_exists($invoiceName)) {
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
        } else {
            $this->exts->log(__FUNCTION__ . '::Already Exists ' . $invoiceName);
        }
    } else if ($this->exts->exists('a[href*="facture-paiement/"]')) {
        $invoices_url = $this->exts->getElementsAttribute('a[href*="facture-paiement/"]', 'href');
        foreach ($invoices_url as $invoice_url_index => $invoice_url) {
            $this->exts->openUrl($invoice_url);
            sleep(15);
            if ($this->exts->exists('li[data-e2e="bp-linkPDF"] a')) {
                $this->isNoInvoice = false;
                $invoiceDate = trim($this->exts->extract('#last-bill-date', null, 'innerText'));
                $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
                $invoiceName = trim(explode('/', end(explode('/facture-paiement/', $invoice_url)))[0]) . str_replace(' ', '', $invoiceDate);
                $invoiceAmount = '';
                $invoiceFileName = $invoiceName . '.pdf';
                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd m Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if (!$this->exts->invoice_exists($invoiceName)) {
                    $this->exts->moveToElementAndClick('li[data-e2e="bp-linkPDF"] a');

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
                } else {
                    $this->exts->log(__FUNCTION__ . '::Already Exists ' . $invoiceName);
                }
            }
        }
    }
}

private function processProAccInvoice()
{
    sleep(10);
    $this->exts->capture("4-invoices-page-ProAccInvoice");
    $invoices = [];

    $rows_len = count($this->exts->getElements('#historical-bills-container div.row'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('#historical-bills-container div.row')[$i];
        if ($this->exts->getElement('a.bill-link', $row) != null) {
            $download_button = $this->exts->getElement('a.bill-link', $row);
            $invoiceDate = trim($this->exts->extract('span.capitalize:not(.bill-amount)', $row, 'innerText'));
            $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
            if ($this->exts->urlContains('/contracts/closed/')) {
                $invoiceName = trim(explode('/', end(explode('/contracts/closed/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            } else {
                $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            }
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.bill-amount', $row, 'innerText'))) . ' EUR';
            $invoiceFileName = $invoiceName . '.pdf';
            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceFileName: ' . $invoiceFileName);

            $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            if (!$this->exts->invoice_exists($invoiceName)) {
                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
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
            } else {
                $this->exts->log(__FUNCTION__ . '::Already Exists ' . $invoiceName);
            }
        }
    }

    $rows_len = count($this->exts->getElements('div.historical-bills-container #bill-archive ul.items-list li'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('div.historical-bills-container #bill-archive ul.items-list li')[$i];
        if ($this->exts->getElement('span.icon-pdf-file', $row) != null) {
            $download_button = $this->exts->getElement('span.icon-pdf-file', $row);
            $invoiceDate = trim($this->exts->extract('.item-list-button-label', $row, 'innerText'));
            $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
            if ($this->exts->urlContains('/contracts/closed/')) {
                $invoiceName = trim(explode('/', end(explode('/contracts/closed/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            } else {
                $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            }
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.ht-numb', $row, 'innerText'))) . ' EUR';
            $invoiceFileName = $invoiceName . '.pdf';
            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceFileName: ' . $invoiceFileName);

            $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            if (!$this->exts->invoice_exists($invoiceName)) {
                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
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
            } else {
                $this->exts->log(__FUNCTION__ . '::Already Exists ' . $invoiceName);
            }
        }
    }

    $rows_len = count($this->exts->getElements('#bill-archive ul.items-list li a div.item-container div.item-text span'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('#bill-archive ul.items-list li')[$i];
        if ($this->exts->getElement('a', $row) != null) {
            $download_button = $this->exts->getElement('a', $row);
            $invoiceDate = trim($this->exts->extract('a div.item-container div.item-text span', $row, 'innerText'));
            $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
            if ($this->exts->urlContains('/contracts/closed/')) {
                $invoiceName = trim(explode('/', end(explode('/contracts/closed/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            } else {
                $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            }
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('a div.item-container div.amount', $row, 'innerText'))) . ' EUR';
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
                $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
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

private function processFacturePaiement()
{
    $this->exts->capture("4-invoices-page-FacturePaiement");
    $invoices = [];

    $current_url = $this->exts->getUrl();

    $rows_len = count($this->exts->getElements('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr')[$i];
        $tags = $this->exts->getElements('td', $row);

        if (count($tags) >= 4 && $this->exts->getElement('a[class*="downloadIcon"]', $row) != null) {
            $download_button = $this->exts->getElement('a[class*="downloadIcon"]', $row);
            $invoiceName = '';
            $invoiceDate = trim($tags[1]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

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
                $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
            }

            $this->exts->moveToElementAndClick('button[data-e2e="download-link"]');


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

                if ($this->exts->exists('button[data-e2e="pdf-cancel-popup"]')) {
                    $this->exts->moveToElementAndClick('button[data-e2e="pdf-cancel-popup"]');
                    sleep(5);
                }

                $this->exts->execute_javascript('history.back();');
                sleep(15);
            }



            if (strpos($this->exts->getUrl(), 'voir-la-facture/true') !== false) {
                $this->exts->openUrl($current_url);
            }
        }
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#otc, input#currentCode';
    $two_factor_message_selector = 'h3 + p[color="black"], label#titreAccroche';
    $two_factor_submit_selector = 'button[data-testid="submit-otc"], button#submit-button';

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
            $this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);

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
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
            $indications_sel = $this->exts->getElements('ol[class*="timeline-captcha"] li', null, 'css');
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
                        $actions = $this->exts->webdriver->action();
                        $this->exts->log('Clicking X/Y: ' . $coordinate['x'] . '/' . $coordinate['y']);
                        $actions->moveToElement($challenge_wraper, intval($coordinate['x']), intval($coordinate['y']))->click()->perform();
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
        $result = $this->exts->extract('div c-wiz:nth-child(2) span[lang="en"] > span > span', null, 'innerText');
        $result = str_replace('%0A', "\n", $result);
        $this->exts->close_new_window();
    } catch (\Exception $ex) {
        $this->exts->log("Failed to get translated instruction");
    }

    return $result;
}