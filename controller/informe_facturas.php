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

require_once 'plugins/facturacion_base/extras/fs_pdf.php';
require_model('cliente.php');
require_model('factura_cliente.php');
require_model('factura_proveedor.php');
require_model('forma_pago.php');
require_model('pais.php');
require_model('proveedor.php');
require_model('serie.php');

class informe_facturas extends fs_controller
{
   public $agente;
   public $cliente;
   public $desde;
   public $factura_cli;
   public $factura_pro;
   public $hasta;
   public $mostrar;
   public $pagada;
   public $pais;
   public $proveedor;
   public $serie;
   public $stats;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Facturas', 'informes', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->agente = new agente();
      $this->desde = Date('1-m-Y');
      $this->factura_cli = new factura_cliente();
      $this->factura_pro = new factura_proveedor();
      $this->hasta = Date('t-m-Y');
      $this->pais = new pais();
      $this->serie = new serie();
      $this->stats = array();
      
      $this->mostrar = 'general';
      if( isset($_GET['mostrar']) )
      {
         $this->mostrar = $_GET['mostrar'];
      }
      
      if( isset($_REQUEST['buscar_cliente']) )
      {
         $this->buscar_cliente();
      }
      else if( isset($_REQUEST['buscar_proveedor']) )
      {
         $this->buscar_proveedor();
      }
      else if( isset($_POST['listado']) )
      {
         if($_POST['listado'] == 'facturascli')
         {
            if($_POST['generar'] == 'pdf')
            {
               $this->pdf_facturas_cli();
            }
            else
               $this->csv_facturas_cli();
         }
         else
         {
            if($_POST['generar'] == 'pdf')
            {
               $this->pdf_facturas_prov();
            }
            else
               $this->csv_facturas_prov();
         }
      }
      else if( isset($_POST['informe']) )
      {
         if($_POST['informe'] == 'facturascli')
         {
            if($_POST['unidades'] == 'TRUE')
            {
               $this->informe_ventas_unidades();
            }
            else
            {
               $this->informe_ventas();
            }
         }
         else
         {
            if($_POST['unidades'] == 'TRUE')
            {
               $this->informe_compras_unidades();
            }
            else
            {
               $this->informe_compras();
            }
         }
      }
      else if($this->mostrar == 'general')
      {
         $this->albaranes_pendientes();
      }
   }
   
   private function buscar_cliente()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $cliente = new cliente();
      $json = array();
      foreach($cliente->search($_REQUEST['buscar_cliente']) as $cli)
      {
         $json[] = array('value' => $cli->nombre, 'data' => $cli->codcliente);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_cliente'], 'suggestions' => $json) );
   }
   
    private function buscar_proveedor()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $proveedor = new proveedor();
      $json = array();
      foreach($proveedor->search($_REQUEST['buscar_proveedor']) as $pro)
      {
         $json[] = array('value' => $pro->nombre, 'data' => $pro->codproveedor);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_proveedor'], 'suggestions' => $json) );
   }
   
   public function provincias()
   {
      $final = array();
      
      $provincias = array();
      $sql = "SELECT DISTINCT provincia FROM dirclientes ORDER BY provincia ASC;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $provincias[] = $d['provincia'];
         }
      }
      
      foreach($provincias as $pro)
      {
         if($pro != '')
         {
            $final[ mb_strtolower($pro, 'UTF8') ] = $pro;
         }
      }
      
      return $final;
   }
   
   private function csv_facturas_cli()
   {
      $this->template = FALSE;
      
      header("content-type:application/csv;charset=UTF-8");
      header("Content-Disposition: attachment; filename=\"facturas_cli.csv\"");
      echo "serie,factura,asiento,fecha,subcuenta,descripcion,cifnif,base,iva,totaliva,totalrecargo,totalirpf,total\n";
      
      $codserie = FALSE;
      if($_POST['codserie'] != '')
      {
         $codserie = $_POST['codserie'];
      }
      
      $codagente = FALSE;     
      if($_POST['codagente'] != '')
      {
         $codagente = $_POST['codagente'];
      }
      
      $codcliente = FALSE;     
      if($_POST['codcliente'] != '')
      {
         $codcliente = $_POST['codcliente'];
      }
      
      $estado = FALSE;
      if($_POST['estado'] != '')
      {
         $estado = $_POST['estado'];
      }
      
      $facturas = $this->factura_cli->all_desde($_POST['desde'], $_POST['hasta'], $codserie, $codagente, $codcliente, $estado);
      if($facturas)
      {
         foreach($facturas as $fac)
         {
            $linea = array(
                'serie' => $fac->codserie,
                'factura' => $fac->numero,
                'asiento' => '-',
                'fecha' => $fac->fecha,
                'subcuenta' => '-',
                'descripcion' => $fac->nombrecliente,
                'cifnif' => $fac->cifnif,
                'base' => 0,
                'iva' => 0,
                'totaliva' => 0,
                'totalrecargo' => 0,
                'totalirpf' => 0,
                'total' => 0
            );
            
            $asiento = $fac->get_asiento();
            if($asiento)
            {
               $linea['asiento'] = $asiento->numero;
               $partidas = $asiento->get_partidas();
               if($partidas)
               {
                  $linea['subcuenta'] = $partidas[0]->codsubcuenta;
               }
            }
            
            if($fac->totalirpf != 0)
            {
               $linea['totalirpf'] = $fac->totalirpf;
               $linea['total'] = $fac->total;
               echo '"'.join('","', $linea)."\"\n";
               $linea['totalirpf'] = 0;
            }
            
            $linivas = $fac->get_lineas_iva();
            if($linivas)
            {
               foreach($linivas as $liva)
               {
                  /// acumulamos la base
                  if( !isset($impuestos[$liva->iva]['base']) )
                  {
                     $impuestos[$liva->iva]['base'] = $liva->neto;
                  }
                  else
                     $impuestos[$liva->iva]['base'] += $liva->neto;
                     
                  /// acumulamos el iva
                  if( !isset($impuestos[$liva->iva]['iva']) )
                  {
                     $impuestos[$liva->iva]['iva'] = $liva->totaliva;
                  }
                  else
                     $impuestos[$liva->iva]['iva'] += $liva->totaliva;
                     
                  /// completamos y añadimos la línea al CSV
                  $linea['base'] = $liva->neto;
                  $linea['iva'] = $liva->iva;
                  $linea['totaliva'] = $liva->totaliva;
                  $linea['totalrecargo'] = $liva->totalrecargo;
                  $linea['total'] = $liva->totallinea;
                  echo '"'.join('","', $linea)."\"\n";
               }
            }
         }
      }
   }
   
   private function csv_facturas_prov()
   {
      $this->template = FALSE;
      
      header("content-type:application/csv;charset=UTF-8");
      header("Content-Disposition: attachment; filename=\"facturas_prov.csv\"");
      echo "serie,factura,asiento,fecha,subcuenta,descripcion,cifnif,base,iva,totaliva,totalrecargo,totalirpf,total\n";
      
      $codserie = FALSE;
      if($_POST['codserie'] != '')
      {
         $codserie = $_POST['codserie'];
      }
      
      $codagente = FALSE;     
      if($_POST['codagente'] != '')
      {
         $codagente = $_POST['codagente'];
      }
      
      $codproveedor = FALSE;     
      if($_POST['codproveedor'] != '')
      {
         $codproveedor = $_POST['codproveedor'];
      }
      
      $estado = FALSE;
      if($_POST['estado'] != '')
      {
         $estado = $_POST['estado'];
      }
      
      $facturas = $this->factura_pro->all_desde($_POST['desde'], $_POST['hasta'], $codserie, $codagente, $codproveedor, $estado);
      if($facturas)
      {
         foreach($facturas as $fac)
         {
            $linea = array(
                'serie' => $fac->codserie,
                'factura' => $fac->numero,
                'asiento' => '-',
                'fecha' => $fac->fecha,
                'subcuenta' => '-',
                'descripcion' => $fac->nombre,
                'cifnif' => $fac->cifnif,
                'base' => 0,
                'iva' => 0,
                'totaliva' => 0,
                'totalrecargo' => 0,
                'totalirpf' => 0,
                'total' => 0
            );
            
            $asiento = $fac->get_asiento();
            if($asiento)
            {
               $linea['asiento'] = $asiento->numero;
               $partidas = $asiento->get_partidas();
               if($partidas)
               {
                  $linea['subcuenta'] = $partidas[0]->codsubcuenta;
               }
            }
            
            if($fac->totalirpf != 0)
            {
               $linea['totalirpf'] = $fac->totalirpf;
               $linea['total'] = $fac->total;
               echo '"'.join('","', $linea)."\"\n";
               $linea['totalirpf'] = 0;
            }
            
            $linivas = $fac->get_lineas_iva();
            if($linivas)
            {
               foreach($linivas as $liva)
               {
                  /// acumulamos la base
                  if( !isset($impuestos[$liva->iva]['base']) )
                  {
                     $impuestos[$liva->iva]['base'] = $liva->neto;
                  }
                  else
                     $impuestos[$liva->iva]['base'] += $liva->neto;
                  
                  /// acumulamos el iva
                  if( !isset($impuestos[$liva->iva]['iva']) )
                  {
                     $impuestos[$liva->iva]['iva'] = $liva->totaliva;
                  }
                  else
                     $impuestos[$liva->iva]['iva'] += $liva->totaliva;
                  
                  /// completamos y añadimos la línea al CSV
                  $linea['base'] = $liva->neto;
                  $linea['iva'] = $liva->iva;
                  $linea['totaliva'] = $liva->totaliva;
                  $linea['totalrecargo'] = $liva->totalrecargo;
                  $linea['total'] = $liva->totallinea;
                  echo '"'.join('","', $linea)."\"\n";
               }
            }
         }
      }
   }
   
   private function pdf_facturas_cli()
   {
      /// desactivamos el motor de plantillas
      $this->template = FALSE;
      
      $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
      $pdf_doc->pdf->addInfo('Title', 'Facturas emitidas del '.$_POST['desde'].' al '.$_POST['hasta'] );
      $pdf_doc->pdf->addInfo('Subject', 'Facturas emitidas del '.$_POST['desde'].' al '.$_POST['hasta'] );
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $codserie = FALSE;
      if($_POST['codserie'] != '')
      {
         $codserie = $_POST['codserie'];
      }
      
      $codagente = FALSE;     
      if($_POST['codagente'] != '')
      {
         $codagente = $_POST['codagente'];
      }
      
      $codcliente = FALSE;     
      if($_POST['codcliente'] != '')
      {
         $codcliente = $_POST['codcliente'];
      }
      
      $estado = FALSE;
      if($_POST['estado'] != '')
      {
         $estado = $_POST['estado'];
      }
      
      $facturas = $this->factura_cli->all_desde($_POST['desde'], $_POST['hasta'], $codserie, $codagente, $codcliente, $estado);
      if($facturas)
      {
         $total_lineas = count($facturas);
         $linea_actual = 0;
         $lppag = 31;
         $total = $totalrecargo = $totalirpf = 0;
         $impuestos = array();
         $pagina = 1;
         
         while($linea_actual < $total_lineas)
         {
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
               $pagina++;
            }
            
            /// encabezado
            $pdf_doc->pdf->ezText($this->empresa->nombre." - Facturas de ventas del ".$_POST['desde']." al ".$_POST['hasta']);

            if($codserie)
            {
               $pdf_doc->pdf->ezText("Serie: ".$codserie);
            }
            
            if($codagente)
            {
               $agente = '';
               $agente = new agente();
               $agente = $agente->get($codagente);
               $nombre_agente = $agente->nombre;
               $pdf_doc->pdf->ezText("Agente: ". $nombre_agente);
            }

            if($codcliente)
            {
               $nombre = '';
               $cliente = new cliente();
               $cliente = $cliente->get($codcliente);
               $nombre = $cliente->nombre;
               $pdf_doc->pdf->ezText("Cliente: ".$nombre);
            }
            
            if($estado)
            {
               if($estado == 'pagada')
               {
                  $pdf_doc->pdf->ezText("Estado: Pagadas");
               }
               else
               {
                  $pdf_doc->pdf->ezText("Estado: Sin Pagar");
               }
            }
            
            $pdf_doc->pdf->ezText("\n", 14);
            
            /// tabla principal
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                   'serie' => '<b>S</b>',
                   'factura' => '<b>Fact.</b>',
                   'asiento' => '<b>Asi.</b>',
                   'fecha' => '<b>Fecha</b>',
                   'subcuenta' => '<b>Subcuenta</b>',
                   'descripcion' => '<b>Descripción</b>',
                   'cifnif' => '<b>'.FS_CIFNIF.'</b>',
                   'base' => '<b>Base Im.</b>',
                   'iva' => '<b>% IVA</b>',
                   'totaliva' => '<b>IVA</b>',
                   'totalrecargo' => '<b>RE</b>',
                   'totalirpf' => '<b>IRPF</b>',
                   'total' => '<b>Total</b>'
               )
            );
            for($i = 0; $i < $lppag AND $linea_actual < $total_lineas; $i++)
            {
               $linea = array(
                   'serie' => $facturas[$linea_actual]->codserie,
                   'factura' => $facturas[$linea_actual]->codigo,
                   'asiento' => '-',
                   'fecha' => $facturas[$linea_actual]->fecha,
                   'subcuenta' => '-',
                   'descripcion' => $facturas[$linea_actual]->nombrecliente,
                   'cifnif' => $facturas[$linea_actual]->cifnif,
                   'base' => 0,
                   'iva' => 0,
                   'totaliva' => 0,
                   'totalrecargo' => 0,
                   'totalirpf' => '-',
                   'total' => 0
               );
               $asiento = $facturas[$linea_actual]->get_asiento();
               if($asiento)
               {
                  $linea['asiento'] = $asiento->numero;
                  $partidas = $asiento->get_partidas();
                  if($partidas)
                  {
                     $linea['subcuenta'] = $partidas[0]->codsubcuenta;
                  }
               }
               
               if($facturas[$linea_actual]->totalirpf != 0)
               {
                  $linea['totalirpf'] = $this->show_numero($facturas[$linea_actual]->totalirpf);
                  $linea['total'] = $this->show_numero($facturas[$linea_actual]->total);
                  /// añade la línea al PDF
                  $pdf_doc->add_table_row($linea);
                  $linea['totalirpf'] = '-';
               }
               
               $linivas = $facturas[$linea_actual]->get_lineas_iva();
               if($linivas)
               {
                  $nueva_linea = FALSE;
                  
                  foreach($linivas as $liva)
                  {
                     /// acumulamos la base
                     if( !isset($impuestos[$liva->iva]['base']) )
                     {
                        $impuestos[$liva->iva]['base'] = $liva->neto;
                     }
                     else
                        $impuestos[$liva->iva]['base'] += $liva->neto;
                     
                     /// acumulamos el iva
                     if( !isset($impuestos[$liva->iva]['iva']) )
                     {
                        $impuestos[$liva->iva]['iva'] = $liva->totaliva;
                     }
                     else
                        $impuestos[$liva->iva]['iva'] += $liva->totaliva;
                     
                     /// completamos y añadimos la línea al PDF
                     $linea['base'] = $this->show_numero($liva->neto);
                     $linea['iva'] = $this->show_numero($liva->iva);
                     $linea['totaliva'] = $this->show_numero($liva->totaliva);
                     $linea['totalrecargo'] = $this->show_numero($liva->totalrecargo);
                     $linea['total'] = $this->show_numero($liva->totallinea);
                     $pdf_doc->add_table_row($linea);
                     
                     if($nueva_linea)
                     {
                        $i++;
                     }
                     else
                        $nueva_linea = TRUE;
                  }
               }
               
               $totalrecargo += $facturas[$linea_actual]->totalrecargo;
               $totalirpf += $facturas[$linea_actual]->totalirpf;
               $total += $facturas[$linea_actual]->total;
               $linea_actual++;
            }
            $pdf_doc->save_table(
               array(
                   'fontSize' => 8,
                   'cols' => array(
                       'base' => array('justification' => 'right'),
                       'iva' => array('justification' => 'right'),
                       'totaliva' => array('justification' => 'right'),
                       'totalrecargo' => array('justification' => 'right'),
                       'totalirpf' => array('justification' => 'right'),
                       'total' => array('justification' => 'right')
                   ),
                   'shaded' => 0,
                   'width' => 780
               )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            
            /// Rellenamos la última tabla
            $pdf_doc->new_table();
            $titulo = array('pagina' => '<b>Suma y sigue</b>');
            $fila = array('pagina' => $pagina . '/' . ceil($total_lineas / $lppag));
            $opciones = array(
                'cols' => array('base' => array('justification' => 'right')),
                'showLines' => 0,
                'width' => 780
            );
            foreach($impuestos as $i => $value)
            {
               $titulo['base'.$i] = '<b>Base '.$i.'%</b>';
               $fila['base'.$i] = $this->show_precio($value['base']);
               $opciones['cols']['base'.$i] = array('justification' => 'right');
               if($i != 0)
               {
                  $titulo['iva'.$i] = '<b>IVA '.$i.'%</b>';
                  $fila['iva'.$i] = $this->show_precio($value['iva']);
                  $opciones['cols']['iva'.$i] = array('justification' => 'right');
               }
            }
            $titulo['totalrecargo'] = '<b>RE</b>';
            $titulo['totalirpf'] = '<b>IRPF</b>';
            $titulo['total'] = '<b>Total</b>';
            $fila['totalrecargo'] = $this->show_precio($totalrecargo);
            $fila['totalirpf'] = $this->show_precio($totalirpf);
            $fila['total'] = $this->show_precio($total);
            $opciones['cols']['totalrecargo'] = array('justification' => 'right');
            $opciones['cols']['totalirpf'] = array('justification' => 'right');
            $opciones['cols']['total'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
         }
      }
      else
      {
         $pdf_doc->pdf->ezText($this->empresa->nombre." - Facturas de ventas del ".$_POST['desde']." al ".$_POST['hasta'].":\n\n", 14);
         $pdf_doc->pdf->ezText("Ninguna.\n\n", 14);
      }
      
      $pdf_doc->show();
   }
   
   private function pdf_facturas_prov()
   {
      /// desactivamos el motor de plantillas
      $this->template = FALSE;
      
      $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
      $pdf_doc->pdf->addInfo('Title', 'Facturas recibidas del '.$_POST['desde'].' al '.$_POST['hasta'] );
      $pdf_doc->pdf->addInfo('Subject', 'Facturas recibidas del '.$_POST['desde'].' al '.$_POST['hasta'] );
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $codserie = FALSE;
      if($_POST['codserie'] != '')
      {
         $codserie = $_POST['codserie'];
      }
      
      $codagente = FALSE;     
      if($_POST['codagente'] != '')
      {
         $codagente = $_POST['codagente'];
      }
      
      $codproveedor = FALSE;     
      if($_POST['codproveedor'] != '')
      {
         $codproveedor = $_POST['codproveedor'];
      }
      
      $estado = FALSE;
      if($_POST['estado'] != '')
      {
         $estado = $_POST['estado'];
      }
      
      $facturas = $this->factura_pro->all_desde($_POST['desde'], $_POST['hasta'], $codserie, $codagente, $codproveedor, $estado);
      if($facturas)
      {
         $total_lineas = count($facturas);
         $linea_actual = 0;
         $lppag = 31;
         $total = $totalrecargo = $totalirpf = 0;
         $impuestos = array();
         $pagina = 1;
         
         while($linea_actual < $total_lineas)
         {
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
               $pagina++;
            }
            
            /// encabezado
            $pdf_doc->pdf->ezText($this->empresa->nombre." - Facturas de compras del ".$_POST['desde']." al ".$_POST['hasta']);
            
            if($codserie)
            {
               $pdf_doc->pdf->ezText("Serie: ".$codserie);
            }
            
            if($codagente)
            {
               $agente = '';
               $agente = new agente();
               $agente = $agente->get($codagente);
               $nombre_agente = $agente->nombre;
               $pdf_doc->pdf->ezText("Agente: ". $nombre_agente);
            }
            
            if($codproveedor)
            {
               $nombre = '';
               $proveedor = new proveedor();
               $proveedor = $proveedor->get($codproveedor);
               $proveedor = $proveedor->nombre;
               $pdf_doc->pdf->ezText("Proveedor: ".$proveedor);
            }
            
            if($estado)
            {
               if($estado == 'pagada')
               {
                  $pdf_doc->pdf->ezText("Estado: Pagadas");
               }
               else
               {
                  $pdf_doc->pdf->ezText("Estado: Sin Pagar");
               }
            }
            
            $pdf_doc->pdf->ezText("\n", 14);
            
            /// tabla principal
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                   'serie' => '<b>S</b>',
                   'factura' => '<b>Fact.</b>',
                   'asiento' => '<b>Asi.</b>',
                   'fecha' => '<b>Fecha</b>',
                   'subcuenta' => '<b>Subcuenta</b>',
                   'descripcion' => '<b>Descripción</b>',
                   'cifnif' => '<b>'.FS_CIFNIF.'</b>',
                   'base' => '<b>Base Im.</b>',
                   'iva' => '<b>% IVA</b>',
                   'totaliva' => '<b>IVA</b>',
                   'totalrecargo' => '<b>RE</b>',
                   'totalirpf' => '<b>IRPF</b>',
                   'total' => '<b>Total</b>'
               )
            );
            for($i = 0; $i < $lppag AND $linea_actual < $total_lineas; $i++)
            {
               $linea = array(
                   'serie' => $facturas[$linea_actual]->codserie,
                   'factura' => $facturas[$linea_actual]->codigo,
                   'asiento' => '-',
                   'fecha' => $facturas[$linea_actual]->fecha,
                   'subcuenta' => '-',
                   'descripcion' => $facturas[$linea_actual]->nombre,
                   'cifnif' => $facturas[$linea_actual]->cifnif,
                   'base' => 0,
                   'iva' => 0,
                   'totaliva' => 0,
                   'totalrecargo' => 0,
                   'totalirpf' => '-',
                   'total' => 0
               );
               $asiento = $facturas[$linea_actual]->get_asiento();
               if($asiento)
               {
                  $linea['asiento'] = $asiento->numero;
                  $partidas = $asiento->get_partidas();
                  if($partidas)
                  {
                     $linea['subcuenta'] = $partidas[0]->codsubcuenta;
                  }
               }
               
               if($facturas[$linea_actual]->totalirpf != 0)
               {
                  $linea['totalirpf'] = $this->show_numero($facturas[$linea_actual]->totalirpf);
                  $linea['total'] = $this->show_numero($facturas[$linea_actual]->total);
                  /// añade la línea al PDF
                  $pdf_doc->add_table_row($linea);
                  $linea['totalirpf'] = '-';
               }
               
               $linivas = $facturas[$linea_actual]->get_lineas_iva();
               if($linivas)
               {
                  $nueva_linea = FALSE;
                  
                  foreach($linivas as $liva)
                  {
                     /// acumulamos la base
                     if( !isset($impuestos[$liva->iva]['base']) )
                     {
                        $impuestos[$liva->iva]['base'] = $liva->neto;
                     }
                     else
                        $impuestos[$liva->iva]['base'] += $liva->neto;
                     
                     /// acumulamos el iva
                     if( !isset($impuestos[$liva->iva]['iva']) )
                     {
                        $impuestos[$liva->iva]['iva'] = $liva->totaliva;
                     }
                     else
                        $impuestos[$liva->iva]['iva'] += $liva->totaliva;
                     
                     /// completamos y añadimos la línea al PDF
                     $linea['base'] = $this->show_numero($liva->neto);
                     $linea['iva'] = $this->show_numero($liva->iva);
                     $linea['totaliva'] = $this->show_numero($liva->totaliva);
                     $linea['totalrecargo'] = $this->show_numero($liva->totalrecargo);
                     $linea['total'] = $this->show_numero($liva->totallinea);
                     $pdf_doc->add_table_row($linea);
                     
                     if($nueva_linea)
                     {
                        $i++;
                     }
                     else
                        $nueva_linea = TRUE;
                  }
               }
               
               $totalrecargo += $facturas[$linea_actual]->totalrecargo;
               $totalirpf += $facturas[$linea_actual]->totalirpf;
               $total += $facturas[$linea_actual]->total;
               $linea_actual++;
            }
            $pdf_doc->save_table(
               array(
                   'fontSize' => 8,
                   'cols' => array(
                       'base' => array('justification' => 'right'),
                       'iva' => array('justification' => 'right'),
                       'totaliva' => array('justification' => 'right'),
                       'totalrecargo' => array('justification' => 'right'),
                       'totalirpf' => array('justification' => 'right'),
                       'total' => array('justification' => 'right')
                   ),
                   'shaded' => 0,
                   'width' => 780
               )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            
            /// Rellenamos la última tabla
            $pdf_doc->new_table();
            $titulo = array('pagina' => '<b>Suma y sigue</b>');
            $fila = array('pagina' => $pagina . '/' . ceil($total_lineas / $lppag));
            $opciones = array(
                'cols' => array('base' => array('justification' => 'right')),
                'showLines' => 0,
                'width' => 780
            );
            foreach($impuestos as $i => $value)
            {
               $titulo['base'.$i] = '<b>Base '.$i.'%</b>';
               $fila['base'.$i] = $this->show_precio($value['base']);
               $opciones['cols']['base'.$i] = array('justification' => 'right');
               if($i != 0)
               {
                  $titulo['iva'.$i] = '<b>IVA '.$i.'%</b>';
                  $fila['iva'.$i] = $this->show_precio($value['iva']);
                  $opciones['cols']['iva'.$i] = array('justification' => 'right');
               }
            }
            $titulo['totalrecargo'] = '<b>RE</b>';
            $titulo['totalirpf'] = '<b>IRPF</b>';
            $titulo['total'] = '<b>Total</b>';
            $fila['totalrecargo'] = $this->show_precio($totalrecargo);
            $fila['totalirpf'] = $this->show_precio($totalirpf);
            $fila['total'] = $this->show_precio($total);
            $opciones['cols']['totalrecargo'] = array('justification' => 'right');
            $opciones['cols']['totalirpf'] = array('justification' => 'right');
            $opciones['cols']['total'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
         }
      }
      else
      {
         $pdf_doc->pdf->ezText($this->empresa->nombre." - Facturas de compras del ".$_POST['desde'].' al '.$_POST['hasta'].":\n\n", 14);
         $pdf_doc->pdf->ezText("Ninguna.\n\n", 14);
      }
      
      $pdf_doc->show();
   }
   
   public function stats_last_days()
   {
      $stats = array();
      $stats_cli = $this->stats_last_days_aux('facturascli');
      $stats_pro = $this->stats_last_days_aux('facturasprov');
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'day' => $value['day'],
             'total_cli' => round($value['total'], FS_NF0),
             'total_pro' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
      {
         $stats[$i]['total_pro'] = round($value['total'], FS_NF0);
      }
      
      return $stats;
   }
   
   private function stats_last_days_aux($table_name = 'facturascli', $numdays = 25)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('d-m-Y').'-'.$numdays.' day'));
      
      /// inicializamos los resultados
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 day', 'd') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('day' => $i, 'total' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
      {
         $sql_aux = "to_char(fecha,'FMDD')";
      }
      else
         $sql_aux = "DATE_FORMAT(fecha, '%d')";
      
      /// consultamos para la divisa de la empresa
      $data = $this->db->select("SELECT ".$sql_aux." as dia, sum(neto) as total FROM ".$table_name
              ." WHERE fecha >= ".$this->empresa->var2str($desde)
              ." AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))
              ." AND coddivisa = ".$this->empresa->var2str($this->empresa->coddivisa)
              ." GROUP BY ".$sql_aux." ORDER BY dia ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['dia']);
            $stats[$i]['total'] = floatval($d['total']);
         }
      }
      
      /// ahora consultamos el resto de divisas
      $data = $this->db->select("SELECT ".$sql_aux." as dia, sum(neto/tasaconv) as total FROM ".$table_name
              ." WHERE fecha >= ".$this->empresa->var2str($desde)
              ." AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))
              ." AND coddivisa != ".$this->empresa->var2str($this->empresa->coddivisa)
              ." GROUP BY ".$sql_aux." ORDER BY dia ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['dia']);
            $stats[$i]['total'] += $this->euro_convert( floatval($d['total']) );
         }
      }
      
      return $stats;
   }
   
   public function stats_last_months()
   {
      $stats = array();
      $stats_cli = $this->stats_last_months_aux('facturascli');
      $stats_pro = $this->stats_last_months_aux('facturasprov');
      $stats_impuestos = $this->stats_last_months_impuestos();
      $meses = array(
          1 => 'ene',
          2 => 'feb',
          3 => 'mar',
          4 => 'abr',
          5 => 'may',
          6 => 'jun',
          7 => 'jul',
          8 => 'ago',
          9 => 'sep',
          10 => 'oct',
          11 => 'nov',
          12 => 'dic'
      );
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'month' => $meses[ $value['month'] ],
             'neto_cli' => round($value['neto'], FS_NF0),
             'total_cli' => $value['total'],
             'impuestos_cli' => $value['totaliva'],
             'neto_pro' => 0,
             'total_pro' => 0,
             'impuestos_pro' => 0,
             'beneficios' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
      {
         $stats[$i]['neto_pro'] = round($value['neto'], FS_NF0);
         $stats[$i]['total_pro'] = $value['total'];
         $stats[$i]['impuestos_pro'] = $value['totaliva'];
      }
      
      foreach($stats_impuestos as $i => $value)
      {
         $impuestos = $stats[$i]['impuestos_cli'] - $stats[$i]['impuestos_pro'];
         if($impuestos < 0)
         {
            $impuestos = 0;
         }
         $impuestos += $value['impuestos'];
         
         $stats[$i]['beneficios'] = round($stats[$i]['total_cli'] - $stats[$i]['total_pro'] - $impuestos, FS_NF0);
      }
      
      /// leemos para completar $this->stats
      $num = 0;
      foreach($stats as $st)
      {
         $this->stats['media_ventas'] += $st['neto_cli'];
         $this->stats['media_compras'] += $st['neto_pro'];
         $this->stats['media_beneficios'] += $st['beneficios'];
         $num++;
      }
      
      if($num > 0)
      {
         $this->stats['media_ventas'] = $this->stats['media_ventas'] / $num;
         $this->stats['media_compras'] = $this->stats['media_compras'] / $num;
         $this->stats['media_beneficios'] = $this->stats['media_beneficios'] / $num;
      }
      
      return $stats;
   }
   
   private function stats_last_months_aux($table_name = 'facturascli', $num = 11)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('1-m-Y').'-'.$num.' month'));
      
      /// inicializamos los resultados
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 month', 'm') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('month' => $i, 'neto' => 0, 'total' => 0, 'totaliva' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
      {
         $sql_aux = "to_char(fecha,'FMMM')";
      }
      else
         $sql_aux = "DATE_FORMAT(fecha, '%m')";
      
      /// primero consultamos la divisa de la empresa
      $data = $this->db->select("SELECT ".$sql_aux." as mes, sum(neto) as neto, sum(total) as total, sum(totaliva) as totaliva FROM ".$table_name
              ." WHERE fecha >= ".$this->empresa->var2str($desde)
              ." AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))
              ." AND coddivisa = ".$this->empresa->var2str($this->empresa->coddivisa)
              ." GROUP BY ".$sql_aux." ORDER BY mes ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['mes']);
            $stats[$i]['neto'] = floatval($d['neto']);
            $stats[$i]['total'] = floatval($d['total']);
            $stats[$i]['totaliva'] = floatval($d['totaliva']);
         }
      }
      
      /// ahora consultamos el resto de divisas
      $data = $this->db->select("SELECT ".$sql_aux." as mes, sum(neto/tasaconv) as neto, sum(totaleuros) as total, sum(totaliva/tasaconv) as totaliva FROM ".$table_name
              ." WHERE fecha >= ".$this->empresa->var2str($desde)
              ." AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))
              ." AND coddivisa != ".$this->empresa->var2str($this->empresa->coddivisa)
              ." GROUP BY ".$sql_aux." ORDER BY mes ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['mes']);
            $stats[$i]['neto'] += $this->euro_convert( floatval($d['neto']) );
            $stats[$i]['total'] += $this->euro_convert( floatval($d['total']) );
            $stats[$i]['totaliva'] += $this->euro_convert( floatval($d['totaliva']) );
         }
      }
      
      return $stats;
   }
   
   private function stats_last_months_impuestos($num = 11)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('1-m-Y').'-'.$num.' month'));
      
      /// inicializamos los resultados
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 month', 'm') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('month' => $i, 'impuestos' => 0);
      }
      
      if( $this->db->table_exists('co_partidas') AND $this->empresa->codpais == 'ESP' )
      {
         if( strtolower(FS_DB_TYPE) == 'postgresql')
         {
            $sql_aux = "to_char(fecha,'FMMM')";
         }
         else
            $sql_aux = "DATE_FORMAT(fecha, '%m')";
         
         $sql = "select ".$sql_aux." as mes, sum(p.debe-p.haber) as total from co_asientos a, co_partidas p"
                 . " where p.idasiento = a.idasiento and p.codsubcuenta LIKE '57%' and a.tipodocumento IS NULL"
                 . " and a.fecha >= ".$this->empresa->var2str($desde)
                 . " and a.fecha <= ".$this->empresa->var2str($this->today())
                 . " GROUP BY mes ORDER BY mes ASC;";
         
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $i = intval($d['mes']);
               $saldo = floatval($d['total']);
               
               if($saldo < 0)
               {
                  $stats[$i] = array(
                      'month' => $i,
                      'impuestos' => abs($saldo),
                  );
               }
            }
         }
      }
      
      return $stats;
   }
   
   public function stats_last_years()
   {
      $stats = array();
      $stats_cli = $this->stats_last_years_aux('facturascli');
      $stats_pro = $this->stats_last_years_aux('facturasprov');
      $stats_impuestos = $this->stats_last_years_impuestos();
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'year' => $value['year'],
             'neto_cli' => round($value['neto'], FS_NF0),
             'total_cli' => $value['total'],
             'impuestos_cli' => $value['totaliva'],
             'neto_pro' => 0,
             'total_pro' => 0,
             'impuestos_pro' => 0,
             'beneficios' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
      {
         $stats[$i]['neto_pro'] = round($value['neto'], FS_NF0);
         $stats[$i]['total_pro'] = $value['total'];
         $stats[$i]['impuestos_pro'] = $value['totaliva'];
      }
      
      foreach($stats_impuestos as $i => $value)
      {
         $impuestos = $stats[$i]['impuestos_cli'] - $stats[$i]['impuestos_pro'];
         if($impuestos < 0)
         {
            $impuestos = 0;
         }
         $impuestos += $value['impuestos'];
         
         $stats[$i]['beneficios'] = round($stats[$i]['total_cli'] - $stats[$i]['total_pro'] - $impuestos, FS_NF0);
      }
      
      return $stats;
   }
   
   private function stats_last_years_aux($table_name = 'facturascli', $num = 4)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('d-m-Y').'-'.$num.' year'));
      
      /// inicializamos los resultados
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 year', 'Y') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('year' => $i, 'neto' => 0, 'total' => 0, 'totaliva' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
      {
         $sql_aux = "to_char(fecha,'FMYYYY')";
      }
      else
         $sql_aux = "DATE_FORMAT(fecha, '%Y')";
      
      /// consultamos la divisa de la empresa
      $data = $this->db->select("SELECT ".$sql_aux." as ano, sum(neto) as neto, sum(total) as total, sum(totaliva) as totaliva FROM ".$table_name
              ." WHERE fecha >= ".$this->empresa->var2str($desde)
              ." AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))
              ." AND coddivisa = ".$this->empresa->var2str($this->empresa->coddivisa)
              ." GROUP BY ".$sql_aux." ORDER BY ano ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['ano']);
            $stats[$i]['neto'] = floatval($d['neto']);
            $stats[$i]['total'] = floatval($d['total']);
            $stats[$i]['totaliva'] = floatval($d['totaliva']);
         }
      }
      
      /// ahpra el resto de divisas
      $data = $this->db->select("SELECT ".$sql_aux." as ano, sum(neto/tasaconv) as neto, sum(totaleuros) as total, sum(totaliva/tasaconv) as totaliva FROM ".$table_name
              ." WHERE fecha >= ".$this->empresa->var2str($desde)
              ." AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))
              ." AND coddivisa != ".$this->empresa->var2str($this->empresa->coddivisa)
              ." GROUP BY ".$sql_aux." ORDER BY ano ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['ano']);
            $stats[$i]['neto'] += $this->euro_convert( floatval($d['neto']) );
            $stats[$i]['total'] += $this->euro_convert( floatval($d['total']) );
            $stats[$i]['totaliva'] += $this->euro_convert( floatval($d['totaliva']) );
         }
      }
      
      return $stats;
   }
   
   private function stats_last_years_impuestos($num = 4)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('d-m-Y').'-'.$num.' year'));
      
      /// inicializamos los resultados
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 year', 'Y') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('year' => $i, 'impuestos' => 0);
      }
      
      if( $this->db->table_exists('co_partidas') AND $this->empresa->codpais == 'ESP' )
      {
         if( strtolower(FS_DB_TYPE) == 'postgresql')
         {
            $sql_aux = "to_char(fecha,'FMYYYY')";
         }
         else
            $sql_aux = "DATE_FORMAT(fecha, '%Y')";
         
         $sql = "select ".$sql_aux." as ano, sum(p.debe-p.haber) as total from co_asientos a, co_partidas p"
                 . " where p.idasiento = a.idasiento and p.codsubcuenta LIKE '57%' and a.tipodocumento IS NULL"
                 . " and a.fecha >= ".$this->empresa->var2str($desde)
                 . " and a.fecha <= ".$this->empresa->var2str($this->today())
                 . " GROUP BY ano ORDER BY ano ASC;";
         
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $i = intval($d['ano']);
               $saldo = floatval($d['total']);
               
               if($saldo < 0)
               {
                  $stats[$i] = array(
                      'year' => $i,
                      'impuestos' => abs($saldo),
                  );
               }
            }
         }
      }
      
      return $stats;
   }
   
   private function date_range($first, $last, $step = '+1 day', $format = 'd-m-Y' )
   {
      $dates = array();
      $current = strtotime($first);
      $last = strtotime($last);
      
      while( $current <= $last )
      {
         $dates[] = date($format, $current);
         $current = strtotime($step, $current);
      }
      
      return $dates;
   }
   
   private function albaranes_pendientes()
   {
      $this->stats = array(
          'alb_ptes_compra' => 0,
          'alb_ptes_compra_importe' => 0,
          'alb_ptes_venta' => 0,
          'alb_ptes_venta_importe' => 0,
          'facturas_compra' => 0,
          'facturas_compra_importe' => 0,
          'facturas_venta' => 0,
          'facturas_venta_importe' => 0,
          'total' => 0,
          'media_ventas' => 0,
          'media_compras' => 0,
          'media_beneficios' => 0
      );
      
      $sql = "SELECT COUNT(idalbaran) as num, SUM(totaleuros) as total FROM albaranesprov WHERE ptefactura;";
      $data = $this->db->select($sql);
      if($data)
      {
         $this->stats['alb_ptes_compra'] = intval($data[0]['num']);
         $this->stats['alb_ptes_compra_importe'] = $this->euro_convert( floatval($data[0]['total']) );
      }
      
      $sql = "SELECT COUNT(idalbaran) as num, SUM(totaleuros) as total FROM albaranescli WHERE ptefactura;";
      $data = $this->db->select($sql);
      if($data)
      {
         $this->stats['alb_ptes_venta'] = intval($data[0]['num']);
         $this->stats['alb_ptes_venta_importe'] = $this->euro_convert( floatval($data[0]['total']) );
      }
      
      $sql = "SELECT COUNT(idfactura) as num, SUM(totaleuros) as total FROM facturasprov WHERE fecha >= "
              .$this->empresa->var2str($this->desde)." AND fecha <= ".$this->empresa->var2str($this->hasta).";";
      $data = $this->db->select($sql);
      if($data)
      {
         $this->stats['facturas_compra'] = intval($data[0]['num']);
         $this->stats['facturas_compra_importe'] = $this->euro_convert( floatval($data[0]['total']) );
      }
      
      $sql = "SELECT COUNT(idfactura) as num, SUM(totaleuros) as total FROM facturascli WHERE fecha >= "
              .$this->empresa->var2str($this->desde)." AND fecha <= ".$this->empresa->var2str($this->hasta).";";
      $data = $this->db->select($sql);
      if($data)
      {
         $this->stats['facturas_venta'] = intval($data[0]['num']);
         $this->stats['facturas_venta_importe'] = $this->euro_convert( floatval($data[0]['total']) );
      }
      
      $this->stats['total'] = $this->stats['facturas_venta_importe'] + $this->stats['alb_ptes_venta_importe'];
      $this->stats['total'] -= $this->stats['facturas_compra_importe'] + $this->stats['alb_ptes_compra_importe'];
   }
   
   public function stats_impagos($tabla = 'facturasprov')
   {
      $stats = array();
      
      $sql = "select pagada,sum(totaleuros) as total from ".$tabla." group by pagada order by pagada desc;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $stats[] = array(
                'txt' => $this->empresa->str2bool($d['pagada']) ? 'Pagadas':'Impagadas',
                'total' => round( abs( $this->euro_convert( floatval($d['total']) ) ), FS_NF0)
            );
         }
      }
      
      return $stats;
   }
   
   public function stats_series($tabla = 'facturasprov')
   {
      $stats = array();
      $serie0 = new serie();
      
      $sql = "select codserie,sum(totaleuros) as total from ".$tabla." group by codserie order by total desc;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $serie = $serie0->get($d['codserie']);
            if($serie)
            {
               $stats[] = array(
                   'txt' => $serie->descripcion,
                   'total' => round( abs( $this->euro_convert( floatval($d['total']) ) ), FS_NF0)
               );
            }
            else
            {
               $stats[] = array(
                   'txt' => $d['codserie'],
                   'total' => round( abs( $this->euro_convert( floatval($d['total']) ) ), FS_NF0)
               );
            }
         }
      }
      
      return $stats;
   }
   
   public function stats_almacenes($tabla = 'facturasprov')
   {
      $stats = array();
      $al0 = new almacen();
      
      $sql = "select codalmacen,sum(totaleuros) as total from ".$tabla." group by codalmacen order by total desc;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $alma = $al0->get($d['codalmacen']);
            if($alma)
            {
               $stats[] = array(
                   'txt' => $alma->nombre,
                   'total' => round( abs( $this->euro_convert( floatval($d['total']) ) ), FS_NF0)
               );
            }
            else
            {
               $stats[] = array(
                   'txt' => $d['codalmacen'],
                   'total' => round( abs( $this->euro_convert( floatval($d['total']) ) ), FS_NF0)
               );
            }
         }
      }
      
      return $stats;
   }
   
   public function stats_formas_pago($tabla = 'facturasprov')
   {
      $stats = array();
      $fp0 = new forma_pago();
      
      $sql = "select codpago,sum(totaleuros) as total from ".$tabla." group by codpago order by total desc;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $formap = $fp0->get($d['codpago']);
            if($formap)
            {
               $stats[] = array(
                   'txt' => $formap->descripcion,
                   'total' => round( abs( $this->euro_convert( floatval($d['total']) ) ), FS_NF0)
               );
            }
            else
            {
               $stats[] = array(
                   'txt' => $d['codpago'],
                   'total' => round( abs( $this->euro_convert( floatval($d['total']) ) ), FS_NF0)
               );
            }
         }
      }
      
      return $stats;
   }
   
   public function stats_last_operations($tabla = 'facturasprov', $ndias = 180)
   {
      $stats = array();
      
      /// rellenamos $nidas de datos
      for($i = $ndias; $i > 0; $i--)
      {
         $stats[date('d-m-Y', strtotime('-'.$i.'days'))] = array(
             'diario' => 0,
             'semanal' => 0,
             'semana' => date('Y#W', strtotime('-'.$i.'days'))
         );
      }
      
      $sql = "select fecha,count(*) as total from ".$tabla." group by fecha order by fecha desc";
      $data = $this->db->select_limit($sql, $ndias, 0);
      if($data)
      {
         foreach( array_reverse($data) as $d )
         {
            $fecha = date('d-m-Y', strtotime($d['fecha']));
            if( isset($stats[$fecha]) )
            {
               $stats[$fecha]['diario'] = intval($d['total']);
               
               /// añadimos el cálculo para la semana
               $semana = date('Y#W', strtotime($d['fecha']));
               foreach($stats as $i => $value)
               {
                  if($value['semana'] == $semana)
                  {
                     $stats[$i]['semanal'] += intval($d['total']);
                  }
               }
            }
         }
      }
      
      return $stats;
   }
   
   private function informe_compras()
   {
      $sql = "SELECT codproveedor,fecha,SUM(neto) as total FROM facturasprov"
              . " WHERE fecha >= ".$this->empresa->var2str($_POST['desde'])
              . " AND fecha <= ".$this->empresa->var2str($_POST['hasta']);
      
      if($_POST['codserie'] != '')
      {
         $sql .= " AND codserie = ".$this->empresa->var2str($_POST['codserie']);
      }
      
      if($_POST['codagente'] != '')
      {
         $sql .= " AND codagente = ".$this->empresa->var2str($_POST['codagente']);
      }
      
      if($_POST['codproveedor'] != '')
      {
         $sql .= " AND codproveedor = ".$this->empresa->var2str($_POST['codproveedor']);
      }
      
      if($_POST['minimo'] != '')
      {
         $sql .= " AND neto > ".$this->empresa->var2str($_POST['minimo']);
      }
      
      $sql .= " GROUP BY codproveedor,fecha ORDER BY codproveedor ASC, fecha DESC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         $this->template = FALSE;
         
         header("content-type:application/csv;charset=UTF-8");
         header("Content-Disposition: attachment; filename=\"informe_compras.csv\"");
         echo "codproveedor;nombre;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";
         
         $proveedor = new proveedor();
         $stats = array();
         foreach($data as $d)
         {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if( !isset($stats[ $d['codproveedor'] ][ $anyo ]) )
            {
               $stats[ $d['codproveedor'] ][ $anyo ] = array(
                   1 => 0,
                   2 => 0,
                   3 => 0,
                   4 => 0,
                   5 => 0,
                   6 => 0,
                   7 => 0,
                   8 => 0,
                   9 => 0,
                   10 => 0,
                   11 => 0,
                   12 => 0,
                   13 => 0,
                   14 => 0
               );
            }
            
            $stats[ $d['codproveedor'] ][ $anyo ][ $mes ] += floatval($d['total']);
            $stats[ $d['codproveedor'] ][ $anyo ][13] += floatval($d['total']);
         }
         
         $totales = array();
         foreach($stats as $i => $value)
         {
            /// calculamos la variación
            $anterior = 0;
            foreach( array_reverse($value, TRUE) as $j => $value2 )
            {
               if($anterior > 0)
               {
                  $value[$j][14] = ($value2[13]*100/$anterior) - 100;
               }
               
               $anterior = $value2[13];
               
               if( isset($totales[$j]) )
               {
                  foreach($value2 as $k => $value3)
                  {
                     $totales[$j][$k] += $value3;
                  }
               }
               else
               {
                  $totales[$j] = $value2;
               }
            }
            
            $pro = $proveedor->get($i);
            foreach($value as $j => $value2)
            {
               if($pro)
               {
                  echo '"'.$i.'";'.$this->fix_html($pro->nombre).';'.$j;
               }
               else
               {
                  echo '"'.$i.'";-;'.$j;
               }
               
               foreach($value2 as $value3)
               {
                  echo ';'.number_format($value3, FS_NF0, ',', '');
               }
               
               echo "\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
         }
         
         foreach( array_reverse($totales, TRUE) as $i => $value)
         {
            echo ";TOTALES;".$i;
            $l_total = 0;
            foreach($value as $j => $value3)
            {
               if($j < 13)
               {
                  echo ';'.number_format($value3, FS_NF0, ',', '');
                  $l_total += $value3;
               }
            }
            echo ";".number_format($l_total, FS_NF0, ',', '').";\n";
         }
      }
      else
      {
         $this->new_error_msg('Sin resultados.');
      }
   }
   
   private function informe_ventas()
   {
      $sql = "SELECT codcliente,fecha,SUM(neto) as total FROM facturascli"
              . " WHERE fecha >= ".$this->empresa->var2str($_POST['desde'])
              . " AND fecha <= ".$this->empresa->var2str($_POST['hasta']);
      
      if($_POST['codpais'] != '')
      {
         $sql .= " AND codpais = ".$this->empresa->var2str($_POST['codpais']);
      }
      
      if($_POST['provincia'] != '')
      {
         $sql .= " AND lower(provincia) = lower(".$this->empresa->var2str($_POST['provincia']).")";
      }
      
      if($_POST['codcliente'] != '')
      {
         $sql .= " AND codcliente = ".$this->empresa->var2str($_POST['codcliente']);
      }
      
      if($_POST['codserie'] != '')
      {
         $sql .= " AND codserie = ".$this->empresa->var2str($_POST['codserie']);
      }
      
      if($_POST['codagente'] != '')
      {
         $sql .= " AND codagente = ".$this->empresa->var2str($_POST['codagente']);
      }
      
      if($_POST['minimo'] != '')
      {
         $sql .= " AND neto > ".$this->empresa->var2str($_POST['minimo']);
      }
      
      $sql .= " GROUP BY codcliente,fecha ORDER BY codcliente ASC, fecha DESC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         $this->template = FALSE;
         
         header("content-type:application/csv;charset=UTF-8");
         header("Content-Disposition: attachment; filename=\"informe_ventas.csv\"");
         echo "codcliente;nombre;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";
         
         $cliente = new cliente();
         $stats = array();
         foreach($data as $d)
         {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if( !isset($stats[ $d['codcliente'] ][ $anyo ]) )
            {
               $stats[ $d['codcliente'] ][ $anyo ] = array(
                   1 => 0,
                   2 => 0,
                   3 => 0,
                   4 => 0,
                   5 => 0,
                   6 => 0,
                   7 => 0,
                   8 => 0,
                   9 => 0,
                   10 => 0,
                   11 => 0,
                   12 => 0,
                   13 => 0,
                   14 => 0
               );
            }
            
            $stats[ $d['codcliente'] ][ $anyo ][ $mes ] += floatval($d['total']);
            $stats[ $d['codcliente'] ][ $anyo ][13] += floatval($d['total']);
         }
         
         $totales = array();
         foreach($stats as $i => $value)
         {
            /// calculamos la variación y los totales
            $anterior = 0;
            foreach( array_reverse($value, TRUE) as $j => $value2 )
            {
               if($anterior > 0)
               {
                  $value[$j][14] = ($value2[13]*100/$anterior) - 100;
               }
               
               $anterior = $value2[13];
               
               if( isset($totales[$j]) )
               {
                  foreach($value2 as $k => $value3)
                  {
                     $totales[$j][$k] += $value3;
                  }
               }
               else
               {
                  $totales[$j] = $value2;
               }
            }
            
            $cli = $cliente->get($i);
            foreach($value as $j => $value2)
            {
               if($cli)
               {
                  echo '"'.$i.'";'.$this->fix_html($cli->nombre).';'.$j;
               }
               else
               {
                  echo '"'.$i.'";-;'.$j;
               }
               
               foreach($value2 as $value3)
               {
                  echo ';'.number_format($value3, FS_NF0, ',', '');
               }
               
               echo "\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
         }
         
         foreach( array_reverse($totales, TRUE) as $i => $value)
         {
            echo ";TOTALES;".$i;
            $l_total = 0;
            foreach($value as $j => $value3)
            {
               if($j < 13)
               {
                  echo ';'.number_format($value3, FS_NF0, ',', '');
                  $l_total += $value3;
               }
            }
            echo ";".number_format($l_total, FS_NF0, ',', '').";\n";
         }
      }
      else
      {
         $this->new_error_msg('Sin resultados.');
      }
   }
   
   private function informe_compras_unidades()
   {
      $sql = "SELECT f.codproveedor,f.fecha,l.referencia,SUM(l.cantidad) as total"
              . " FROM facturasprov f, lineasfacturasprov l"
              . " WHERE f.idfactura = l.idfactura AND l.referencia IS NOT NULL"
              . " AND f.fecha >= ".$this->empresa->var2str($_POST['desde'])
              . " AND f.fecha <= ".$this->empresa->var2str($_POST['hasta']);
      
      if($_POST['codserie'] != '')
      {
         $sql .= " AND f.codserie = ".$this->empresa->var2str($_POST['codserie']);
      }
      
      if($_POST['codagente'] != '')
      {
         $sql .= " AND f.codagente = ".$this->empresa->var2str($_POST['codagente']);
      }
      
      if($_POST['codproveedor'] != '')
      {
         $sql .= " AND codproveedor = ".$this->empresa->var2str($_POST['codproveedor']);
      }
      
      if($_POST['minimo'] != '')
      {
         $sql .= " AND l.cantidad > ".$this->empresa->var2str($_POST['minimo']);
      }
      
      $sql .= " GROUP BY f.codproveedor,f.fecha,l.referencia ORDER BY f.codproveedor ASC, l.referencia ASC, f.fecha DESC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         $this->template = FALSE;
         
         header("content-type:application/csv;charset=UTF-8");
         header("Content-Disposition: attachment; filename=\"informe_compras_unidades.csv\"");
         echo "codproveedor;nombre;referencia;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";
         
         $proveedor = new proveedor();
         $stats = array();
         foreach($data as $d)
         {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if( !isset($stats[ $d['codproveedor'] ][ $d['referencia'] ][ $anyo ]) )
            {
               $stats[ $d['codproveedor'] ][ $d['referencia'] ][ $anyo ] = array(
                   1 => 0,
                   2 => 0,
                   3 => 0,
                   4 => 0,
                   5 => 0,
                   6 => 0,
                   7 => 0,
                   8 => 0,
                   9 => 0,
                   10 => 0,
                   11 => 0,
                   12 => 0,
                   13 => 0,
                   14 => 0
               );
            }
            
            $stats[ $d['codproveedor'] ][ $d['referencia'] ][ $anyo ][ $mes ] += floatval($d['total']);
            $stats[ $d['codproveedor'] ][ $d['referencia'] ][ $anyo ][13] += floatval($d['total']);
         }
         
         foreach($stats as $i => $value)
         {
            $pro = $proveedor->get($i);
            foreach($value as $j => $value2)
            {
               /// calculamos la variación
               $anterior = 0;
               foreach( array_reverse($value2, TRUE) as $k => $value3 )
               {
                  if($anterior > 0)
                  {
                     $value2[$k][14] = ($value3[13]*100/$anterior) - 100;
                  }
                  
                  $anterior = $value3[13];
               }
               
               foreach($value2 as $k => $value3)
               {
                  if($pro)
                  {
                     echo '"'.$i.'";'.$this->fix_html($pro->nombre).';"'.$j.'";'.$k;
                  }
                  else
                  {
                     echo '"'.$i.'";-;"'.$j.'";'.$k;
                  }
                  
                  foreach($value3 as $value4)
                  {
                     echo ';'.number_format($value4, FS_NF0, ',', '');
                  }
                  
                  echo "\n";
               }
               echo ";;;;;;;;;;;;;;;\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
         }
      }
      else
      {
         $this->new_error_msg('Sin resultados.');
      }
   }
   
   private function informe_ventas_unidades()
   {
      $sql = "SELECT f.codcliente,f.fecha,l.referencia,SUM(l.cantidad) as total"
              . " FROM facturascli f, lineasfacturascli l"
              . " WHERE f.idfactura = l.idfactura AND l.referencia IS NOT NULL"
              . " AND f.fecha >= ".$this->empresa->var2str($_POST['desde'])
              . " AND f.fecha <= ".$this->empresa->var2str($_POST['hasta']);
      
      if($_POST['codpais'] != '')
      {
         $sql .= " AND f.codpais = ".$this->empresa->var2str($_POST['codpais']);
      }
      
      if($_POST['provincia'] != '')
      {
         $sql .= " AND lower(f.provincia) = lower(".$this->empresa->var2str($_POST['provincia']).")";
      }
      
      if($_POST['codcliente'] != '')
      {
         $sql .= " AND codcliente = ".$this->empresa->var2str($_POST['codcliente']);
      }
      
      if($_POST['codserie'] != '')
      {
         $sql .= " AND f.codserie = ".$this->empresa->var2str($_POST['codserie']);
      }
      
      if($_POST['codagente'] != '')
      {
         $sql .= " AND f.codagente = ".$this->empresa->var2str($_POST['codagente']);
      }
      
      if($_POST['minimo'] != '')
      {
         $sql .= " AND l.cantidad > ".$this->empresa->var2str($_POST['minimo']);
      }
      
      $sql .= " GROUP BY f.codcliente,f.fecha,l.referencia ORDER BY f.codcliente ASC, l.referencia ASC, f.fecha DESC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         $this->template = FALSE;
         
         header("content-type:application/csv;charset=UTF-8");
         header("Content-Disposition: attachment; filename=\"informe_ventas_unidades.csv\"");
         echo "codcliente;nombre;referencia;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";
         
         $cliente = new cliente();
         $stats = array();
         foreach($data as $d)
         {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if( !isset($stats[ $d['codcliente'] ][ $d['referencia'] ][ $anyo ]) )
            {
               $stats[ $d['codcliente'] ][ $d['referencia'] ][ $anyo ] = array(
                   1 => 0,
                   2 => 0,
                   3 => 0,
                   4 => 0,
                   5 => 0,
                   6 => 0,
                   7 => 0,
                   8 => 0,
                   9 => 0,
                   10 => 0,
                   11 => 0,
                   12 => 0,
                   13 => 0,
                   14 => 0
               );
            }
            
            $stats[ $d['codcliente'] ][ $d['referencia'] ][ $anyo ][ $mes ] += floatval($d['total']);
            $stats[ $d['codcliente'] ][ $d['referencia'] ][ $anyo ][13] += floatval($d['total']);
         }
         
         foreach($stats as $i => $value)
         {
            $cli = $cliente->get($i);
            foreach($value as $j => $value2)
            {
               /// calculamos la variación
               $anterior = 0;
               foreach( array_reverse($value2, TRUE) as $k => $value3 )
               {
                  if($anterior > 0)
                  {
                     $value2[$k][14] = ($value3[13]*100/$anterior) - 100;
                  }
                  
                  $anterior = $value3[13];
               }
               
               foreach($value2 as $k => $value3)
               {
                  if($cli)
                  {
                     echo '"'.$i.'";'.$this->fix_html($cli->nombre).';"'.$j.'";'.$k;
                  }
                  else
                  {
                     echo '"'.$i.'";-;"'.$j.'";'.$k;
                  }
                  
                  foreach($value3 as $value4)
                  {
                     echo ';'.number_format($value4, FS_NF0, ',', '');
                  }
                  
                  echo "\n";
               }
               echo ";;;;;;;;;;;;;;;\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
         }
      }
      else
      {
         $this->new_error_msg('Sin resultados.');
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
}
