<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2015-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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
 * Un atributo para artículos.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class atributo extends \fs_model
{

    /**
     * Clave primaria.
     * @var string 
     */
    public $codatributo;
    public $nombre;

    public function __construct($data = FALSE)
    {
        parent::__construct('atributos');
        if ($data) {
            $this->codatributo = $data['codatributo'];
            $this->nombre = $data['nombre'];
        } else {
            $this->codatributo = NULL;
            $this->nombre = NULL;
        }
    }

    protected function install()
    {
        return '';
    }

    public function url()
    {
        return 'index.php?page=ventas_atributos&cod=' . urlencode($this->codatributo);
    }

    public function valores()
    {
        $valor0 = new \atributo_valor();
        return $valor0->all_from_atributo($this->codatributo);
    }

    public function get($cod)
    {
        $data = $this->db->select("SELECT * FROM atributos WHERE codatributo = " . $this->var2str($cod) . ";");
        if ($data) {
            return new \atributo($data[0]);
        }

        return FALSE;
    }

    public function get_by_nombre($nombre, $minusculas = FALSE)
    {
        if ($minusculas) {
            $data = $this->db->select("SELECT * FROM atributos WHERE lower(nombre) = " . $this->var2str(mb_strtolower($nombre, 'UTF8')) . ";");
        } else {
            $data = $this->db->select("SELECT * FROM atributos WHERE nombre = " . $this->var2str($nombre) . ";");
        }

        if ($data) {
            return new \atributo($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->codatributo)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM atributos WHERE codatributo = " . $this->var2str($this->codatributo) . ";");
    }

    public function save()
    {
        $this->nombre = $this->no_html($this->nombre);

        if ($this->exists()) {
            $sql = "UPDATE atributos SET nombre = " . $this->var2str($this->nombre)
                . " WHERE codatributo = " . $this->var2str($this->codatributo) . ";";
        } else {
            $sql = "INSERT INTO atributos (codatributo,nombre) VALUES "
                . "(" . $this->var2str($this->codatributo)
                . "," . $this->var2str($this->nombre) . ");";
        }

        return $this->db->exec($sql);
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM atributos WHERE codatributo = " . $this->var2str($this->codatributo) . ";");
    }

    /**
     * 
     * @return \atributo[]
     */
    public function all()
    {
        $lista = [];

        $data = $this->db->select("SELECT * FROM atributos ORDER BY LOWER(nombre) ASC;");
        if ($data) {
            foreach ($data as $d) {
                $lista[] = new \atributo($d);
            }
        }

        return $lista;
    }
}
