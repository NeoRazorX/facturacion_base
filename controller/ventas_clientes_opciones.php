<?php

/*
 * This file is part of facturacion_base
 * Copyright (C) 2015-2016    Carlos Garcia Gomez        neorazorx@gmail.com
 * Copyright (C) 2015         Luis Miguel Pérez Romero   luismipr@gmail.com
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

require_model('grupo_clientes.php');

/**
 * Description of opciones_servicios
 *
 * @author Carlos Garcia Gomez
 */
class ventas_clientes_opciones extends fs_controller
{
   public $nuevocli_setup;
   public $grupo;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Opciones', 'clientes', FALSE, FALSE);
   }

   protected function private_core()
   {
      $this->share_extension();

      $this->grupo = new grupo_clientes();

      /// cargamos la configuración
      $fsvar = new fs_var();
      $this->nuevocli_setup = $fsvar->array_get(
         array(
            'nuevocli_cifnif_req' => 0,
            'nuevocli_direccion' => 1,
            'nuevocli_direccion_req' => 0,
            'nuevocli_codpostal' => 1,
            'nuevocli_codpostal_req' => 0,
            'nuevocli_pais' => 0,
            'nuevocli_pais_req' => 0,
            'nuevocli_provincia' => 1,
            'nuevocli_provincia_req' => 0,
            'nuevocli_ciudad' => 1,
            'nuevocli_ciudad_req' => 0,
            'nuevocli_telefono1' => 0,
            'nuevocli_telefono1_req' => 0,
            'nuevocli_telefono2' => 0,
            'nuevocli_telefono2_req' => 0,
            'nuevocli_email' => 0,
            'nuevocli_email_req' => 0,
            'nuevocli_codgrupo' => '',
         ),
         FALSE
      );

      if( isset($_POST['setup']) )
      {
         $this->nuevocli_setup['nuevocli_cifnif_req'] = ( isset($_POST['nuevocli_cifnif_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_direccion'] = ( isset($_POST['nuevocli_direccion']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_direccion_req'] = ( isset($_POST['nuevocli_direccion_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_codpostal'] = ( isset($_POST['nuevocli_codpostal']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_codpostal_req'] = ( isset($_POST['nuevocli_codpostal_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_pais'] = ( isset($_POST['nuevocli_pais']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_pais_req'] = ( isset($_POST['nuevocli_pais_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_provincia'] = ( isset($_POST['nuevocli_provincia']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_provincia_req'] = ( isset($_POST['nuevocli_provincia_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_ciudad'] = ( isset($_POST['nuevocli_ciudad']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_ciudad_req'] = ( isset($_POST['nuevocli_ciudad_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_telefono1'] = ( isset($_POST['nuevocli_telefono1']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_telefono1_req'] = ( isset($_POST['nuevocli_telefono1_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_telefono2'] = ( isset($_POST['nuevocli_telefono2']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_telefono2_req'] = ( isset($_POST['nuevocli_telefono2_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_email'] = ( isset($_POST['nuevocli_email']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_email_req'] = ( isset($_POST['nuevocli_email_req']) ? 1 : 0 );
         $this->nuevocli_setup['nuevocli_codgrupo'] = $_POST['nuevocli_codgrupo'];

         if( $fsvar->array_save($this->nuevocli_setup) )
         {
            $this->new_message('Datos guardados correctamente.');
         }
         else
            $this->new_error_msg('Error al guardar los datos.');
      }
   }

   private function share_extension()
   {
      $fsext = new fs_extension();
      $fsext->name = 'opciones_clientes';
      $fsext->from = __CLASS__;
      $fsext->to = 'ventas_clientes';
      $fsext->type = 'button';
      $fsext->text = '<span class="glyphicon glyphicon-wrench" aria-hidden="true" title="Opciones para nuevos clientes"></span>'
              . '<span class="hidden-xs">&nbsp; Opciones</span>';
      $fsext->save();
   }
}
