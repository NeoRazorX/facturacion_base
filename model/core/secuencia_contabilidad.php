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
 * Clase que permite la compatibilidad con Eneboo.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class secuencia_contabilidad extends \fs_model
{

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

    public function __construct($data = FALSE)
    {
        parent::__construct('co_secuencias');
        if ($data) {
            $this->codejercicio = $data['codejercicio'];
            $this->descripcion = $data['descripcion'];
            $this->idsecuencia = $this->intval($data['idsecuencia']);
            $this->nombre = $data['nombre'];
            $this->valor = $this->intval($data['valor']);
            $this->valorout = $this->intval($data['valorout']);
        } else {
            $this->codejercicio = NULL;
            $this->descripcion = NULL;
            $this->idsecuencia = NULL;
            $this->nombre = NULL;
            $this->valor = NULL;
            $this->valorout = 1;
        }
    }

    protected function install()
    {
        return '';
    }

    public function get_by_params($codejercicio, $nombre)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($codejercicio)
            . " AND nombre = " . $this->var2str($nombre) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \secuencia_contabilidad($data[0]);
        }

        return FALSE;
    }

    public function get_by_params2($codejercicio, $nombre)
    {
        $sec = $this->get_by_params($codejercicio, $nombre);
        if ($sec) {
            return $sec;
        }

        $newsec = new \secuencia_contabilidad();
        $newsec->codejercicio = $codejercicio;
        $newsec->descripcion = 'Creado por FacturaScripts';
        $newsec->nombre = $nombre;
        return $newsec;
    }

    public function exists()
    {
        if (is_null($this->idsecuencia)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idsecuencia = " .
                $this->var2str($this->idsecuencia) . ";");
    }

    public function save()
    {
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

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idsecuencia = " . $this->var2str($this->idsecuencia) . ";");
    }
}
