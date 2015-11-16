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
require_model('cliente.php');
require_model('cuenta_banco.php');
require_model('cuenta_banco_cliente.php');
require_model('forma_pago.php');

/**
 * Esta clase agrupa los procedimientos de imprimir/enviar albaranes y facturas.
 */
class ventas_imprimir extends fs_controller
{
   public $albaran;
   public $cliente;
   public $factura;
   public $impresion;
   public $impuesto;
   
   private $logo;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'imprimir', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->albaran = FALSE;
      $this->cliente = FALSE;
      $this->factura = FALSE;
      $this->impuesto = new impuesto();
      
      /// obtenemos los datos de configuración de impresión
      $this->impresion = array(
          'print_ref' => '1',
          'print_dto' => '1',
          'print_alb' => '0'
      );
      $fsvar = new fs_var();
      $this->impresion = $fsvar->array_get($this->impresion, FALSE);
      
      $this->logo = FALSE;
      if( file_exists('tmp/'.FS_TMP_NAME.'logo.png') )
      {
         $this->logo = 'tmp/'.FS_TMP_NAME.'logo.png';
      }
      else if( file_exists('tmp/'.FS_TMP_NAME.'logo.jpg') )
      {
         $this->logo = 'tmp/'.FS_TMP_NAME.'logo.jpg';
      }
      
      if( isset($_REQUEST['albaran']) AND isset($_REQUEST['id']) )
      {
         $alb = new albaran_cliente();
         $this->albaran = $alb->get($_REQUEST['id']);
         if($this->albaran)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->albaran->codcliente);
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
         $fac = new factura_cliente();
         $this->factura = $fac->get($_REQUEST['id']);
         if($this->factura)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->factura->codcliente);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('factura', $_REQUEST['tipo']);
         }
         else
            $this->generar_pdf_factura($_REQUEST['tipo']);
      }
      
      $this->share_extensions();
   }
   
   private function share_extensions()
   {
      $extensiones = array(
          array(
              'name' => 'imprimir_albaran',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_albaran',
              'type' => 'pdf',
              'text' => ucfirst(FS_ALBARAN).' simple',
              'params' => '&albaran=TRUE'
          ),
          array(
              'name' => 'email_albaran',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_albaran',
              'type' => 'email',
              'text' => ucfirst(FS_ALBARAN).' simple',
              'params' => '&albaran=TRUE'
          ),
          array(
              'name' => 'imprimir_factura',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_factura',
              'type' => 'pdf',
              'text' => ucfirst(FS_FACTURA).' simple',
              'params' => '&factura=TRUE&tipo=simple'
          ),
          array(
              'name' => 'imprimir_factura_firma',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_factura',
              'type' => 'pdf',
              'text' => ucfirst(FS_FACTURA).' con firma',
              'params' => '&factura=TRUE&tipo=firma'
          ),
          array(
              'name' => 'imprimir_factura_carta',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_factura',
              'type' => 'pdf',
              'text' => 'Modelo carta',
              'params' => '&factura=TRUE&tipo=carta'
          ),
          array(
              'name' => 'email_factura',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_factura',
              'type' => 'email',
              'text' => ucfirst(FS_FACTURA).' simple',
              'params' => '&factura=TRUE&tipo=simple'
          )
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
   
   private function generar_pdf_cabecera(&$pdf_doc, &$lppag)
   {
      /// ¿Añadimos el logo?
      if($this->logo)
      {
         if( function_exists('imagecreatefromstring') )
         {
            $pdf_doc->pdf->ezImage($this->logo, 0, 150, 'none');
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
         {
            $direccion .= ' - ' . $this->empresa->codpostal;
         }
         
         if($this->empresa->ciudad)
         {
            $direccion .= ' - ' . $this->empresa->ciudad;
         }
         
         if($this->empresa->provincia)
         {
            $direccion .= ' (' . $this->empresa->provincia . ')';
         }
         
         if($this->empresa->telefono)
         {
            $direccion .= ' - Teléfono: ' . $this->empresa->telefono;
         }
         
         $pdf_doc->pdf->ezText($this->fix_html($direccion), 9, array('justification' => 'center'));
      }
   }
   
   private function generar_pdf_lineas(&$pdf_doc, &$lineas, &$linea_actual, &$lppag, &$documento)
   {
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
      
      $multi_iva = FALSE;
      $multi_re = FALSE;
      $multi_irpf = FALSE;
      $iva = FALSE;
      $re = FALSE;
      $irpf = FALSE;
      /// leemos las líneas para ver si hay que mostrar los tipos de iva, re o irpf
      foreach($lineas as $lin)
      {
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
      }
      
      /*
       * Creamos la tabla con las lineas del documento
       */
      $pdf_doc->new_table();
      $table_header = array(
          'alb' => '<b>'.ucfirst(FS_ALBARAN).'</b>',
          'descripcion' => '<b>Ref. + Descripción</b>',
          'cantidad' => '<b>Cant.</b>',
          'pvp' => '<b>PVP</b>',
      );
      
      /// ¿Desactivamos la columna de albaran?
      if( get_class($documento) == 'factura_cliente' )
      {
         if($this->impresion['print_alb'])
         {
            /// aunque esté activada, si la factura no viene de un albaran, la desactivamos
            $this->impresion['print_alb'] = FALSE;
            foreach($lineas as $lin)
            {
               if($lin->idalbaran)
               {
                  $this->impresion['print_alb'] = TRUE;
                  break;
               }
            }
         }
         
         if( !$this->impresion['print_alb'] )
         {
            unset($table_header['alb']);
         }
      }
      else
      {
         unset($table_header['alb']);
      }
      
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
         $descripcion = $this->fix_html($lineas[$linea_actual]->descripcion);
         if( !is_null($lineas[$linea_actual]->referencia) )
         {
            $descripcion = '<b>'.$lineas[$linea_actual]->referencia.'</b> '.$descripcion;
         }
         
         $fila = array(
             'alb' => '-',
             'cantidad' => $lineas[$linea_actual]->cantidad,
             'descripcion' => $descripcion,
             'pvp' => $this->show_precio($lineas[$linea_actual]->pvpunitario, $documento->coddivisa),
             'dto' => $this->show_numero($lineas[$linea_actual]->dtopor) . " %",
             'iva' => $this->show_numero($lineas[$linea_actual]->iva) . " %",
             're' => $this->show_numero($lineas[$linea_actual]->recargo) . " %",
             'irpf' => $this->show_numero($lineas[$linea_actual]->irpf) . " %",
             'importe' => $this->show_precio($lineas[$linea_actual]->pvptotal, $documento->coddivisa)
         );
         
         if( get_class($lineas[$linea_actual]) == 'linea_factura_cliente' )
         {
            $fila['alb'] = $lineas[$linea_actual]->albaran_numero();
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
                  'shaded' => 0
              )
      );
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
      $pdf_doc->pdf->addInfo('Subject', FS_ALBARAN.' de cliente ' . $this->albaran->codigo);
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
            
            $this->generar_pdf_cabecera($pdf_doc, $lppag);
            
            /*
             * Esta es la tabla con los datos del cliente:
             * Albarán:             Fecha:
             * Cliente:             CIF/NIF:
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
                   'campo1' => "<b>Cliente:</b>",
                   'dato1' => $this->fix_html($this->albaran->nombrecliente),
                   'campo2' => "<b>".FS_CIFNIF.":</b>",
                   'dato2' => $this->albaran->cifnif
               )
            );
            $pdf_doc->add_table_row(
               array(
                   'campo1' => "<b>Dirección:</b>",
                   'dato1' => $this->fix_html($this->albaran->direccion.' CP: '.$this->albaran->codpostal.
                           ' - '.$this->albaran->ciudad.' ('.$this->albaran->provincia.')'),
                   'campo2' => "<b>Teléfonos:</b>",
                   'dato2' => $this->cliente->telefono1.'  '.$this->cliente->telefono2
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
            
            $this->generar_pdf_lineas($pdf_doc, $lineas, $linea_actual, $lppag, $this->albaran);
            
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
               $fila['irpf'] = $this->show_precio($this->albaran->totalirpf);
               $opciones['cols']['irpf'] = array('justification' => 'right');
            }
            
            $titulo['liquido'] = '<b>Total</b>';
            $fila['liquido'] = $this->show_precio($this->albaran->total, $this->albaran->coddivisa);
            $opciones['cols']['liquido'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
            
            $pdf_doc->pdf->addText(10, 10, 8, $pdf_doc->center_text($this->fix_html($this->empresa->pie_factura), 153), 0, 1.5);
            
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
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show(FS_ALBARAN.'_'.$this->albaran->codigo.'.pdf');
   }
   
   private function generar_pdf_factura($tipo='simple', $archivo=FALSE)
   {
      if(!$archivo)
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      /// Creamos el PDF y escribimos sus metadatos
      $pdf_doc = new fs_pdf();
      $pdf_doc->pdf->addInfo('Title', ucfirst(FS_FACTURA).' '.$this->factura->codigo);
      $pdf_doc->pdf->addInfo('Subject', ucfirst(FS_FACTURA).' '.$this->factura->codigo);
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
            
            /*
             * Creamos la cabecera de la página, en este caso para el modelo carta
             */
            if($tipo == 'carta')
            {
               $direccion = $this->factura->nombrecliente."\n".$this->factura->direccion;
               if($this->factura->codpostal AND $this->factura->ciudad)
                  $direccion .= "\n CP: " . $this->factura->codpostal . ' ' . $this->factura->ciudad;
               else if($this->factura->ciudad)
                  $direccion .= "\n" . $this->factura->ciudad;
               
               if($this->factura->provincia)
                  $direccion .= "\n(" . $this->factura->provincia . ")";
               
               $pdf_doc->pdf->ezText("\n\n", 10);
               $pdf_doc->new_table();
               $pdf_doc->add_table_row(
                  array(
                      'campos' => "<b>".ucfirst(FS_FACTURA).":</b>\n<b>Fecha:</b>\n<b>".FS_CIFNIF.":</b>",
                      'factura' => $this->factura->codigo."\n".$this->factura->fecha."\n".$this->factura->cifnif,
                      'cliente' => $this->fix_html($direccion)
                  )
               );
               $pdf_doc->save_table(
                  array(
                      'cols' => array(
                          'campos' => array('justification' => 'right', 'width' => 100),
                          'factura' => array('justification' => 'left'),
                          'cliente' => array('justification' => 'right')
                      ),
                      'showLines' => 0,
                      'width' => 520
                  )
               );
               $pdf_doc->pdf->ezText("\n\n\n", 14);
            }
            else /// esta es la cabecera de la página para los modelos 'simple' y 'firma'
            {
               $this->generar_pdf_cabecera($pdf_doc, $lppag);
               
               /*
                * Esta es la tabla con los datos del cliente:
                * Factura:             Fecha:
                * Cliente:             CIF/NIF:
                * Dirección:           Teléfonos:
                */
               $pdf_doc->new_table();
               
               if($this->factura->idfacturarect)
               {
                  $pdf_doc->add_table_row(
                     array(
                        'campo1' => "<b>".ucfirst(FS_FACTURA_RECTIFICATIVA).":</b>",
                        'dato1' => $this->factura->codigo,
                        'campo2' => "<b>Fecha:</b>",
                        'dato2' => $this->factura->fecha
                     )
                  );
                  $pdf_doc->add_table_row(
                     array(
                        'campo1' => "<b>Original:</b>",
                        'dato1' => $this->factura->codigorect,
                        'campo2' => '',
                        'dato2' => ''
                     )
                  );
               }
               else
               {
                  $pdf_doc->add_table_row(
                     array(
                        'campo1' => "<b>".ucfirst(FS_FACTURA).":</b>",
                        'dato1' => $this->factura->codigo,
                        'campo2' => "<b>Fecha:</b>",
                        'dato2' => $this->factura->fecha
                     )
                  );
               }
               
               $pdf_doc->add_table_row(
                  array(
                     'campo1' => "<b>Cliente:</b>",
                     'dato1' => $this->fix_html($this->factura->nombrecliente),
                     'campo2' => "<b>".FS_CIFNIF.":</b>",
                     'dato2' => $this->factura->cifnif
                  )
               );
               $pdf_doc->add_table_row(
                  array(
                     'campo1' => "<b>Dirección:</b>",
                     'dato1' => $this->factura->direccion.' CP: '.$this->factura->codpostal.' - '.$this->factura->ciudad.
                                 ' ('.$this->factura->provincia.')',
                     'campo2' => "<b>Teléfonos:</b>",
                     'dato2' => $this->cliente->telefono1.'  '.$this->cliente->telefono2
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
               
               /// en el tipo 'firma' caben menos líneas
               if($tipo == 'firma')
               {
                  $lppag -= 3;
               }
            }
            
            $this->generar_pdf_lineas($pdf_doc, $lineas, $linea_actual, $lppag, $this->factura);
            
            if( $linea_actual == count($lineas) )
            {
               /*
                * Añadimos la parte de la firma y las observaciones,
                * para el tipo 'firma'
                */
               if($tipo == 'firma')
               {
                  $pdf_doc->pdf->ezText("\n", 9);
                  
                  $pdf_doc->new_table();
                  $pdf_doc->add_table_header(
                     array(
                        'campo1' => "<b>Observaciones</b>",
                        'campo2' => "<b>Firma</b>"
                     )
                  );
                  $pdf_doc->add_table_row(
                     array(
                        'campo1' => $this->fix_html($this->factura->observaciones),
                        'campo2' => ""
                     )
                  );
                  $pdf_doc->save_table(
                     array(
                        'cols' => array(
                           'campo1' => array('justification' => 'left'),
                           'campo2' => array('justification' => 'right', 'width' => 100)
                        ),
                        'showLines' => 4,
                        'width' => 530,
                        'shaded' => 0
                     )
                  );
               }
               else if($this->factura->observaciones != '')
               {
                  $pdf_doc->pdf->ezText("\n".$this->fix_html($this->factura->observaciones), 9);
               }
               
               if(!$this->factura->pagada)
               {
                  $fp0 = new forma_pago();
                  $forma_pago = $fp0->get($this->factura->codpago);
                  if($forma_pago)
                  {
                     $texto_pago = "\n<b>Forma de pago</b>: ".$forma_pago->descripcion;
                     
                     if($forma_pago->domiciliado)
                     {
                        $cbc0 = new cuenta_banco_cliente();
                        $encontrada = FALSE;
                        foreach($cbc0->all_from_cliente($this->factura->codcliente) as $cbc)
                        {
                           if($cbc->iban)
                           {
                              $texto_pago .= "\n<b>Domiciliado en</b>: ".$cbc->iban;
                           }
                           else
                           {
                              $texto_pago .= "\n<b>Domiciliado en</b>: ".$cbc->swift;
                           }
                           $encontrada = TRUE;
                           break;
                        }
                        if(!$encontrada)
                        {
                           $texto_pago .= "\n<b>El cliente no tiene cuenta bancaria asignada.</b>";
                        }
                     }
                     else if($forma_pago->codcuenta)
                     {
                        $cb0 = new cuenta_banco();
                        $cuenta_banco = $cb0->get($forma_pago->codcuenta);
                        if($cuenta_banco)
                        {
                           if($cuenta_banco->iban)
                           {
                              $texto_pago .= "\n<b>IBAN</b>: ".$cuenta_banco->iban;
                           }
                           else
                           {
                              $texto_pago .= "\n<b>SWIFT o BIC</b>: ".$cuenta_banco->swift;
                           }
                        }
                     }
                     
                     $texto_pago .= "\n<b>Vencimiento</b>: ".$this->factura->vencimiento;
                     $pdf_doc->pdf->ezText($texto_pago, 9);
                  }
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
               $fila['irpf'] = $this->show_precio($this->factura->totalirpf);
               $opciones['cols']['irpf'] = array('justification' => 'right');
            }
            
            $titulo['liquido'] = '<b>Total</b>';
            $fila['liquido'] = $this->show_precio($this->factura->total, $this->factura->coddivisa);
            $opciones['cols']['liquido'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
            
            /// pié de página para la factura
            $pdf_doc->pdf->addText(10, 10, 8, $pdf_doc->center_text($this->fix_html($this->empresa->pie_factura), 153), 0, 1.5);
            
            $pagina++;
         }
      }
      else
      {
         $pdf_doc->pdf->ezText('¡'.ucfirst(FS_FACTURA).' sin líneas!', 20);
      }
      
      if($archivo)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'enviar') )
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show(FS_FACTURA.'_'.$this->factura->codigo.'.pdf');
   }
   
   private function enviar_email($doc, $tipo='simple')
   {
      if( $this->empresa->can_send_mail() )
      {
         if( $_POST['email'] != $this->cliente->email )
         {
            $this->cliente->email = $_POST['email'];
            $this->cliente->save();
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
         
         if($doc == 'factura')
         {
            $filename = 'factura_'.$this->factura->codigo.'.pdf';
            $this->generar_pdf_factura($tipo, $filename);
         }
         else
         {
            $filename = 'albaran_'.$this->albaran->codigo.'.pdf';
            $this->generar_pdf_albaran($filename);
         }
         
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
            
            if($doc == 'factura')
            {
               $mail->Subject = $this->empresa->nombre . ': Su factura '.$this->factura->codigo;
               $mail->AltBody = 'Buenos días, le adjunto su factura '.$this->factura->codigo.".\n".$this->empresa->email_firma;
            }
            else
            {
               $mail->Subject = $this->empresa->nombre . ': Su '.FS_ALBARAN.' '.$this->albaran->codigo;
               $mail->AltBody = 'Buenos días, le adjunto su '.FS_ALBARAN.' '.$this->albaran->codigo.".\n".$this->empresa->email_firma;
            }
            
            $mail->WordWrap = 50;
            $mail->MsgHTML( nl2br($_POST['mensaje']) );
            $mail->AddAttachment('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
            $mail->AddAddress($_POST['email'], $this->cliente->razonsocial);
            if( isset($_POST['concopia']) )
            {
               $mail->AddCC($_POST['email_copia'], $this->cliente->razonsocial);
            }
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
            $lineasiva[$lin->codimpuesto]['totallinea'] = $lineasiva[$lin->codimpuesto]['neto']
                    + $lineasiva[$lin->codimpuesto]['totaliva'] + $lineasiva[$lin->codimpuesto]['totalrecargo'];
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
            $lineasiva[$lin->codimpuesto]['totallinea'] = $lineasiva[$lin->codimpuesto]['neto']
                    + $lineasiva[$lin->codimpuesto]['totaliva'] + $lineasiva[$lin->codimpuesto]['totalrecargo'];
         }
      }
      
      foreach($lineasiva as $lin)
      {
         $retorno[] = $lin;
      }
      
      return $retorno;
   }
}
