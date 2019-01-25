<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2014-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\model;

/**
 * Primer nivel del plan contable.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class grupo_epigrafes extends \fs_extended_model
{

    /**
     *
     * @var string
     */
    public $codejercicio;

    /**
     *
     * @var string
     */
    public $codgrupo;

    /**
     *
     * @var string
     */
    public $descripcion;

    /**
     * Clave primaria
     * @var integer
     */
    public $idgrupo;

    /**
     * 
     * @param array|false $data
     */
    public function __construct($data = FALSE)
    {
        parent::__construct('co_gruposepigrafes', $data);
    }

    /**
     * 
     * @param string $codejercicio
     *
     * @return \grupo_epigrafes[]
     */
    public function all_from_ejercicio($codejercicio)
    {
        $epilist = [];
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = " . $this->var2str($codejercicio)
            . " ORDER BY codgrupo ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $ep) {
                $epilist[] = new \grupo_epigrafes($ep);
            }
        }

        return $epilist;
    }

    /**
     * 
     * @param string $cod
     * @param string $codejercicio
     *
     * @return bool|\grupo_epigrafes
     */
    public function get_by_codigo($cod, $codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codgrupo = " . $this->var2str($cod)
            . " AND codejercicio = " . $this->var2str($codejercicio) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \grupo_epigrafes($data[0]);
        }

        return FALSE;
    }

    /**
     * 
     * @return \epigrafe
     */
    public function get_epigrafes()
    {
        $epigrafe = new \epigrafe();
        return $epigrafe->all_from_grupo($this->idgrupo);
    }

    /**
     * 
     * @return string
     */
    public function model_class_name()
    {
        return 'grupo_epigrafes';
    }

    /**
     * 
     * @return string
     */
    public function primary_column()
    {
        return 'idgrupo';
    }

    /**
     * 
     * @return bool
     */
    public function save()
    {
        return $this->test() ? parent::save() : false;
    }

    /**
     * 
     * @return bool
     */
    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);

        if (strlen($this->codejercicio) > 0 && strlen($this->codgrupo) > 0 && strlen($this->descripcion) > 0) {
            return TRUE;
        }

        $this->new_error_msg('Faltan datos en el grupo de epígrafes.');
        return FALSE;
    }

    /**
     * 
     * @param string $type
     *
     * @return string
     */
    public function url($type = 'auto')
    {
        if (is_null($this->idgrupo) || $type == 'list') {
            return 'index.php?page=contabilidad_epigrafes';
        }

        return 'index.php?page=contabilidad_epigrafes&grupo=' . $this->idgrupo;
    }
}
