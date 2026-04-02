<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListBancoOnlineMovimiento extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['name'] = 'ListBancoOnlineMovimiento';
        $data['title'] = 'bancos-online-movimientos';
        $data['menu'] = 'accounting';
        $data['icon'] = 'fa-solid fa-exchange-alt';
        $data['showonmenu'] = true;
        $data['ordernum'] = 56;
        return $data;
    }

    protected function createViews(): void
    {
        $this->addView('ListBancoOnlineMovimiento', 'BancoOnlineMovimiento', 'movimientos-bancarios', 'fa-solid fa-exchange-alt');
        $this->addSearchFields('ListBancoOnlineMovimiento', ['descripcion', 'contraparte']);
        $this->addOrderBy('ListBancoOnlineMovimiento', ['fecha', 'id'], 'fecha', 2);
        $this->addOrderBy('ListBancoOnlineMovimiento', ['importe'], 'importe');

        // Filtros
        $this->addFilterPeriod('ListBancoOnlineMovimiento', 'fecha', 'periodo', 'fecha');

        $this->addFilterSelect('ListBancoOnlineMovimiento', 'idcuenta', 'cuenta', 'idcuenta',
            'bancos_online_cuentas', 'id', 'iban');
    }

    protected function loadData($viewName, $view): void
    {
        switch ($viewName) {
            case 'ListBancoOnlineMovimiento':
                $view->loadData();
                break;
        }
    }
}
