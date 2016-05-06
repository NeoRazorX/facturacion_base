<?php
/*
 * This file is part of FacturaSctipts
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

require_model('asiento.php');
require_model('ejercicio.php');
require_model('factura_cliente.php');
require_model('factura_proveedor.php');
require_model('partida.php');
require_model('regularizacion_iva.php');
require_model('subcuenta.php');

class contabilidad_regusiva extends fs_controller
{
   public $allow_delete;
   public $aux_regiva;
   public $factura_cli;
   public $factura_pro;
   public $fecha_desde;
   public $fecha_hasta;
   public $periodo;
   public $regiva;
   public $s_regiva;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Regularizaciones de IVA', 'contabilidad', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->regiva = new regularizacion_iva();
      $this->factura_cli = new factura_cliente();
      $this->factura_pro = new factura_proveedor();
      
      switch( Date('n') )
      {
         case '1':
            $this->fecha_desde = Date('01-10-Y', strtotime( Date('Y').' -1 year') );
            $this->fecha_hasta = Date('31-12-Y', strtotime( Date('Y').' -1 year') );
            $this->periodo = 'T4';
            break;
         
         case '2':
         case '3':
         case '4':
            $this->fecha_desde = Date('01-01-Y');
            $this->fecha_hasta = Date('31-03-Y');
            $this->periodo = 'T1';
            break;
         
         case '5':
         case '6':
         case '7':
            $this->fecha_desde = Date('01-04-Y');
            $this->fecha_hasta = Date('30-06-Y');
            $this->periodo = 'T2';
            break;
         
         case '8':
         case '9':
         case '10':
            $this->fecha_desde = Date('01-07-Y');
            $this->fecha_hasta = Date('30-09-Y');
            $this->periodo = 'T3';
            break;
         
         case '11':
         case '12':
            $this->fecha_desde = Date('01-10-Y');
            $this->fecha_hasta = Date('31-12-Y');
            $this->periodo = 'T4';
            break;
      }
      
      $this->s_regiva = FALSE;
      if( isset($_REQUEST['id']) )
      {
         $this->s_regiva = $this->regiva->get($_REQUEST['id']);
         if($this->s_regiva)
         {
            $this->page->title = 'Regularización '.$this->s_regiva->periodo.'@'.$this->s_regiva->codejercicio;
         }
      }
      else if( isset($_POST['proceso']) )
      {
         if( $this->factura_cli->huecos() )
         {
            $this->new_error_msg('Tienes huecos en la facturación y por tanto no puedes regularizar el iva.');
         }
         else if($_POST['proceso'] == 'guardar')
         {
            $this->guardar_regiva();
         }
         else
            $this->completar_regiva();
      }
      else
      {
         if( isset($_GET['delete']) )
         {
            $regiva0 = $this->regiva->get($_GET['delete']);
            if($regiva0)
            {
               if( $regiva0->delete() )
               {
                  $this->new_message('Regularización eliminada correctamente.');
               }
               else
                  $this->new_error_msg('Imposible eliminar la regularización.');
            }
            else
               $this->new_error_msg('Regularización no encontrada.');
         }
      }
   }
   
   private function completar_regiva()
   {
      $this->template = 'ajax/contabilidad_regusiva';
      
      $this->aux_regiva = array();
      
      $ejercicio = new ejercicio();
      $partida = new partida();
      $subcuenta = new subcuenta();
      
      $eje0 = $ejercicio->get_by_fecha($_POST['desde'], TRUE);
      if($eje0)
      {
         $continuar = TRUE;
         $saldo = 0;
         
         /// obtenemos el IVA soportado
         foreach($subcuenta->all_from_cuentaesp('IVASOP', $eje0->codejercicio) as $scta_ivasop)
         {
            $tot_sop = $partida->totales_from_subcuenta_fechas($scta_ivasop->idsubcuenta, $_POST['desde'], $_POST['hasta']);
            if($tot_sop['saldo'])
            {
               /// invertimos el debe y el haber
               $this->aux_regiva[] = array(
                   'subcuenta' => $scta_ivasop,
                   'debe' => $tot_sop['haber'],
                   'haber' => $tot_sop['debe']
               );
               $saldo += $tot_sop['haber'] - $tot_sop['debe'];
            }
         }
         
         /// obtenemos el IVA repercutido
         foreach($subcuenta->all_from_cuentaesp('IVAREP', $eje0->codejercicio) as $scta_ivarep)
         {
            $tot_rep = $partida->totales_from_subcuenta_fechas($scta_ivarep->idsubcuenta, $_POST['desde'], $_POST['hasta']);
            if($tot_rep['saldo'])
            {
               /// invertimos el debe y el haber
               $this->aux_regiva[] = array(
                   'subcuenta' => $scta_ivarep,
                   'debe' => $tot_rep['haber'],
                   'haber' => $tot_rep['debe']
               );
               $saldo += $tot_rep['haber'] - $tot_rep['debe'];
            }
         }
         
         if($continuar)
         {
            if($saldo > 0)
            {
               $scta_ivaacr = $subcuenta->get_cuentaesp('IVAACR', $eje0->codejercicio);
               if($scta_ivaacr)
               {
                  $this->aux_regiva[] = array(
                      'subcuenta' => $scta_ivaacr,
                      'debe' => 0,
                      'haber' => $saldo
                  );
               }
               else
                  $this->new_error_msg('No se encuentra la subcuenta acreedora por IVA.');
            }
            else if($saldo < 0)
            {
               $scta_ivadeu = $subcuenta->get_cuentaesp('IVADEU', $eje0->codejercicio);
               if($scta_ivadeu)
               {
                  $this->aux_regiva[] = array(
                      'subcuenta' => $scta_ivadeu,
                      'debe' => abs($saldo),
                      'haber' => 0
                  );
               }
               else
                  $this->new_error_msg('No se encuentra la subcuenta deudora por IVA.');
            }
         }
         else
            $this->new_error_msg('Error al leer las subcuentas.');
      }
      else
         $this->new_error_msg('El ejercicio está cerrado.');
   }
   
   private function guardar_regiva()
   {
      $asiento = new asiento();
      $ejercicio = new ejercicio();
      $subcuenta = new subcuenta();
      
      $eje0 = $ejercicio->get_by_fecha($_POST['desde'], TRUE);
      if($eje0)
      {
         $continuar = TRUE;
         $saldo = 0;
         
         /// guardamos el asiento
         $asiento->codejercicio = $eje0->codejercicio;
         $asiento->concepto = 'REGULARIZACIÓN IVA '.$_POST['periodo'];
         $asiento->fecha = $_POST['hasta'];
         $asiento->editable = FALSE;
         if( $asiento->save() )
         {
            /// obtenemos el IVA soportado
            foreach($subcuenta->all_from_cuentaesp('IVASOP', $eje0->codejercicio) as $scta_ivasop)
            {
               $par0 = new partida();
               $par0->idasiento = $asiento->idasiento;
               $par0->concepto = $asiento->concepto;
               $par0->coddivisa = $scta_ivasop->coddivisa;
               $par0->tasaconv = $scta_ivasop->tasaconv();
               $par0->codsubcuenta = $scta_ivasop->codsubcuenta;
               $par0->idsubcuenta = $scta_ivasop->idsubcuenta;
               
               $tot_sop = $par0->totales_from_subcuenta_fechas($scta_ivasop->idsubcuenta, $_POST['desde'], $_POST['hasta']);
               if($tot_sop['saldo'])
               {
                  /// invertimos el debe y el haber
                  $par0->debe = $tot_sop['haber'];
                  $par0->haber = $tot_sop['debe'];
                  $saldo += $tot_sop['haber'] - $tot_sop['debe'];
                  
                  if( !$par0->save() )
                  {
                     $this->new_error_msg('Error al guardar la partida de la subcuenta de IVA soportado.');
                     $continuar = FALSE;
                  }
               }
            }
            
            /// obtenemos el IVA repercutido
            foreach($subcuenta->all_from_cuentaesp('IVAREP', $eje0->codejercicio) as $scta_ivarep)
            {
               $par1 = new partida();
               $par1->idasiento = $asiento->idasiento;
               $par1->concepto = $asiento->concepto;
               $par1->coddivisa = $scta_ivarep->coddivisa;
               $par1->tasaconv = $scta_ivarep->tasaconv();
               $par1->codsubcuenta = $scta_ivarep->codsubcuenta;
               $par1->idsubcuenta = $scta_ivarep->idsubcuenta;
               
               $tot_rep = $par1->totales_from_subcuenta_fechas($scta_ivarep->idsubcuenta, $_POST['desde'], $_POST['hasta']);
               if($tot_rep['saldo'])
               {
                  /// invertimos el debe y el haber
                  $par1->debe = $tot_rep['haber'];
                  $par1->haber = $tot_rep['debe'];
                  $saldo += $tot_rep['haber'] - $tot_rep['debe'];
                  
                  if( !$par1->save() )
                  {
                     $this->new_error_msg('Error al guardar la partida de la subcuenta de IVA repercutido.');
                     $continuar = FALSE;
                  }
               }
            }
         }
         else
         {
            $this->new_error_msg('Imposible guardar el asiento.');
            $continuar = FALSE;
         }
         
         if($continuar)
         {
            if($saldo > 0)
            {
               $scta_ivaacr = $subcuenta->get_cuentaesp('IVAACR', $eje0->codejercicio);
               if($scta_ivaacr)
               {
                  $par2 = new partida();
                  $par2->idasiento = $asiento->idasiento;
                  $par2->concepto = $asiento->concepto;
                  $par2->coddivisa = $scta_ivaacr->coddivisa;
                  $par2->tasaconv = $scta_ivaacr->tasaconv();
                  $par2->codsubcuenta = $scta_ivaacr->codsubcuenta;
                  $par2->idsubcuenta = $scta_ivaacr->idsubcuenta;
                  $par2->debe = 0;
                  $par2->haber = $saldo;
                  if( !$par2->save() )
                  {
                     $this->new_error_msg('Error al guardar la partida de la subcuenta de acreedor por IVA.');
                     $continuar = FALSE;
                  }
               }
               else
                  $this->new_error_msg('No se encuentra la subcuenta acreedora por IVA.');
            }
            else if($saldo < 0)
            {
               $scta_ivadeu = $subcuenta->get_cuentaesp('IVADEU', $eje0->codejercicio);
               if($scta_ivadeu)
               {
                  $par2 = new partida();
                  $par2->idasiento = $asiento->idasiento;
                  $par2->concepto = $asiento->concepto;
                  $par2->coddivisa = $scta_ivadeu->coddivisa;
                  $par2->tasaconv = $scta_ivadeu->tasaconv();
                  $par2->codsubcuenta = $scta_ivadeu->codsubcuenta;
                  $par2->idsubcuenta = $scta_ivadeu->idsubcuenta;
                  $par2->debe = abs($saldo);
                  $par2->haber = 0;
                  if( !$par2->save() )
                  {
                     $this->new_error_msg('Error al guardar la partida de la subcuenta deudora por IVA.');
                     $continuar = FALSE;
                  }
               }
               else
                  $this->new_error_msg('No se encuentra la subcuenta deudora por IVA.');
            }
         }
         else
            $this->new_error_msg('Error al leer las subcuentas.');
         
         if($continuar)
         {
            $this->regiva = new regularizacion_iva();
            $this->regiva->codejercicio = $eje0->codejercicio;
            $this->regiva->fechaasiento = $asiento->fecha;
            $this->regiva->fechafin = $_POST['hasta'];
            $this->regiva->fechainicio = $_POST['desde'];
            $this->regiva->idasiento = $asiento->idasiento;
            $this->regiva->periodo = $_POST['periodo'];
            
            if( $this->regiva->save() )
            {
               $this->new_message('<a href="'.$this->regiva->url().'">Regularización</a> guardada correctamente.');
               header('Location: '.$this->regiva->url());
            }
            else if( $asiento->delete() )
            {
               $this->new_error_msg('Error al guardar la regularización. Se ha eliminado el asiento.');
            }
            else
               $this->new_error_msg('Error al guardar la regularización. No se ha podido eliminar el asiento.');
         }
         else
         {
            $asiento->delete();
         }
      }
      else
         $this->new_error_msg('El ejercicio está cerrado.');
   }
   
   public function desglosar_iva_compras()
   {
      $desglose = array();
      
      if( $this->db->table_exists('lineasivafactprov') )
      {
         $sql = "select iva,recargo,sum(neto) as neto,sum(totaliva) as totaliva,sum(totalrecargo) as totalrecargo"
                 . " from lineasivafactprov where idfactura in (select idfactura from facturasprov"
                 . " where fecha >= ".$this->empresa->var2str($this->s_regiva->fechainicio)
                 . " and fecha <= ".$this->empresa->var2str($this->s_regiva->fechafin)
                 . " and idasiento < ".$this->empresa->var2str($this->s_regiva->idasiento).")"
                 . " group by iva,recargo order by iva asc, recargo asc;";
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $desglose[] = array(
                   'iva' => floatval($d['iva']),
                   'recargo' => floatval($d['recargo']),
                   'neto' => floatval($d['neto']),
                   'totaliva' => floatval($d['totaliva']),
                   'totalrecargo' => floatval($d['totalrecargo']),
               );
            }
         }
      }
      
      return $desglose;
   }
   
   public function desglosar_iva_ventas()
   {
      $desglose = array();
      
      if( $this->db->table_exists('lineasivafactcli') )
      {
         $sql = "select iva,recargo,sum(neto) as neto,sum(totaliva) as totaliva,sum(totalrecargo) as totalrecargo"
                 . " from lineasivafactcli where idfactura in (select idfactura from facturascli"
                 . " where fecha >= ".$this->empresa->var2str($this->s_regiva->fechainicio)
                 . " and fecha <= ".$this->empresa->var2str($this->s_regiva->fechafin)
                 . " and idasiento < ".$this->empresa->var2str($this->s_regiva->idasiento).")"
                 . " group by iva,recargo order by iva asc, recargo asc;";
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $desglose[] = array(
                   'iva' => floatval($d['iva']),
                   'recargo' => floatval($d['recargo']),
                   'neto' => floatval($d['neto']),
                   'totaliva' => floatval($d['totaliva']),
                   'totalrecargo' => floatval($d['totalrecargo']),
               );
            }
         }
      }
      
      return $desglose;
   }
   
   public function facturas_compra_posteriores()
   {
      $facturas = array();
      
      $sql = "SELECT * FROM facturasprov WHERE fecha >= ".$this->empresa->var2str($this->s_regiva->fechainicio)
              . " and fecha <= ".$this->empresa->var2str($this->s_regiva->fechafin)
              . " and idasiento > ".$this->empresa->var2str($this->s_regiva->idasiento)
              . " ORDER BY fecha ASC;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $facturas[] = new factura_proveedor($d);
         }
      }
      
      return $facturas;
   }
   
   public function facturas_venta_posteriores()
   {
      $facturas = array();
      
      $sql = "SELECT * FROM facturascli WHERE fecha >= ".$this->empresa->var2str($this->s_regiva->fechainicio)
              . " and fecha <= ".$this->empresa->var2str($this->s_regiva->fechafin)
              . " and idasiento > ".$this->empresa->var2str($this->s_regiva->idasiento)
              . " ORDER BY fecha ASC;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $facturas[] = new factura_cliente($d);
         }
      }
      
      return $facturas;
   }
   
   public function partidas_problematicas_compras()
   {
      $partidas = array();
      
      $subcuenta = new subcuenta();
      $ejercicio = new ejercicio();
      $eje0 = $ejercicio->get($this->s_regiva->codejercicio);
      
      foreach($subcuenta->all_from_cuentaesp('IVASOP', $eje0->codejercicio) as $scta_ivasop)
      {
         $sql = "select * from co_partidas where codsubcuenta = ".$eje0->var2str($scta_ivasop->codsubcuenta)
                 . " and idasiento in (select idasiento from co_asientos"
                 . " where fecha >= ".$this->empresa->var2str($this->s_regiva->fechainicio)
                 . " and fecha <= ".$this->empresa->var2str($this->s_regiva->fechafin).")"
                 . " and idasiento != ".$this->empresa->var2str($this->s_regiva->idasiento)
                 . " and idasiento not in (select idasiento from facturasprov where idasiento is not null);";
         
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $partidas[] = new partida($d);
            }
         }
      }
      
      return $partidas;
   }
   
   public function partidas_problematicas_ventas()
   {
      $partidas = array();
      
      $subcuenta = new subcuenta();
      $ejercicio = new ejercicio();
      $eje0 = $ejercicio->get($this->s_regiva->codejercicio);
      
      foreach($subcuenta->all_from_cuentaesp('IVAREP', $eje0->codejercicio) as $scta_ivasop)
      {
         $sql = "select * from co_partidas where codsubcuenta = ".$eje0->var2str($scta_ivasop->codsubcuenta)
                 . " and idasiento in (select idasiento from co_asientos"
                 . " where fecha >= ".$this->empresa->var2str($this->s_regiva->fechainicio)
                 . " and fecha <= ".$this->empresa->var2str($this->s_regiva->fechafin).")"
                 . " and idasiento != ".$this->empresa->var2str($this->s_regiva->idasiento)
                 . " and idasiento not in (select idasiento from facturascli where idasiento is not null);";
         
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $partidas[] = new partida($d);
            }
         }
      }
      
      return $partidas;
   }
}
