<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace FacturaScripts\model;
require_model('ejercicio.php');
/**
 * Description of balance_cuenta
 *
 * @author Jesus
 */
/**
 * Detalle de un balance.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class balance_cuenta extends \fs_model {

    /**
     * Clave primaria.
     * @var type 
     */
    public $idbalance;
    public $codbalance;
    public $codcuenta;
    public $desccuenta;

    public function __construct($aux = FALSE) {
        parent::__construct('co_cuentascb');
        if ($aux) {
            $this->idbalance = $this->intval($aux['id']);
            $this->codbalance = $aux['codbalance'];
            $this->codcuenta = $aux['codcuenta'];
            $this->desccuenta = $aux['desccuenta'];
        } else {
            $this->idbalance = NULL;
            $this->codbalance = NULL;
            $this->codcuenta = NULL;
            $this->desccuenta = NULL;
        }
    }

    protected function install() {
        return '';
    }

    public function get($getid) {
        $result = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($getid) . ";");
        if ($result) {
            return new \balance_cuenta($result[0]);
        }
        return FALSE;
    }

    public function exists() {
        if (is_null($this->idbalance)) {
            return FALSE;
        }
        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->idbalance) . ";");
    }

    public function save() {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET codbalance = " . $this->var2str($this->codbalance) .
                    ", codcuenta = " . $this->var2str($this->codcuenta) .
                    ", desccuenta = " . $this->var2str($this->desccuenta) .
                    "  WHERE id = " . $this->var2str($this->idbalance) . ";";

            return $this->db->exec($sql);
        } else {
            $sql = "INSERT INTO " . $this->table_name . " (codbalance,codcuenta,desccuenta) VALUES "
                    . "(" . $this->var2str($this->codbalance)
                    . "," . $this->var2str($this->codcuenta)
                    . "," . $this->var2str($this->desccuenta) . ");";

            if ($this->db->exec($sql)) {
                $this->idbalance = $this->db->lastval();
                return TRUE;
            }
            return FALSE;
        }
    }

    public function delete() {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->idbalance) . ";");
    }

    public function all() {
        $balist = array();

        $data = $this->db->select("SELECT * FROM " . $this->table_name . ";");
        if ($data) {
            foreach ($data as $aux) {
                $balist[] = new \balance_cuenta($aux);
            }
        }

        return $balist;
    }

    public function all_from_codbalance($cod) {
        $balist = array();

        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codbalance = " . $this->var2str($cod) . " ORDER BY codcuenta ASC;");
        if ($data) {
            foreach ($data as $aux) {
                $balist[] = new \balance_cuenta($aux);
            }
        }

        return $balist;
    }

}
