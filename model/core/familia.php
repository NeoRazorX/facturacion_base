<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
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
 * Una familia de artículos.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class familia extends \fs_model
{

    /**
     * Clave primaria.
     *
     * @var string 
     */
    public $codfamilia;

    /**
     *
     * @var string
     */
    public $descripcion;

    /**
     * Código de la familia madre.
     *
     * @var string 
     */
    public $madre;

    /**
     *
     * @var string
     */
    public $nivel;

    public function __construct($data = FALSE)
    {
        parent::__construct('familias');
        if ($data) {
            $this->codfamilia = $data['codfamilia'];
            $this->descripcion = $data['descripcion'];
            $this->madre = isset($data['madre']) ? $data['madre'] : null;
            $this->nivel = isset($data['nivel']) ? $data['nivel'] : '';
        } else {
            $this->codfamilia = NULL;
            $this->descripcion = '';
            $this->madre = NULL;
            $this->nivel = '';
        }
    }

    /**
     * 
     * @return string
     */
    protected function install()
    {
        $this->clean_cache();
        return "INSERT INTO " . $this->table_name . " (codfamilia,descripcion) VALUES ('VARI','VARIOS');";
    }

    /**
     * 
     * @return string
     */
    public function url()
    {
        if (is_null($this->codfamilia)) {
            return "index.php?page=ventas_familias";
        }

        return "index.php?page=ventas_familia&cod=" . urlencode($this->codfamilia);
    }

    /**
     * 
     * @param int $len
     *
     * @return string
     */
    public function descripcion($len = 12)
    {
        if (mb_strlen($this->descripcion) > $len) {
            return substr($this->descripcion, 0, $len) . '...';
        }

        return $this->descripcion;
    }

    public function get($cod)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codfamilia = " . $this->var2str($cod) . ";");
        if ($data) {
            return new \familia($data[0]);
        }

        return FALSE;
    }

    public function get_articulos($offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $articulo = new \articulo();
        return $articulo->all_from_familia($this->codfamilia, $offset, $limit);
    }

    /**
     * 
     * @return bool
     */
    public function exists()
    {
        if (is_null($this->codfamilia)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codfamilia = " . $this->var2str($this->codfamilia) . ";");
    }

    /**
     * Comprueba los datos de la familia, devuelve TRUE si son correctos
     *
     * @return bool
     */
    public function test()
    {
        $this->codfamilia = $this->no_html($this->codfamilia);
        $this->descripcion = $this->no_html($this->descripcion);

        if (mb_strlen($this->codfamilia) < 1 || mb_strlen($this->codfamilia) > 8) {
            $this->new_error_msg("Código de familia no válido. Deben ser entre 1 y 8 caracteres.");
        } elseif (mb_strlen($this->descripcion) < 1 || mb_strlen($this->descripcion) > 100) {
            $this->new_error_msg("Descripción de familia no válida.");
        } else {
            return true;
        }

        return false;
    }

    /**
     * Guarda los datos en la base de datos
     *
     * @return bool
     */
    public function save()
    {
        if ($this->test()) {
            $this->clean_cache();

            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET descripcion = " . $this->var2str($this->descripcion) .
                    ", madre = " . $this->var2str($this->madre) .
                    "  WHERE codfamilia = " . $this->var2str($this->codfamilia) . ";";
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codfamilia,descripcion,madre) VALUES " .
                    "(" . $this->var2str($this->codfamilia) .
                    "," . $this->var2str($this->descripcion) .
                    "," . $this->var2str($this->madre) . ");";
            }

            return $this->db->exec($sql);
        }

        return FALSE;
    }

    /**
     * Elimina la familia de la base de datos
     *
     * @return bool
     */
    public function delete()
    {
        $this->clean_cache();
        $sql = "DELETE FROM " . $this->table_name . " WHERE codfamilia = " . $this->var2str($this->codfamilia) . ";"
            . " UPDATE " . $this->table_name . " SET madre = " . $this->var2str($this->madre)
            . " WHERE madre = " . $this->var2str($this->codfamilia) . ";";

        return $this->db->exec($sql);
    }

    /**
     * Limpia la caché
     */
    private function clean_cache()
    {
        $this->cache->delete('m_familia_all');
    }

    /**
     * Devuelve un array con todas las familias
     *
     * @return \familia[]
     */
    public function all()
    {
        /// lee la lista de la caché
        $famlist = $this->cache->get_array('m_familia_all');
        if (!$famlist) {
            /// si la lista no está en caché, leemos de la base de datos
            $data = $this->db->select("SELECT * FROM " . $this->table_name . " ORDER BY lower(descripcion) ASC;");
            if ($data) {
                foreach ($data as $d) {
                    if (is_null($d['madre'])) {
                        $famlist[] = new \familia($d);
                        foreach ($this->aux_all($data, $d['codfamilia'], '· ') as $value) {
                            $famlist[] = new \familia($value);
                        }
                    }
                }
            }

            /// guardamos la lista en caché
            $this->cache->set('m_familia_all', $famlist);
        }

        return $famlist;
    }

    /**
     * Completa los datos de la lista de familias con el nivel.
     *
     * @param array $familias
     * @param string $madre
     * @param string $nivel
     *
     * @return \familia[]
     */
    private function aux_all(&$familias, $madre, $nivel)
    {
        $subfamilias = [];
        foreach ($familias as $fam) {
            if ($fam['madre'] == $madre) {
                $fam['nivel'] = $nivel;
                $subfamilias[] = $fam;
                foreach ($this->aux_all($familias, $fam['codfamilia'], '&nbsp;&nbsp;' . $nivel) as $value) {
                    $subfamilias[] = $value;
                }
            }
        }

        return $subfamilias;
    }

    private function all_from($sql)
    {
        $famlist = [];
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $a) {
                $famlist[] = new \familia($a);
            }
        }
        return $famlist;
    }

    public function madres()
    {
        $famlist = $this->all_from("SELECT * FROM " . $this->table_name . " WHERE madre IS NULL ORDER BY lower(descripcion) ASC;");
        if (empty($famlist)) {
            /// si la lista está vacía, ponemos madre a NULL en todas por si el usuario ha estado jugando
            $this->db->exec("UPDATE " . $this->table_name . " SET madre = NULL;");
        }

        return $famlist;
    }

    public function hijas($codmadre = FALSE)
    {
        if (!$codmadre) {
            $codmadre = $this->codfamilia;
        }

        return $this->all_from("SELECT * FROM " . $this->table_name . " WHERE madre = " . $this->var2str($codmadre) . " ORDER BY descripcion ASC;");
    }

    public function search($query)
    {
        $query = $this->no_html(mb_strtolower($query, 'UTF8'));
        return $this->all_from("SELECT * FROM " . $this->table_name . " WHERE lower(descripcion) LIKE '%" . $query . "%' ORDER BY descripcion ASC;");
    }

    /**
     * Aplicamos correcciones a la tabla.
     */
    public function fix_db()
    {
        /// comprobamos que las familias con madre, su madre exista.
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE madre IS NOT NULL;");
        foreach ($data as $d) {
            $fam = $this->get($d['madre']);
            if (!$fam || $fam->codfamilia == $fam->madre) {
                /// si la madre no existe, o es la propia familia, desvinculamos
                $this->db->exec("UPDATE " . $this->table_name . " SET madre = null WHERE codfamilia = "
                    . $this->var2str($d['codfamilia']) . ";");
            }
        }
    }
}
