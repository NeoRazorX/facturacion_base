<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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
   /**
    * Clave primaria.
    * @var type 
    */
   public $codtarifa;
   
   /**
    * Nombre de la tarifa.
    * @var type 
    */
   public $nombre;
   
   /**
    * Incremento porcentual o descuento
    * @var type 
    */
   private $incporcentual;
   
   /**
    * Incremento lineal o descuento lineal
    * @var type 
    */
   private $inclineal;
   
   /**
    * Fórmula a aplicar
    * @var type 
    */
   public $aplicar_a;
   
   /**
    * no vender por debajo de coste
    * @var boolean 
    */
   public $mincoste;
   
   /**
    * no vender por encima de pvp
    * @var boolean 
    */
   public $maxpvp;
   
   public function __construct($t = FALSE)
   {
      parent::__construct('tarifas', 'plugins/facturacion_base/');
      if( $t )
      {
         $this->codtarifa = $t['codtarifa'];
         $this->nombre = $t['nombre'];
         $this->incporcentual = floatval($t['incporcentual']);
         $this->inclineal = floatval($t['inclineal']);
         $this->mincoste = $this->str2bool($t['mincoste']);
         $this->maxpvp = $this->str2bool($t['maxpvp']);
         
         $this->aplicar_a = 'pvp';
         if( !is_null($t['aplicar_a']) )
         {
            $this->aplicar_a = $t['aplicar_a'];
         }
      }
      else
      {
         $this->codtarifa = NULL;
         $this->nombre = NULL;
         $this->incporcentual = 0;
         $this->inclineal = 0;
         $this->aplicar_a = 'pvp';
         $this->mincoste = TRUE;
         $this->maxpvp = TRUE;
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
   
   public function x()
   {
      if($this->aplicar_a == 'pvp')
      {
         return (0 - $this->incporcentual);
      }
      else
      {
         return $this->incporcentual;
      }
   }
   
   public function set_x($dto)
   {
      if($this->aplicar_a == 'pvp')
      {
         $this->incporcentual = 0 - $dto;
      }
      else
      {
         $this->incporcentual = $dto;
      }
   }
   
   public function y()
   {
      if($this->aplicar_a == 'pvp')
      {
         return (0 - $this->inclineal);
      }
      else
      {
         return $this->inclineal;
      }
   }
   
   public function set_y($inc)
   {
      if($this->aplicar_a == 'pvp')
      {
         $this->inclineal = 0 - $inc;
      }
      else
      {
         $this->inclineal = $inc;
      }
   }
   
   /**
    * Devuelve un texto explicativo de lo que hace la tarifa
    * @return type
    */
   public function diff()
   {
      $texto = '';
      $x = $this->x();
      $y = $this->y();
      
      if($this->aplicar_a == 'pvp')
      {
         $texto = 'Precio de venta ';
         $x = 0 - $x;
         $y = 0 - $y;
      }
      else
      {
         $texto = 'Precio de coste ';
      }
      
      if($x != 0)
      {
         if($x > 0)
         {
            $texto .= '+';
         }
         
         $texto .= $x.'% ';
      }
      
      if($y != 0)
      {
         if($y > 0)
         {
            $texto .= ' +';
         }
         
         $texto .= $y;
      }
      
      return $texto;
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
         $articulos[$i]->dtopor = 0;
         
         $pvp = $articulos[$i]->pvp;
         if($this->aplicar_a == 'pvp')
         {
            if( $this->x() >= 0 )
            {
               $articulos[$i]->dtopor = $this->x();
               $articulos[$i]->pvp = $articulos[$i]->pvp - $this->y();
            }
            else
            {
               $articulos[$i]->pvp = $articulos[$i]->pvp * (100 - $this->x())/100 - $this->y();
            }
         }
         else
         {
            $articulos[$i]->pvp = $articulos[$i]->preciocoste() * (100 + $this->x())/100 + $this->y();
         }
         
         if($this->mincoste)
         {
            if( $articulos[$i]->pvp * (100 - $articulos[$i]->dtopor) / 100 < $articulos[$i]->preciocoste() )
            {
               $articulos[$i]->dtopor = 0;
               $articulos[$i]->pvp = $articulos[$i]->preciocoste();
            }
         }
         
         if($this->maxpvp)
         {
            if($articulos[$i]->pvp * (100 - $articulos[$i]->dtopor) / 100 > $pvp)
            {
               $articulos[$i]->dtopor = 0;
               $articulos[$i]->pvp = $pvp;
            }
         }
         
         $articulos[$i]->tarifa_diff = $this->diff();
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
      
      if( strlen($this->codtarifa) < 1 OR strlen($this->codtarifa) > 6 )
      {
         $this->new_error_msg("Código de tarifa no válido. Debe tener entre 1 y 6 caracteres.");
      }
      else if( strlen($this->nombre) < 1 OR strlen($this->nombre) > 50 )
      {
         $this->new_error_msg("Nombre de tarifa no válido. Debe tener entre 1 y 50 caracteres.");
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
            $sql = "UPDATE ".$this->table_name." SET nombre = ".$this->var2str($this->nombre)
                    .", incporcentual = ".$this->var2str($this->incporcentual)
                    .", inclineal =".$this->var2str($this->inclineal)
                    .", aplicar_a =".$this->var2str($this->aplicar_a)
                    .", mincoste =".$this->var2str($this->mincoste)
                    .", maxpvp =".$this->var2str($this->maxpvp)
                    ."  WHERE codtarifa = ".$this->var2str($this->codtarifa).";";
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (codtarifa,nombre,incporcentual,inclineal,
               aplicar_a,mincoste,maxpvp) VALUES (".$this->var2str($this->codtarifa)
                    .",".$this->var2str($this->nombre)
                    .",".$this->var2str($this->incporcentual)
                    .",".$this->var2str($this->inclineal)
                    .",".$this->var2str($this->aplicar_a)
                    .",".$this->var2str($this->mincoste)
                    .",".$this->var2str($this->maxpvp).");";
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
