<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace FacturaScripts\model;
require_model('cuenta.php');

/**
 * Description of grupo_epigrafes
 *
 * @author Jesus
 */
/**
 * Primer nivel del plan contable.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class grupo_epigrafes extends \fs_model {

    /**
     * Clave primaria
     * @var integer
     */
    public $idgrupo;
    public $codgrupo;
    public $codejercicio;
    public $descripcion;

    public function __construct($aux = FALSE) {
        parent::__construct('co_gruposepigrafes');
        if ($aux) {
            $this->idgrupo = $this->intval($aux['idgrupo']);
            $this->codgrupo = $aux['codgrupo'];
            $this->descripcion = $aux['descripcion'];
            $this->codejercicio = $aux['codejercicio'];
        } else {
            $this->idgrupo = NULL;
            $this->codgrupo = NULL;
            $this->descripcion = NULL;
            $this->codejercicio = NULL;
        }
    }

    protected function install() {
        return '';
    }

    public function url() {
        if (is_null($this->idgrupo)) {
            return 'index.php?page=contabilidad_epigrafes';
        }
        
        return 'index.php?page=contabilidad_epigrafes&grupo=' . $this->idgrupo;
    }

    public function get_epigrafes() {
        $epigrafe = new \epigrafe();
        return $epigrafe->all_from_grupo($this->idgrupo);
    }

    public function get($getid) {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idgrupo = " . $this->var2str($getid) . ";");
        if ($data) {
            return new \grupo_epigrafes($data[0]);
        }
        
        return FALSE;
    }

    public function get_by_codigo($cod, $codejercicio) {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codgrupo = " . $this->var2str($cod)
                . " AND codejercicio = " . $this->var2str($codejercicio) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \grupo_epigrafes($data[0]);
        }
        
        return FALSE;
    }

    public function exists() {
        if (is_null($this->idgrupo)) {
            return FALSE;
        }
        
        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idgrupo = " . $this->var2str($this->idgrupo) . ";");
    }

    public function test() {
        $this->descripcion = $this->no_html($this->descripcion);

        if (strlen($this->codejercicio) > 0 AND strlen($this->codgrupo) > 0 AND strlen($this->descripcion) > 0) {
            return TRUE;
        }
        
        $this->new_error_msg('Faltan datos en el grupo de epígrafes.');
        return FALSE;
    }

    public function save() {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET codgrupo = " . $this->var2str($this->codgrupo)
                        . ", descripcion = " . $this->var2str($this->descripcion)
                        . ", codejercicio = " . $this->var2str($this->codejercicio)
                        . "  WHERE idgrupo = " . $this->var2str($this->idgrupo) . ";";

                return $this->db->exec($sql);
            }
            
            $sql = "INSERT INTO " . $this->table_name . " (codgrupo,descripcion,codejercicio) VALUES
                     (" . $this->var2str($this->codgrupo) .
                    "," . $this->var2str($this->descripcion) .
                    "," . $this->var2str($this->codejercicio) . ");";

            if ($this->db->exec($sql)) {
                $this->idgrupo = $this->db->lastval();
                return TRUE;
            }
        }
        
        return FALSE;
    }

    public function delete() {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idgrupo = " . $this->var2str($this->idgrupo) . ";");
    }

    public function all_from_ejercicio($codejercicio) {
        $epilist = array();
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($codejercicio)
                . " ORDER BY codgrupo ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $ep) {
                $epilist[] = new \grupo_epigrafes($ep);
            }
        }

        return $epilist;
    }

}
