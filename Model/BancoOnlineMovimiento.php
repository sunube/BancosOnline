<?php

namespace FacturaScripts\Plugins\BancosOnline\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class BancoOnlineMovimiento extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var int */
    public $idcuenta;

    /** @var string */
    public $tx_id;

    /** @var string */
    public $fecha;

    /** @var float */
    public $importe;

    /** @var string */
    public $moneda;

    /** @var string */
    public $descripcion;

    /** @var string */
    public $contraparte;

    /** @var string */
    public $estado;

    /** @var string */
    public $creation_date;

    public function clear(): void
    {
        parent::clear();
        $this->moneda = 'EUR';
        $this->estado = 'BOOK';
        $this->creation_date = date('Y-m-d H:i:s');
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'bancos_online_movimientos';
    }

    public function test(): bool
    {
        $this->descripcion = Tools::noHtml($this->descripcion ?? '');
        $this->contraparte = Tools::noHtml($this->contraparte ?? '');

        if (empty($this->tx_id)) {
            Tools::log()->warning('El ID de transaccion es obligatorio.');
            return false;
        }

        return parent::test();
    }

    /**
     * Inserta o actualiza un movimiento por idcuenta + tx_id.
     */
    public static function upsert(int $idcuenta, array $data): self
    {
        $mov = new self();
        $where = [
            new DataBaseWhere('idcuenta', $idcuenta),
            new DataBaseWhere('tx_id', $data['tx_id'] ?? ''),
        ];

        if (!$mov->loadFromCode('', $where)) {
            $mov->idcuenta = $idcuenta;
            $mov->tx_id = $data['tx_id'] ?? '';
        }

        $mov->fecha = $data['fecha'] ?? date('Y-m-d');
        $mov->importe = (float) ($data['importe'] ?? 0);
        $mov->moneda = $data['moneda'] ?? 'EUR';
        $mov->descripcion = $data['descripcion'] ?? '';
        $mov->contraparte = $data['contraparte'] ?? '';
        $mov->estado = $data['estado'] ?? 'BOOK';

        $mov->save();
        return $mov;
    }

    /**
     * Obtiene movimientos con filtros y datos de cuenta/empresa.
     */
    public static function search(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        $db = new DataBase();
        $sql = "SELECT m.*, c.banco, c.iban, c.idempresa,"
            . " e.nombrecorto as empresa_nombre,"
            . " b.color as banco_color"
            . " FROM bancos_online_movimientos m"
            . " JOIN bancos_online_cuentas c ON m.idcuenta = c.id"
            . " LEFT JOIN empresas e ON c.idempresa = e.idempresa"
            . " LEFT JOIN bancos_online_bancos b ON c.banco = b.nombre"
            . " WHERE 1=1";

        $params = [];

        if (!empty($filters['idcuenta'])) {
            $sql .= " AND m.idcuenta = " . (int) $filters['idcuenta'];
        }

        if (!empty($filters['idempresa'])) {
            $sql .= " AND c.idempresa = " . (int) $filters['idempresa'];
        }

        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND m.fecha >= " . $db->var2str($filters['fecha_desde']);
        }

        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND m.fecha <= " . $db->var2str($filters['fecha_hasta']);
        }

        if (!empty($filters['buscar'])) {
            $term = $db->var2str('%' . $filters['buscar'] . '%');
            $sql .= " AND (m.descripcion LIKE " . $term . " OR m.contraparte LIKE " . $term . ")";
        }

        $sql .= " ORDER BY m.fecha DESC, m.id DESC";
        $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        return $db->select($sql);
    }

    /**
     * Totales mensuales de ingresos y gastos.
     */
    public static function monthlyTotals(int $months = 6, ?int $idempresa = null): array
    {
        $db = new DataBase();
        $cutoff = date('Y-m-d', strtotime("-{$months} months"));

        // Compatible con MySQL y PostgreSQL
        $sql = "SELECT DATE_FORMAT(m.fecha, '%%Y-%%m') as mes,"
            . " SUM(CASE WHEN m.importe > 0 THEN m.importe ELSE 0 END) as ingresos,"
            . " SUM(CASE WHEN m.importe < 0 THEN ABS(m.importe) ELSE 0 END) as gastos"
            . " FROM bancos_online_movimientos m"
            . " JOIN bancos_online_cuentas c ON m.idcuenta = c.id"
            . " WHERE m.fecha >= " . $db->var2str($cutoff);

        if (null !== $idempresa) {
            $sql .= " AND c.idempresa = " . (int) $idempresa;
        }

        $sql .= " GROUP BY DATE_FORMAT(m.fecha, '%%Y-%%m') ORDER BY mes";

        return $db->select($sql);
    }

    /**
     * Flujo diario de los ultimos N dias.
     */
    public static function dailyFlow(int $days = 30, ?int $idempresa = null): array
    {
        $db = new DataBase();
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));

        $sql = "SELECT m.fecha,"
            . " SUM(m.importe) as flujo_neto,"
            . " SUM(CASE WHEN m.importe > 0 THEN m.importe ELSE 0 END) as ingresos,"
            . " SUM(CASE WHEN m.importe < 0 THEN ABS(m.importe) ELSE 0 END) as gastos"
            . " FROM bancos_online_movimientos m"
            . " JOIN bancos_online_cuentas c ON m.idcuenta = c.id"
            . " WHERE m.fecha >= " . $db->var2str($cutoff);

        if (null !== $idempresa) {
            $sql .= " AND c.idempresa = " . (int) $idempresa;
        }

        $sql .= " GROUP BY m.fecha ORDER BY m.fecha";

        return $db->select($sql);
    }

    public function url(string $type = 'auto', string $list = 'ListBancoOnlineMovimiento'): string
    {
        return parent::url($type, $list);
    }
}
