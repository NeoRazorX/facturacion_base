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
 * Una tarifa para los artículos.
 */
class tarifa extends fs_model
{
   public $codtarifa;
   public $nombre;
   public $incporcentual;
   public $inclineal;
   public $aplicar_a;
   
   public function __construct($t = FALSE)
   {
      parent::__construct('tarifas', 'plugins/facturacion_base/');
      if( $t )
      {
         $this->codtarifa = $t['codtarifa'];
         $this->nombre = $t['nombre'];
         $this->incporcentual = floatval( $t['incporcentual'] );
         $this->inclineal = floatval( $t['inclineal'] );
         $this->aplicar_a = $t['aplicar_a'];
      }
      else
      {
         $this->codtarifa = NULL;
         $this->nombre = NULL;
         $this->incporcentual = 0;
         $this->inclineal = 0;
         $this->aplicar_a = NULL;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function url()
   {
      return 'index.php?page=ventas_articulos#tarifas';
   }
   
   public function dtopor()
   {
      return (0 - $this->incporcentual);
   }
   
    public function inclineal()
   {
      return (0 - $this->inclineal);
   }
   
   /**
    * Rellenamos los descuentos y los datos de la tarifa de una lista de
    * artículos.
    * @param type $articulos
    */
   public function set_precios(&$articulos)
   {
      foreach($articulos as $i => $value)
      {
         $articulos[$i]->codtarifa = $this->codtarifa;
         $articulos[$i]->tarifa_nombre = $this->nombre;
         $articulos[$i]->tarifa_url = $this->url();
         $aplicartarifa= $this->aplicar_a;
         
         $articulos[$i]->dtopor = 0;
         $articulos[$i]->pvp = $articulos[$i]->$aplicartarifa*(100+$this->incporcentual)/100+$this->inclineal;
         $articulos[$i]->tarifa_diff = $aplicartarifa.' + '.$this->incporcentual.'% +'.$this->inclineal.' €';
      }
   }
   
   public function get($cod)
   {
      $tarifa = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codtarifa = ".$this->var2str($cod).";");
      if($tarifa)
      {
         return new tarifa($tarifa[0]);
      }
      else
         return FALSE;
   }
   
   public function get_new_codigo()
   {
      $cod = $this->db->select("SELECT MAX(".$this->db->sql_to_int('codtarifa').") as cod FROM ".$this->table_name.";");
      if($cod)
      {
         return sprintf('%06s', (1 + intval($cod[0]['cod'])));
      }
      else
         return '000001';
   }
   
   public function exists()
   {
      if( is_null($this->codtarifa) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE codtarifa = ".$this->var2str($this->codtarifa).";");
   }
   
   public function test()
   {
      $status = FALSE;
      
      $this->codtarifa = trim($this->codtarifa);
      $this->nombre = $this->no_html($this->nombre);
      
      if( !preg_match("/^[A-Z0-9]{1,6}$/i", $this->codtarifa) )
      {
         $this->new_error_msg("Código de tarifa no válido.");
      }
      else if( strlen($this->nombre) < 1 OR strlen($this->nombre) > 50 )
      {
         $this->new_error_msg("Nombre de tarifa no válido.");
      }
      else
         $status = TRUE;
      
      return $status;
   }
   
   public function save()
   {
      if( $this->test() )
      {
         if( $this->exists() )
         {
            $sql = "UPDATE ".$this->table_name." SET nombre = ".$this->var2str($this->nombre).",
               incporcentual = ".$this->var2str($this->incporcentual).",
               inclineal =".$this->var2str($this->inclineal).",
               aplicar_a =".$this->var2str($this->aplicar_a)."
               WHERE codtarifa = ".$this->var2str($this->codtarifa).";";
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (codtarifa,nombre,incporcentual,inclineal,aplicar_a)
               VALUES (".$this->var2str($this->codtarifa).",".$this->var2str($this->nombre).",
               ".$this->var2str($this->incporcentual).",".$this->var2str($this->inclineal).",".$this->var2str($this->aplicar_a).");";
         }
         return $this->db->exec($sql);
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE codtarifa = ".$this->var2str($this->codtarifa).";");
   }
   
   public function all()
   {
      $tarlist = array();
      
      $data = $this->db->select("SELECT * FROM ".$this->table_name." ORDER BY codtarifa ASC;");
      if($data)
      {
         foreach($data as $t)
            $tarlist[] = new tarifa($t);
      }
      
      return $tarlist;
   }
}
