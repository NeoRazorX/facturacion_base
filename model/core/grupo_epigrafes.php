<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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
 * Primer nivel del plan contable.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class grupo_epigrafes extends \fs_model
{

    /**
     * Clave primaria
     * @var integer
     */
    public $idgrupo;
    public $codgrupo;
    public $codejercicio;
    public $descripcion;

    public function __construct($data = FALSE)
    {
        parent::__construct('co_gruposepigrafes');
        if ($data) {
            $this->idgrupo = $this->intval($data['idgrupo']);
            $this->codgrupo = $data['codgrupo'];
            $this->descripcion = $data['descripcion'];
            $this->codejercicio = $data['codejercicio'];
        } else {
            $this->idgrupo = NULL;
            $this->codgrupo = NULL;
            $this->descripcion = NULL;
            $this->codejercicio = NULL;
        }
    }

    protected function install()
    {
        return '';
    }

    public function url()
    {
        if (is_null($this->idgrupo)) {
            return 'index.php?page=contabilidad_epigrafes';
        }

        return 'index.php?page=contabilidad_epigrafes&grupo=' . $this->idgrupo;
    }

    public function get_epigrafes()
    {
        $epigrafe = new \epigrafe();
        return $epigrafe->all_from_grupo($this->idgrupo);
    }

    public function get($getid)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idgrupo = " . $this->var2str($getid) . ";");
        if ($data) {
            return new \grupo_epigrafes($data[0]);
        }

        return FALSE;
    }

    public function get_by_codigo($cod, $codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codgrupo = " . $this->var2str($cod)
            . " AND codejercicio = " . $this->var2str($codejercicio) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \grupo_epigrafes($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idgrupo)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idgrupo = " . $this->var2str($this->idgrupo) . ";");
    }

    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);

        if (strlen($this->codejercicio) > 0 && strlen($this->codgrupo) > 0 && strlen($this->descripcion) > 0) {
            return TRUE;
        }

        $this->new_error_msg('Faltan datos en el grupo de epígrafes.');
        return FALSE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET codgrupo = " . $this->var2str($this->codgrupo)
                    . ", descripcion = " . $this->var2str($this->descripcion)
                    . ", codejercicio = " . $this->var2str($this->codejercicio)
                    . "  WHERE idgrupo = " . $this->var2str($this->idgrupo) . ";";

                return $this->db->exec($sql);
            }

            $sql = "INSERT INTO " . $this->table_name . " (codgrupo,descripcion,codejercicio) VALUES "
                . "(" . $this->var2str($this->codgrupo)
                . "," . $this->var2str($this->descripcion)
                . "," . $this->var2str($this->codejercicio) . ");";

            if ($this->db->exec($sql)) {
                $this->idgrupo = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idgrupo = " . $this->var2str($this->idgrupo) . ";");
    }

    public function all_from_ejercicio($codejercicio)
    {
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
