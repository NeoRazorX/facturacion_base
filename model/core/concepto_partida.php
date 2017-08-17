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
 * Un concepto predefinido para una partida (la línea de un asiento contable).
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class concepto_partida extends \fs_model
{

    /**
     * Clave primaria.
     * @var string 
     */
    public $idconceptopar;
    public $concepto;

    public function __construct($data = FALSE)
    {
        parent::__construct('co_conceptospar');
        if ($data) {
            $this->idconceptopar = $data['idconceptopar'];
            $this->concepto = $data['concepto'];
        } else {
            $this->idconceptopar = NULL;
            $this->concepto = NULL;
        }
    }

    protected function install()
    {
        return '';
    }

    public function get($id)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idconceptopar = " . $this->var2str($id) . ";");
        if ($data) {
            return new \concepto_partida($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idconceptopar)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idconceptopar = " . $this->var2str($this->idconceptopar) . ";");
    }

    public function test()
    {
        $this->concepto = $this->no_html($this->concepto);
        return TRUE;
    }

    public function save()
    {
        return FALSE;
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idconceptopar = " . $this->var2str($this->idconceptopar) . ";");
    }

    public function all()
    {
        $concelist = array();

        $data = $this->db->select("SELECT * FROM " . $this->table_name . " ORDER BY idconceptopar ASC;");
        if ($data) {
            foreach ($data as $c) {
                $concelist[] = new \concepto_partida($c);
            }
        }

        return $concelist;
    }
}
