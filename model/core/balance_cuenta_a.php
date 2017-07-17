<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace FacturaScripts\model;
require_model('ejercicio.php');
/**
 * Description of balance_cuenta_a
 *
 * @author Jesus
 */
/**
 * Detalle abreviado de un balance.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class balance_cuenta_a extends \fs_model {

    /**
     * Clave primaria.
     * @var type 
     */
    public $idbalancea;
    public $codbalance;
    public $codcuenta;
    public $desccuenta;

    public function __construct($aux = FALSE) {
        parent::__construct('co_cuentascbba');
        if ($aux) {
            $this->idbalancea = $this->intval($aux['id']);
            $this->codbalance = $aux['codbalance'];
            $this->codcuenta = $aux['codcuenta'];
            $this->desccuenta = $aux['desccuenta'];
        } else {
            $this->idbalancea = NULL;
            $this->codbalance = NULL;
            $this->codcuenta = NULL;
            $this->desccuenta = NULL;
        }
    }

    protected function install() {
        return '';
    }

    /**
     * Devuelve el saldo del balance de un ejercicio.
     * @param ejercicio $ejercicio
     * @param type $desde
     * @param type $hasta
     * @return int
     */
    public function saldo(&$ejercicio, $desde = FALSE, $hasta = FALSE) {
        $extra = '';
        if (isset($ejercicio->idasientopyg)) {
            if (isset($ejercicio->idasientocierre)) {
                $extra = " AND idasiento NOT IN (" . $this->var2str($ejercicio->idasientocierre)
                        . "," . $this->var2str($ejercicio->idasientopyg) . ')';
            } else
                $extra = " AND idasiento != " . $this->var2str($ejercicio->idasientopyg);
        }
        else if (isset($ejercicio->idasientocierre)) {
            $extra = " AND idasiento != " . $this->var2str($ejercicio->idasientocierre);
        }

        if ($desde AND $hasta) {
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

    public function get($getid) {
        $bca = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($getid) . ";");
        if ($bca) {
            return new \balance_cuenta_a($bca[0]);
        }
        return FALSE;
    }

    public function exists() {
        if (is_null($this->idbalancea)) {
            return FALSE;
        }
        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->idbalancea) . ";");
    }

    public function save() {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET codbalance = " . $this->var2str($this->codbalance) .
                    ", codcuenta = " . $this->var2str($this->codcuenta) .
                    ", desccuenta = " . $this->var2str($this->desccuenta) .
                    "  WHERE id = " . $this->var2str($this->idbalancea) . ";";

            return $this->db->exec($sql);
        } else {
            $sql = "INSERT INTO " . $this->table_name . " (codbalance,codcuenta,desccuenta) VALUES "
                    . "(" . $this->var2str($this->codbalance)
                    . "," . $this->var2str($this->codcuenta)
                    . "," . $this->var2str($this->desccuenta) . ");";

            if ($this->db->exec($sql)) {
                $this->idbalancea = $this->db->lastval();
                return TRUE;
            }
            return FALSE;
        }
    }

    public function delete() {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->idbalancea) . ";");
    }

    public function all() {
        $balist = array();

        $data = $this->db->select("SELECT * FROM " . $this->table_name . ";");
        if ($data) {
            foreach ($data as $b) {
                $balist[] = new \balance_cuenta_a($b);
            }
        }

        return $balist;
    }

    public function all_from_codbalance($cod) {
        $balist = array();
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codbalance = " . $this->var2str($cod) . " ORDER BY codcuenta ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $b) {
                $balist[] = new \balance_cuenta_a($b);
            }
        }

        return $balist;
    }

    public function search_by_codbalance($cod) {
        $balist = array();
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codbalance LIKE '" . $this->no_html($cod) . "%' ORDER BY codcuenta ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $b) {
                $balist[] = new \balance_cuenta_a($b);
            }
        }

        return $balist;
    }

}
