<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2015  Marcelo Rubele marceloru@gmail.com
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
 * Una tipoidfiscal de la numeracion del documento del cliente
 * se deja la clase con nombre tipoidfiscal por compatibilidad con campos
 * existente en cliente, pero deberia ser tipoidentificacion
 * ejemplo el campo cifnif deberia llamarse identificacion o documento
 * y el campo tipodifiscal, llamarse tipo de identificacion/documento
 * de esta forma, en el actual campo cifnif va el numero y en este campo tipodifiscal
 * va el tipo de identificacion, CIF / NIF en España, DNI, Pasaporte, CUIT, CUIL, Otro 
 * en argentina, etc
 */
class tipoidfiscal extends fs_model
{
   /**
    * Clave primaria. Varchar (2).
    * @var type 
    */
   public $codtipo;
   public $descripcion;
   
   public function __construct($s=FALSE)
   {
      parent::__construct('tipoidfiscales','plugins/id_fiscal/');
      if($s)
      {
         $this->codtipo = $s['codtipo'];
         $this->descripcion = $s['descripcion'];
      }
      else
      {
         $this->codtipo = '';
         $this->descripcion = '';
      }
   }
   
   public function install()
   {
      $this->clean_cache();
      return "INSERT INTO ".$this->table_name." (codtipo,descripcion) VALUES ('1','DNI'), ".
         "('2','CUIT'), ('3','OTRO'), ('4','CIF'), ('5','NIF');" ;
   }
   
   public function url()
   {
      if( is_null($this->codtipo) )
      {
         return 'index.php?page=contabilidad_tipoidfiscales';
      }
      else
      {
         return 'index.php?page=contabilidad_tipoidfiscales#'.$this->codtipo;
   }
   }
   
   public function is_default()
   {
      return ( $this->codtipo == $this->default_items->codtipo() );
   }
   
   public function get($cod)
   {
      $tipoidfiscal = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codtipo = ".$this->var2str($cod).";");
      if($tipoidfiscal)
      {
         return new tipoidfiscal($tipoidfiscal[0]);
      }
      else
         return FALSE;
   }
   
   public function exists()
   {
      if( is_null($this->codtipo) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE codtipo = ".$this->var2str($this->codtipo).";");
   }
   
   public function test()
   {
      $status = FALSE;
      
      $this->codtipo = trim($this->codtipo);
      $this->descripcion = $this->no_html($this->descripcion);
      
      if( strlen($this->codtipo) < 1 OR strlen($this->codtipo) > 4 )
      {
         $this->new_error_msg("Código de tipo no válido.");
      }
      else if( strlen($this->descripcion) < 1 OR strlen($this->descripcion) > 100 )
      {
         $this->new_error_msg("Descripción de tipo no válida.");
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
                    " WHERE codtipo = ".$this->var2str($this->codtipo).";";
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (codtipo,descripcion)
               VALUES (".$this->var2str($this->codtipo).",".$this->var2str($this->descripcion).");";
         }
         return $this->db->exec($sql);
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      $this->clean_cache();
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE codtipo = ".$this->var2str($this->codtipo).";");
   }
   
   private function clean_cache()
   {
      $this->cache->delete('m_tipoidfiscal_all');
   }
   
   public function all()
   {
      $tipoidfiscallist = $this->cache->get_array('m_tipoidfiscal_all');
      if( !$tipoidfiscallist )
      {
         $tipoidfiscales = $this->db->select("SELECT * FROM ".$this->table_name." ORDER BY codtipo ASC;");
         if($tipoidfiscales)
         {
            foreach($tipoidfiscales as $s)
               $tipoidfiscallist[] = new tipoidfiscal($s);
         }
         $this->cache->set('m_codtipo_all', $tipoidfiscallist);
      }
      return $tipoidfiscallist;
   }
}
