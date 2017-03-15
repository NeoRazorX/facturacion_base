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

require_model('cuenta.php');

/**
 * Primer nivel del plan contable.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class grupo_epigrafes extends \fs_model
{
   /**
    * Clave primaria
    * @var type 
    */
   public $idgrupo;
   public $codgrupo;
   public $codejercicio;
   public $descripcion;
   
   public function __construct($f = FALSE)
   {
      parent::__construct('co_gruposepigrafes');
      if($f)
      {
         $this->idgrupo = $this->intval($f['idgrupo']);
         $this->codgrupo = $f['codgrupo'];
         $this->descripcion = $f['descripcion'];
         $this->codejercicio = $f['codejercicio'];
      }
      else
      {
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
      if( is_null($this->idgrupo) )
      {
         return 'index.php?page=contabilidad_epigrafes';
      }
      else
         return 'index.php?page=contabilidad_epigrafes&grupo='.$this->idgrupo;
   }
   
   public function get_epigrafes()
   {
      $epigrafe = new \epigrafe();
      return $epigrafe->all_from_grupo($this->idgrupo);
   }
   
   public function get($id)
   {
      $grupo = $this->db->select("SELECT * FROM ".$this->table_name." WHERE idgrupo = ".$this->var2str($id).";");
      if($grupo)
      {
         return new \grupo_epigrafes($grupo[0]);
      }
      else
         return FALSE;
   }
   
   public function get_by_codigo($cod, $codejercicio)
   {
      $sql = "SELECT * FROM ".$this->table_name." WHERE codgrupo = ".$this->var2str($cod)
              ." AND codejercicio = ".$this->var2str($codejercicio).";";
      
      $grupo = $this->db->select($sql);
      if($grupo)
      {
         return new \grupo_epigrafes($grupo[0]);
      }
      else
         return FALSE;
   }
   
   public function exists()
   {
      if( is_null($this->idgrupo) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE idgrupo = ".$this->var2str($this->idgrupo).";");
      }
   }
   
   public function test()
   {
      $this->descripcion = $this->no_html($this->descripcion);
      
      if( strlen($this->codejercicio)>0 AND strlen($this->codgrupo)>0 AND strlen($this->descripcion)>0 )
      {
         return TRUE;
      }
      else
      {
         $this->new_error_msg('Faltan datos en el grupo de epígrafes.');
         return FALSE;
      }
   }
   
   public function save()
   {
      if( $this->test() )
      {
         if( $this->exists() )
         {
            $sql = "UPDATE ".$this->table_name." SET codgrupo = ".$this->var2str($this->codgrupo)
                    .", descripcion = ".$this->var2str($this->descripcion)
                    .", codejercicio = ".$this->var2str($this->codejercicio)
                    ."  WHERE idgrupo = ".$this->var2str($this->idgrupo).";";
            
            return $this->db->exec($sql);
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (codgrupo,descripcion,codejercicio) VALUES
                     (".$this->var2str($this->codgrupo).
                    ",".$this->var2str($this->descripcion).
                    ",".$this->var2str($this->codejercicio).");";
            
            if( $this->db->exec($sql) )
            {
               $this->idgrupo = $this->db->lastval();
               return TRUE;
            }
            else
               return FALSE;
         }
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE idgrupo = ".$this->var2str($this->idgrupo).";");
   }
   
   public function all_from_ejercicio($codejercicio)
   {
      $epilist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE codejercicio = ".$this->var2str($codejercicio)
              ." ORDER BY codgrupo ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $ep)
         {
            $epilist[] = new \grupo_epigrafes($ep);
         }
      }
      
      return $epilist;
   }
}


/**
 * Segundo nivel del plan contable.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class epigrafe extends \fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $idepigrafe;
   
   /**
    * Existen varias versiones de la contabilidad de Eneboo/Abanq,
    * en una tenemos grupos, epigrafes, cuentas y subcuentas: 4 niveles.
    * En la otra tenemos epígrafes (con hijos), cuentas y subcuentas: multi-nivel.
    * FacturaScripts usa un híbrido: grupos, epígrafes (con hijos), cuentas
    * y subcuentas.
    */
   public $idpadre;
   public $codepigrafe;
   public $idgrupo;
   public $codejercicio;
   public $descripcion;
   
   public $codgrupo;
   private static $grupos;
   
   public function __construct($e = FALSE)
   {
      parent::__construct('co_epigrafes');
      if($e)
      {
         $this->idepigrafe = $this->intval($e['idepigrafe']);
         $this->idpadre = $this->intval($e['idpadre']);
         $this->codepigrafe = $e['codepigrafe'];
         $this->idgrupo = $this->intval($e['idgrupo']);
         $this->descripcion = $e['descripcion'];
         $this->codejercicio = $e['codejercicio'];
         
         if( !isset(self::$grupos) )
         {
            $ge = new \grupo_epigrafes();
            self::$grupos = $ge->all_from_ejercicio( $this->codejercicio );
         }
         
         foreach(self::$grupos as $g)
         {
            if($g->idgrupo == $this->idgrupo)
            {
               $this->codgrupo = $g->codgrupo;
               break;
            }
         }
      }
      else
      {
         $this->idepigrafe = NULL;
         $this->idpadre = NULL;
         $this->codepigrafe = NULL;
         $this->idgrupo = NULL;
         $this->codgrupo = NULL;
         $this->descripcion = NULL;
         $this->codejercicio = NULL;
      }
   }
   
   protected function install()
   {
      /// forzamos los creación de la tabla de grupos
      $grupo = new \grupo_epigrafes();
      return '';
   }
   
   public function url()
   {
      if( is_null($this->idepigrafe) )
      {
         return 'index.php?page=contabilidad_epigrafes';
      }
      else
         return 'index.php?page=contabilidad_epigrafes&epi='.$this->idepigrafe;
   }
   
   /**
    * Devuelve el codepigrade del epigrafe padre o false si no lo hay
    * @return type
    */
   public function codpadre()
   {
      $cod = FALSE;
      
      if($this->idpadre)
      {
         $padre = $this->get($this->idpadre);
         if($padre)
         {
            $cod = $padre->codepigrafe;
         }
      }
      
      return $cod;
   }
   
   public function hijos()
   {
      $epilist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE idpadre = ".$this->var2str($this->idepigrafe)
              ." ORDER BY codepigrafe ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $ep)
         {
            $epilist[] = new \epigrafe($ep);
         }
      }
      
      return $epilist;
   }
   
   public function get_cuentas()
   {
      $cuenta = new \cuenta();
      return $cuenta->full_from_epigrafe($this->idepigrafe);
   }
   
   public function get($id)
   {
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE idepigrafe = ".$this->var2str($id).";");
      if($data)
      {
         return new \epigrafe($data[0]);
      }
      else
         return FALSE;
   }
   
   public function get_by_codigo($cod, $codejercicio)
   {
      $sql = "SELECT * FROM ".$this->table_name." WHERE codepigrafe = ".$this->var2str($cod)
              ." AND codejercicio = ".$this->var2str($codejercicio).";";
      
      $data = $this->db->select($sql);
      if($data)
      {
         return new \epigrafe($data[0]);
      }
      else
         return FALSE;
   }
   
   public function exists()
   {
      if( is_null($this->idepigrafe) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE idepigrafe = ".$this->var2str($this->idepigrafe).";");
   }
   
   public function test()
   {
      $this->descripcion = $this->no_html($this->descripcion);
      
      if( strlen($this->codepigrafe) > 0 AND strlen($this->descripcion) > 0 )
      {
         return TRUE;
      }
      else
      {
         $this->new_error_msg('Faltan datos en el epígrafe.');
         return FALSE;
      }
   }
   
   public function save()
   {
      if( $this->test() )
      {
         if( $this->exists() )
         {
            $sql = "UPDATE ".$this->table_name." SET codepigrafe = ".$this->var2str($this->codepigrafe)
                    .", idgrupo = ".$this->var2str($this->idgrupo)
                    .", descripcion = ".$this->var2str($this->descripcion)
                    .", codejercicio = ".$this->var2str($this->codejercicio)
                    .", idpadre = ".$this->var2str($this->idpadre)
                    ."  WHERE idepigrafe = ".$this->var2str($this->idepigrafe).";";
            
            return $this->db->exec($sql);
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (codepigrafe,idgrupo,descripcion,idpadre,codejercicio)
                     VALUES (".$this->var2str($this->codepigrafe).
                    ",".$this->var2str($this->idgrupo).
                    ",".$this->var2str($this->descripcion).
                    ",".$this->var2str($this->idpadre).
                    ",".$this->var2str($this->codejercicio).");";
            
            if( $this->db->exec($sql) )
            {
               $this->idepigrafe = $this->db->lastval();
               return TRUE;
            }
            else
               return FALSE;
         }
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE idepigrafe = ".$this->var2str($this->idepigrafe).";");
   }
   
   public function all($offset=0)
   {
      $epilist = array();
      $sql = "SELECT * FROM ".$this->table_name." ORDER BY codejercicio DESC, codepigrafe ASC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $ep)
         {
            $epilist[] = new \epigrafe($ep);
         }
      }
      
      return $epilist;
   }
   
   public function all_from_grupo($id)
   {
      $epilist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE idgrupo = ".$this->var2str($id)
              ." ORDER BY codepigrafe ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $ep)
         {
            $epilist[] = new \epigrafe($ep);
         }
      }
      
      return $epilist;
   }
   
   public function all_from_ejercicio($codejercicio)
   {
      $epilist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE codejercicio = ".$this->var2str($codejercicio)
              ." ORDER BY codepigrafe ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $ep)
         {
            $epilist[] = new \epigrafe($ep);
         }
      }
      
      return $epilist;
   }
   
   public function super_from_ejercicio($codejercicio)
   {
      $epilist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE codejercicio = ".$this->var2str($codejercicio)
              ." AND idpadre IS NULL AND idgrupo IS NULL ORDER BY codepigrafe ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $ep)
         {
            $epilist[] = new \epigrafe($ep);
         }
      }
      
      return $epilist;
   }
}
