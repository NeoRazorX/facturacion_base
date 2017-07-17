<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace FacturaScripts\model;
require_model('ejercicio.php');
require_model('serie.php');


/**
 * Description of secuencia_contabilidad
 *
 * @author Jesus
 */
/**
 * Clase que permite la compatibilidad con Eneboo.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class secuencia_contabilidad extends \fs_model {

    /**
     * Clave primaria.
     * @var type 
     */
    public $idsecuencia;
    public $valorout;
    public $valor;
    public $descripcion;
    public $nombre;
    public $codejercicio;

    public function __construct($s = FALSE) {
        parent::__construct('co_secuencias');
        if ($s) {
            $this->codejercicio = $s['codejercicio'];
            $this->descripcion = $s['descripcion'];
            $this->idsecuencia = $this->intval($s['idsecuencia']);
            $this->nombre = $s['nombre'];
            $this->valor = $this->intval($s['valor']);
            $this->valorout = $this->intval($s['valorout']);
        } else {
            $this->codejercicio = NULL;
            $this->descripcion = NULL;
            $this->idsecuencia = NULL;
            $this->nombre = NULL;
            $this->valor = NULL;
            $this->valorout = 1;
        }
    }

    protected function install() {
        return '';
    }

    public function get_by_params($eje, $nombre) {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " .
                $this->var2str($eje) . " AND nombre = " . $this->var2str($nombre) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \secuencia_contabilidad($data[0]);
        }

        return FALSE;
    }

    public function get_by_params2($eje, $nombre) {
        $sec = $this->get_by_params($eje, $nombre);
        if ($sec) {
            return $sec;
        }

        $newsec = new \secuencia_contabilidad();
        $newsec->codejercicio = $eje;
        $newsec->descripcion = 'Creado por FacturaScripts';
        $newsec->nombre = $nombre;
        return $newsec;
    }

    public function exists() {
        if (is_null($this->idsecuencia)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idsecuencia = " .
                        $this->var2str($this->idsecuencia) . ";");
    }

    public function save() {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET codejercicio = " . $this->var2str($this->codejercicio) .
                    ", descripcion = " . $this->var2str($this->descripcion) .
                    ", nombre = " . $this->var2str($this->nombre) .
                    ", valor = " . $this->var2str($this->valor) .
                    ", valorout = " . $this->var2str($this->valorout) .
                    "  WHERE idsecuencia = " . $this->var2str($this->idsecuencia) . ";";

            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO " . $this->table_name . " (codejercicio,descripcion,nombre,valor,valorout) VALUES "
                . "(" . $this->var2str($this->codejercicio)
                . "," . $this->var2str($this->descripcion)
                . "," . $this->var2str($this->nombre)
                . "," . $this->var2str($this->valor)
                . "," . $this->var2str($this->valorout) . ");";

        if ($this->db->exec($sql)) {
            $this->idsecuencia = $this->db->lastval();
            return TRUE;
        }

        return FALSE;
    }

    public function delete() {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idsecuencia = " . $this->var2str($this->idsecuencia) . ";");
    }

}
