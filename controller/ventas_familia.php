<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('articulo.php');
require_model('familia.php');

class ventas_familia extends fs_controller
{
   public $allow_delete;
   public $articulos;
   public $familia;
   public $madre;
   public $offset;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Familia', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->familia = FALSE;
      if( isset($_REQUEST['cod']) )
      {
         $fam = new familia();
         $this->familia = $fam->get($_REQUEST['cod']);
      }
      $this->madre = FALSE;
      
      if($this->familia)
      {
         $this->page->title = $this->familia->codfamilia;
         
         if( isset($_POST['cod']) )
         {
            $this->familia->descripcion = $_POST['descripcion'];
            
            $this->familia->madre = NULL;
            if( isset($_POST['madre']) )
            {
               if($_POST['madre'] != '---')
               {
                  $this->familia->madre = $_POST['madre'];
               }
            }
            
            if( $this->familia->save() )
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
         
         $this->madre = $this->familia->get($this->familia->madre);
         $this->articulos = $this->familia->get_articulos($this->offset);
      }
      else
      {
         $this->new_error_msg("Familia no encontrada.", 'error', FALSE, FALSE);
      }
   }
   
   public function url()
   {
      if( !isset($this->familia) )
      {
         return parent::url();
      }
      else if($this->familia)
      {
         return $this->familia->url();
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
   
   public function total_familia()
   {
      $data = $this->db->select("SELECT COUNT(referencia) as total FROM articulos WHERE codfamilia = ".
              $this->empresa->var2str($this->familia->codfamilia).";");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
}
