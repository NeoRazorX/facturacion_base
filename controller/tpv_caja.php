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

require_model('caja.php');

class tpv_caja extends fs_controller
{
   public $allow_delete;
   public $caja;
   public $offset;
   public $resultados;
   public $show_cerrar;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Caja', 'TPV', FALSE, TRUE);
   }
   
   protected function process()
   {
      $this->caja = new caja();
      $this->show_cerrar = FALSE;
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      if( isset($_GET['delete']) )
      {
         if($this->user->admin)
         {
            $caja2 = $this->caja->get($_GET['delete']);
            if($caja2)
            {
               if( $caja2->delete() )
               {
                  $this->new_message("Caja eliminadas correctamente.");
               }
               else
                  $this->new_error_msg("¡Imposible eliminar la caja!");
            }
            else
               $this->new_error_msg("Caja no encontrada.");
         }
         else
            $this->new_error_msg("Tienes que ser administrador para poder eliminar cajas.");
      }
      
      $caja0 = $this->caja->get_last_from_this_server();
      if($caja0)
      {
         if( isset($_GET['cerrar']) )
         {
            if( $this->user->admin )
            {
               $caja0->fecha_fin = Date('d-m-Y H:i:s');
               if( $caja0->save() )
               {
                  $this->new_message("Caja cerrada correctamente.");
               }
               else
                  $this->new_error_msg("¡Imposible cerrar la caja!");
            }
            else
               $this->new_error_msg("Tienes que ser administrador para poder cerrar la caja desde aquí. ¡Listo!");
         }
         else
            $this->show_cerrar = TRUE;
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
         $this->offset = intval($_GET['offset']);
      
      $this->resultados = $this->caja->all($this->offset);
   }
   
   public function anterior_url()
   {
      $url = '';
      
      if($this->offset > 0)
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT);
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      
      if( count($this->resultados) == FS_ITEM_LIMIT )
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT);
      }
      
      return $url;
   }
}
