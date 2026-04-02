<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListBancoOnlineCuenta extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['name'] = 'ListBancoOnlineCuenta';
        $data['title'] = 'bancos-online-cuentas';
        $data['menu'] = 'accounting';
        $data['icon'] = 'fa-solid fa-credit-card';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        $this->addView('ListBancoOnlineCuenta', 'BancoOnlineCuenta', 'cuentas-bancarias', 'fa-solid fa-credit-card');
        $this->addSearchFields('ListBancoOnlineCuenta', ['banco', 'iban', 'nombre']);
        $this->addOrderBy('ListBancoOnlineCuenta', ['banco', 'iban'], 'banco');
        $this->addOrderBy('ListBancoOnlineCuenta', ['saldo'], 'saldo', 2);
        $this->addOrderBy('ListBancoOnlineCuenta', ['last_sync'], 'ultima-sync');

        $this->addFilterSelect('ListBancoOnlineCuenta', 'idempresa', 'empresa', 'idempresa', 'empresas', 'idempresa', 'nombrecorto');
    }

    protected function loadData($viewName, $view): void
    {
        switch ($viewName) {
            case 'ListBancoOnlineCuenta':
                $view->loadData();
                break;
        }
    }
}
