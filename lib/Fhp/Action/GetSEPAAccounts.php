<?php

namespace Fhp\Action;

use Fhp\BaseAction;
use Fhp\Model\SEPAAccount;
use Fhp\Segment\BaseSegment;
use Fhp\Segment\Common\Ktz;
use Fhp\Segment\SPA\HISPA;
use Fhp\Segment\SPA\HKSPAv1;
use Fhp\Segment\SPA\HKSPAv2;
use Fhp\UnsupportedException;

/**
 * Runs an HKSPA request to retrieve account details about the accounts that the user can access through FinTs.
 *
 * TODO In future, once all banks populate the BIC in HIUPD.erweiterungKontobezogen, or if we force library users to
 * supply the BIC to us, we won't need to send an HKSPA anymore, but we can simply fulfil this action from the UPD.
 */
class GetSEPAAccounts extends BaseAction
{
    // Empty request, in order to retrieve all accounts.

    // Response
    /** @var SEPAAccount[] */
    private $accounts;

    /**
     * @return GetSEPAAccounts A new action instance.
     */
    public static function create()
    {
        return new GetSEPAAccounts();
    }

    /**
     * @return SEPAAccount[]
     * @throws \Exception See {@link #ensureSuccess()}.
     */
    public function getAccounts()
    {
        $this->ensureSuccess();
        return $this->accounts;
    }

    /** {@inheritdoc} */
    public function createRequest($bpd, $upd)
    {
        /** @var BaseSegment $hispas */
        $hispas = $bpd->requireLatestSupportedParameters('HISPAS');
        switch ($hispas->getVersion()) {
            case 1:
                return HKSPAv1::createEmpty();
            case 2:
                return HKSPAv2::createEmpty();
            default:
                throw new UnsupportedException('Unsupported HKSPA version: ' . $hispas->getVersion());
        }
    }

    /** {@inheritdoc} */
    public function processResponse($response)
    {
        parent::processResponse($response);
        /** @var HISPA $hispa */
        $hispa = $response->requireSegment(HISPA::class);
        $this->accounts = array_map(function ($ktz) {
            /** @var Ktz $ktz */
            $account = new SEPAAccount();
            $account->setIban($ktz->iban);
            $account->setBic($ktz->bic);
            $account->setAccountNumber($ktz->kontonummer);
            $account->setSubAccount($ktz->unterkontomerkmal);
            $account->setBlz($ktz->kreditinstitutskennung->kreditinstitutscode);
            return $account;
        }, $hispa->getSepaKontoverbindung());
    }
}
