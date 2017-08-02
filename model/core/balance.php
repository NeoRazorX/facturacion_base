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
 * Define que cuentas hay que usar para generar los distintos informes contables.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class balance extends \fs_model
{

    /**
     * Clave primaria.
     * @var string 
     */
    public $codbalance;
    public $descripcion4ba;
    public $descripcion4;
    public $nivel4;
    public $descripcion3;
    public $orden3;
    public $nivel3;
    public $descripcion2;
    public $nivel2;
    public $descripcion1;
    public $nivel1;
    public $naturaleza;

    public function __construct($data = FALSE)
    {
        parent::__construct('co_codbalances08');
        if ($data) {
            $this->codbalance = $data['codbalance'];
            $this->naturaleza = $data['naturaleza'];
            $this->nivel1 = $data['nivel1'];
            $this->descripcion1 = $data['descripcion1'];
            $this->nivel2 = $this->intval($data['nivel2']);
            $this->descripcion2 = $data['descripcion2'];
            $this->nivel3 = $data['nivel3'];
            $this->descripcion3 = $data['descripcion3'];
            $this->orden3 = $data['orden3'];
            $this->nivel4 = $data['nivel4'];
            $this->descripcion4 = $data['descripcion4'];
            $this->descripcion4ba = $data['descripcion4ba'];
        } else {
            $this->codbalance = NULL;
            $this->naturaleza = NULL;
            $this->nivel1 = NULL;
            $this->descripcion1 = NULL;
            $this->nivel2 = NULL;
            $this->descripcion2 = NULL;
            $this->nivel3 = NULL;
            $this->descripcion3 = NULL;
            $this->orden3 = NULL;
            $this->nivel4 = NULL;
            $this->descripcion4 = NULL;
            $this->descripcion4ba = NULL;
        }
    }

    protected function install()
    {
        return '';
    }

    public function url()
    {
        if (is_null($this->codbalance)) {
            return 'index.php?page=editar_balances';
        }

        return 'index.php?page=editar_balances&cod=' . $this->codbalance;
    }

    public function get($cod)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codbalance = " . $this->var2str($cod) . ";");
        if ($data) {
            return new \balance($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->codbalance)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codbalance = " .
                $this->var2str($this->codbalance) . ";");
    }

    public function save()
    {
        $this->descripcion1 = $this->no_html($this->descripcion1);
        $this->descripcion2 = $this->no_html($this->descripcion2);
        $this->descripcion3 = $this->no_html($this->descripcion3);
        $this->descripcion4 = $this->no_html($this->descripcion4);
        $this->descripcion4ba = $this->no_html($this->descripcion4ba);

        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET naturaleza = " . $this->var2str($this->naturaleza) .
                ", nivel1 = " . $this->var2str($this->nivel1) .
                ", descripcion1 = " . $this->var2str($this->descripcion1) .
                ", nivel2 = " . $this->var2str($this->nivel2) .
                ", descripcion2 = " . $this->var2str($this->descripcion2) .
                ", nivel3 = " . $this->var2str($this->nivel3) .
                ", descripcion3 = " . $this->var2str($this->descripcion3) .
                ", orden3 = " . $this->var2str($this->orden3) .
                ", nivel4 = " . $this->var2str($this->nivel4) .
                ", descripcion4 = " . $this->var2str($this->descripcion4) .
                ", descripcion4ba = " . $this->var2str($this->descripcion4ba) .
                "  WHERE codbalance = " . $this->var2str($this->codbalance) . ";";
        } else {
            $sql = "INSERT INTO " . $this->table_name . " (codbalance,naturaleza,nivel1,descripcion1,
            nivel2,descripcion2,nivel3,descripcion3,orden3,nivel4,descripcion4,descripcion4ba) VALUES 
                  (" . $this->var2str($this->codbalance) .
                "," . $this->var2str($this->naturaleza) .
                "," . $this->var2str($this->nivel1) .
                "," . $this->var2str($this->descripcion1) .
                "," . $this->var2str($this->nivel2) .
                "," . $this->var2str($this->descripcion2) .
                "," . $this->var2str($this->nivel3) .
                "," . $this->var2str($this->descripcion3) .
                "," . $this->var2str($this->orden3) .
                "," . $this->var2str($this->nivel4) .
                "," . $this->var2str($this->descripcion4) .
                "," . $this->var2str($this->descripcion4ba) . ");";
        }

        return $this->db->exec($sql);
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE codbalance = " . $this->var2str($this->codbalance) . ";");
    }

    public function all()
    {
        $balist = array();

        $data = $this->db->select("SELECT * FROM " . $this->table_name . " ORDER BY codbalance ASC;");
        if ($data) {
            foreach ($data as $b) {
                $balist[] = new \balance($b);
            }
        }

        return $balist;
    }
}
