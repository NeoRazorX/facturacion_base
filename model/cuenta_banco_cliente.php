<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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
 * Una cuenta bancaria de un cliente.
 */
class cuenta_banco_cliente extends fs_model
{
   public $codcuenta; /// pkey
   public $codcliente;
   public $descripcion;
   public $iban;
   public $swift;
   
   public function __construct($c = FALSE)
   {
      parent::__construct('cuentasbcocli', 'plugins/facturacion_base/');
      if($c)
      {
         $this->codcliente = $c['codcliente'];
         $this->codcuenta = $c['codcuenta'];
         $this->descripcion = $c['descripcion'];
         $this->iban = $c['iban'];
         $this->swift = $c['swift'];
      }
      else
      {
         $this->codcliente = NULL;
         $this->codcuenta = NULL;
         $this->descripcion = NULL;
         $this->iban = NULL;
         $this->swift = NULL;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function get($cod)
   {
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codcuenta = ".$this->var2str($cod).";");
      if($data)
      {
         return new cuenta_banco_cliente($data[0]);
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
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE codcuenta = ".$this->var2str($this->codcuenta).";");
   }
   
   public function save()
   {
      $this->descripcion = $this->no_html($this->descripcion);
      
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET descripcion = ".$this->var2str($this->descripcion).
                 ", codcliente = ".$this->var2str($this->codcliente).
                 ", iban = ".$this->var2str($this->iban).
                 ", swift = ".$this->var2str($this->swift).
                 " WHERE codcuenta = ".$this->var2str($this->codcuenta).";";
      }
      else
      {
         $this->codcuenta = $this->get_new_codigo();
         $sql = "INSERT INTO ".$this->table_name." (codcliente,codcuenta,descripcion,iban,swift)".
                 " VALUES (".$this->var2str($this->codcliente).
                 ",".$this->var2str($this->codcuenta).
                 ",".$this->var2str($this->descripcion).
                 ",".$this->var2str($this->iban).
                 ",".$this->var2str($this->swift).");";
      }
      
      return $this->db->exec($sql);
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE codcuenta = ".$this->var2str($this->codcuenta).";");
   }
   
   public function all_from_cliente($codcli)
   {
      $clist = array();
      
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codcliente = ".$this->var2str($codcli)." ORDER BY descripcion ASC;");
      if($data)
      {
         foreach($data as $d)
            $clist[] = new cuenta_banco_cliente($d);
      }
      
      return $clist;
   }
}
