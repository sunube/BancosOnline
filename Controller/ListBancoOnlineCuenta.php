<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Base\DataBase;
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

        // Filtro de empresa: construir array de valores
        $empresas = $this->getEmpresasFilter();
        if (!empty($empresas)) {
            $this->addFilterSelect('ListBancoOnlineCuenta', 'idempresa', 'empresa', 'idempresa', $empresas);
        }
    }

    protected function loadData($viewName, $view): void
    {
        switch ($viewName) {
            case 'ListBancoOnlineCuenta':
                $view->loadData();
                break;
        }
    }

    private function getEmpresasFilter(): array
    {
        $result = [];
        $db = new DataBase();
        $rows = $db->select("SELECT idempresa, nombrecorto FROM empresas ORDER BY nombrecorto");
        foreach ($rows as $row) {
            $result[] = [
                'code' => $row['idempresa'],
                'description' => $row['nombrecorto'],
            ];
        }
        return $result;
    }
}
