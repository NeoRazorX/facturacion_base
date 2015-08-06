<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/extras/fs_pdf.php';
require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';
require_model('articulo_proveedor.php');
require_model('proveedor.php');

/**
 * Esta clase agrupa los procedimientos de imprimir/enviar albaranes e imprimir facturas.
 */
class compras_imprimir extends fs_controller
{
   public $albaran;
   public $articulo_proveedor;
   public $proveedor;
   public $factura;
   public $impuesto;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'imprimir', 'compras', FALSE, FALSE);
   }
   
   protected function process()
   {
      $this->albaran = FALSE;
      $this->articulo_proveedor = new articulo_proveedor();
      $this->proveedor = FALSE;
      $this->factura = FALSE;
      $this->impuesto = new impuesto();
      
      if( isset($_REQUEST['albaran']) AND isset($_REQUEST['id']) )
      {
         $alb = new albaran_proveedor();
         $this->albaran = $alb->get($_REQUEST['id']);
         if($this->albaran)
         {
            $proveedor = new proveedor();
            $this->proveedor = $proveedor->get($this->albaran->codproveedor);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('albaran');
         }
         else
            $this->generar_pdf_albaran();
      }
      else if( isset($_REQUEST['factura']) AND isset($_REQUEST['id']) )
      {
         $fac = new factura_proveedor();
         $this->factura = $fac->get($_REQUEST['id']);
         if($this->factura)
         {
            $proveedor = new proveedor();
            $this->proveedor = $proveedor->get($this->factura->codproveedor);
         }
         
         $this->generar_pdf_factura();
      }
      
      $this->share_extensions();
   }
   
   private function share_extensions()
   {
      $extensiones = array(
          array(
              'name' => 'imprimir_albaran_proveedor',
              'page_from' => __CLASS__,
              'page_to' => 'compras_albaran',
              'type' => 'pdf',
              'text' => ucfirst(FS_ALBARAN).' simple',
              'params' => '&albaran=TRUE'
          ),
          array(
              'name' => 'email_albaran_proveedor',
              'page_from' => __CLASS__,
              'page_to' => 'compras_albaran',
              'type' => 'email',
              'text' => ucfirst(FS_ALBARAN).' simple',
              'params' => '&albaran=TRUE'
          ),
          array(
              'name' => 'imprimir_factura_proveedor',
              'page_from' => __CLASS__,
              'page_to' => 'compras_factura',
              'type' => 'pdf',
              'text' => 'Factura simple',
              'params' => '&factura=TRUE'
          ),
      );
      foreach($extensiones as $ext)
      {
         $fsext = new fs_extension($ext);
         if( !$fsext->save() )
         {
            $this->new_error_msg('Error al guardar la extensión '.$ext['name']);
         }
      }
   }
   
   private function generar_pdf_albaran($archivo = FALSE)
   {
      if(!$archivo)
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      $pdf_doc = new fs_pdf();
      $pdf_doc->pdf->addInfo('Title', FS_ALBARAN.' '. $this->albaran->codigo);
      $pdf_doc->pdf->addInfo('Subject', FS_ALBARAN.' de proveedor ' . $this->albaran->codigo);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $lineas = $this->albaran->get_lineas();
      $lineas_iva = $this->get_lineas_iva($lineas);
      if($lineas)
      {
         $linea_actual = 0;
         $pagina = 1;
         
         /// imprimimos las páginas necesarias
         while( $linea_actual < count($lineas) )
         {
            $lppag = 35;
            
            /// salto de página
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
            }
            
            /// ¿Añadimos el logo?
            if( file_exists('tmp/'.FS_TMP_NAME.'logo.png') )
            {
               if( function_exists('imagecreatefromstring') )
               {
                  $pdf_doc->pdf->ezImage('tmp/'.FS_TMP_NAME.'logo.png', 0, 200, 'none');
                  $lppag -= 2; /// si metemos el logo, caben menos líneas
               }
               else
               {
                  die('ERROR: no se encuentra la función imagecreatefromstring(). '
                  . 'Y por tanto no se puede usar el logotipo en los documentos.');
               }
            }
            else
            {
               $pdf_doc->pdf->ezText("<b>".$this->empresa->nombre."</b>", 16, array('justification' => 'center'));
               $pdf_doc->pdf->ezText(FS_CIFNIF.": ".$this->empresa->cifnif, 8, array('justification' => 'center'));
               
               $direccion = $this->empresa->direccion;
               if($this->empresa->codpostal)
                  $direccion .= ' - ' . $this->empresa->codpostal;
               if($this->empresa->ciudad)
                  $direccion .= ' - ' . $this->empresa->ciudad;
               if($this->empresa->provincia)
                  $direccion .= ' (' . $this->empresa->provincia . ')';
               if($this->empresa->telefono)
                  $direccion .= ' - Teléfono: ' . $this->empresa->telefono;
               $pdf_doc->pdf->ezText($this->fix_html($direccion), 9, array('justification' => 'center'));
            }
            
            /*
             * Esta es la tabla con los datos del proveedor:
             * Albarán:             Fecha:
             * Proveedor:             CIF/NIF:
             * Dirección:           Teléfonos:
             */
            $pdf_doc->new_table();
            $pdf_doc->add_table_row(
               array(
                   'campo1' => "<b>".ucfirst(FS_ALBARAN).":</b>",
                   'dato1' => $this->albaran->codigo,
                   'campo2' => "<b>Fecha:</b>",
                   'dato2' => $this->albaran->fecha
               )
            );
            $pdf_doc->add_table_row(
               array(
                   'campo1' => "<b>Proveedor:</b>",
                   'dato1' => $this->fix_html($this->albaran->nombre),
                   'campo2' => "<b>".FS_CIFNIF.":</b>",
                   'dato2' => $this->albaran->cifnif
               )
            );
            $pdf_doc->save_table(
               array(
                   'cols' => array(
                       'campo1' => array('justification' => 'right'),
                       'dato1' => array('justification' => 'left'),
                       'campo2' => array('justification' => 'right'),
                       'dato2' => array('justification' => 'left')
                   ),
                   'showLines' => 0,
                   'width' => 520,
                   'shaded' => 0
               )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            
            /*
             * Creamos la tabla con las lineas del albarán:
             * 
             * Cantidad    Ref. Prov. + Descripción    PVP   DTO   Importe
             */
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                  'cantidad' => '<b>Cant.</b>',
                  'descripcion' => '<b>Ref. Prov. + Descripción</b>',
                  'pvp' => '<b>PVP</b>',
                  'dto' => '<b>Dto.</b>',
                  'importe' => '<b>Importe</b>'
               )
            );
            for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < count($lineas)));)
            {
               $descripcion = $this->fix_html($lineas[$linea_actual]->descripcion);
               if( !is_null($lineas[$linea_actual]->referencia) )
               {
                  $descripcion = '<b>'.  $this->get_referencia_proveedor($lineas[$linea_actual]->referencia, $this->albaran->codproveedor).
                          '</b> '.$this->fix_html($lineas[$linea_actual]->descripcion);
               }
               
               $fila = array(
                  'cantidad' => $lineas[$linea_actual]->cantidad,
                  'descripcion' => $descripcion,
                  'pvp' => $this->show_precio($lineas[$linea_actual]->pvpunitario, $this->albaran->coddivisa),
                  'dto' => $this->show_numero($lineas[$linea_actual]->dtopor, 0) . " %",
                  'importe' => $this->show_precio($lineas[$linea_actual]->pvptotal, $this->albaran->coddivisa)
               );
               
               $pdf_doc->add_table_row($fila);
               $linea_actual++;
            }
            $pdf_doc->save_table(
               array(
                   'fontSize' => 8,
                   'cols' => array(
                       'cantidad' => array('justification' => 'right'),
                       'pvp' => array('justification' => 'right'),
                       'dto' => array('justification' => 'right'),
                       'importe' => array('justification' => 'right')
                   ),
                   'width' => 520,
                   'shaded' => 0
               )
            );
            
            if( $linea_actual == count($lineas) )
            {
               if($this->albaran->observaciones != '')
               {
                  $pdf_doc->pdf->ezText("\n".$this->fix_html($this->albaran->observaciones), 9);
               }
            }
            
            $pdf_doc->set_y(80);
            
            /*
             * Rellenamos la última tabla de la página:
             * 
             * Página            Neto    IVA   Total
             */
            $pdf_doc->new_table();
            $titulo = array('pagina' => '<b>Página</b>', 'neto' => '<b>Neto</b>',);
            $fila = array(
                'pagina' => $pagina . '/' . ceil(count($lineas) / $lppag),
                'neto' => $this->show_precio($this->albaran->neto, $this->albaran->coddivisa),
            );
            $opciones = array(
                'cols' => array(
                    'neto' => array('justification' => 'right'),
                ),
                'showLines' => 4,
                'width' => 520
            );
            foreach($lineas_iva as $li)
            {
               $imp = $this->impuesto->get($li['codimpuesto']);
               if($imp)
               {
                  $titulo['iva'.$li['iva']] = '<b>'.$imp->descripcion.'</b>';
               }
               else
                  $titulo['iva'.$li['iva']] = '<b>'.FS_IVA.' '.$li['iva'].'%</b>';
               
               $fila['iva'.$li['iva']] = $this->show_precio($li['totaliva'], $this->albaran->coddivisa);
               
               if($li['totalrecargo'] != 0)
               {
                  $fila['iva'.$li['iva']] .= ' (RE: '.$this->show_precio($li['totalrecargo'], $this->albaran->coddivisa).')';
               }
               
               $opciones['cols']['iva'.$li['iva']] = array('justification' => 'right');
            }
            
            if($this->albaran->totalirpf != 0)
            {
               $titulo['irpf'] = '<b>'.FS_IRPF.' '.$this->albaran->irpf.'%</b>';
               $fila['irpf'] = $this->show_precio(0 - $this->albaran->totalirpf);
               $opciones['cols']['irpf'] = array('justification' => 'right');
            }
            
            $titulo['liquido'] = '<b>Total</b>';
            $fila['liquido'] = $this->show_precio($this->albaran->total, $this->albaran->coddivisa);
            $opciones['cols']['liquido'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
            
            $pagina++;
         }
      }
      
      if($archivo)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'enviar') )
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show();
   }
   
   private function generar_pdf_factura($archivo=FALSE)
   {
      if(!$archivo)
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      /// Creamos el PDF y escribimos sus metadatos
      $pdf_doc = new fs_pdf();
      $pdf_doc->pdf->addInfo('Title', 'Factura ' . $this->factura->codigo);
      $pdf_doc->pdf->addInfo('Subject', 'Factura de proveedor ' . $this->factura->codigo);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $lineas = $this->factura->get_lineas();
      $lineas_iva = $this->factura->get_lineas_iva();
      if($lineas)
      {
         $lineasfact = count($lineas);
         $linea_actual = 0;
         $pagina = 1;
         
         // Imprimimos las páginas necesarias
         while($linea_actual < $lineasfact)
         {
            $lppag = 35; /// líneas por página
            
            /// salto de página
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
            }
            
            /// ¿Añadimos el logo?
            if( file_exists('tmp/'.FS_TMP_NAME.'logo.png') )
            {
               if( function_exists('imagecreatefromstring') )
               {
                  $pdf_doc->pdf->ezImage('tmp/'.FS_TMP_NAME.'logo.png', 0, 200, 'none');
                  $lppag -= 2; /// si metemos el logo, caben menos líneas
               }
               else
               {
                  die('ERROR: no se encuentra la función imagecreatefromstring(). '
                  . 'Y por tanto no se puede usar el logotipo en los documentos.');
               }
            }
            else
            {
               $pdf_doc->pdf->ezText("<b>".$this->empresa->nombre."</b>", 16, array('justification' => 'center'));
               $pdf_doc->pdf->ezText(FS_CIFNIF.": ".$this->empresa->cifnif, 8, array('justification' => 'center'));
               
               $direccion = $this->empresa->direccion;
               if($this->empresa->codpostal)
                  $direccion .= ' - ' . $this->empresa->codpostal;
               if($this->empresa->ciudad)
                  $direccion .= ' - ' . $this->empresa->ciudad;
               if($this->empresa->provincia)
                  $direccion .= ' (' . $this->empresa->provincia . ')';
               if($this->empresa->telefono)
                  $direccion .= ' - Teléfono: ' . $this->empresa->telefono;
               $pdf_doc->pdf->ezText($this->fix_html($direccion), 9, array('justification' => 'center'));
            }
            
            /*
             * Esta es la tabla con los datos del proveedor:
             * Factura:             Fecha:
             * Proveedor:             CIF/NIF:
             * Dirección:           Teléfonos:
             */
            $pdf_doc->new_table();
            $pdf_doc->add_table_row(
               array(
                  'campo1' => "<b>Factura:</b>",
                  'dato1' => $this->factura->codigo,
                  'campo2' => "<b>Fecha:</b>",
                  'dato2' => $this->factura->fecha
               )
            );
            $pdf_doc->add_table_row(
               array(
                  'campo1' => "<b>Proveedor:</b>",
                  'dato1' => $this->fix_html($this->factura->nombre),
                  'campo2' => "<b>".FS_CIFNIF.":</b>",
                  'dato2' => $this->factura->cifnif
               )
            );
            $pdf_doc->save_table(
               array(
                  'cols' => array(
                     'campo1' => array('justification' => 'right'),
                     'dato1' => array('justification' => 'left'),
                     'campo2' => array('justification' => 'right'),
                     'dato2' => array('justification' => 'left')
                  ),
                  'showLines' => 0,
                  'width' => 520,
                  'shaded' => 0
               )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            
            /*
             * Creamos la tabla con las lineas de la factura:
             * 
             * Cantidad    Ref. Prov. + Descripción    PVP   DTO    Importe
             */
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                  'cantidad' => '<b>Cant.</b>',
                  'descripcion' => '<b>Ref. Prov. + Descripción</b>',
                  'pvp' => '<b>PVP</b>',
                  'dto' => '<b>Dto.</b>',
                  'importe' => '<b>Importe</b>'
               )
            );
            for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < $lineasfact));)
            {
               $descripcion = $this->fix_html($lineas[$linea_actual]->descripcion);
               if( !is_null($lineas[$linea_actual]->referencia) )
               {
                  $descripcion = '<b>'.  $this->get_referencia_proveedor($lineas[$linea_actual]->referencia, $this->factura->codproveedor).
                          '</b> '.$this->fix_html($lineas[$linea_actual]->descripcion);
               }
               
               $fila = array(
                  'cantidad' => $lineas[$linea_actual]->cantidad,
                  'descripcion' => $descripcion,
                  'pvp' => $this->show_precio($lineas[$linea_actual]->pvpunitario, $this->factura->coddivisa),
                  'dto' => $this->show_numero($lineas[$linea_actual]->dtopor, 0) . " %",
                  'importe' => $this->show_precio($lineas[$linea_actual]->pvptotal, $this->factura->coddivisa)
               );
               
               $pdf_doc->add_table_row($fila);
               $linea_actual++;
            }
            $pdf_doc->save_table(
               array(
                   'fontSize' => 8,
                   'cols' => array(
                       'cantidad' => array('justification' => 'right'),
                       'pvp' => array('justification' => 'right'),
                       'dto' => array('justification' => 'right'),
                       'importe' => array('justification' => 'right')
                   ),
                   'width' => 520,
                   'shaded' => 0
               )
            );
            
            if( $linea_actual == count($lineas) )
            {
               if($this->factura->observaciones != '')
               {
                  $pdf_doc->pdf->ezText("\n".$this->fix_html($this->factura->observaciones), 9);
               }
            }
            
            $pdf_doc->set_y(80);
            
            /*
             * Rellenamos la última tabla de la página:
             * 
             * Página            Neto    IVA   Total
             */
            $pdf_doc->new_table();
            $titulo = array('pagina' => '<b>Página</b>', 'neto' => '<b>Neto</b>',);
            $fila = array(
                'pagina' => $pagina . '/' . ceil(count($lineas) / $lppag),
                'neto' => $this->show_precio($this->factura->neto, $this->factura->coddivisa),
            );
            $opciones = array(
                'cols' => array(
                    'neto' => array('justification' => 'right'),
                ),
                'showLines' => 4,
                'width' => 520
            );
            foreach($lineas_iva as $li)
            {
               $imp = $this->impuesto->get($li->codimpuesto);
               if($imp)
               {
                  $titulo['iva'.$li->iva] = '<b>'.$imp->descripcion.'</b>';
               }
               else
                  $titulo['iva'.$li->iva] = '<b>'.FS_IVA.' '.$li->iva.'%</b>';
               
               $fila['iva'.$li->iva] = $this->show_precio($li->totaliva, $this->factura->coddivisa);
               
               if($li->totalrecargo != 0)
               {
                  $fila['iva'.$li->iva] .= ' (RE: '.$this->show_precio($li->totalrecargo, $this->factura->coddivisa).')';
               }
               
               $opciones['cols']['iva'.$li->iva] = array('justification' => 'right');
            }
            
            if($this->factura->totalirpf != 0)
            {
               $titulo['irpf'] = '<b>'.FS_IRPF.' '.$this->factura->irpf.'%</b>';
               $fila['irpf'] = $this->show_precio(0 - $this->factura->totalirpf);
               $opciones['cols']['irpf'] = array('justification' => 'right');
            }
            
            $titulo['liquido'] = '<b>Total</b>';
            $fila['liquido'] = $this->show_precio($this->factura->total, $this->factura->coddivisa);
            $opciones['cols']['liquido'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
            
            $pagina++;
         }
      }
      
      if($archivo)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'enviar') )
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show();
   }
   
   private function get_referencia_proveedor($ref, $codproveedor)
   {
      $artprov = $this->articulo_proveedor->get_by($ref, $codproveedor);
      if($artprov)
      {
         return $artprov->refproveedor;
      }
      else
         return $ref;
   }
   
   private function enviar_email($doc)
   {
      if( $this->empresa->can_send_mail() )
      {
         if( $_POST['email'] != $this->proveedor->email )
         {
            $this->proveedor->email = $_POST['email'];
            $this->proveedor->save();
         }
         
         /// obtenemos la configuración extra del email
         $mailop = array(
             'mail_host' => 'smtp.gmail.com',
             'mail_port' => '465',
             'mail_user' => '',
             'mail_enc' => 'ssl'
         );
         $fsvar = new fs_var();
         $mailop = $fsvar->array_get($mailop, FALSE);
         
         $filename = 'albaran_'.$this->albaran->codigo.'.pdf';
         $this->generar_pdf_albaran($filename);
         
         if( file_exists('tmp/'.FS_TMP_NAME.'enviar/'.$filename) )
         {
            $mail = new PHPMailer();
            $mail->IsSMTP();
            $mail->SMTPAuth = TRUE;
            $mail->SMTPSecure = $mailop['mail_enc'];
            $mail->Host = $mailop['mail_host'];
            $mail->Port = intval($mailop['mail_port']);
            
            $mail->Username = $this->empresa->email;
            if($mailop['mail_user'] != '')
            {
               $mail->Username = $mailop['mail_user'];
            }
            
            $mail->Password = $this->empresa->email_password;
            $mail->From = $this->empresa->email;
            $mail->FromName = $this->user->nick;
            $mail->CharSet = 'UTF-8';
            
            $mail->Subject = $this->empresa->nombre . ': Mi '.FS_ALBARAN.' '.$this->albaran->codigo;
            $mail->AltBody = 'Buenos días, le adjunto mi '.FS_ALBARAN.' '.$this->albaran->codigo.".\n".$this->empresa->email_firma;
            
            $mail->WordWrap = 50;
            $mail->MsgHTML( nl2br($_POST['mensaje']) );
            $mail->AddAttachment('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
            $mail->AddAddress($_POST['email'], $this->proveedor->razonsocial);
            $mail->IsHTML(TRUE);
            
            if( $mail->Send() )
            {
               $this->new_message('Mensaje enviado correctamente.');
            }
            else
               $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
            
            unlink('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
         }
         else
            $this->new_error_msg('Imposible generar el PDF.');
      }
   }
   
   private function fix_html($txt)
   {
      $newt = str_replace('&lt;', '<', $txt);
      $newt = str_replace('&gt;', '>', $newt);
      $newt = str_replace('&quot;', '"', $newt);
      $newt = str_replace('&#39;', "'", $newt);
      return $newt;
   }
   
   private function get_lineas_iva($lineas)
   {
      $retorno = array();
      $lineasiva = array();
      
      foreach($lineas as $lin)
      {
         if( isset($lineasiva[$lin->codimpuesto]) )
         {
            $lineasiva[$lin->codimpuesto]['neto'] += $lin->pvptotal;
            $lineasiva[$lin->codimpuesto]['totaliva'] += ($lin->pvptotal*$lin->iva)/100;
            $lineasiva[$lin->codimpuesto]['totalrecargo'] += ($lin->pvptotal*$lin->recargo)/100;
            $lineasiva[$lin->codimpuesto]['totallinea'] = $lineasiva[$lin->codimpuesto]['neto'] + $lineasiva[$lin->codimpuesto]['totaliva']
                    + $lineasiva[$lin->codimpuesto]['totalrecargo'];
         }
         else
         {
            $lineasiva[$lin->codimpuesto] = array(
                'codimpuesto' => $lin->codimpuesto,
                'iva' => $lin->iva,
                'recargo' => $lin->recargo,
                'neto' => $lin->pvptotal,
                'totaliva' => ($lin->pvptotal*$lin->iva)/100,
                'totalrecargo' => ($lin->pvptotal*$lin->recargo)/100,
                'totallinea' => 0
            );
            $lineasiva[$lin->codimpuesto]['totallinea'] = $lineasiva[$lin->codimpuesto]['neto'] + $lineasiva[$lin->codimpuesto]['totaliva']
                    + $lineasiva[$lin->codimpuesto]['totalrecargo'];
         }
      }
      
      foreach($lineasiva as $lin)
      {
         $retorno[] = $lin;
      }
      
      return $retorno;
   }
}
