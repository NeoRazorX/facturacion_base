<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\model;

/**
 * Detalle abreviado de un balance.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class balance_cuenta_a extends \fs_model
{

    /**
     * Clave primaria.
     * @var integer
     */
    public $id;
    public $codbalance;
    public $codcuenta;
    public $desccuenta;

    public function __construct($data = FALSE)
    {
        parent::__construct('co_cuentascbba');
        if ($data) {
            $this->id = $this->intval($data['id']);
            $this->codbalance = $data['codbalance'];
            $this->codcuenta = $data['codcuenta'];
            $this->desccuenta = $data['desccuenta'];
        } else {
            $this->id = NULL;
            $this->codbalance = NULL;
            $this->codcuenta = NULL;
            $this->desccuenta = NULL;
        }
    }

    protected function install()
    {
        return '';
    }

    /**
     * Devuelve el saldo del balance de un ejercicio.
     * @param ejercicio $ejercicio
     * @param string $desde
     * @param string $hasta
     * @return int
     */
    public function saldo(&$ejercicio, $desde = FALSE, $hasta = FALSE)
    {
        $extra = '';
        if (isset($ejercicio->idasientopyg)) {
            if (isset($ejercicio->idasientocierre)) {
                $extra = " AND idasiento NOT IN (" . $this->var2str($ejercicio->idasientocierre)
                    . "," . $this->var2str($ejercicio->idasientopyg) . ')';
            } else {
                $extra = " AND idasiento != " . $this->var2str($ejercicio->idasientopyg);
            }
        } else if (isset($ejercicio->idasientocierre)) {
            $extra = " AND idasiento != " . $this->var2str($ejercicio->idasientocierre);
        }

        if ($desde && $hasta) {
            $extra .= " AND idasiento IN (SELECT idasiento FROM co_asientos WHERE "
                . "fecha >= " . $this->var2str($desde) . " AND "
                . "fecha <= " . $this->var2str($hasta) . ")";
        }

        if ($this->codcuenta == '129') {
            $data = $this->db->select("SELECT SUM(debe) as debe, SUM(haber) as haber FROM co_partidas
            WHERE idsubcuenta IN (SELECT idsubcuenta FROM co_subcuentas
               WHERE (codcuenta LIKE '6%' OR codcuenta LIKE '7%') AND codejercicio = " . $this->var2str($ejercicio->codejercicio) . ")" . $extra . ";");
        } else {
            $data = $this->db->select("SELECT SUM(debe) as debe, SUM(haber) as haber FROM co_partidas
            WHERE idsubcuenta IN (SELECT idsubcuenta FROM co_subcuentas
               WHERE codcuenta LIKE '" . $this->no_html($this->codcuenta) . "%'"
                . " AND codejercicio = " . $this->var2str($ejercicio->codejercicio) . ")" . $extra . ";");
        }

        if ($data) {
            return floatval($data[0]['haber']) - floatval($data[0]['debe']);
        }

        return 0;
    }

    public function get($getid)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($getid) . ";");
        if ($data) {
            return new \balance_cuenta_a($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->id)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
    }

    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET codbalance = " . $this->var2str($this->codbalance) .
                ", codcuenta = " . $this->var2str($this->codcuenta) .
                ", desccuenta = " . $this->var2str($this->desccuenta) .
                "  WHERE id = " . $this->var2str($this->id) . ";";

            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO " . $this->table_name . " (codbalance,codcuenta,desccuenta) VALUES "
            . "(" . $this->var2str($this->codbalance)
            . "," . $this->var2str($this->codcuenta)
            . "," . $this->var2str($this->desccuenta) . ");";

        if ($this->db->exec($sql)) {
            $this->id = $this->db->lastval();
            return TRUE;
        }

        return FALSE;
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
    }

    public function all()
    {
        return $this->all_from("SELECT * FROM " . $this->table_name . ";");
    }

    public function all_from_codbalance($cod)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codbalance = " . $this->var2str($cod) . " ORDER BY codcuenta ASC;";
        return $this->all_from($sql);
    }

    public function search_by_codbalance($cod)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codbalance LIKE '" . $this->no_html($cod) . "%' ORDER BY codcuenta ASC;";
        return $this->all_from($sql);
    }

    private function all_from($sql)
    {
        $balist = array();
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $a) {
                $balist[] = new \balance_cuenta_a($a);
            }
        }

        return $balist;
    }
}
