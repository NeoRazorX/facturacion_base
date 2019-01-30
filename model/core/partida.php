<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\model;

/**
 * La línea de un asiento.
 * Se relaciona con un asiento y una subcuenta.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class partida extends \fs_extended_model
{

    /**
     *
     * @var float
     */
    public $baseimponible;

    /**
     *
     * @var string
     */
    public $cifnif;

    /**
     *
     * @var string
     */
    public $codcontrapartida;

    /**
     *
     * @var string
     */
    public $coddivisa;

    /**
     *
     * @var string
     */
    public $codserie;

    /**
     * Código, que no ID, de la subcuenta relacionada.
     * @var string 
     */
    public $codsubcuenta;

    /**
     *
     * @var string
     */
    public $concepto;

    /**
     *
     * @var float|int
     */
    public $debe;

    /**
     *
     * @var float|int
     */
    public $debeme;

    /**
     *
     * @var string
     */
    public $documento;

    /**
     *
     * @var string
     */
    public $factura;

    /**
     *
     * @var string
     */
    public $fecha;

    /**
     *
     * @var float|int
     */
    public $haber;

    /**
     *
     * @var float|int
     */
    public $haberme;

    /**
     * ID del asiento relacionado.
     * @var integer
     */
    public $idasiento;

    /**
     *
     * @var int
     */
    public $idconcepto;

    /**
     *
     * @var int
     */
    public $idcontrapartida;

    /**
     * Clave primaria.
     * @var integer
     */
    public $idpartida;

    /**
     * ID de la subcuenta relacionada.
     * @var integer
     */
    public $idsubcuenta;

    /**
     *
     * @var float|int
     */
    public $iva;

    /**
     *
     * @var string|int
     */
    public $numero;

    /**
     *
     * @var bool
     */
    public $punteada;

    /**
     *
     * @var float|int
     */
    public $recargo;

    /**
     *
     * @var float|int
     */
    public $saldo;

    /**
     *
     * @var \subcuenta
     */
    private static $subcuenta;

    /**
     *
     * @var float|int
     */
    public $sum_debe;

    /**
     *
     * @var float|int
     */
    public $sum_haber;

    /**
     *
     * @var float|int
     */
    public $tasaconv;

    /**
     *
     * @var string
     */
    public $tipodocumento;

    /**
     * 
     * @param array|bool $data
     */
    public function __construct($data = FALSE)
    {
        parent::__construct('co_partidas', $data);
        $this->numero = 0;
        $this->fecha = Date('d-m-Y');
        $this->saldo = 0.0;
        $this->sum_debe = 0.0;
        $this->sum_haber = 0.0;

        if (!isset(self::$subcuenta)) {
            self::$subcuenta = new \subcuenta();
        }
    }

    /**
     * 
     * @param int $id
     *
     * @return \partida[]
     */
    public function all_from_asiento($id)
    {
        $plist = [];
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idasiento = "
            . $this->var2str($id) . " ORDER BY codsubcuenta ASC;";

        $partidas = $this->db->select($sql);
        if ($partidas) {
            foreach ($partidas as $p) {
                $plist[] = new \partida($p);
            }
        }

        return $plist;
    }

    /**
     * 
     * @param int $id
     * @param int $offset
     *
     * @return \partida[]
     */
    public function all_from_subcuenta($id, $offset = 0)
    {
        $plist = [];
        $sql = "SELECT a.numero,a.fecha,p.idpartida,p.debe,p.haber FROM co_asientos a, co_partidas p"
            . " WHERE a.idasiento = p.idasiento AND p.idsubcuenta = " . $this->var2str($id)
            . " ORDER BY a.numero ASC, p.idpartida ASC;";

        $ordenadas = $this->db->select($sql);
        if ($ordenadas) {
            $partida = new \partida();
            $i = 0;
            $saldo = 0;
            $sum_debe = 0;
            $sum_haber = 0;
            foreach ($ordenadas as $po) {
                $saldo += floatval($po['debe']) - floatval($po['haber']);
                $sum_debe += floatval($po['debe']);
                $sum_haber += floatval($po['haber']);
                if ($i >= $offset && $i < ($offset + FS_ITEM_LIMIT)) {
                    $aux = $partida->get($po['idpartida']);
                    if ($aux) {
                        $aux->numero = intval($po['numero']);
                        $aux->fecha = Date('d-m-Y', strtotime($po['fecha']));
                        $aux->saldo = $saldo;
                        $aux->sum_debe = $sum_debe;
                        $aux->sum_haber = $sum_haber;
                        $plist[] = $aux;
                    }
                }
                $i++;
            }
        }

        return $plist;
    }

    public function clear()
    {
        parent::clear();
        $this->concepto = '';
        $this->punteada = FALSE;
        $this->tasaconv = 1;
        $this->coddivisa = $this->default_items->coddivisa();
        $this->haberme = 0.0;
        $this->debeme = 0.0;
        $this->recargo = 0.0;
        $this->iva = 0.0;
        $this->baseimponible = 0.0;
        $this->debe = 0.0;
        $this->haber = 0.0;
        $this->numero = 0;
        $this->fecha = Date('d-m-Y');
        $this->saldo = 0.0;
        $this->sum_debe = 0.0;
        $this->sum_haber = 0.0;
    }

    /**
     * 
     * @return string
     */
    public function contrapartida_url()
    {
        $subc = $this->get_contrapartida();
        if ($subc) {
            return $subc->url();
        }

        return '#';
    }

    /**
     * 
     * @param int $id
     *
     * @return int
     */
    public function count_from_subcuenta($id)
    {
        $sql = "SELECT a.numero,a.fecha,p.idpartida FROM co_asientos a, co_partidas p"
            . " WHERE a.idasiento = p.idasiento AND p.idsubcuenta = " . $this->var2str($id)
            . " ORDER BY a.numero ASC, p.idpartida ASC;";

        $ordenadas = $this->db->select($sql);
        if ($ordenadas) {
            return count($ordenadas);
        }

        return 0;
    }

    /**
     * 
     * @return bool
     */
    public function delete()
    {
        if (parent::delete()) {
            $subc = $this->get_subcuenta();
            if ($subc) {
                $subc->save(); /// guardamos la subcuenta para actualizar su saldo
            }

            return TRUE;
        }

        return FALSE;
    }

    /**
     * 
     * @param string $codejercicio
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     */
    public function full_from_ejercicio($codejercicio, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT a.numero,a.fecha,s.codsubcuenta,s.descripcion,p.concepto,p.debe,p.haber"
            . " FROM co_asientos a, co_subcuentas s, co_partidas p"
            . " WHERE a.codejercicio = " . $this->var2str($codejercicio)
            . " AND p.idasiento = a.idasiento AND p.idsubcuenta = s.idsubcuenta"
            . " ORDER BY a.numero ASC, p.codsubcuenta ASC";

        $data = $this->db->select_limit($sql, $limit, $offset);
        if ($data) {
            return $data;
        }

        return [];
    }

    /**
     * 
     * @param int $id
     *
     * @return \partida[]
     */
    public function full_from_subcuenta($id)
    {
        $plist = [];
        $sql = "SELECT a.numero,a.fecha,p.idpartida FROM co_asientos a, co_partidas p"
            . " WHERE a.idasiento = p.idasiento AND p.idsubcuenta = " . $this->var2str($id)
            . " ORDER BY a.numero ASC, p.idpartida ASC";

        $saldo = 0;
        $sum_debe = 0;
        $sum_haber = 0;

        $partida = new \partida();
        $offset = 0;
        $data = $this->db->select_limit($sql, 100, $offset);
        while ($data) {
            foreach ($data as $po) {
                $aux = $partida->get($po['idpartida']);
                if ($aux) {
                    $aux->numero = intval($po['numero']);
                    $aux->fecha = Date('d-m-Y', strtotime($po['fecha']));
                    $saldo += $aux->debe - $aux->haber;
                    $sum_debe += $aux->debe;
                    $sum_haber += $aux->haber;
                    $aux->saldo = $saldo;
                    $aux->sum_debe = $sum_debe;
                    $aux->sum_haber = $sum_haber;
                    $plist[] = $aux;
                }

                $offset++;
            }

            $data = $this->db->select_limit($sql, 100, $offset);
        }

        return $plist;
    }

    /**
     * 
     * @return \subcuenta|bool
     */
    public function get_contrapartida()
    {
        if (is_null($this->idcontrapartida)) {
            return FALSE;
        }

        return self::$subcuenta->get($this->idcontrapartida);
    }

    /**
     * 
     * @return \subcuenta
     */
    public function get_subcuenta()
    {
        return self::$subcuenta->get($this->idsubcuenta);
    }

    /**
     * 
     * @return string
     */
    public function model_class_name()
    {
        return 'partida';
    }

    /**
     * 
     * @return string
     */
    public function primary_column()
    {
        return 'idpartida';
    }

    /**
     * 
     * @return bool
     */
    public function save()
    {
        $this->concepto = $this->no_html($this->concepto);
        $this->documento = $this->no_html($this->documento);
        $this->cifnif = $this->no_html($this->cifnif);

        if (parent::save()) {
            $subc = $this->get_subcuenta();
            if ($subc) {
                $subc->save(); /// guardamos la subcuenta para actualizar su saldo
            }
            return true;
        }

        return false;
    }

    /**
     * 
     * @return string
     */
    public function subcuenta_url()
    {
        $subc = $this->get_subcuenta();
        if ($subc) {
            return $subc->url();
        }

        return '#';
    }

    /**
     * 
     * @param string $cod
     *
     * @return array
     */
    public function totales_from_ejercicio($cod)
    {
        return $this->totales_aux("SELECT COALESCE(SUM(p.debe), 0) as debe,COALESCE(SUM(p.haber), 0) as haber"
                . " FROM co_partidas p, co_asientos a"
                . " WHERE p.idasiento = a.idasiento AND a.codejercicio = " . $this->var2str($cod) . ";");
    }

    /**
     * 
     * @param int $id
     *
     * @return array
     */
    public function totales_from_subcuenta($id)
    {
        return $this->totales_aux("SELECT COALESCE(SUM(debe), 0) as debe,COALESCE(SUM(haber), 0) as haber"
                . " FROM " . $this->table_name . " WHERE idsubcuenta = " . $this->var2str($id) . ";");
    }

    /**
     * 
     * @param int        $id
     * @param string     $fechaini
     * @param string     $fechafin
     * @param array|bool $excluir
     *
     * @return array
     */
    public function totales_from_subcuenta_fechas($id, $fechaini, $fechafin, $excluir = FALSE)
    {
        if ($excluir) {
            $sql = "SELECT COALESCE(SUM(p.debe), 0) as debe,
            COALESCE(SUM(p.haber), 0) as haber FROM co_partidas p, co_asientos a
            WHERE p.idasiento = a.idasiento AND p.idsubcuenta = " . $this->var2str($id) . "
               AND a.fecha BETWEEN " . $this->var2str($fechaini) . " AND " . $this->var2str($fechafin) . "
               AND p.idasiento NOT IN ('" . implode("','", $excluir) . "');";
            return $this->totales_aux($sql);
        }

        $sql = "SELECT COALESCE(SUM(p.debe), 0) as debe,
            COALESCE(SUM(p.haber), 0) as haber FROM co_partidas p, co_asientos a
            WHERE p.idasiento = a.idasiento AND p.idsubcuenta = " . $this->var2str($id) . "
               AND a.fecha BETWEEN " . $this->var2str($fechaini) . " AND " . $this->var2str($fechafin) . ";";
        return $this->totales_aux($sql);
    }

    /**
     * 
     * @param string $type
     *
     * @return string
     */
    public function url($type = 'auto')
    {
        if (is_null($this->idasiento) || $type == 'list') {
            return 'index.php?page=contabilidad_asientos';
        }

        return 'index.php?page=contabilidad_asiento&id=' . $this->idasiento;
    }

    /**
     * 
     * @param string $sql
     *
     * @return array
     */
    private function totales_aux($sql)
    {
        $totales = ['debe' => 0, 'haber' => 0, 'saldo' => 0];
        $resultados = $this->db->select($sql);
        if ($resultados) {
            $totales['debe'] = floatval($resultados[0]['debe']);
            $totales['haber'] = floatval($resultados[0]['haber']);
            $totales['saldo'] = floatval($resultados[0]['debe']) - floatval($resultados[0]['haber']);
        }

        return $totales;
    }
}
