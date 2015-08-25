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

require_model('provincia.php');
require_model('pais.php');

class admin_provincias extends fs_controller
{
   public $allow_delete;
   public $provincia;
   public $pais;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Provincias', 'admin', FALSE, TRUE);
   }
   
   protected function process()
   {
      $this->provincia = new provincia();
      $this->pais = new pais();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      if( isset($_POST['scodprovincia']) )
      {
         $provincia = $this->provincia->get($_POST['scodprovincia']);
         if( !$provincia )
         {
            $provincia = new provincia();
            $provincia->codprovincia = $_POST['scodprovincia'];
         }
         
         $provincia->codpais = $_POST['scodpais'];
         $provincia->nombre = $_POST['snombre'];
         
         if( $provincia->save() )
         {
            $this->new_message("Provincia ".$provincia->nombre." guardada correctamente.");
         }
         else
            $this->new_error_msg("¡Imposible guardar la provincia!");
      }
      else if( isset($_GET['delete']) )
      {
         if(FS_DEMO)
         {
            $this->new_error_msg('En el modo demo no puedes eliminar provincias. Otro usuario podría necesitarlo.');
         }
         else
         {
            $provincia = $this->provincia->get($_GET['delete']);
            if( $provincia )
            {
               if( $provincia->delete() )
               {
                  $this->new_message("Provincia ".$provincia->nombre." eliminado correctamente.");
               }
               else
                  $this->new_error_msg("¡Imposible eliminar el provincia!");
            }
            else
               $this->new_error_msg("¡Provincia no encontrado!");
         }
      }
   }
}
