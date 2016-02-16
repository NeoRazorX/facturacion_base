<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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
 * Una cuenta bancaria de un proveedor.
 */
class cuenta_banco_proveedor extends fs_model
{
   /**
    * Clave primaria. Varchar(6).
    * @var type 
    */
   public $codcuenta;
   
   /**
    * Código del proveedor.
    * @var type 
    */
   public $codproveedor;
   
   public $descripcion;
   public $iban;
   public $swift;
   public $principal;
   
   public function __construct($c = FALSE)
   {
      parent::__construct('cuentasbcopro', 'plugins/facturacion_base/');
      if($c)
      {
         $this->codcuenta = $c['codcuenta'];
         $this->codproveedor = $c['codproveedor'];
         $this->descripcion = $c['descripcion'];
         $this->iban = $c['iban'];
         $this->swift = $c['swift'];
         $this->principal = $this->str2bool($c['principal']);
      }
      else
      {
         $this->codcuenta = NULL;
         $this->codproveedor = NULL;
         $this->descripcion = NULL;
         $this->iban = NULL;
         $this->swift = NULL;
         $this->principal = TRUE;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   /**
    * Devuelve el IBAN con o sin espacios.
    * @param type $espacios
    * @return type
    */
   public function iban($espacios = FALSE)
   {
      if($espacios)
      {
         return $this->iban;
      }
      else
      {
         return str_replace(' ', '', $this->iban);
      }
   }
   
   public function url()
   {
      if( is_null($this->codproveedor) )
      {
         return '#';
      }
      else
      {
         return 'index.php?page=compras_proveedor&cod='.$this->codproveedor.'#cuentasb';
      }
   }
   
   public function get($cod)
   {
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codcuenta = ".$this->var2str($cod).";");
      if($data)
      {
         return new cuenta_banco_proveedor($data[0]);
      }
      else
         return FALSE;
   }
   
   private function get_new_codigo()
   {
      $sql = "SELECT MAX(".$this->db->sql_to_int('codcuenta').") as cod FROM ".$this->table_name.";";
      $cod = $this->db->select($sql);
      if($cod)
      {
         return 1 + intval($cod[0]['cod']);
      }
      else
         return 1;
   }
   
   public function exists()
   {
      if( is_null($this->codcuenta) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM ".$this->table_name
                 ." WHERE codcuenta = ".$this->var2str($this->codcuenta).";");
      }
   }
   
   public function save()
   {
      $this->descripcion = $this->no_html($this->descripcion);
      
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET descripcion = ".$this->var2str($this->descripcion).
                 ", codproveedor = ".$this->var2str($this->codproveedor).
                 ", iban = ".$this->var2str($this->iban).
                 ", swift = ".$this->var2str($this->swift).
                 ", principal = ".$this->var2str($this->principal).
                 " WHERE codcuenta = ".$this->var2str($this->codcuenta).";";
      }
      else
      {
         $this->codcuenta = $this->get_new_codigo();
         $sql = "INSERT INTO ".$this->table_name." (codcuenta,codproveedor,descripcion,iban,swift,principal)"
                 . " VALUES (".$this->var2str($this->codcuenta).
                 ",".$this->var2str($this->codproveedor).
                 ",".$this->var2str($this->descripcion).
                 ",".$this->var2str($this->iban).
                 ",".$this->var2str($this->swift).
                 ",".$this->var2str($this->principal).");";
      }
      
      return $this->db->exec($sql);
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE codcuenta = ".$this->var2str($this->codcuenta).";");
   }
   
   public function all_from_proveedor($codpro)
   {
      $clist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE codproveedor = ".$this->var2str($codpro)
              ." ORDER BY descripcion ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $clist[] = new cuenta_banco_proveedor($d);
         }
      }
      
      return $clist;
   }
}
