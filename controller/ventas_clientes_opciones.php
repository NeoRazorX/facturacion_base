<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2015   Carlos Garcia Gomez        neorazorx@gmail.com
 * Copyright (C) 2015   Luis Miguel Pérez Romero   luismipr@gmail.com
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

require_model('grupo_clientes.php');
/**
 * Description of opciones_servicios
 *
 * @author carlos
 */
class ventas_clientes_opciones extends fs_controller
{
   public $allow_delete;
   public $ct_setup;
   public $grupo;
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Opciones', 'clientes', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      $this->grupo = new grupo_clientes();
      $this->share_extensions();
      
      /// cargamos la configuración
      $fsvar = new fs_var();
      $this->ct_setup = $fsvar->array_get(
         array(
            'ct_nombre' => 0,
            'ct_nombre_req' => 0,
            'ct_cifnif' => 0,
            'ct_cifnif_req' => 0,
            'ct_direccion' => 0,
            'ct_direccion_req' => 0,
            'ct_codpostal' => 0,
            'ct_codpostal_req' => 0,
            'ct_pais' => 0,
            'ct_pais_req' => 0,
            'ct_provincia' => 0,
            'ct_provincia_req' => 0,
            'ct_ciudad' => 0,
            'ct_ciudad_req' => 0,
            'ct_telefono1' => 0,
            'ct_telefono1_req' => 0,
            'ct_telefono2' => 0,
            'ct_telefono2_req' => 0,
            'ct_grupo' => 0,
            'ct_grupo_req' => 0, 
            'ct_grupo_pred' => 0,
            
         ),
         FALSE
      );
      
      if( isset($_POST['ct_setup']) )
      {
         $this->ct_setup['ct_nombre'] = ( isset($_POST['ct_nombre']) ? 1 : 0 );
         $this->ct_setup['ct_nombre_req'] = ( isset($_POST['ct_nombre_req']) ? 1 : 0 );
         $this->ct_setup['ct_cifnif'] = ( isset($_POST['ct_cifnif']) ? 1 : 0 );
         $this->ct_setup['ct_cifnif_req'] = ( isset($_POST['ct_cifnif_req']) ? 1 : 0 );
         $this->ct_setup['ct_direccion'] = ( isset($_POST['ct_direccion']) ? 1 : 0 );
         $this->ct_setup['ct_direccion_req'] = ( isset($_POST['ct_direccion_req']) ? 1 : 0 );
         $this->ct_setup['ct_codpostal'] = ( isset($_POST['ct_codpostal']) ? 1 : 0 );
         $this->ct_setup['ct_codpostal_req'] = ( isset($_POST['ct_codpostal_req']) ? 1 : 0 );
         $this->ct_setup['ct_pais'] = ( isset($_POST['ct_pais']) ? 1 : 0 );
         $this->ct_setup['ct_pais_req'] = ( isset($_POST['ct_pais_req']) ? 1 : 0 );
         $this->ct_setup['ct_provincia'] = ( isset($_POST['ct_provincia']) ? 1 : 0 );
         $this->ct_setup['ct_provincia_req'] = ( isset($_POST['ct_provincia_req']) ? 1 : 0 );
         $this->ct_setup['ct_ciudad'] = ( isset($_POST['ct_ciudad']) ? 1 : 0 );
         $this->ct_setup['ct_ciudad_req'] = ( isset($_POST['ct_ciudad_req']) ? 1 : 0 );
         $this->ct_setup['ct_telefono1'] = ( isset($_POST['ct_telefono1']) ? 1 : 0 );
         $this->ct_setup['ct_telefono1_req'] = ( isset($_POST['ct_telefono1_req']) ? 1 : 0 );
         $this->ct_setup['ct_telefono2'] = ( isset($_POST['ct_telefono2']) ? 1 : 0 );
         $this->ct_setup['ct_telefono2_req'] = ( isset($_POST['ct_telefono2_req']) ? 1 : 0 );
         $this->ct_setup['ct_grupo'] = ( isset($_POST['ct_grupo']) ? 1 : 0 );
         $this->ct_setup['ct_grupo_req'] = ( isset($_POST['ct_grupo_req']) ? 1 : 0 );
         $this->ct_setup['ct_grupo_pred'] = $_POST['ct_grupo_pred'];
         if( $fsvar->array_save($this->ct_setup) )
         {
            $this->new_message('Datos guardados correctamente.');
         }
         else
            $this->new_error_msg('Error al guardar los datos.');
      }
      
   }
   
   private function share_extensions()
   {
      $fsext = new fs_extension();
      $fsext->name = 'opciones_clientes';
      $fsext->from = __CLASS__;
      $fsext->to = 'ventas_clientes';
      $fsext->type = 'button';
      $fsext->text = '<span class="glyphicon glyphicon-wrench" aria-hidden="true">'
              . '</span><span class="hidden-xs">&nbsp; Opciones</span>';
      $fsext->save();
   }
}
