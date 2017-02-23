<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2015-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

if( !function_exists('fs_tipos_id_fiscal') )
{
   /**
    * Devuelve la lista de identificadores fiscales.
    * @return type
    */
   function fs_tipos_id_fiscal()
   {
      return array(FS_CIFNIF,'Pasaporte','DNI','NIF','CIF','CUIT');
   }
}


if( !function_exists('remote_printer') )
{
   /**
    * Vuelca en la salida estándar el buffer de tickets pendientes de imprimir.
    */
   function remote_printer()
   {
      if( isset($_REQUEST['terminal']) )
      {
         require_model('terminal_caja.php');
         
         $t0 = new terminal_caja();
         $terminal = $t0->get($_REQUEST['terminal']);
         if($terminal)
         {
            echo $terminal->tickets;
            
            $terminal->tickets = '';
            $terminal->save();
         }
         else
            echo 'ERROR: terminal no encontrado.';
      }
   }
}


if( !function_exists('plantilla_email') )
{
   /**
    * Devuelve el texto para un email con las modificaciones oportunas.
    * @param type $tipo
    * @param type $documento
    * @param type $firma
    * @return type
    */
   function plantilla_email($tipo, $documento, $firma)
   {
      /// obtenemos las plantillas
      $fsvar = new fs_var();
      $plantillas = array(
          'mail_factura' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
          'mail_albaran' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
          'mail_pedido' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
          'mail_presupuesto' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
      );
      $plantillas = $fsvar->array_get($plantillas, FALSE);
      
      if($tipo == 'albaran')
      {
         $documento = FS_ALBARAN.' '.$documento;
      }
      else if($tipo == 'pedido')
      {
         $documento = FS_PEDIDO.' '.$documento;
      }
      else if($tipo == 'presupuesto')
      {
         $documento = FS_PRESUPUESTO.' '.$documento;
      }
      else
      {
         $documento = $tipo.' '.$documento;
      }
      
      $txt = str_replace('#DOCUMENTO#', $documento, $plantillas['mail_'.$tipo]);
      $txt = str_replace('#FIRMA#', $firma, $txt);
      
      return $txt;
   }
}
