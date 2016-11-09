<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('agente.php');

class admin_agente extends fs_controller
{
   public $agente;
   public $allow_delete;
   
   /*
    * Esta página está en la carpeta admin, pero no se necesita ser admin para usarla.
    * Está en la carpeta admin porque su antecesora también lo está (y debe estarlo).
    */
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Empleado', 'admin', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->ppage = $this->page->get('admin_agentes');
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->agente = FALSE;
      if( isset($_GET['cod']) )
      {
         $agente = new agente();
         $this->agente = $agente->get($_GET['cod']);
      }
      
      if($this->agente)
      {
         $this->page->title .= ' ' . $this->agente->codagente;
         
         if( isset($_POST['nombre']) )
         {
            if( $this->user_can_edit() )
            {
               $this->agente->nombre = $_POST['nombre'];
               $this->agente->apellidos = $_POST['apellidos'];
               $this->agente->dnicif = $_POST['dnicif'];
               $this->agente->telefono = $_POST['telefono'];
               $this->agente->email = $_POST['email'];
               $this->agente->cargo = $_POST['cargo'];
               $this->agente->provincia = $_POST['provincia'];
               $this->agente->ciudad = $_POST['ciudad'];
               $this->agente->direccion = $_POST['direccion'];
               $this->agente->codpostal = $_POST['codpostal'];
               
               $this->agente->f_nacimiento = NULL;
               if($_POST['f_nacimiento'] != '')
               {
                  $this->agente->f_nacimiento = $_POST['f_nacimiento'];
               }
               
               $this->agente->f_alta = NULL;
               if($_POST['f_alta'] != '')
               {
                  $this->agente->f_alta = $_POST['f_alta'];
               }
               
               $this->agente->f_baja = NULL;
               if($_POST['f_baja'] != '')
               {
                  $this->agente->f_baja = $_POST['f_baja'];
               }
               
               $this->agente->seg_social = $_POST['seg_social'];
               $this->agente->banco = $_POST['banco'];
               $this->agente->porcomision = floatval($_POST['porcomision']);
               
               if( $this->agente->save() )
               {
                  $this->new_message("Datos del empleado guardados correctamente.");
               }
               else
                  $this->new_error_msg("¡Imposible guardar los datos del empleado!");
            }
            else
               $this->new_error_msg('No tienes permiso para modificar estos datos.');
         }
      }
      else
      {
         $this->new_error_msg("Empleado no encontrado.", 'error', FALSE, FALSE);
      }
   }
   
   private function user_can_edit()
   {
      if(FS_DEMO)
      {
         return ($this->user->codagente == $this->agente->codagente);
      }
      else
      {
         return TRUE;
      }
   }
   
   public function url()
   {
      if( !isset($this->agente) )
      {
         return parent::url();
      }
      else if($this->agente)
      {
         return $this->agente->url();
      }
      else
         return $this->page->url();
   }
}
