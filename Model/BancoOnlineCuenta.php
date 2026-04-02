<?php

namespace FacturaScripts\Plugins\BancosOnline\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class BancoOnlineCuenta extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $account_uid;

    /** @var int */
    public $idempresa;

    /** @var string */
    public $banco;

    /** @var string */
    public $iban;

    /** @var string */
    public $moneda;

    /** @var string */
    public $nombre;

    /** @var string */
    public $tipo_cuenta;

    /** @var string */
    public $session_id;

    /** @var string */
    public $identification_hash;

    /** @var float */
    public $saldo;

    /** @var string */
    public $saldo_moneda;

    /** @var string */
    public $last_sync;

    /** @var string */
    public $creation_date;

    public function clear(): void
    {
        parent::clear();
        $this->moneda = 'EUR';
        $this->saldo = 0;
        $this->saldo_moneda = 'EUR';
        $this->creation_date = date('Y-m-d H:i:s');
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'bancos_online_cuentas';
    }

    public function test(): bool
    {
        $this->account_uid = Tools::noHtml($this->account_uid ?? '');
        $this->banco = Tools::noHtml($this->banco ?? '');
        $this->iban = Tools::noHtml($this->iban ?? '');
        $this->nombre = Tools::noHtml($this->nombre ?? '');

        if (empty($this->account_uid)) {
            Tools::log()->warning('El UID de la cuenta es obligatorio.');
            return false;
        }

        return parent::test();
    }

    /**
     * Busca una cuenta por su account_uid de Enable Banking.
     */
    public static function findByUid(string $uid): ?self
    {
        $cuenta = new self();
        $where = [new DataBaseWhere('account_uid', $uid)];

        if ($cuenta->loadFromCode('', $where)) {
            return $cuenta;
        }

        return null;
    }

    /**
     * Inserta o actualiza una cuenta por su account_uid.
     */
    public static function upsert(array $data): self
    {
        $cuenta = self::findByUid($data['account_uid'] ?? '');

        if (null === $cuenta) {
            $cuenta = new self();
        }

        $cuenta->account_uid = $data['account_uid'] ?? '';
        $cuenta->banco = $data['banco'] ?? '';
        $cuenta->iban = $data['iban'] ?? '';
        $cuenta->moneda = $data['moneda'] ?? 'EUR';
        $cuenta->nombre = $data['nombre'] ?? '';
        $cuenta->tipo_cuenta = $data['tipo_cuenta'] ?? '';
        $cuenta->session_id = $data['session_id'] ?? '';
        $cuenta->identification_hash = $data['identification_hash'] ?? '';

        // Solo asigna empresa si viene en los datos o si es nueva
        if (isset($data['idempresa'])) {
            $cuenta->idempresa = $data['idempresa'];
        }

        $cuenta->save();
        return $cuenta;
    }

    /**
     * Actualiza el saldo de la cuenta.
     */
    public function updateBalance(float $saldo, string $moneda = 'EUR'): bool
    {
        $this->saldo = $saldo;
        $this->saldo_moneda = $moneda;
        $this->last_sync = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Devuelve todas las cuentas con datos de empresa y banco.
     */
    public static function allWithDetails(?int $idempresa = null): array
    {
        $db = new DataBase();
        $sql = "SELECT c.*, e.nombrecorto as empresa_nombre, b.color as banco_color, b.logo_url as banco_logo"
            . " FROM bancos_online_cuentas c"
            . " LEFT JOIN empresas e ON c.idempresa = e.idempresa"
            . " LEFT JOIN bancos_online_bancos b ON c.banco = b.nombre";

        if (null !== $idempresa) {
            $sql .= " WHERE c.idempresa = " . (int) $idempresa;
        }

        $sql .= " ORDER BY e.nombrecorto, c.banco, c.iban";

        return $db->select($sql);
    }

    /**
     * Devuelve el saldo total de todas las cuentas (o de una empresa).
     */
    public static function totalBalance(?int $idempresa = null): float
    {
        $db = new DataBase();
        $sql = "SELECT COALESCE(SUM(saldo), 0) as total FROM bancos_online_cuentas";

        if (null !== $idempresa) {
            $sql .= " WHERE idempresa = " . (int) $idempresa;
        }

        $rows = $db->select($sql);
        return $rows ? (float) $rows[0]['total'] : 0.0;
    }

    public function url(string $type = 'auto', string $list = 'ListBancoOnlineCuenta'): string
    {
        return parent::url($type, $list);
    }
}
