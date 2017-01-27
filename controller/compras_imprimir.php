<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once __DIR__.'/../extras/fs_pdf.php';
require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';
require_model('articulo_proveedor.php');
require_model('articulo_traza.php');
require_model('proveedor.php');

/**
 * Esta clase agrupa los procedimientos de imprimir/enviar albaranes de proveedor
 * e imprimir facturas de proveedor.
 */
class compras_imprimir extends fs_controller
{
   public $documento;
   public $impresion;
   public $impuesto;
   public $proveedor;
   
   private $articulo_proveedor;
   private $articulo_traza;
   private $numpaginas;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'imprimir', 'compras', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->articulo_proveedor = new articulo_proveedor();
      $this->documento = FALSE;
      $this->impuesto = new impuesto();
      $this->proveedor = FALSE;
      
      /// obtenemos los datos de configuración de impresión
      $this->impresion = array(
          'print_ref' => '1',
          'print_dto' => '1',
          'print_alb' => '0'
      );
      $fsvar = new fs_var();
      $this->impresion = $fsvar->array_get($this->impresion, FALSE);
      
      if( isset($_REQUEST['albaran']) AND isset($_REQUEST['id']) )
      {
         $this->articulo_traza = new articulo_traza();
         
         $alb = new albaran_proveedor();
         $this->documento = $alb->get($_REQUEST['id']);
         if($this->documento)
         {
            $proveedor = new proveedor();
            $this->proveedor = $proveedor->get($this->documento->codproveedor);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email();
         }
         else
            $this->generar_pdf_albaran();
      }
      else if( isset($_REQUEST['factura']) AND isset($_REQUEST['id']) )
      {
         $this->articulo_traza = new articulo_traza();
         
         $fac = new factura_proveedor();
         $this->documento = $fac->get($_REQUEST['id']);
         if($this->documento)
         {
            $proveedor = new proveedor();
            $this->proveedor = $proveedor->get($this->documento->codproveedor);
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
   
   private function generar_pdf_lineas(&$pdf_doc, &$lineas, &$linea_actual, &$lppag)
   {
      /// calculamos el número de páginas
      if( !isset($this->numpaginas) )
      {
         $this->numpaginas = 0;
         $linea_a = 0;
         while( $linea_a < count($lineas) )
         {
            $lppag2 = $lppag;
            foreach($lineas as $i => $lin)
            {
               if($i >= $linea_a AND $i < $linea_a + $lppag2)
               {
                  $linea_size = 1;
                  $len = mb_strlen($lin->referencia.' '.$lin->descripcion);
                  while($len > 85)
                  {
                     $len -= 85;
                     $linea_size += 0.5;
                  }
                  
                  $aux = explode("\n", $lin->descripcion);
                  if( count($aux) > 1 )
                  {
                     $linea_size += 0.5 * ( count($aux) - 1);
                  }
                  
                  if($linea_size > 1)
                  {
                     $lppag2 -= $linea_size - 1;
                  }
               }
            }
            
            $linea_a += $lppag2;
            $this->numpaginas++;
         }
         
         if($this->numpaginas == 0)
         {
            $this->numpaginas = 1;
         }
      }
      
      if($this->impresion['print_dto'])
      {
         $this->impresion['print_dto'] = FALSE;
         
         /// leemos las líneas para ver si de verdad mostramos los descuentos
         foreach($lineas as $lin)
         {
            if($lin->dtopor != 0)
            {
               $this->impresion['print_dto'] = TRUE;
               break;
            }
         }
      }
      
      $dec_cantidad = 0;
      $multi_iva = FALSE;
      $multi_re = FALSE;
      $multi_irpf = FALSE;
      $iva = FALSE;
      $re = FALSE;
      $irpf = FALSE;
      /// leemos las líneas para ver si hay que mostrar los tipos de iva, re o irpf
      foreach($lineas as $i => $lin)
      {
         if( $lin->cantidad != intval($lin->cantidad) )
         {
            $dec_cantidad = 2;
         }
         
         if($iva === FALSE)
         {
            $iva = $lin->iva;
         }
         else if($lin->iva != $iva)
         {
            $multi_iva = TRUE;
         }
         
         if($re === FALSE)
         {
            $re = $lin->recargo;
         }
         else if($lin->recargo != $re)
         {
            $multi_re = TRUE;
         }
         
         if($irpf === FALSE)
         {
            $irpf = $lin->irpf;
         }
         else if($lin->irpf != $irpf)
         {
            $multi_irpf = TRUE;
         }
         
         /// restamos líneas al documento en función del tamaño de la descripción
         if($i >= $linea_actual AND $i < $linea_actual+$lppag)
         {
            $linea_size = 1;
            $len = mb_strlen($lin->referencia.' '.$lin->descripcion);
            while($len > 85)
            {
               $len -= 85;
               $linea_size += 0.5;
            }
            
            $aux = explode("\n", $lin->descripcion);
            if( count($aux) > 1 )
            {
               $linea_size += 0.5 * ( count($aux) - 1);
            }
            
            if($linea_size > 1)
            {
               $lppag -= $linea_size - 1;
            }
         }
      }
      
      /*
       * Creamos la tabla con las lineas del documento
       */
      $pdf_doc->new_table();
      $table_header = array(
          'cantidad' => '<b>Cant.</b>',
          'descripcion' => '<b>Ref. Prov. + Descripción</b>',
          'pvp' => '<b>Precio</b>',
      );
      
      if($this->impresion['print_dto'])
      {
         $table_header['dto'] = '<b>Dto.</b>';
      }
      
      if($multi_iva)
      {
         $table_header['iva'] = '<b>'.FS_IVA.'</b>';
      }
      
      if($multi_re)
      {
         $table_header['re'] = '<b>R.E.</b>';
      }
      
      if($multi_irpf)
      {
         $table_header['irpf'] = '<b>'.FS_IRPF.'</b>';
      }
      
      $table_header['importe'] = '<b>Importe</b>';
      $pdf_doc->add_table_header($table_header);
      
      for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < count($lineas)));)
      {
         $descripcion = $pdf_doc->fix_html($lineas[$linea_actual]->descripcion);
         if( !is_null($lineas[$linea_actual]->referencia) )
         {
            $descripcion = '<b>'.$this->get_referencia_proveedor($lineas[$linea_actual]->referencia)
                    .'</b> '.$descripcion;
         }
         
         /// ¿El articulo tiene trazabilidad?
         $descripcion .= $this->generar_trazabilidad($lineas[$linea_actual]);
         
         $fila = array(
             'cantidad' => $this->show_numero($lineas[$linea_actual]->cantidad, $dec_cantidad),
             'descripcion' => $descripcion,
             'pvp' => $this->show_precio($lineas[$linea_actual]->pvpunitario, $this->documento->coddivisa, TRUE, FS_NF0_ART),
             'dto' => $this->show_numero($lineas[$linea_actual]->dtopor) . " %",
             'iva' => $this->show_numero($lineas[$linea_actual]->iva) . " %",
             're' => $this->show_numero($lineas[$linea_actual]->recargo) . " %",
             'irpf' => $this->show_numero($lineas[$linea_actual]->irpf) . " %",
             'importe' => $this->show_precio($lineas[$linea_actual]->pvptotal, $this->documento->coddivisa)
         );
         
         if($lineas[$linea_actual]->dtopor == 0)
         {
            $fila['dto'] = '';
         }
         
         if($lineas[$linea_actual]->recargo == 0)
         {
            $fila['re'] = '';
         }
         
         if($lineas[$linea_actual]->irpf == 0)
         {
            $fila['irpf'] = '';
         }
         
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
                      'iva' => array('justification' => 'right'),
                      're' => array('justification' => 'right'),
                      'irpf' => array('justification' => 'right'),
                      'importe' => array('justification' => 'right')
                  ),
                  'width' => 520,
                  'shaded' => 1,
                  'shadeCol' => array(0.95, 0.95, 0.95),
                  'lineCol' => array(0.3, 0.3, 0.3),
              )
      );
      
      /// ¿Última página?
      if( $linea_actual == count($lineas) )
      {
         if($this->documento->observaciones != '')
         {
            $pdf_doc->pdf->ezText("\n".$pdf_doc->fix_html($this->documento->observaciones), 9);
         }
      }
      
      $pdf_doc->set_y(80);
   }
   
   private function get_referencia_proveedor($ref)
   {
      $artprov = $this->articulo_proveedor->get_by($ref, $this->documento->codproveedor);
      if($artprov)
      {
         return $artprov->refproveedor;
      }
      else
         return $ref;
   }
   
   /**
    * Devuelve el texto con los números de serie o lotes de la $linea
    * @param linea_albaran_compra $linea
    * @return string
    */
   private function generar_trazabilidad($linea)
   {
      $lineast = array();
      if( get_class_name($linea) == 'linea_albaran_proveedor' )
      {
         $lineast = $this->articulo_traza->all_from_linea('idlalbcompra', $linea->idlinea);
      }
      else
      {
         $lineast = $this->articulo_traza->all_from_linea('idlfaccompra', $linea->idlinea);
      }
      
      $txt = '';
      foreach($lineast as $lt)
      {
         $txt .= "\n";
         if($lt->numserie)
         {
            $txt .= 'N/S: '.$lt->numserie.' ';
         }
         
         if($lt->lote)
         {
            $txt .= 'Lote: '.$lt->lote;
         }
      }
      
      return $txt;
   }
   
   private function generar_pdf_datos_proveedor(&$pdf_doc)
   {
      $tipo_doc = ucfirst(FS_ALBARAN);
      $rectificativa = FALSE;
      if( get_class_name($this->documento) == 'factura_proveedor' )
      {
         if($this->documento->idfacturarect)
         {
            $tipo_doc = ucfirst(FS_FACTURA_RECTIFICATIVA);
            $rectificativa = TRUE;
         }
         else
         {
            $tipo_doc = 'Factura';
         }
      }
      
      $tipoidfiscal = FS_CIFNIF;
      if($this->proveedor)
      {
         $tipoidfiscal = $this->proveedor->tipoidfiscal;
      }
      
      /*
       * Esta es la tabla con los datos del proveedor:
       * Documento:               Fecha:
       * Proveedor:             CIF/NIF:
       */
      $pdf_doc->new_table();
      $pdf_doc->add_table_row(
              array(
                  'campo1' => "<b>".$tipo_doc.":</b>",
                  'dato1' => $this->documento->codigo,
                  'campo2' => "<b>Fecha:</b> ".$this->documento->fecha
              )
      );
      
      if($rectificativa)
      {
         $pdf_doc->add_table_row(
                 array(
                     'campo1' => "<b>Original:</b>",
                     'dato1' => $this->documento->codigorect,
                     'campo2' => '',
                 )
         );
      }
      
      $pdf_doc->add_table_row(
              array(
                  'campo1' => "<b>Proveedor:</b>",
                  'dato1' => $pdf_doc->fix_html($this->documento->nombre),
                  'campo2' => "<b>".$tipoidfiscal.":</b> ".$this->documento->cifnif
              )
      );
      
      $pdf_doc->save_table(
              array(
                  'cols' => array(
                      'campo1' => array('width' => 90, 'justification' => 'right'),
                      'dato1' => array('justification' => 'left'),
                      'campo2' => array('justification' => 'right'),
                  ),
                  'showLines' => 0,
                  'width' => 520,
                  'shaded' => 0
              )
      );
      $pdf_doc->pdf->ezText("\n", 10);
   }
   
   private function generar_pdf_totales(&$pdf_doc, &$lineas_iva, $pagina)
   {
      /*
       * Rellenamos la última tabla de la página:
       * 
       * Página            Neto    IVA   Total
       */
      $pdf_doc->new_table();
      $titulo = array('pagina' => '<b>Página</b>', 'neto' => '<b>Neto</b>',);
      $fila = array(
          'pagina' => $pagina . '/' . $this->numpaginas,
          'neto' => $this->show_precio($this->documento->neto, $this->documento->coddivisa),
      );
      $opciones = array(
          'cols' => array(
                    'neto' => array('justification' => 'right'),
          ),
          'showLines' => 3,
          'shaded' => 2,
          'shadeCol2' => array(0.95, 0.95, 0.95),
          'lineCol' => array(0.3, 0.3, 0.3),
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
         
         $fila['iva'.$li['iva']] = $this->show_precio($li['totaliva'], $this->documento->coddivisa);
         
         if($li['totalrecargo'] != 0)
         {
            $fila['iva'.$li['iva']] .= "\nR.E. ".$li['recargo']."%: ".$this->show_precio($li['totalrecargo'], $this->documento->coddivisa);
         }
         
         $opciones['cols']['iva'.$li['iva']] = array('justification' => 'right');
      }
      
      if($this->documento->totalirpf != 0)
      {
         $titulo['irpf'] = '<b>'.FS_IRPF.' '.$this->documento->irpf.'%</b>';
         $fila['irpf'] = $this->show_precio($this->documento->totalirpf);
         $opciones['cols']['irpf'] = array('justification' => 'right');
      }
      
      $titulo['liquido'] = '<b>Total</b>';
      $fila['liquido'] = $this->show_precio($this->documento->total, $this->documento->coddivisa);
      $opciones['cols']['liquido'] = array('justification' => 'right');
      $pdf_doc->add_table_header($titulo);
      $pdf_doc->add_table_row($fila);
      $pdf_doc->save_table($opciones);
   }
   
   private function generar_pdf_albaran($archivo = FALSE)
   {
      if(!$archivo)
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      /// Creamos el PDF y escribimos sus metadatos
      $pdf_doc = new fs_pdf();
      $pdf_doc->pdf->addInfo('Title', ucfirst(FS_ALBARAN).' '. $this->documento->codigo);
      $pdf_doc->pdf->addInfo('Subject', ucfirst(FS_ALBARAN).' de proveedor ' . $this->documento->codigo);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $lineas = $this->documento->get_lineas();
      $lineas_iva = $pdf_doc->get_lineas_iva($lineas);
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
            
            $pdf_doc->generar_pdf_cabecera($this->empresa, $lppag);
            $this->generar_pdf_datos_proveedor($pdf_doc);
            $this->generar_pdf_lineas($pdf_doc, $lineas, $linea_actual, $lppag);
            $this->generar_pdf_totales($pdf_doc, $lineas_iva, $pagina);
            $pagina++;
         }
      }
      else
      {
         $pdf_doc->pdf->ezText('¡'.ucfirst(FS_ALBARAN).' sin líneas!', 20);
      }
      
      if($archivo)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'enviar') )
         {
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         }
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show(FS_ALBARAN.'_compra_'.$this->documento->codigo.'.pdf');
   }
   
   private function generar_pdf_factura($archivo = FALSE)
   {
      if(!$archivo)
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      /// Creamos el PDF y escribimos sus metadatos
      $pdf_doc = new fs_pdf();
      $pdf_doc->pdf->addInfo('Title', 'Factura ' . $this->documento->codigo);
      $pdf_doc->pdf->addInfo('Subject', 'Factura de proveedor ' . $this->documento->codigo);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $lineas = $this->documento->get_lineas();
      $lineas_iva = $pdf_doc->get_lineas_iva($lineas);
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
            
            $pdf_doc->generar_pdf_cabecera($this->empresa, $lppag);
            $this->generar_pdf_datos_proveedor($pdf_doc);
            $this->generar_pdf_lineas($pdf_doc, $lineas, $linea_actual, $lppag);
            $this->generar_pdf_totales($pdf_doc, $lineas_iva, $pagina);
            $pagina++;
         }
      }
      else
      {
         $pdf_doc->pdf->ezText('¡Factura sin líneas!', 20);
      }
      
      if($archivo)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'enviar') )
         {
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         }
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show('factura_compra_'.$this->documento->codigo.'.pdf');
   }
   
   private function enviar_email()
   {
      if( $this->empresa->can_send_mail() )
      {
         if($this->proveedor)
         {
            if( $_POST['email'] != $this->proveedor->email AND isset($_POST['guardar']) )
            {
               $this->proveedor->email = $_POST['email'];
               $this->proveedor->save();
            }
         }
         
         $filename = 'albaran_'.$this->documento->codigo.'.pdf';
         $this->generar_pdf_albaran($filename);
         $razonsocial = $this->documento->nombre;
         
         if( file_exists('tmp/'.FS_TMP_NAME.'enviar/'.$filename) )
         {
            $mail = $this->empresa->new_mail();
            $mail->FromName = $this->user->get_agente_fullname();
            
            if($_POST['de'] != $mail->From)
            {
               $mail->addReplyTo($_POST['de'], $mail->FromName);
            }
            
            $mail->addAddress($_POST['email'], $razonsocial);
            if($_POST['email_copia'])
            {
               if( isset($_POST['cco']) )
               {
                  $mail->addBCC($_POST['email_copia'], $razonsocial);
               }
               else
               {
                  $mail->addCC($_POST['email_copia'], $razonsocial);
               }
            }
            
            $mail->Subject = $this->empresa->nombre . ': Mi '.FS_ALBARAN.' '.$this->documento->codigo;
            if( $this->is_html($_POST['mensaje']) )
            {
               $mail->AltBody = strip_tags($_POST['mensaje']);
               $mail->msgHTML($_POST['mensaje']);
               $mail->isHTML(TRUE);
            }
            else
            {
               $mail->Body = $_POST['mensaje'];
            }
            
            $mail->addAttachment('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
            if( is_uploaded_file($_FILES['adjunto']['tmp_name']) )
            {
               $mail->addAttachment($_FILES['adjunto']['tmp_name'], $_FILES['adjunto']['name']);
            }
            
            if( $this->empresa->mail_connect($mail) )
            {
               if( $mail->send() )
               {
                  $this->new_message('Mensaje enviado correctamente.');
                  $this->empresa->save_mail($mail);
               }
               else
                  $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
            }
            else
               $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
            
            unlink('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
         }
         else
            $this->new_error_msg('Imposible generar el PDF.');
      }
   }
   
   public function is_html($txt)
   {
      if( stripos($txt, '<html') === FALSE )
      {
         return FALSE;
      }
      else
      {
         return TRUE;
      }
   }
}
