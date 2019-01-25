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
 * Segundo nivel del plan contable.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class epigrafe extends \fs_extended_model
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
    public $codepigrafe;

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
     *
     * @var \grupo_epigrafes[]
     */
    private static $grupos;

    /**
     * Clave primaria.
     * @var integer
     */
    public $idepigrafe;

    /**
     *
     * @var int
     */
    public $idgrupo;

    /**
     * Existen varias versiones de la contabilidad de Eneboo/Abanq,
     * en una tenemos grupos, epigrafes, cuentas y subcuentas: 4 niveles.
     * En la otra tenemos epígrafes (con hijos), cuentas y subcuentas: multi-nivel.
     * FacturaScripts usa un híbrido: grupos, epígrafes (con hijos), cuentas
     * y subcuentas.
     *
     * @var int
     */
    public $idpadre;

    /**
     * 
     * @param array|bool $data
     */
    public function __construct($data = FALSE)
    {
        parent::__construct('co_epigrafes', $data);
        if (!isset(self::$grupos)) {
            $ge = new \grupo_epigrafes();
            self::$grupos = $ge->all_from_ejercicio($this->codejercicio);
        }

        foreach (self::$grupos as $g) {
            if ($g->idgrupo == $this->idgrupo) {
                $this->codgrupo = $g->codgrupo;
                break;
            }
        }
    }

    /**
     * 
     * @param int $offset
     *
     * @return \epigrafe[]
     */
    public function all($offset = 0)
    {
        $epilist = [];
        $sql = "SELECT * FROM " . $this->table_name() . " ORDER BY codejercicio DESC, codepigrafe ASC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        if ($data) {
            foreach ($data as $ep) {
                $epilist[] = new \epigrafe($ep);
            }
        }

        return $epilist;
    }

    /**
     * 
     * @param int $id
     *
     * @return \epigrafe[]
     */
    public function all_from_grupo($id)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE idgrupo = " . $this->var2str($id)
            . " ORDER BY codepigrafe ASC;";

        return $this->all_from($sql);
    }

    /**
     * 
     * @param string $codejercicio
     *
     * @return \epigrafe[]
     */
    public function all_from_ejercicio($codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = " . $this->var2str($codejercicio)
            . " ORDER BY codepigrafe ASC;";

        return $this->all_from($sql);
    }

    /**
     * Devuelve el codepigrade del epigrafe padre o false si no lo hay
     *
     * @return string
     */
    public function codpadre()
    {
        if ($this->idpadre) {
            $padre = $this->get($this->idpadre);
            if ($padre) {
                return $padre->codepigrafe;
            }
        }

        return '';
    }

    /**
     * Aplica algunas correcciones a la tabla.
     */
    public function fix_db()
    {
        $this->db->exec("UPDATE " . $this->table_name() . " SET idgrupo = NULL WHERE idgrupo NOT IN (SELECT idgrupo FROM co_gruposepigrafes);");
    }

    /**
     * 
     * @param string $cod
     * @param string $codejercicio
     *
     * @return bool|\epigrafe
     */
    public function get_by_codigo($cod, $codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codepigrafe = " . $this->var2str($cod)
            . " AND codejercicio = " . $this->var2str($codejercicio) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \epigrafe($data[0]);
        }

        return FALSE;
    }

    /**
     * 
     * @return \cuenta[]
     */
    public function get_cuentas()
    {
        $cuenta = new \cuenta();
        return $cuenta->full_from_epigrafe($this->idepigrafe);
    }

    /**
     * 
     * @return \epigrafe[]
     */
    public function hijos()
    {
        $epilist = [];
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE idpadre = " . $this->var2str($this->idepigrafe)
            . " ORDER BY codepigrafe ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $ep) {
                $epilist[] = new \epigrafe($ep);
            }
        }

        return $epilist;
    }

    /**
     * 
     * @return string
     */
    protected function install()
    {
        /// forzamos los creación de la tabla de grupos
        new \grupo_epigrafes();

        return '';
    }

    /**
     * 
     * @return string
     */
    public function model_class_name()
    {
        return 'epigrafe';
    }

    /**
     * 
     * @return string
     */
    public function primary_column()
    {
        return 'idepigrafe';
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
     * @param string $codejercicio
     *
     * @return \epigrafe[]
     */
    public function super_from_ejercicio($codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = " . $this->var2str($codejercicio)
            . " AND idpadre IS NULL AND idgrupo IS NULL ORDER BY codepigrafe ASC;";

        return $this->all_from($sql);
    }

    /**
     * 
     * @return bool
     */
    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);

        if (strlen($this->codepigrafe) > 0 && strlen($this->descripcion) > 0) {
            return TRUE;
        }

        $this->new_error_msg('Faltan datos en el epígrafe.');
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
        if (is_null($this->idepigrafe) || $type == 'list') {
            return 'index.php?page=contabilidad_epigrafes';
        }

        return 'index.php?page=contabilidad_epigrafes&epi=' . $this->idepigrafe;
    }

    /**
     * 
     * @param string $sql
     *
     * @return \epigrafe[]
     */
    private function all_from($sql)
    {
        $epilist = [];
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $ep) {
                $epilist[] = new \epigrafe($ep);
            }
        }

        return $epilist;
    }
}
