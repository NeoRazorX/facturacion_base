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

require_model('articulo.php');
require_model('fabricante.php');

class ventas_fabricante extends fs_controller
{
   public $allow_delete;
   public $articulos;
   public $fabricante;
   public $offset;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Familia', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
     
      
      $this->fabricante = FALSE;
      if( isset($_REQUEST['cod']) )
      {
         $fab = new fabricante();
         $this->fabricante = $fab->get($_REQUEST['cod']);
      }
     
      
      if($this->fabricante)
      {
         $this->page->title = $this->fabricante->codfabricante;
         
         if( isset($_POST['cod']) )
         {
            $this->fabricante->nombre = $_POST['nombre'];

            if( $this->fabricante->save() )
            {
               $this->new_message("Datos modificados correctamente");
            }
            else
               $this->new_error_msg("Imposible modificar los datos.");
         }
         
         $this->offset = 0;
         if( isset($_GET['offset']) )
         {
            $this->offset = intval($_GET['offset']);
         }
         
         $this->articulos = $this->fabricante->get_articulos($this->offset);
      }
      else
         $this->new_error_msg("Fabricante no encontrado.");
   }
   
   public function url()
   {
      if( !isset($this->fabricante) )
      {
         return parent::url();
      }
      else if($this->fabricante)
      {
         return $this->fabricante->url();
      }
      else
         return $this->page->url();
   }

   public function anterior_url()
   {
      $url = '';
      
      if($this->offset > '0')
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT);
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      
      if(count($this->articulos)==FS_ITEM_LIMIT)
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT);
      }
      
      return $url;
   }
   
   public function total_fabricante()
   {
      $data = $this->db->select("SELECT COUNT(referencia) as total FROM articulos WHERE codfabricante = ".
              $this->empresa->var2str($this->fabricante->codfabricante).";");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
}
