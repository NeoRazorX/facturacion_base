<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Una familia de artículos (el equivalente a la marca del artículo).
 */
class familia extends fs_model
{
   public $codfamilia;
   public $descripcion;
   public $madre;
   
   public $nivel;
   
   public function __construct($f=FALSE)
   {
      parent::__construct('familias', 'plugins/facturacion_base/');
      if($f)
      {
         $this->codfamilia = $f['codfamilia'];
         $this->descripcion = $f['descripcion'];
         
         $this->madre = NULL;
         if( isset($f['madre']) )
         {
            $this->madre = $f['madre'];
         }
         
         $this->nivel = '';
         if( isset($f['nivel']) )
         {
            $this->nivel = $f['nivel'];
         }
      }
      else
      {
         $this->codfamilia = NULL;
         $this->descripcion = '';
         $this->madre = NULL;
         $this->nivel = '';
      }
   }
   
   protected function install()
   {
      $this->clean_cache();
      return "INSERT INTO ".$this->table_name." (codfamilia,descripcion) VALUES ('VARI','VARIOS');";
   }
   
   public function url()
   {
      if( is_null($this->codfamilia) )
      {
         return "index.php?page=ventas_familias";
      }
      else
         return "index.php?page=ventas_familia&cod=".$this->codfamilia;
   }
   
   public function descripcion($len = 12)
   {
      if( mb_strlen($this->descripcion) > $len )
      {
         return substr($this->descripcion, 0, $len).'...';
      }
      else
      {
         return $this->descripcion;
      }
   }
   
   /**
    * @deprecated since version 50
    * @return type
    */
   public function is_default()
   {
      return FALSE;
   }
   
   public function get($cod)
   {
      $f = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codfamilia = ".$this->var2str($cod).";");
      if($f)
      {
         return new familia($f[0]);
      }
      else
         return FALSE;
   }

   public function get_articulos($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $articulo = new articulo();
      return $articulo->all_from_familia($this->codfamilia, $offset, $limit);
   }
   
   public function exists()
   {
      if( is_null($this->codfamilia) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE codfamilia = ".$this->var2str($this->codfamilia).";");
   }
   
   public function test()
   {
      $status = FALSE;
      
      $this->codfamilia = trim($this->codfamilia);
      $this->descripcion = $this->no_html($this->descripcion);
      
      if( strlen($this->codfamilia) < 1 OR strlen($this->codfamilia) > 8 )
      {
         $this->new_error_msg("Código de familia no válido. Deben ser entre 1 y 8 caracteres.");
      }
      else if( strlen($this->descripcion) < 1 OR strlen($this->descripcion) > 100 )
      {
         $this->new_error_msg("Descripción de familia no válida.");
      }
      else
         $status = TRUE;
      
      return $status;
   }
   
   public function save()
   {
      if( $this->test() )
      {
         $this->clean_cache();
         
         if( $this->exists() )
         {
            $sql = "UPDATE ".$this->table_name." SET descripcion = ".$this->var2str($this->descripcion).
                    ", madre = ".$this->var2str($this->madre).
                    " WHERE codfamilia = ".$this->var2str($this->codfamilia).";";
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (codfamilia,descripcion,madre) VALUES ".
                    "(".$this->var2str($this->codfamilia).
                    ",".$this->var2str($this->descripcion).
                    ",".$this->var2str($this->madre).");";
         }
         
         return $this->db->exec($sql);
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      $this->clean_cache();
      $sql = "DELETE FROM ".$this->table_name." WHERE codfamilia = ".$this->var2str($this->codfamilia).";"
              . "UPDATE ".$this->table_name." SET madre = ".$this->var2str($this->madre)." WHERE madre = ".$this->var2str($this->codfamilia).";"
              . "UPDATE articulos SET codfamilia = ".$this->var2str($this->madre)." WHERE codfamilia = ".$this->var2str($this->codfamilia).";";
      
      return $this->db->exec($sql);
   }
   
   private function clean_cache()
   {
      $this->cache->delete('m_familia_all');
   }
   
   public function all()
   {
      $famlist = $this->cache->get_array('m_familia_all');
      if(!$famlist)
      {
         $data = $this->db->select("SELECT * FROM ".$this->table_name." ORDER BY lower(descripcion) ASC;");
         if($data)
         {
            foreach($data as $d)
            {
               if( is_null($d['madre']) )
               {
                  $famlist[] = new familia($d);
                  foreach( $this->aux_all($data, $d['codfamilia'], '· ') as $value )
                  {
                     $famlist[] = new familia($value);
                  }
               }
            }
         }
         
         $this->cache->set('m_familia_all', $famlist);
      }
      
      return $famlist;
   }
   
   private function aux_all(&$familias, $madre, $nivel)
   {
      $subfamilias = array();
      
      foreach($familias as $fam)
      {
         if($fam['madre'] == $madre)
         {
            $fam['nivel'] = $nivel;
            $subfamilias[] = $fam;
            foreach( $this->aux_all($familias, $fam['codfamilia'], '&nbsp;&nbsp;'.$nivel) as $value )
            {
               $subfamilias[] = $value;
            }
         }
      }
      
      return $subfamilias;
   }
   
   public function madres()
   {
      $famlist = array();
      
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE madre IS NULL ORDER BY lower(descripcion) ASC;");
      if($data)
      {
         foreach($data as $d)
            $famlist[] = new familia($d);
      }
      
      return $famlist;
   }
   
   public function hijas($codmadre = FALSE)
   {
      $famlist = array();
      
      if(!$codmadre)
      {
         $codmadre = $this->codfamilia;
      }
      
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE madre = ".$this->var2str($codmadre)." ORDER BY descripcion ASC;");
      if($data)
      {
         foreach($data as $d)
            $famlist[] = new familia($d);
      }
      
      return $famlist;
   }
   
   public function search($query)
   {
      $famlist = array();
      $query = $this->no_html( strtolower($query) );
      
      $familias = $this->db->select("SELECT * FROM ".$this->table_name." WHERE lower(descripcion) LIKE '%".$query."%' ORDER BY descripcion ASC;");
      if($familias)
      {
         foreach($familias as $f)
            $famlist[] = new familia($f);
      }
      
      return $famlist;
   }
}
